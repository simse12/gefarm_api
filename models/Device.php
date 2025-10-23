<?php
/**
 * Device Model - GeFarm API
 * Gestione tabella gefarm_devices
 */

require_once __DIR__ . '/../config/database.php';

class Device {
    private $conn;
    private $table = 'gefarm_devices';
    
    // Proprietà corrispondenti alle colonne della tabella
    public $id;
    public $device_id;
    public $device_type;
    public $nome_dispositivo;
    public $ssid_ap;
    public $device_password_hash;
    public $first_setup_completed;
    public $chain2_active;
    public $firmware_version;
    public $last_seen;
    public $created_at;
    
    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }
    
    /**
     * Ottieni dispositivo per device_id
     */
    public function getByDeviceId($device_id) {
        $query = "SELECT * FROM " . $this->table . " WHERE device_id = :device_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':device_id', $device_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Ottieni dispositivo per ID interno
     */
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Ottieni dispositivi di un utente (tramite gefarm_user_devices)
     */
    public function getUserDevices($user_id) {
        $query = "SELECT d.*, ud.role, ud.nickname, ud.is_favorite, ud.added_at
                  FROM " . $this->table . " d
                  INNER JOIN gefarm_user_devices ud ON d.id = ud.device_id
                  WHERE ud.user_id = :user_id
                  ORDER BY ud.is_favorite DESC, ud.added_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Crea nuovo dispositivo
     */
    public function create() {
        try {
            $query = "INSERT INTO " . $this->table . " 
                      (device_id, device_type, nome_dispositivo, ssid_ap, chain2_active, firmware_version) 
                      VALUES 
                      (:device_id, :device_type, :nome_dispositivo, :ssid_ap, :chain2_active, :firmware_version)";
            
            $stmt = $this->conn->prepare($query);
            
            // Sanitizza
            $this->device_id = htmlspecialchars(strip_tags($this->device_id));
            $this->device_type = htmlspecialchars(strip_tags($this->device_type));
            $this->nome_dispositivo = htmlspecialchars(strip_tags($this->nome_dispositivo));
            $this->chain2_active = $this->chain2_active ?? 0;
            
            // Validazione device_type (ENUM)
            $valid_types = ['emcengine', 'emcinverter', 'emcbox'];
            if (!in_array($this->device_type, $valid_types)) {
                error_log("Device type validation failed: {$this->device_type}");
                throw new Exception("Tipo dispositivo non valido: {$this->device_type}");
            }
            
            // Usa bindValue invece di bindParam per gestire correttamente i NULL
            $stmt->bindValue(':device_id', $this->device_id, PDO::PARAM_STR);
            $stmt->bindValue(':device_type', $this->device_type, PDO::PARAM_STR);
            $stmt->bindValue(':nome_dispositivo', $this->nome_dispositivo, PDO::PARAM_STR);
            $stmt->bindValue(':ssid_ap', $this->ssid_ap, $this->ssid_ap === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':chain2_active', $this->chain2_active, PDO::PARAM_INT);
            $stmt->bindValue(':firmware_version', $this->firmware_version, $this->firmware_version === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                error_log("Device created successfully with ID: {$this->id}");
                return true;
            }
            
            // Log errore se execute fallisce
            $errorInfo = $stmt->errorInfo();
            error_log("Device create failed: " . json_encode($errorInfo));
            return false;
            
        } catch (Exception $e) {
            error_log("Device create exception: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Verifica se device_id esiste già
     */
    public function deviceIdExists($device_id) {
        $query = "SELECT id FROM " . $this->table . " WHERE device_id = :device_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':device_id', $device_id);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Aggiorna last_seen
     */
    public function updateLastSeen($device_id) {
        $query = "UPDATE " . $this->table . " SET last_seen = NOW() WHERE device_id = :device_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':device_id', $device_id);
        
        return $stmt->execute();
    }
    
    /**
     * Aggiorna informazioni dispositivo
     */
    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];
        
        $allowed_fields = ['nome_dispositivo', 'device_type', 'ssid_ap', 'chain2_active', 
                          'firmware_version', 'first_setup_completed'];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $query = "UPDATE " . $this->table . " SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        
        return $stmt->execute($params);
    }
    
    /**
     * Associa dispositivo a utente
     */
    public function addToUser($user_id, $device_id, $role = 'user', $nickname = null) {
        try {
            // Nota: ON DUPLICATE KEY UPDATE richiede di bindare i parametri 2 volte
            // o di usare VALUES()
            $query = "INSERT INTO gefarm_user_devices (user_id, device_id, role, nickname) 
                      VALUES (:user_id, :device_id, :role, :nickname)
                      ON DUPLICATE KEY UPDATE 
                        role = VALUES(role), 
                        nickname = VALUES(nickname)";
            
            $stmt = $this->conn->prepare($query);
            
            // Usa bindValue per gestire correttamente i NULL
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':device_id', $device_id, PDO::PARAM_INT);
            $stmt->bindValue(':role', $role, PDO::PARAM_STR);
            $stmt->bindValue(':nickname', $nickname, $nickname === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                error_log("Device {$device_id} associated to user {$user_id} as {$role}");
                return true;
            }
            
            // Log errore se execute fallisce
            $errorInfo = $stmt->errorInfo();
            error_log("addToUser failed: " . json_encode($errorInfo));
            return false;
            
        } catch (Exception $e) {
            error_log("addToUser exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Rimuovi dispositivo da utente
     */
    public function removeFromUser($user_id, $device_id) {
        $query = "DELETE FROM gefarm_user_devices WHERE user_id = :user_id AND device_id = :device_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':device_id', $device_id);
        
        return $stmt->execute();
    }
}
?>
