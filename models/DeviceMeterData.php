<?php
/**
 * DeviceMeterData Model - GeFarm API
 * Gestione tabella gefarm_device_meter_data
 */

$databasePath = __DIR__ . '/../config/database.php';
if (!file_exists($databasePath)) {
    throw new Exception('File database.php non trovato');
}
require_once $databasePath;
require_once __DIR__ . '/../config/encryption_config.php';

class DeviceMeterData {
    private $conn;
    private $table = 'gefarm_device_meter_data';
    
    // Proprietà corrispondenti alle colonne della tabella
    public $id;
    public $device_id;
    public $inserted_by_user_id; 
    public $cf; // Codice Fiscale (criptato)
    public $nome;
    public $cognome;
    public $indirizzo;
    public $zip_code;
    public $citta;
    public $provincia;
    public $pod; // Point of Delivery
    public $email;
    public $telefono;
    public $is_active;
    public $valid_from;
    public $valid_to;
    public $created_at;
    
    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }
    
    /**
     * Crea nuovo record dati contatore
     * ✅ CORRETTO: Aggiunta gestione inserted_by_user_id nel bind.
     */
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  (device_id, inserted_by_user_id, cf, nome, cognome, indirizzo, zip_code, citta, provincia, 
                   pod, email, telefono, is_active) 
                  VALUES 
                  (:device_id, :inserted_by_user_id, :cf, :nome, :cognome, :indirizzo, :zip_code, :citta, :provincia,
                   :pod, :email, :telefono, :is_active)";
        
        $stmt = $this->conn->prepare($query);
        
        // Cripta il CF
        $cf_encrypted = EncryptionConfig::encrypt($this->cf);
        
        // Sanitizza
        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->cognome = htmlspecialchars(strip_tags($this->cognome));
        $this->indirizzo = htmlspecialchars(strip_tags($this->indirizzo));
        $this->citta = htmlspecialchars(strip_tags($this->citta));
        $this->provincia = htmlspecialchars(strip_tags($this->provincia));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->is_active = $this->is_active ?? 1;
        
        // Bind con gestione NULL per i campi opzionali
        $stmt->bindValue(':device_id', $this->device_id, PDO::PARAM_INT);
        // ✅ Gestione NULL per l'utente inseritore
        $stmt->bindValue(':inserted_by_user_id', $this->inserted_by_user_id, $this->inserted_by_user_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT); 
        $stmt->bindValue(':cf', $cf_encrypted, PDO::PARAM_STR);
        $stmt->bindValue(':nome', $this->nome, PDO::PARAM_STR);
        $stmt->bindValue(':cognome', $this->cognome, PDO::PARAM_STR);
        $stmt->bindValue(':indirizzo', $this->indirizzo, PDO::PARAM_STR);
        $stmt->bindValue(':zip_code', $this->zip_code, PDO::PARAM_STR);
        $stmt->bindValue(':citta', $this->citta, PDO::PARAM_STR);
        $stmt->bindValue(':provincia', $this->provincia, PDO::PARAM_STR);
        $stmt->bindValue(':pod', $this->pod, $this->pod === null ? PDO::PARAM_NULL : PDO::PARAM_STR); 
        $stmt->bindValue(':email', $this->email, PDO::PARAM_STR);
        $stmt->bindValue(':telefono', $this->telefono, $this->telefono === null ? PDO::PARAM_NULL : PDO::PARAM_STR); 
        $stmt->bindValue(':is_active', $this->is_active, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        $errorInfo = $stmt->errorInfo();
        error_log("DeviceMeterData create failed: " . json_encode($errorInfo));
        return false;
    }
    
    /**
     * Ottieni dati attivi per un dispositivo
     * ✅ CORRETTO: Decrittografia del CF.
     */
    public function getActiveByDeviceId($device_id) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE device_id = :device_id AND is_active = 1 
                  ORDER BY created_at DESC LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':device_id', $device_id);
        $stmt->execute();
        
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Decripta CF se presente
        if ($data && !empty($data['cf'])) {
            $data['cf'] = EncryptionConfig::decrypt($data['cf']);
        }
        
        return $data;
    }
    
    /**
     * Ottieni tutti i record per un dispositivo (storico)
     */
    public function getAllByDeviceId($device_id) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE device_id = :device_id 
                  ORDER BY created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':device_id', $device_id);
        $stmt->execute();
        
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decripta CF per ogni record
        foreach ($records as &$record) {
            if (!empty($record['cf'])) {
                $record['cf'] = EncryptionConfig::decrypt($record['cf']);
            }
        }
        
        return $records;
    }
    
    /**
     * Aggiorna dati contatore (disattiva il vecchio, crea nuovo)
     */
    public function updateForDevice($device_id, $new_data) {
        // Disattiva il record corrente
        $query = "UPDATE " . $this->table . " 
                  SET is_active = 0, valid_to = NOW() 
                  WHERE device_id = :device_id AND is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':device_id', $device_id);
        $stmt->execute();
        
        // Crea nuovo record
        $this->device_id = $device_id;
        foreach ($new_data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
        
        return $this->create();
    }
    
    /**
     * Elimina (disattiva) dati contatore
     */
    public function deactivate($device_id) {
        $query = "UPDATE " . $this->table . " 
                  SET is_active = 0, valid_to = NOW() 
                  WHERE device_id = :device_id AND is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':device_id', $device_id);
        
        return $stmt->execute();
    }
}