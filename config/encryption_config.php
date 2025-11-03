<?php
/**
 * Encryption Configuration - Gefarm API
 * Configurazione per criptazione AES-256
 */

class EncryptionConfig {
    // ⚠️ IMPORTANTE: Chiave di criptazione (DEVE essere esattamente 32 caratteri)
    // Genera con: openssl rand -hex 16
    private static $encryption_key = "a6d94a23221bb7f46ee8c9138f782e46"; // 32 chars
    
    // Metodo di criptazione
    private static $cipher_method = "AES-256-CBC";
    
    /**
     * Cripta un valore
     */
    public static function encrypt($data) {
        if (empty($data)) return $data;
        
        $iv_length = openssl_cipher_iv_length(self::$cipher_method);
        $iv = openssl_random_pseudo_bytes($iv_length);
        
        $encrypted = openssl_encrypt(
            $data,
            self::$cipher_method,
            self::$encryption_key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        // Combina IV + dati criptati e codifica in base64
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decripta un valore
     */
    public static function decrypt($data) {
        if (empty($data)) return $data;
        
        $data = base64_decode($data);
        $iv_length = openssl_cipher_iv_length(self::$cipher_method);
        
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        return openssl_decrypt(
            $encrypted,
            self::$cipher_method,
            self::$encryption_key,
            OPENSSL_RAW_DATA,
            $iv
        );
    }
    
    /**
     * Hash sicuro per password
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * Verifica password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}
?>
