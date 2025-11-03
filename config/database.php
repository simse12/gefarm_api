<?php
/**
 * Database Configuration - Gefarm API
 * Connessione MySQL con singleton pattern
 */

class Database {
    // Configurazione database
    private $host = "localhost";
    private $db_name = "namedb";
    private $username = "username";
    private $password = ""; 
    private $charset = "utf8mb4";
    
    // Singleton
    private static $instance = null;
    private $connection = null;
    
    /**
     * Costruttore privato per singleton
     */
    private function __construct() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}"
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Errore di connessione al database'
            ]));
        }
    }
    
    /**
     * Ottieni istanza singleton
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Ottieni connessione PDO
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Previeni clonazione
     */
    private function __clone() {}
    
    /**
     * Previeni unserialize
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
?>
