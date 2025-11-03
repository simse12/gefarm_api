<?php
/**
 * JWT Helper - Gefarm API
 * Gestione JSON Web Tokens (senza librerie esterne)
 */

require_once __DIR__ . '/../config/jwt_config.php';

class JWTHelper {
    
    /**
     * Crea un JWT token
     */
    public static function createToken($user_id, $email) {
        $issued_at = time();
        $expiration = $issued_at + JWTConfig::$token_expiration;
        
        $payload = [
            'iss' => JWTConfig::$issuer,
            'aud' => JWTConfig::$audience,
            'iat' => $issued_at,
            'exp' => $expiration,
            'data' => [
                'user_id' => $user_id,
                'email' => $email
            ]
        ];
        
        return self::encode($payload);
    }
    
    /**
     * Valida e decodifica un JWT token
     */
    public static function validateToken($token) {
        try {
            $decoded = self::decode($token);
            
            // Verifica scadenza
            if (isset($decoded->exp) && $decoded->exp < time()) {
                return ['valid' => false, 'error' => 'Token scaduto'];
            }
            
            // Verifica issuer
            if (!isset($decoded->iss) || $decoded->iss !== JWTConfig::$issuer) {
                return ['valid' => false, 'error' => 'Token non valido (issuer)'];
            }
            
            return [
                'valid' => true,
                'data' => $decoded->data
            ];
            
        } catch (Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Codifica JWT (header.payload.signature)
     */
    private static function encode($payload) {
        $header = [
            'typ' => 'JWT',
            'alg' => JWTConfig::$algorithm
        ];
        
        $header_encoded = self::base64UrlEncode(json_encode($header));
        $payload_encoded = self::base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac(
            'sha256',
            "$header_encoded.$payload_encoded",
            JWTConfig::$secret_key,
            true
        );
        $signature_encoded = self::base64UrlEncode($signature);
        
        return "$header_encoded.$payload_encoded.$signature_encoded";
    }
    
    /**
     * Decodifica JWT
     */
    private static function decode($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            throw new Exception('Token JWT malformato');
        }
        
        list($header_encoded, $payload_encoded, $signature_encoded) = $parts;
        
        // Verifica firma
        $signature = hash_hmac(
            'sha256',
            "$header_encoded.$payload_encoded",
            JWTConfig::$secret_key,
            true
        );
        $signature_check = self::base64UrlEncode($signature);
        
        if ($signature_encoded !== $signature_check) {
            throw new Exception('Firma JWT non valida');
        }
        
        // Decodifica payload
        $payload = json_decode(self::base64UrlDecode($payload_encoded));
        
        if (!$payload) {
            throw new Exception('Payload JWT non valido');
        }
        
        return $payload;
    }
    
    /**
     * Base64 URL-safe encode
     */
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL-safe decode
     */
    private static function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
    
    /**
     * Estrai token dall'header Authorization
     */
    public static function getBearerToken() {
        $headers = getallheaders();
        
        if (isset($headers['Authorization'])) {
            $matches = [];
            if (preg_match('/Bearer\s+(.*)$/i', $headers['Authorization'], $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
}
?>
