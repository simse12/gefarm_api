<?php
/**
 * JWT Configuration - GeFarm API
 * Configurazione per JSON Web Tokens
 */

class JWTConfig {
    // ⚠️ IMPORTANTE: Cambia questa chiave in produzione!
    // Genera con: openssl rand -base64 32
    public static $secret_key = "insert secret key";
    
    // Algoritmo di firma
    public static $algorithm = 'HS256';
    
    // Durata token in secondi (es. 1 ora = 3600 secondi)
    public static $token_expiration = 3600;

    // Durata refresh token in secondi (es. 7 giorni = 604800 secondi)
    public static $refresh_token_expiration = 604800;
    
    // Issuer (chi emette il token)
    public static $issuer = "insert issuer";
    
    // Audience (per chi è il token)
    public static $audience = "insert audience";
}
?>
