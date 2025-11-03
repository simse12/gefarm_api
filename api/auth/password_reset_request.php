<?php
/**
 * Password Reset Request Endpoint - Gefarm API v2.2
 * POST /api/auth/password_reset_request.php
 * 
 * Richiede il reset della password e invia email con token
 * 
 * BODY (JSON):
 * {
 *   "email": "user@example.com"
 * }
 * 
 * RESPONSE:
 * - 200: Email di reset inviata con successo
 * - 400: Email non valida
 * - 404: Utente non trovato
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
error_log("=== PASSWORD RESET REQUEST ===");
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
    
    error_log("Password reset request for: $email");
    
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
    $query = "SELECT id, email, nome, reset_token, reset_token_expiry 
              FROM gefarm_users
              WHERE email = :email 
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        error_log("User not found: $email");
        // Per sicurezza, non rivelare se l'email esiste o meno
        Response::success([
            'message' => 'Se l\'email è registrata, riceverai le istruzioni per il reset della password.'
        ], 200);
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 4. RATE LIMITING - Verifica se esiste già un token valido recente
    if (!empty($user['reset_token']) && !empty($user['reset_token_expiry'])) {
        $expiry = new DateTime($user['reset_token_expiry']);
        $now = new DateTime();
        
        // Se il token è ancora valido da meno di 5 minuti, blocca la richiesta
        $created = (clone $expiry)->modify('-1 hour'); // Token valido per 1 ora
        $cooldown = (clone $created)->modify('+5 minutes');
        
        if ($now < $cooldown) {
            error_log("Rate limiting: Recent reset request for $email");
            Response::error('Hai già richiesto un reset. Attendi qualche minuto prima di riprovare.', 429);
        }
    }
    
    // 5. GENERA TOKEN DI RESET
    $reset_token = TokenHelper::generateNumericToken(6); // Token a 6 cifre
    $reset_token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour')); // Valido per 1 ora
    
    error_log("Generated reset token: $reset_token (expires: $reset_token_expiry)");
    
    // 6. SALVA TOKEN NEL DATABASE
    $query_update = "UPDATE gefarm_users 
                     SET reset_token = :reset_token, 
                         reset_token_expiry = :reset_token_expiry 
                     WHERE id = :user_id";
    
    $stmt_update = $db->prepare($query_update);
    $stmt_update->bindParam(':reset_token', $reset_token);
    $stmt_update->bindParam(':reset_token_expiry', $reset_token_expiry);
    $stmt_update->bindParam(':user_id', $user['id']);
    
    if (!$stmt_update->execute()) {
        error_log("ERROR: Failed to save reset token - " . json_encode($stmt_update->errorInfo()));
        Response::error('Errore durante la richiesta di reset', 500);
    }
    
    // 7. INVIO EMAIL CON TOKEN
    $email_sent = EmailHelper::sendPasswordResetEmail(
        $user['email'],
        $user['nome'],
        $reset_token
    );
    
    if (!$email_sent) {
        error_log("ERROR: Failed to send password reset email to {$user['email']}");
        Response::error('Errore durante l\'invio dell\'email', 500);
    }
    
    error_log("Password reset email sent successfully to {$user['email']}");
    
    // 8. RISPOSTA SUCCESSO (generica per sicurezza)
    Response::success([
        'message' => 'Se l\'email è registrata, riceverai le istruzioni per il reset della password.',
        'hint' => 'Controlla anche la cartella spam'
    ], 200);
    
} catch (PDOException $e) {
    error_log("DATABASE ERROR in password_reset_request.php: " . $e->getMessage());
    Response::error('Errore del database', 500);
    
} catch (Exception $e) {
    error_log("GENERAL ERROR in password_reset_request.php: " . $e->getMessage());
    Response::error('Errore del server', 500);
}
?>
