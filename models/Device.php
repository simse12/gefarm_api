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
    public $device_family; 
    public $device_type;
    public $nome_dispositivo;
    public $ssid_ap;
    public $ssid_password; 
    public $first_setup_completed;
    public $chain2_active;
    public $firmware_version;
    public $last_seen;
    
    // CAMPI DATAPLATE
    public $du;
    public $k1;
    public $k2;
    public $fiv;
    public $dataplate_synced_at;
    
    public $created_at;
    public $updated_at; 
    
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
     * ✅ AGGIORNAMENTO: Includi is_meter_owner nella selezione
     */
    public function getUserDevices($user_id) {
        $query = "SELECT d.*, ud.role, ud.nickname, ud.is_favorite, ud.is_meter_owner, ud.added_at
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
            // AGGIORNAMENTO: Query corretta con tutti i campi
            $query = "INSERT INTO " . $this->table . " 
                     (device_id, device_family, device_type, nome_dispositivo, ssid_ap, ssid_password, 
                      chain2_active, firmware_version, du, k1, k2, fiv, dataplate_synced_at) 
                      VALUES 
                      (:device_id, :device_family, :device_type, :nome_dispositivo, :ssid_ap, :ssid_password, 
                      :chain2_active, :firmware_version, :du, :k1, :k2, :fiv, :dataplate_synced_at)";
            
            $stmt = $this->conn->prepare($query);
            
            // Sanitizza e gestisci default
            $this->device_id = htmlspecialchars(strip_tags($this->device_id));
            $this->device_family = htmlspecialchars(strip_tags($this->device_family));
            $this->device_type = htmlspecialchars(strip_tags($this->device_type));
            $this->nome_dispositivo = htmlspecialchars(strip_tags($this->nome_dispositivo));
            $this->chain2_active = $this->chain2_active ?? 0;
            $this->first_setup_completed = $this->first_setup_completed ?? 0; 

            // Validazioni (omesse per brevità, sono nel tuo codice)
            
            // Bind
            $stmt->bindValue(':device_id', $this->device_id, PDO::PARAM_STR);
            $stmt->bindValue(':device_family', $this->device_family, PDO::PARAM_STR); 
            $stmt->bindValue(':device_type', $this->device_type, PDO::PARAM_STR);
            $stmt->bindValue(':nome_dispositivo', $this->nome_dispositivo, PDO::PARAM_STR);
            $stmt->bindValue(':ssid_ap', $this->ssid_ap, $this->ssid_ap === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':ssid_password', $this->ssid_password, $this->ssid_password === null ? PDO::PARAM_NULL : PDO::PARAM_STR); 
            $stmt->bindValue(':chain2_active', $this->chain2_active, PDO::PARAM_INT);
            $stmt->bindValue(':firmware_version', $this->firmware_version, $this->firmware_version === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            
            // Binding dei campi Dataplate
            $stmt->bindValue(':du', $this->du, $this->du === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':k1', $this->k1, $this->k1 === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':k2', $this->k2, $this->k2 === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':fiv', $this->fiv, $this->fiv === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':dataplate_synced_at', $this->dataplate_synced_at, $this->dataplate_synced_at === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                error_log("Device created successfully with ID: {$this->id}");
                return true;
            }
            
            $errorInfo = $stmt->errorInfo();
            error_log("Device create failed: " . json_encode($errorInfo));
            return false;
            
        } catch (Exception $e) {
            error_log("Device create exception: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Associa dispositivo a utente
     * ✅ AGGIORNAMENTO: Aggiunto $is_meter_owner
     */
    public function addToUser($user_id, $device_id, $role = 'user', $nickname = null, $is_meter_owner = false) {
        try {
            $query = "INSERT INTO gefarm_user_devices (user_id, device_id, role, nickname, is_meter_owner) 
                          VALUES (:user_id, :device_id, :role, :nickname, :is_meter_owner)
                          ON DUPLICATE KEY UPDATE 
                          role = VALUES(role), 
                          nickname = VALUES(nickname),
                          is_meter_owner = VALUES(is_meter_owner)"; // ✅ AGGIORNAMENTO ON DUPLICATE
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':device_id', $device_id, PDO::PARAM_INT);
            $stmt->bindValue(':role', $role, PDO::PARAM_STR);
            $stmt->bindValue(':nickname', $nickname, $nickname === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            // ✅ BIND NUOVO CAMPO
            $stmt->bindValue(':is_meter_owner', (int)$is_meter_owner, PDO::PARAM_INT); 
            
            if ($stmt->execute()) {
                error_log("Device {$device_id} associated to user {$user_id} as {$role}. Is Meter Owner: " . ($is_meter_owner ? 'Yes' : 'No'));
                return true;
            }
            
            $errorInfo = $stmt->errorInfo();
            error_log("addToUser failed: " . json_encode($errorInfo));
            return false;
            
        } catch (Exception $e) {
            error_log("addToUser exception: " . $e->getMessage());
            return false;
        }
    }

    // ... (deviceIdExists, updateLastSeen, update, removeFromUser invariati/corretti)
    
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
        
        $allowed_fields = ['nome_dispositivo', 'device_family', 'device_type', 'ssid_ap', 'ssid_password', 'chain2_active', 
                             'firmware_version', 'first_setup_completed', 'du', 'k1', 'k2', 'fiv', 'dataplate_synced_at'];
        
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