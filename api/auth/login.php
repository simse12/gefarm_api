<?php
/**
 * User Login Endpoint - Gefarm API v2.2
 * POST /api/auth/login.php
 * 
 * Autentica l'utente e rilascia JWT token (solo se email verificata)
 * 
 * BODY (JSON):
 * {
 *   "email": "user@example.com",
 *   "password": "SecurePass123"
 * }
 * 
 * RESPONSE:
 * - 200: Login effettuato con successo (restituisce JWT token)
 * - 400: Dati non validi
 * - 401: Credenziali errate
 * - 403: Email non verificata
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
require_once __DIR__ . '/../../utils/jwt_helper.php';
require_once __DIR__ . '/../../utils/response.php';

// Log della richiesta
error_log("=== LOGIN REQUEST ===");
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
    $password = isset($input['password']) ? $input['password'] : '';
    
    error_log("Login attempt for: $email");
    
    // Validazioni
    if (!Validator::email($email)) {
        error_log("Invalid email format: $email");
        Response::error('Email non valida', 400);
    }
    
    if (empty($password)) {
        error_log("Empty password");
        Response::error('Password obbligatoria', 400);
    }
    
    // 2. CONNESSIONE DATABASE
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    if (!$db) {
        error_log("ERROR: Database connection failed");
        Response::error('Errore di connessione al database', 500);
    }
    
    // 3. RICERCA UTENTE
    $query = "SELECT id, email, password_hash, nome, cognome, telefono, email_verified, created_at 
              FROM gefarm_users
              WHERE email = :email 
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        error_log("Login failed: User not found - $email");
        // Per sicurezza, non distinguiamo tra utente non trovato e password errata
        Response::error('Credenziali non valide', 401);
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 4. VERIFICA PASSWORD
    if (!password_verify($password, $user['password_hash'])) {
        error_log("Login failed: Invalid password for - $email");
        Response::error('Credenziali non valide', 401);
    }
    
    // 5. VERIFICA EMAIL VERIFICATA (CRITICO!)
    if ($user['email_verified'] != 1) {
        error_log("Login blocked: Email not verified - $email");
        Response::error('Email non verificata. Controlla la tua email per il link di verifica.', 403, [
            'email_verified' => false,
            'hint' => 'Usa /api/auth/resend_verification per ricevere un nuovo codice'
        ]);
    }
    
    // 6. GENERA JWT TOKEN
    $jwt_token = JWTHelper::createToken($user['id'], $user['email']);
    
    if (!$jwt_token) {
        error_log("ERROR: Failed to generate JWT token for user ID: {$user['id']}");
        Response::error('Errore durante la generazione del token', 500);
    }
    
    error_log("Login successful for: $email (User ID: {$user['id']})");
    
   
    // 7. AGGIORNA ULTIMO LOGIN
    $query_update = "UPDATE gefarm_users SET last_login = NOW() WHERE id = :user_id";
    $stmt_update = $db->prepare($query_update);
    $stmt_update->bindParam(':user_id', $user['id']);
    $stmt_update->execute();
    
    // 8. RISPOSTA SUCCESSO
    Response::success([
        'token' => $jwt_token,
        'user' => [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'nome' => $user['nome'],
            'cognome' => $user['cognome'],
            'telefono' => $user['telefono'],
            'email_verified' => true,
            'created_at' => $user['created_at']
        ],
        'message' => 'Login effettuato con successo'
    ], 200);
    
} catch (PDOException $e) {
    error_log("DATABASE ERROR in login.php: " . $e->getMessage());
    Response::error('Errore del database', 500);
    
} catch (Exception $e) {
    error_log("GENERAL ERROR in login.php: " . $e->getMessage());
    Response::error('Errore del server', 500);
}
?>
