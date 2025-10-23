<?php
/**
 * Authentication Middleware - GeFarm API
 * Verifica JWT token per endpoint protetti
 */

require_once __DIR__ . '/../utils/jwt_helper.php';
require_once __DIR__ . '/../utils/response.php';

class AuthMiddleware {
    
    /**
     * Verifica autenticazione
     * @return object User data se autenticato, altrimenti termina con errore
     */
    public static function authenticate() {
        // Ottieni token dall'header
        $token = JWTHelper::getBearerToken();
        
        if (!$token) {
            Response::unauthorized('Token di autenticazione mancante');
        }
        
        // Valida token
        $validation = JWTHelper::validateToken($token);
        
        if (!$validation['valid']) {
            Response::unauthorized($validation['error'] ?? 'Token non valido');
        }
        
        // Ritorna i dati utente
        return $validation['data'];
    }
    
    /**
     * Verifica se l'utente è il proprietario della risorsa
     */
    public static function isOwner($user_id, $resource_user_id) {
        return (int)$user_id === (int)$resource_user_id;
    }
}
?>
