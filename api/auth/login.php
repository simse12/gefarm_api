<?php
/**
 * Login Endpoint - GeFarm API
 * POST /api/auth/login.php
 * Login utente esistente
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Gestisci preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validator.php';
require_once __DIR__ . '/../../utils/jwt_helper.php';

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Metodo non consentito', 405);
}

try {
    // Leggi input JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        Response::error('Dati JSON non validi', 400);
    }
    
    // Campi richiesti
    if (empty($input['email']) || empty($input['password'])) {
        Response::error('Email e password sono obbligatori', 400);
    }
    
    // Validazione email
    if (!Validator::email($input['email'])) {
        Response::error('Email non valida', 400);
    }
    
    // Tentativo login
    $user = new User();
    $login_result = $user->login($input['email'], $input['password']);
    
    if (!$login_result['success']) {
        Response::unauthorized($login_result['error']);
    }
    
    // Genera JWT token
    $token = JWTHelper::createToken(
        $login_result['user']['id'], 
        $login_result['user']['email']
    );
    
    Response::success([
        'user' => $login_result['user'],
        'token' => $token
    ], 'Login effettuato con successo');
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    Response::serverError('Errore del server durante il login');
}
?>
