<?php
/**
 * Resend Email Verification Endpoint - Gefarm API v2.2
 * POST /api/auth/resend_verification.php
 * 
 * Reinvia l'email di verifica con un nuovo token
 * 
 * BODY (JSON):
 * {
 *   "email": "user@example.com"
 * }
 * 
 * RESPONSE:
 * - 200: Email di verifica reinviata
 * - 400: Email non valida
 * - 404: Utente non trovato
 * - 409: Email già verificata
 * - 429: Troppi tentativi (rate limiting)
 * - 500: Errore server
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestione preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo metodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Metodo non consentito. Utilizzare POST.'
    ]);
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/validator.php';
require_once __DIR__ . '/../../utils/tokenHelper.php';
require_once __DIR__ . '/../../utils/email_helper.php';
require_once __DIR__ . '/../../utils/response.php';

// Log della richiesta
error_log("=== RESEND VERIFICATION REQUEST ===");
error_log("IP: " . $_SERVER['REMOTE_ADDR']);
error_log("Time: " . date('Y-m-d H:i:s'));

try {
    // 1. LETTURA E VALIDAZIONE INPUT
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        Response::error('Dati JSON non validi', 400);
    }
    
    // Estrazione email
    $email = isset($input['email']) ? trim($input['email']) : '';
    
    error_log("Resend verification request for: $email");
    
    // Validazione email
    if (!Validator::email($email)) {
        error_log("Invalid email format: $email");
        Response::error('Email non valida', 400);
    }
    
    // 2. CONNESSIONE DATABASE
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    if (!$db) {
        error_log("ERROR: Database connection failed");
        Response::error('Errore di connessione al database', 500);
    }
    
    // 3. RICERCA UTENTE
    $query = "SELECT id, email, nome, email_verified, reset_token, reset_token_expiry, created_at 
              FROM gefarm_users
              WHERE email = :email 
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        error_log("User not found: $email");
        Response::error('Utente non trovato', 404);
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 4. VERIFICA SE GIÀ VERIFICATO
    if ($user['email_verified'] == 1) {
        error_log("Email already verified: $email");
        Response::error('Email già verificata', 409);
    }
    
    // 5. RATE LIMITING - Max 1 richiesta ogni 2 minuti
    if (!empty($user['reset_token_expiry'])) {
        $token_created = (new DateTime($user['reset_token_expiry']))->modify('-24 hours');
        $now = new DateTime();
        $cooldown = (clone $token_created)->modify('+2 minutes');
        
        if ($now < $cooldown) {
            $wait_seconds = $cooldown->getTimestamp() - $now->getTimestamp();
            error_log("Rate limiting: Recent verification request for $email");
            Response::error("Attendi ancora $wait_seconds secondi prima di richiedere un nuovo codice.", 429);
        }
    }
    
    // 6. GENERA NUOVO TOKEN
    $reset_token = TokenHelper::generateNumericToken(6);
    $reset_token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    error_log("Generated new verification token: $reset_token (expires: $reset_token_expiry)");
    
    // 7. AGGIORNA TOKEN NEL DATABASE
    $query_update = "UPDATE gefarm_users 
                     SET reset_token = :reset_token, 
                         reset_token_expiry = :reset_token_expiry 
                     WHERE id = :user_id";
    
    $stmt_update = $db->prepare($query_update);
    $stmt_update->bindParam(':reset_token', $reset_token);
    $stmt_update->bindParam(':reset_token_expiry', $reset_token_expiry);
    $stmt_update->bindParam(':user_id', $user['id']);
    
    if (!$stmt_update->execute()) {
        error_log("ERROR: Failed to update verification token - " . json_encode($stmt_update->errorInfo()));
        Response::error('Errore durante l\'aggiornamento del token', 500);
    }
    
    // 8. INVIO EMAIL
    $email_sent = EmailHelper::sendVerificationEmail(
        $user['email'],
        $user['nome'],
        $reset_token
    );
    
    if (!$email_sent) {
        error_log("ERROR: Failed to send verification email to {$user['email']}");
        Response::error('Errore durante l\'invio dell\'email', 500);
    }
    
    error_log("Verification email resent successfully to {$user['email']}");
    
    // 9. RISPOSTA SUCCESSO
    Response::success([
        'message' => 'Email di verifica inviata nuovamente. Controlla la tua casella email.',
        'expires_at' => $reset_token_expiry,
        'hint' => 'Il codice è valido per 24 ore'
    ], 200);
    
} catch (PDOException $e) {
    error_log("DATABASE ERROR in resend_verification.php: " . $e->getMessage());
    Response::error('Errore del database', 500);
    
} catch (Exception $e) {
    error_log("GENERAL ERROR in resend_verification.php: " . $e->getMessage());
    Response::error('Errore del server', 500);
}
?>
