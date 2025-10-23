<?php
/**
 * Endpoint: GET /api/meter/active
 * Descrizione: Ottiene i dati contatore attivi per un dispositivo
 * Autenticazione: Richiesta
 */
// Configura headers
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


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Metodo non consentito. Usa GET.', 405);
    exit;
}

try {
    // Verifica autenticazione
    $auth_data = AuthMiddleware::authenticate();
    
    // Ottieni device_id dalla query string
    if (empty($_GET['device_id'])) {
        Response::error('Parametro device_id mancante', 400);
    }
    
    $device_code = $_GET['device_id'];
    
    $db = Database::getInstance()->getConnection();
    
    // Verifica che il dispositivo esista e appartenga all'utente
    $stmt = $db->prepare("SELECT d.id, d.device_id, d.chain2_active 
                          FROM gefarm_devices d
                          JOIN gefarm_user_devices ud ON d.id = ud.device_id
                          WHERE d.device_id = :device_code 
                          AND ud.user_id = :user_id
                          LIMIT 1");
    $stmt->bindValue(':device_code', $device_code, PDO::PARAM_STR);
    $stmt->bindValue(':user_id', $auth_data->user_id, PDO::PARAM_INT);
    $stmt->execute();
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$device) {
        Response::error('Dispositivo non trovato o non sei autorizzato', 404);
    }
    
    $device_id = $device['id'];
    
    // Se chain2 non è attivo
    if (!$device['chain2_active']) {
        Response::success(['chain2_active' => false], 'Chain2 non attivo per questo dispositivo');
        exit;
    }
    
    // Ottieni dati contatore attivi
    $stmt = $db->prepare("SELECT id, device_id, nome, cognome, 
                         indirizzo, zip_code, citta, provincia, 
                         pod, email, telefono, valid_from
                         FROM gefarm_device_meter_data
                         WHERE device_id = :device_id 
                         AND is_active = 1
                         LIMIT 1");
    $stmt->bindValue(':device_id', $device_id, PDO::PARAM_INT);
    $stmt->execute();
    $meter_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$meter_data) {
        Response::error('Dati contatore non trovati', 404);
    }
    
    // Aggiungi flag chain2_active
    $meter_data['chain2_active'] = true;
    
    // Non inviamo CF (dato sensibile) nella risposta
    
    Response::success($meter_data, 'Dati contatore recuperati con successo');
    
} catch (Exception $e) {
    error_log("Get meter data error: " . $e->getMessage());
    Response::serverError('Errore durante il recupero dei dati contatore');
}
?>