<?php
/**
 * Update Profile Endpoint - GeFarm API
 * PUT /api/user/update_profile.php
 * Aggiorna profilo utente autenticato
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gestisci preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validator.php';

// Solo PUT
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    Response::error('Metodo non consentito', 405);
}

try {
    // Verifica autenticazione
    $auth_data = AuthMiddleware::authenticate();
    
    // Leggi input JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        Response::error('Dati JSON non validi', 400);
    }
    
    // Validazioni (se presenti)
    $errors = [];
    
    if (isset($input['nome'])) {
        $check = Validator::length($input['nome'], 2, 100, 'Nome');
        if (!$check['valid']) $errors['nome'] = $check['error'];
    }
    
    if (isset($input['cognome'])) {
        $check = Validator::length($input['cognome'], 2, 100, 'Cognome');
        if (!$check['valid']) $errors['cognome'] = $check['error'];
    }
    
    if (!empty($errors)) {
        Response::validationError($errors);
    }
    
    // Aggiorna profilo
    $user = new User();
    
    if ($user->updateProfile($auth_data->user_id, $input)) {
        // Ottieni profilo aggiornato
        $updated_profile = $user->getById($auth_data->user_id);
        
        Response::success($updated_profile, 'Profilo aggiornato con successo');
    } else {
        Response::error('Nessuna modifica effettuata', 400);
    }
    
} catch (Exception $e) {
    error_log("Update profile error: " . $e->getMessage());
    Response::serverError('Errore durante l\'aggiornamento del profilo');
}
?>
