<?php
/**
 * Email Verification Endpoint - Gefarm API v2.2
 * POST /api/auth/verify_email.php
 * 
 * Verifica l'email dell'utente tramite token
 * 
 * BODY (JSON):
 * {
 *   "email": "user@example.com",
 *   "token": "123456"
 * }
 * 
 * RESPONSE:
 * - 200: Email verificata con successo
 * - 400: Dati non validi
 * - 404: Utente non trovato
 * - 410: Token scaduto
 * - 401: Token non valido
 * - 409: Email già verificata
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
require_once __DIR__ . '/../../utils/response.php';

// Log della richiesta
error_log("=== EMAIL VERIFICATION REQUEST ===");
error_log("IP: " . $_SERVER['REMOTE_ADDR']);
error_log("Time: " . date('Y-m-d H:i:s'));

try {
    // 1. LETTURA E VALIDAZIONE INPUT
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        Response::error('Dati JSON non validi', 400);
    }
    
    // Estrazione campi
    $email = isset($input['email']) ? trim($input['email']) : '';
    $token = isset($input['token']) ? trim($input['token']) : '';
    
    error_log("Verification attempt for: $email");
    
    // Validazioni
    if (!Validator::email($email)) {
        error_log("Invalid email format: $email");
        Response::error('Email non valida', 400);
    }
    
    if (empty($token)) {
        error_log("Empty verification token");
        Response::error('Token di verifica obbligatorio', 400);
    }
    
    // Verifica formato token (deve essere numerico di 6 cifre)
    if (!preg_match('/^\d{6}$/', $token)) {
        error_log("Invalid token format: $token");
        Response::error('Formato token non valido', 400);
    }
    
    // 2. CONNESSIONE DATABASE
    // SBAGLIATO new Database(); usare sempre Database::getIstance();
    $database = Database::getInstance(); 
    $db = $database->getConnection();
    
    if (!$db) {
        error_log("ERROR: Database connection failed");
        Response::error('Errore di connessione al database', 500);
    }
    
    // 3. RICERCA UTENTE
    $query = "SELECT id, email, email_verified, reset_token, reset_token_expiry, nome, cognome 
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
    
    // 5. VERIFICA SCADENZA TOKEN
    $now = new DateTime();
    $expiry = new DateTime($user['reset_token_expiry']);
    
    if ($now > $expiry) {
        error_log("Token expired for: $email (expiry: {$user['reset_token_expiry']})");
        Response::error('Token scaduto. Richiedi un nuovo token di verifica.', 410);
    }
    
    // 6. VERIFICA TOKEN
    if ($user['reset_token'] !== $token) {
        error_log("Invalid token for $email - Expected: {$user['reset_token']}, Got: $token");
        Response::error('Token non valido', 401);
    }
    
    // 7. AGGIORNA UTENTE - IMPOSTA COME VERIFICATO
    $query_update = "UPDATE gefarm_users
                     SET email_verified = 1, 
                         reset_token = NULL, 
                         reset_token_expiry = NULL,
                         verified_at = NOW() 
                     WHERE id = :user_id";
    
    $stmt_update = $db->prepare($query_update);
    $stmt_update->bindParam(':user_id', $user['id']);
    
    if (!$stmt_update->execute()) {
        error_log("ERROR: Failed to update user verification - " . json_encode($stmt_update->errorInfo()));
        Response::error('Errore durante la verifica', 500);
    }
    
    error_log("Email verified successfully: $email (User ID: {$user['id']})");
    
    // 8. RISPOSTA SUCCESSO
    Response::success([
        'user_id' => (int)$user['id'],
        'email' => $user['email'],
        'nome' => $user['nome'],
        'cognome' => $user['cognome'],
        'email_verified' => true,
        'verified_at' => date('Y-m-d H:i:s'),
        'message' => 'Email verificata con successo. Ora puoi effettuare il login.'
    ], 200);
    
} catch (PDOException $e) {
    error_log("DATABASE ERROR in verify_email.php: " . $e->getMessage());
    Response::error('Errore del database', 500);
    
} catch (Exception $e) {
    error_log("GENERAL ERROR in verify_email.php: " . $e->getMessage());
    Response::error('Errore del server', 500);
}
?>






