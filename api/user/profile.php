<?php
/**
 * Profile Endpoint - GeFarm API
 * GET /api/user/profile.php
 * Ottieni profilo utente autenticato
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gestisci preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/response.php';

// Solo GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Metodo non consentito', 405);
}

try {
    // Verifica autenticazione
    $auth_data = AuthMiddleware::authenticate();
    
    // Ottieni profilo utente
    $user = new User();
    $profile = $user->getById($auth_data->user_id);
    
    if (!$profile) {
        Response::notFound('Utente non trovato');
    }
    
    Response::success($profile, 'Profilo recuperato con successo');
    
} catch (Exception $e) {
    error_log("Profile error: " . $e->getMessage());
    Response::serverError('Errore durante il recupero del profilo');
}
?>
