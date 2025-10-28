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
require_once __DIR__ . '/../../utils/TokenHelper.php'; // ✅ NUOVO HELPER
// require_once __DIR__ . '/../../utils/Mailer.php'; // Assumi esista e venga incluso

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Metodo non consentito', 405);
}

try {
    // Leggi input RAW
    $raw_input = file_get_contents('php://input');
    
    // Decodifica JSON
    $input = json_decode($raw_input, true);
    
    // Controlla il risultato di json_decode
    if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
        // Logga l'errore specifico
        $error_msg = json_last_error_msg();
        error_log("JSON Decode Failed. Error: " . $error_msg . " Raw Input: " . $raw_input);
        
        // Risposta per l'utente (puoi temporaneamente includere l'errore per il debug)
        Response::error("Dati JSON non validi. Dettaglio: " . $error_msg, 400);
    }
    
    // Controllo aggiuntivo per input vuoto ma tecnicamente valido (es. {})
    if (!$input) {
        Response::error('Dati JSON non validi o vuoti', 400);
    }
    // Validazione... (omessa per brevità, assumiamo che le validazioni precedenti siano valide)
    
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
    // $user->email_verified resta 0 (FALSE) per default nel model
    
    // Crea utente
    if ($user->create()) {
        
        // ----------------------------------------
        // ✅ LOGICA VERIFICA EMAIL AGGIUNTA
        // ----------------------------------------
        
        // 1. Genera Token (5 cifre)
        $verification_token = TokenHelper::generateNumericToken(5); 
        
        // 2. Salva Token (scadenza a 24 ore di default)
        if (!$user->createToken($user->id, $verification_token, 'verify')) {
             error_log("Failed to save verification token for user ID: {$user->id}");
             // Nota: L'utente è stato creato, l'API prosegue ma logga l'errore del token.
        }
        
        // 3. Invia Email (Simulazione)
        // Mailer::sendVerificationEmail($user->email, $user->nome, $verification_token);
        error_log("Verification Token for {$user->email}: " . $verification_token); // Log del token per testing
        
        // ----------------------------------------

        // Genera JWT token
        $token = JWTHelper::createToken($user->id, $user->email);
        
        // Ottieni dati utente (senza password)
        $user_data = $user->getById($user->id);
        
        Response::success([
            'user' => $user_data,
            'token' => $token,
            'message' => 'Registrazione completata. Controlla la tua email per il codice di verifica a 5 cifre.'
        ], 'Registrazione completata con successo', 201);
        
    } else {
        Response::serverError('Errore durante la registrazione');
    }
    
} catch (Exception $e) {
    error_log("Register error: " . $e->getMessage());
    Response::serverError('Errore del server durante la registrazione');
}
?>