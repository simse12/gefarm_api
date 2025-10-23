<?php
/**
 * User Model - GeFarm API
 * Gestione tabella gefarm_users
 */

// Includi il database in modo sicuro
$databasePath = __DIR__ . '/../config/database.php';
if (!file_exists($databasePath)) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'File database.php non trovato']));
}
require_once $databasePath;

// Verifica che la classe esista
if (!class_exists('Database')) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Classe Database non definita']));
}

require_once __DIR__ . '/../config/encryption_config.php';


class DeviceMeterData {
    private $conn;
    private $table = 'gefarm_device_meter_data';
    
    // Proprietà corrispondenti alle colonne della tabella
    public $id;
    public $device_id;
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
     */
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  (device_id, cf, nome, cognome, indirizzo, zip_code, citta, provincia, 
                   pod, email, telefono, is_active) 
                  VALUES 
                  (:device_id, :cf, :nome, :cognome, :indirizzo, :zip_code, :citta, :provincia,
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
        
        // Bind
        $stmt->bindParam(':device_id', $this->device_id);
        $stmt->bindParam(':cf', $cf_encrypted);
        $stmt->bindParam(':nome', $this->nome);
        $stmt->bindParam(':cognome', $this->cognome);
        $stmt->bindParam(':indirizzo', $this->indirizzo);
        $stmt->bindParam(':zip_code', $this->zip_code);
        $stmt->bindParam(':citta', $this->citta);
        $stmt->bindParam(':provincia', $this->provincia);
        $stmt->bindParam(':pod', $this->pod);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':telefono', $this->telefono);
        $stmt->bindParam(':is_active', $this->is_active);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    /**
     * Ottieni dati attivi per un dispositivo
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
?>
