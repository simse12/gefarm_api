<?php
/**
 * Endpoint: POST /api/auth/password_reset_confirm
 * Descrizione: Conferma reset password con token ricevuto
 * Autenticazione: Non richiesta
 */

// Require necessari
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validator.php';
require_once __DIR__ . '/../../config/encryption_config.php';
require_once __DIR__ . '/../../utils/email_helper.php';  // Aggiungiamo EmailHelper

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestione preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Metodo non consentito. Usa POST.', 405);
    exit;
}

try {
    // Ottieni e valida input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        Response::error('Dati JSON non validi', 400);
    }
    
    // Validazione campi richiesti
    if (empty($input['token']) || empty($input['new_password'])) {
        Response::error('Token e nuova password sono obbligatori', 400);
    }
    
    // Validazione nuova password
    $password_check = Validator::password($input['new_password']);
    if (!$password_check['valid']) {
        Response::validationError(['new_password' => $password_check['error']]);
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Verifica token
    $stmt = $db->prepare("SELECT prt.*, u.email, u.nome 
                         FROM gefarm_password_reset_tokens prt
                         JOIN gefarm_users u ON prt.user_id = u.id
                         WHERE prt.token = :token 
                         AND prt.used = 0 
                         AND prt.expires_at > NOW()
                         LIMIT 1");
    $stmt->bindValue(':token', $input['token'], PDO::PARAM_STR);
    $stmt->execute();
    $reset_token = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reset_token) {
        Response::error('Token non valido o scaduto', 400);
    }
    
    // Hash nuova password
    $new_password_hash = EncryptionConfig::hashPassword($input['new_password']);
    
    // Aggiorna password
    $stmt = $db->prepare("UPDATE gefarm_users 
                         SET password_hash = :password_hash 
                         WHERE id = :user_id");
    $stmt->bindValue(':password_hash', $new_password_hash, PDO::PARAM_STR);
    $stmt->bindValue(':user_id', $reset_token['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    
    // Marca token come usato
    $stmt = $db->prepare("UPDATE gefarm_password_reset_tokens 
                         SET used = 1 
                         WHERE id = :id");
    $stmt->bindValue(':id', $reset_token['id'], PDO::PARAM_INT);
    $stmt->execute();
    
    // Invia email di conferma
    EmailHelper::sendPasswordChanged($reset_token['email'], $reset_token['nome']);
    
    Response::success(null, 'Password reimpostata con successo. Ora puoi effettuare il login con la nuova password.');
    
} catch (Exception $e) {
    error_log("Reset password confirmation error: " . $e->getMessage());
    Response::serverError('Errore durante la conferma del reset password: ' . $e->getMessage());
}
?>