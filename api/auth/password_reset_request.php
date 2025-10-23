<?php
/**
 * Endpoint: POST /api/auth/password_reset_request
 * Descrizione: Richiede reset password, genera token e invia email
 * Autenticazione: Non richiesta
 */

// Require necessari
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validator.php';
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
    
    // Validazione email
    if (empty($input['email'])) {
        Response::error('Email è obbligatoria', 400);
    }
    
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        Response::validationError(['email' => 'Email non valida']);
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Verifica che l'utente esista
    $stmt = $db->prepare("SELECT id, nome FROM gefarm_users WHERE email = :email LIMIT 1");
    $stmt->bindValue(':email', $input['email'], PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Per sicurezza, informiamo sempre che l'email è stata inviata anche se l'utente non esiste
    if (!$user) {
        // In produzione, non dovremmo dire se l'email esiste o meno
        Response::success(null, 'Se l\'indirizzo è associato a un account, riceverai un\'email con le istruzioni per reimpostare la password.');
        exit;
    }
    
    // Genera token
    $token = bin2hex(random_bytes(16)); // 32 caratteri hex
    
    // Calcola scadenza (60 minuti)
    $expires_at = date('Y-m-d H:i:s', strtotime('+60 minutes'));
    
    // Rimuovi vecchi token non utilizzati per questo utente
    $stmt = $db->prepare("DELETE FROM gefarm_password_reset_tokens 
                         WHERE user_id = :user_id AND used = 0");
    $stmt->bindValue(':user_id', $user['id'], PDO::PARAM_INT);
    $stmt->execute();
    
    // Inserisci nuovo token
    $stmt = $db->prepare("INSERT INTO gefarm_password_reset_tokens
                        (user_id, token, expires_at, used)
                        VALUES
                        (:user_id, :token, :expires_at, 0)");
    $stmt->bindValue(':user_id', $user['id'], PDO::PARAM_INT);
    $stmt->bindValue(':token', $token, PDO::PARAM_STR);
    $stmt->bindValue(':expires_at', $expires_at, PDO::PARAM_STR);
    
    if ($stmt->execute()) {
        // Invia email con il token
        $email_sent = EmailHelper::sendPasswordResetToken($input['email'], $user['nome'], $token);
        
        // Risposta all'utente
        if ($email_sent) {
            Response::success(null, 'Ti abbiamo inviato un\'email con le istruzioni per reimpostare la password.');
        } else {
            // In caso di errore di invio email, mostra il token (solo per testing)
            Response::success([
                'testing_only' => [
                    'token' => $token, 
                    'expires_at' => $expires_at
                ]
            ], 'Email non inviata per problemi tecnici. Token mostrato SOLO per testing.');
        }
    } else {
        Response::serverError('Errore durante la generazione del token');
    }
    
} catch (Exception $e) {
    error_log("Reset password request error: " . $e->getMessage());
    Response::serverError('Errore: ' . $e->getMessage());
}
?>