<?php
/**
 * Register Endpoint - GeFarm API
 * POST /api/auth/register.php
 * Registrazione nuovo utente
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
    $required_fields = ['email', 'password', 'nome', 'cognome'];
    $missing = [];
    
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        Response::validationError($missing, 'Campi obbligatori mancanti');
    }
    
    // Validazione email
    if (!Validator::email($input['email'])) {
        Response::validationError(['email' => 'Email non valida']);
    }
    
    // Validazione password
    $password_check = Validator::password($input['password']);
    if (!$password_check['valid']) {
        Response::validationError(['password' => $password_check['error']]);
    }
    
    // Validazione nome e cognome
    $nome_check = Validator::length($input['nome'], 2, 100, 'Nome');
    if (!$nome_check['valid']) {
        Response::validationError(['nome' => $nome_check['error']]);
    }
    
    $cognome_check = Validator::length($input['cognome'], 2, 100, 'Cognome');
    if (!$cognome_check['valid']) {
        Response::validationError(['cognome' => $cognome_check['error']]);
    }
    
    // Crea utente
    $user = new User();
    
    // Verifica se email esiste già
    if ($user->emailExists($input['email'])) {
        Response::error('Email già registrata', 409);
    }
    
    // Imposta dati
    $user->email = $input['email'];
    $user->password_hash = $input['password']; // Verrà hashata nel model
    $user->nome = $input['nome'];
    $user->cognome = $input['cognome'];
    $user->avatar_color = $input['avatar_color'] ?? '#00853d';
    
    // Crea utente
    if ($user->create()) {
        // Genera JWT token
        $token = JWTHelper::createToken($user->id, $user->email);
        
        // Ottieni dati utente (senza password)
        $user_data = $user->getById($user->id);
        
        Response::success([
            'user' => $user_data,
            'token' => $token
        ], 'Registrazione completata con successo', 201);
        
    } else {
        Response::serverError('Errore durante la registrazione');
    }
    
} catch (Exception $e) {
    error_log("Register error: " . $e->getMessage());
    Response::serverError('Errore del server durante la registrazione');
}
?>
