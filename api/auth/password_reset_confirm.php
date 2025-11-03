<?php
/**
 * Password Reset Confirm Endpoint - Gefarm API v2.2
 * POST /api/auth/password_reset_confirm.php
 * 
 * Conferma il reset della password con token e imposta nuova password
 * 
 * BODY (JSON):
 * {
 *   "email": "user@example.com",
 *   "token": "123456",
 *   "new_password": "NewSecurePass123"
 * }
 * 
 * RESPONSE:
 * - 200: Password reimpostata con successo
 * - 400: Dati non validi o password non sicura
 * - 401: Token non valido
 * - 404: Utente non trovato
 * - 410: Token scaduto
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
error_log("=== PASSWORD RESET CONFIRM ===");
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
    $new_password = isset($input['new_password']) ? $input['new_password'] : '';
    
    error_log("Password reset confirmation for: $email");
    
    // Validazione email
    if (!Validator::email($email)) {
        error_log("Invalid email format: $email");
        Response::error('Email non valida', 400);
    }
    
    // Validazione token (deve essere numerico di 6 cifre)
    if (empty($token) || !preg_match('/^\d{6}$/', $token)) {
        error_log("Invalid token format");
        Response::error('Token non valido', 400);
    }
    
    // Validazione password
    $password_check = Validator::password($new_password);
    if (!$password_check['valid']) {
        error_log("Password validation failed: {$password_check['error']}");
        Response::error($password_check['error'], 400);
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
        Response::error('Utente non trovato', 404);
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 4. VERIFICA ESISTENZA TOKEN
    if (empty($user['reset_token'])) {
        error_log("No reset token found for: $email");
        Response::error('Nessuna richiesta di reset attiva', 401);
    }
    
    // 5. VERIFICA SCADENZA TOKEN
    $now = new DateTime();
    $expiry = new DateTime($user['reset_token_expiry']);
    
    if ($now > $expiry) {
        error_log("Token expired for: $email (expiry: {$user['reset_token_expiry']})");
        
        // Pulisce il token scaduto
        $query_clean = "UPDATE gefarm_users SET reset_token = NULL, reset_token_expiry = NULL WHERE id = :user_id";
        $stmt_clean = $db->prepare($query_clean);
        $stmt_clean->bindParam(':user_id', $user['id']);
        $stmt_clean->execute();
        
        Response::error('Token scaduto. Richiedi un nuovo reset della password.', 410);
    }
    
    // 6. VERIFICA TOKEN
    if ($user['reset_token'] !== $token) {
        error_log("Invalid token for $email - Expected: {$user['reset_token']}, Got: $token");
        Response::error('Token non valido', 401);
    }
    
    // 7. HASH NUOVA PASSWORD
    $password_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
    
   // 8. AGGIORNA PASSWORD E PULISCE TOKEN
    $query_update = "UPDATE gefarm_users 
                    SET password_hash = :password_hash, 
                        reset_token = NULL, 
                        reset_token_expiry = NULL
                    WHERE id = :user_id";
    
    $stmt_update = $db->prepare($query_update);
    $stmt_update->bindParam(':password_hash', $password_hash);
    $stmt_update->bindParam(':user_id', $user['id']);
    
    if (!$stmt_update->execute()) {
        error_log("ERROR: Failed to update password - " . json_encode($stmt_update->errorInfo()));
        Response::error('Errore durante il reset della password', 500);
    }
    
    error_log("Password reset successful for: $email (User ID: {$user['id']})");
    
    // 9. INVIO EMAIL DI CONFERMA (opzionale ma consigliato)
    // EmailHelper::sendPasswordChangedNotification($user['email'], $user['nome']);
    
    // 10. RISPOSTA SUCCESSO
    Response::success([
        'user_id' => (int)$user['id'],
        'email' => $user['email'],
        'message' => 'Password reimpostata con successo. Ora puoi effettuare il login con la nuova password.'
    ], 200);
    
} catch (PDOException $e) {
    error_log("DATABASE ERROR in password_reset_confirm.php: " . $e->getMessage());
    Response::error('Errore del database', 500);
    
} catch (Exception $e) {
    error_log("GENERAL ERROR in password_reset_confirm.php: " . $e->getMessage());
    Response::error('Errore del server', 500);
}
?>
