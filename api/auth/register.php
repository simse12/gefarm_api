<?php
/**
 * User Registration Endpoint - Gefarm API v2.2
 * POST /api/auth/register.php
 * 
 * Registra un nuovo utente e invia email di verifica
 * 
 * BODY (JSON):
 * {
 *   "email": "user@example.com",
 *   "password": "SecurePass123",
 *   "nome": "Mario",
 *   "cognome": "Rossi",
 *   "telefono": "3331234567" (opzionale)
 * }
 * 
 * RESPONSE:
 * - 201: Utente creato con successo
 * - 400: Dati non validi
 * - 409: Email già registrata
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
error_log("=== REGISTER REQUEST ===");
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
    $nome = isset($input['nome']) ? trim($input['nome']) : '';
    $cognome = isset($input['cognome']) ? trim($input['cognome']) : '';
    $telefono = isset($input['telefono']) ? trim($input['telefono']) : null;
    
    error_log("Registration attempt for: $email");
    
    // Validazioni obbligatorie
    $validations = [
        'email' => Validator::email($email),
        'password' => Validator::password($password),
        'nome' => Validator::required($nome, 'Nome'),
        'cognome' => Validator::required($cognome, 'Cognome')
    ];
    
    // Controllo lunghezze
    if ($validations['nome']['valid']) {
        $validations['nome'] = Validator::length($nome, 2, 50, 'Nome');
    }
    if ($validations['cognome']['valid']) {
        $validations['cognome'] = Validator::length($cognome, 2, 50, 'Cognome');
    }
    
    // Validazione telefono (opzionale)
    if (!empty($telefono)) {
        $validations['telefono'] = Validator::phone($telefono);
    }
    
    // Raccolta errori
    $errors = [];
    foreach ($validations as $field => $result) {
        if (is_array($result) && !$result['valid']) {
            $errors[$field] = $result['error'];
        } elseif ($result === false) {
            $errors[$field] = ucfirst($field) . " non valido";
        }
    }
    
    if (!empty($errors)) {
        error_log("Validation errors: " . json_encode($errors));
        Response::error('Dati non validi', 400, $errors);
    }
    
    // 2. CONNESSIONE DATABASE
   $database = Database::getInstance();
   $db = $database->getConnection();
    
    if (!$db) {
        error_log("ERROR: Database connection failed");
        Response::error('Errore di connessione al database', 500);
    }
    
    // 3. VERIFICA EMAIL UNIVOCA
    $query_check = "SELECT id FROM gefarm_users WHERE email = :email LIMIT 1";
    $stmt_check = $db->prepare($query_check);
    $stmt_check->bindParam(':email', $email);
    $stmt_check->execute();
    
    if ($stmt_check->rowCount() > 0) {
        error_log("Registration failed: Email already exists - $email");
        Response::error('Email già registrata', 409);
    }
    
    // 4. GENERA TOKEN DI VERIFICA
    $reset_token = TokenHelper::generateNumericToken(6); // Token a 6 cifre
    $reset_token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    error_log("Generated verification token: $reset_token (expires: $reset_token_expiry)");
    
    // 5. HASH PASSWORD
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // 6. SANITIZZAZIONE DATI
    $nome_safe = Validator::sanitizeString($nome);
    $cognome_safe = Validator::sanitizeString($cognome);
    $telefono_safe = !empty($telefono) ? Validator::sanitizeString($telefono) : null;
    
    // 7. INSERIMENTO NEL DATABASE
    $query_insert = "INSERT INTO gefarm_users 
    (email, password_hash, nome, cognome, telefono, 
    reset_token, reset_token_expiry, email_verified, created_at) 
    VALUES 
    (:email, :password_hash, :nome, :cognome, :telefono, 
    :reset_token, :reset_token_expiry, 0, NOW())";
    
    $stmt_insert = $db->prepare($query_insert);
    $stmt_insert->bindParam(':email', $email);
    $stmt_insert->bindParam(':password_hash', $password_hash);
    $stmt_insert->bindParam(':nome', $nome_safe);
    $stmt_insert->bindParam(':cognome', $cognome_safe);
    $stmt_insert->bindParam(':telefono', $telefono_safe);
    $stmt_insert->bindParam(':reset_token', $reset_token);
    $stmt_insert->bindParam(':reset_token_expiry', $reset_token_expiry);

    if (!$stmt_insert->execute()) {
    error_log("ERROR: Failed to insert user - " . json_encode($stmt_insert->errorInfo()));
    Response::error('Errore durante la registrazione', 500);
    }
     
    
    $user_id = $db->lastInsertId();
    error_log("User created successfully - ID: $user_id");
    
    // 8. INVIO EMAIL DI VERIFICA
    $email_sent = EmailHelper::sendVerificationEmail(
        $email,
        $nome_safe,
        $reset_token
    );
    
    if (!$email_sent) {
        error_log("WARNING: Verification email not sent to $email");
        // Non blocchiamo la registrazione, l'utente può richiedere un nuovo token
    } else {
        error_log("Verification email sent successfully to $email");
    }
    
    // 9. RISPOSTA SUCCESSO
    Response::success([
        'user_id' => (int)$user_id,
        'email' => $email,
        'nome' => $nome_safe,
        'cognome' => $cognome_safe,
        'email_verified' => false,
        'message' => 'Registrazione completata. Controlla la tua email per verificare l\'account.'
    ], 201);
    
} catch (PDOException $e) {
    error_log("DATABASE ERROR in register.php: " . $e->getMessage());
    // MOSTRA IL VERO ERRORE NEL JSON:
Response::error('Errore del DB: ' . $e->getMessage(), 500);
}
catch (Exception $e) {
     error_log("Genaral ERROR in register.php: " . $e->getMessage());
    // MOSTRA IL VERO ERRORE NEL JSON:
Response::error('Errore generico: ' . $e->getMessage(), 500);
}
?>
