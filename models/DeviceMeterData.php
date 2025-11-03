<?php
/**
 * DeviceMeterData Model - Gefarm API
 * Gestione tabella gefarm_device_meter_data
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/encryption_config.php';

class DeviceMeterData {
    private $conn;
    private $table = 'gefarm_device_meter_data';
    
    // Proprietà corrispondenti alle colonne della tabella
    public $id;
    public $device_id;
    public $inserted_by_user_id; 
    public $cf; // Codice Fiscale in chiaro (criptato internamente)
    public $nome;
    public $cognome;
    public $indirizzo;
    public $zip_code;
    public $citta;
    public $provincia;
    public $pod; // Point of Delivery (opzionale)
    // ❌ email e telefono NON sono proprietà assegnabili qui
    // Vengono impostati SOLO dal controller tramite setUserData()
    public $is_active;
    public $valid_from;
    public $valid_to;
    public $created_at;

    // Dati utente registrante (impostati dal controller)
    private $user_email;
    private $user_telefono;

    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }

    /**
     * Imposta email e telefono dell'utente autenticato (obbligatorio prima di create())
     */
    public function setUserData($email, $telefono = null) {
        $this->user_email = htmlspecialchars(strip_tags($email));
        $this->user_telefono = $telefono ? htmlspecialchars(strip_tags($telefono)) : null;
    }

    /**
     * Verifica se esiste già un record attivo con lo stesso CF su questo device
     * ✅ CF univoco per device, non globale
     */
    public function cfAlreadyExistsForDevice($device_id, $cf) {
        $cf_encrypted = EncryptionConfig::encrypt($cf);
        $query = "SELECT id FROM " . $this->table . " 
                  WHERE device_id = :device_id 
                    AND cf_owner_encrypted = :cf 
                    AND is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':device_id', $device_id, PDO::PARAM_INT);
        $stmt->bindParam(':cf', $cf_encrypted, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Crea nuovo record dati contatore
     * ✅ Email e telefono forzati da setUserData()
     * ✅ Validazione CF univoco per device
     */
    public function create() {
    // ✅ Validazione: email/telefono devono essere stati impostati
    if (!isset($this->user_email)) {
        error_log("DeviceMeterData::create() chiamato senza setUserData()");
        return false;
    }

    // ✅ Validazione: CF non deve già esistere attivo su questo device
    if ($this->cfAlreadyExistsForDevice($this->device_id, $this->cf)) {
        error_log("Tentativo di inserire CF già attivo per device_id={$this->device_id}");
        return false;
    }

    $query = "INSERT INTO " . $this->table . " 
              (device_id, inserted_by_user_id, cf_owner_encrypted, nome, cognome, indirizzo, 
               zip_code, citta, provincia, pod, email, telefono, is_active) 
              VALUES 
              (:device_id, :inserted_by_user_id, :cf_owner_encrypted, :nome, :cognome, :indirizzo, 
               :zip_code, :citta, :provincia, :pod, :email, :telefono, :is_active)";
    
    $stmt = $this->conn->prepare($query);
    
    // Cripta il CF (e normalizza in maiuscolo)
    $this->cf = strtoupper($this->cf); // 🔥 AGGIUNTO
    $cf_encrypted = EncryptionConfig::encrypt($this->cf);
    
    // Sanitizza e normalizza in MAIUSCOLO
    $this->nome = strtoupper(htmlspecialchars(strip_tags($this->nome))); // 🔥 MODIFICATO
    $this->cognome = strtoupper(htmlspecialchars(strip_tags($this->cognome))); // 🔥 MODIFICATO
    $this->indirizzo = htmlspecialchars(strip_tags($this->indirizzo)); // Indirizzo rimane normale
    $this->citta = strtoupper(htmlspecialchars(strip_tags($this->citta))); // 🔥 MODIFICATO
    $this->provincia = strtoupper(htmlspecialchars(strip_tags($this->provincia))); // 🔥 MODIFICATO
    
    // POD normalizzato (se presente)
    if ($this->pod) {
        $this->pod = strtoupper($this->pod); // 🔥 AGGIUNTO
    }
    
    $this->is_active = $this->is_active ?? 1;
  
        // Bind
        $stmt->bindValue(':device_id', $this->device_id, PDO::PARAM_INT);
        $stmt->bindValue(':inserted_by_user_id', $this->inserted_by_user_id, $this->inserted_by_user_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':cf_owner_encrypted', $cf_encrypted, PDO::PARAM_STR);
        $stmt->bindValue(':nome', $this->nome, PDO::PARAM_STR);
        $stmt->bindValue(':cognome', $this->cognome, PDO::PARAM_STR);
        $stmt->bindValue(':indirizzo', $this->indirizzo, PDO::PARAM_STR);
        $stmt->bindValue(':zip_code', $this->zip_code, PDO::PARAM_STR);
        $stmt->bindValue(':citta', $this->citta, PDO::PARAM_STR);
        $stmt->bindValue(':provincia', $this->provincia, PDO::PARAM_STR);
        $stmt->bindValue(':pod', $this->pod, $this->pod === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':email', $this->user_email, PDO::PARAM_STR); // ✅ Forzato dall'utente loggato
        $stmt->bindValue(':telefono', $this->user_telefono, $this->user_telefono === null ? PDO::PARAM_NULL : PDO::PARAM_STR); // ✅ Forzato
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
        if ($data && !empty($data['cf_owner_encrypted'])) {
            $data['cf'] = EncryptionConfig::decrypt($data['cf_owner_encrypted']);
            unset($data['cf_owner_encrypted']);
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
            if (!empty($record['cf_owner_encrypted'])) {
                $record['cf'] = EncryptionConfig::decrypt($record['cf_owner_encrypted']);
                unset($record['cf_owner_encrypted']);
            }
        }
        
        return $records;
    }
    
    /**
     * Aggiorna dati contatore (disattiva il vecchio, crea nuovo)
     */
    public function updateForDevice($device_id, $new_data, $user_email, $user_telefono = null) {
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
        $this->setUserData($user_email, $user_telefono);
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