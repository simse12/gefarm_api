<?php
/**
 * User Model - GeFarm API
 * Gestione tabella gefarm_users
 */

$databasePath = __DIR__ . '/../config/database.php';
if (!file_exists($databasePath)) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'File database.php non trovato']));
}
require_once $databasePath;

if (!class_exists('Database')) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Classe Database non definita']));
}

require_once __DIR__ . '/../config/encryption_config.php';


class User {
    private $conn;
    private $table = 'gefarm_users';
    
    // Proprietà
    public $id;
    public $email;
    public $password_hash;
    public $nome;
    public $cognome;
    public $avatar_path;
    public $avatar_color;
    public $email_verified;
    public $created_at;
    public $updated_at;
    
    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }
    
    /**
     * Restituisce la connessione (necessario per le transazioni negli endpoint)
     */
    public function getConnection() {
        return $this->conn;
    }
    
    /**
     * Crea nuovo utente
     */
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                     (email, password_hash, nome, cognome, avatar_color, email_verified) 
                     VALUES 
                     (:email, :password_hash, :nome, :cognome, :avatar_color, :email_verified)";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitizza input
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->cognome = htmlspecialchars(strip_tags($this->cognome));
        $this->avatar_color = $this->avatar_color ?? '#00853d';
        $this->email_verified = $this->email_verified ?? 0;
        
        // Hash password
        $password_to_hash = $this->password_hash;
        $this->password_hash = EncryptionConfig::hashPassword($password_to_hash);
        
        // Bind parametri
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password_hash', $this->password_hash);
        $stmt->bindParam(':nome', $this->nome);
        $stmt->bindParam(':cognome', $this->cognome);
        $stmt->bindParam(':avatar_color', $this->avatar_color);
        $stmt->bindParam(':email_verified', $this->email_verified);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        $errorInfo = $stmt->errorInfo();
        error_log("User create failed: " . json_encode($errorInfo));
        return false;
    }
    
    /**
     * Ottieni utente per Email
     */
    public function getByEmail($email) {
        $query = "SELECT id, password_hash, email, nome, cognome, avatar_path, avatar_color, 
                     email_verified, created_at, updated_at 
                     FROM " . $this->table . " WHERE email = :email LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Login utente
     * ✅ CONTROLLA ANCHE email_verified
     */
    public function login($email, $password) {
        // Seleziona tutti i campi necessari, inclusa email_verified
        $query = "SELECT id, password_hash, email, nome, cognome, email_verified FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'error' => 'Credenziali non valide'];
        }
        
        // 1. Verifica password
        if (!EncryptionConfig::verifyPassword($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Credenziali non valide'];
        }

        // 2. ✅ NUOVO: Verifica se l'email è stata verificata
        if (!$user['email_verified']) {
            return ['success' => false, 'error' => 'Account non verificato. Controlla la tua email per il codice di attivazione.', 'verified' => false];
        }
        
        // 3. Login riuscito
        unset($user['password_hash']);
        
        return ['success' => true, 'user' => $user];
    }
    
    /**
     * Verifica se email esiste
     */
    public function emailExists($email) {
        $query = "SELECT id FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Ottieni utente per ID
     */
    public function getById($id) {
        $query = "SELECT id, email, nome, cognome, avatar_path, avatar_color, 
                     email_verified, created_at, updated_at 
                     FROM " . $this->table . " WHERE id = :id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Aggiorna profilo utente
     */
    public function updateProfile($id, $data) {
        $fields = [];
        $params = [':id' => $id];
        
        $allowed_fields = ['nome', 'cognome', 'avatar_path', 'avatar_color'];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = htmlspecialchars(strip_tags($data[$field]));
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
     * Cambia password
     */
    public function changePassword($id, $current_password, $new_password) {
        // Verifica password corrente
        $query = "SELECT password_hash FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !EncryptionConfig::verifyPassword($current_password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Password corrente non corretta'];
        }
        
        // Aggiorna password
        $new_hash = EncryptionConfig::hashPassword($new_password);
        
        $query = "UPDATE " . $this->table . " SET password_hash = :password_hash WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':password_hash', $new_hash);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Errore durante l\'aggiornamento della password'];
    }
    
    /**
     * Elimina utente
     */
    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }
    
    // ===================================
    // ✅ METODI DI VERIFICA EMAIL AGGIUNTI
    // ===================================

    /**
     * Salva un token di verifica/reset nella tabella gefarm_password_reset_tokens
     */
    public function createToken($user_id, $token, $type = 'verify', $expires_at = null) {
        try {
            $query = "INSERT INTO gefarm_password_reset_tokens 
                        (user_id, type, token, expires_at) 
                        VALUES (:user_id, :type, :token, :expires_at)";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':type', $type, PDO::PARAM_STR);
            $stmt->bindValue(':token', $token, PDO::PARAM_STR);
            // Imposta scadenza a 24 ore se non specificata
            $default_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $expiry = $expires_at ?? $default_expiry;
            $stmt->bindValue(':expires_at', $expiry, PDO::PARAM_STR);
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            error_log("createToken failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Marca l'utente come verificato nella tabella gefarm_users
     */
    public function markEmailVerified($user_id) {
        $query = "UPDATE " . $this->table . " SET email_verified = TRUE WHERE id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        
        return $stmt->execute();
    }
}