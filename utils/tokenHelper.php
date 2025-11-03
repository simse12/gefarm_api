<?php
/**
 * TokenHelper - Gefarm API
 * Genera stringhe e codici per token di verifica e reset.
 */

class TokenHelper {

    /**
     * Genera un token numerico casuale di N cifre (default 5).
     * @param int $length
     * @return string
     */
    public static function generateNumericToken($length = 5) {
        if ($length < 1) return '';
        
        $min = 10 ** ($length - 1);
        $max = (10 ** $length) - 1;
        
        // Genera un numero intero casuale sicuro
        $token = random_int($min, $max);
        
        // Ritorna come stringa
        return (string)$token;
    }
    
    // Potrebbe essere utile anche per token alfanumerici lunghi in futuro
    /*
    public static function generateUniqueToken($length = 64) {
        return bin2hex(random_bytes($length / 2));
    }
    */
}