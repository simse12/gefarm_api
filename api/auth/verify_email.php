<?php
/**
 * Email Verification Endpoint - GeFarm API
 * POST /api/auth/verify_email.php
 * Attiva l'account utente tramite codice a 5 cifre.
 * * Input JSON atteso:
 * { "email": "utente@example.com", "token": "12345" }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Metodo non consentito', 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        Response::error('Dati JSON non validi', 400);
    }

    $email = $input['email'] ?? null;
    $token = $input['token'] ?? null;

    if (empty($email) || empty($token)) {
        Response::validationError(['email' => 'Email e token sono obbligatori']);
    }

    // 1. Verifica la lunghezza del token (5 cifre)
    if (!ctype_digit($token) || strlen($token) !== 5) {
         Response::error('Il codice di verifica deve essere un numero a 5 cifre', 400);
    }
    
    $user_model = new User();
    
    // 2. Ottieni l'ID utente dall'email
    $user_data = $user_model->getByEmail($email);
if (!$user_data) {
    Response::error('Utente non trovato.', 404);
}
$user_id = $user_data['id'];
    
    // Se l'utente è già verificato, rispondiamo con successo
    if ($user_data['user']['email_verified']) {
        Response::success([], 'Email già verificata. Account già attivo.');
    }

    $conn = $user_model->getConnection(); 

    // 3. Cerca il token di verifica
    $query = "SELECT id FROM gefarm_password_reset_tokens 
              WHERE user_id = :user_id AND token = :token 
              AND type = 'verify' AND used = FALSE 
              AND expires_at > NOW() LIMIT 1";
              
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    $token_record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$token_record) {
        // Se non trovato, è sbagliato, scaduto o già usato
        Response::error('Codice di verifica non valido o scaduto. Riprova o richiedi un nuovo codice.', 401);
    }
    
    $token_id = $token_record['id'];

    // Inizia la transazione per garantire che entrambi gli aggiornamenti avvengano
    $conn->beginTransaction();

    try {
        // 4. Marca l'utente come verificato
        if (!$user_model->markEmailVerified($user_id)) {
    $conn->rollBack();
    Response::serverError("Impossibile verificare l'email.");
}
        
        // 5. Marca il token come usato
        $update_token_query = "UPDATE gefarm_password_reset_tokens SET used = TRUE WHERE id = :id";
        $update_stmt = $conn->prepare($update_token_query);
        $update_stmt->bindParam(':id', $token_id);
        
        if (!$update_stmt->execute()) {
             throw new Exception("Fallimento nel marcare il token come usato.");
        }
        
        // 6. Commit e successo
        $conn->commit();
        
        // Restituisce l'utente aggiornato
        $updated_user_data = $user_model->getById($user_id);
        
        Response::success([
            'user' => $updated_user_data
        ], 'Email verificata con successo. Account attivo!');
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Email verification transaction failed for user {$user_id}: " . $e->getMessage());
        Response::serverError('Errore durante l\'attivazione dell\'account');
    }

} catch (Exception $e) {
    error_log("Verify Email endpoint error: " . $e->getMessage());
    Response::serverError('Errore del server');
}
?>