<?php
/**
 * Endpoint: POST /api/meter/submit
 * Descrizione: Invia dati contatore (Chain2) per un dispositivo
 * Autenticazione: Richiesta
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gestisci preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../models/Device.php';
require_once __DIR__ . '/../../models/DeviceMeterData.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validator.php';
require_once __DIR__ . '/../../config/encryption_config.php';
require_once __DIR__ . '/../../config/database.php';  

// Configura headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Metodo non consentito. Usa POST.', 405);
    exit;
}

try {
    // Verifica autenticazione
    $auth_data = AuthMiddleware::authenticate();
    
    // Ottieni e valida input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        Response::error('Dati JSON non validi', 400);
    }
    
    // Campi obbligatori
    $required_fields = [
        'device_id', 'cf', 'nome', 'cognome', 'indirizzo', 
        'zip_code', 'citta', 'provincia', 'email'
    ];
    
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            Response::error("Il campo '$field' è obbligatorio", 400);
        }
    }
    
    // Validazione specifici campi
    if (!Validator::validCF($input['cf'])) {
        Response::validationError(['cf' => 'Codice fiscale non valido']);
    }
    
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        Response::validationError(['email' => 'Email non valida']);
    }
    
   if (!empty($input['pod'])) {
    if (!Validator::validPOD($input['pod'])) {
        Response::validationError(['pod' => 'Codice POD non valido']);
    }
}
    
    $db = Database::getInstance()->getConnection();
    
    // Verifica che il dispositivo esista
    $stmt = $db->prepare("SELECT d.id FROM gefarm_devices d
                          JOIN gefarm_user_devices ud ON d.id = ud.device_id
                          WHERE d.device_id = :device_code 
                          AND ud.user_id = :user_id
                          LIMIT 1");
    $stmt->bindValue(':device_code', $input['device_id'], PDO::PARAM_STR);
    $stmt->bindValue(':user_id', $auth_data->user_id, PDO::PARAM_INT);
    $stmt->execute();
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$device) {
        Response::error('Dispositivo non trovato o non sei autorizzato', 404);
    }
    
    $device_id = $device['id'];
    
    // Disattiva eventuali configurazioni attive precedenti
    $stmt = $db->prepare("UPDATE gefarm_device_meter_data
                          SET is_active = 0, valid_to = NOW()
                          WHERE device_id = :device_id AND is_active = 1");
    $stmt->bindValue(':device_id', $device_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Cripta dati sensibili
    $encrypted_cf = EncryptionConfig::encrypt($input['cf']);
    
    // Inserisci nuovi dati contatore
    $stmt = $db->prepare("INSERT INTO gefarm_device_meter_data
                         (device_id, cf, nome, cognome, indirizzo, 
                         zip_code, citta, provincia, pod, email, 
                         telefono, is_active, valid_from)
                         VALUES
                         (:device_id, :cf, :nome, :cognome, :indirizzo, 
                         :zip_code, :citta, :provincia, :pod, :email,
                         :telefono, 1, NOW())");
                         
    $stmt->bindValue(':device_id', $device_id, PDO::PARAM_INT);
    $stmt->bindValue(':cf', $encrypted_cf, PDO::PARAM_STR);
    $stmt->bindValue(':nome', $input['nome'], PDO::PARAM_STR);
    $stmt->bindValue(':cognome', $input['cognome'], PDO::PARAM_STR);
    $stmt->bindValue(':indirizzo', $input['indirizzo'], PDO::PARAM_STR);
    $stmt->bindValue(':zip_code', $input['zip_code'], PDO::PARAM_STR);
    $stmt->bindValue(':citta', $input['citta'], PDO::PARAM_STR);
    $stmt->bindValue(':provincia', $input['provincia'], PDO::PARAM_STR);
    $stmt->bindValue(':pod', $input['pod'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':email', $input['email'], PDO::PARAM_STR);
    $stmt->bindValue(':telefono', $input['telefono'] ?? null, PDO::PARAM_STR);
    
    if ($stmt->execute()) {
        // Imposta chain2_active = true nel dispositivo
        $stmt = $db->prepare("UPDATE gefarm_devices
                             SET chain2_active = 1
                             WHERE id = :device_id");
        $stmt->bindValue(':device_id', $device_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Dati risposta (nessun dato sensibile)
        $response_data = [
            'meter_data_id' => $db->lastInsertId(),
            'device_id' => $input['device_id'],
            'chain2_activated' => true
        ];
        
        Response::success($response_data, "Dati contatore salvati con successo", 201);
    } else {
        Response::serverError("Impossibile salvare i dati del contatore");
    }
    
} catch (Exception $e) {
    error_log("Submit meter data error: " . $e->getMessage());
    Response::serverError('Errore durante il salvataggio dei dati contatore');
}
?>