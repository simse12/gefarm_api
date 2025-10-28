<?php
/**
 * Endpoint: GET /api/meter/active.php
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
require_once __DIR__ . '/../../config/encryption_config.php'; // Contiene la classe EncryptionConfig
require_once __DIR__ . '/../../config/database.php';  


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
    
    // 1. Verifica che il dispositivo esista e recupera lo status 'is_meter_owner'
    $stmt = $db->prepare("SELECT 
                            d.id, d.device_id, d.chain2_active, ud.is_meter_owner
                          FROM gefarm_devices d
                          JOIN gefarm_user_devices ud ON d.id = ud.device_id
                          WHERE d.device_id = :device_code 
                          AND ud.user_id = :user_id
                          LIMIT 1");
    $stmt->bindValue(':device_code', $device_code, PDO::PARAM_STR);
    $stmt->bindValue(':user_id', $auth_data->user_id, PDO::PARAM_INT);
    $stmt->execute();
    $device_association = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$device_association) {
        Response::error('Dispositivo non trovato o non sei autorizzato', 404);
    }
    
    $device_id = $device_association['id'];
    $is_meter_owner = (bool)$device_association['is_meter_owner']; // ⭐️ Recupera lo status
    
    // Se chain2 non è attivo
    if (!$device_association['chain2_active']) {
        Response::success(['chain2_active' => false], 'Chain2 non attivo per questo dispositivo');
        exit;
    }
    
    // 2. Ottieni dati contatore attivi, includendo il campo CF criptato
    $stmt = $db->prepare("SELECT id, device_id, nome, cognome, 
                             indirizzo, zip_code, citta, provincia, 
                             pod, email, telefono, valid_from,
                             cf_owner_encrypted /* ⭐️ AGGIUNTO: Campo criptato */
                           FROM gefarm_device_meter_data
                           WHERE device_id = :device_id 
                           AND is_active = 1
                           LIMIT 1");
    $stmt->bindValue(':device_id', $device_id, PDO::PARAM_INT);
    $stmt->execute();
    $meter_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$meter_data) {
        // Se non ci sono dati meter attivi, restituisci un messaggio chiaro
        Response::success(['meter_data' => null, 'chain2_active' => true], 'Dati contatore non ancora inseriti o attivi');
        exit;
    }
    
    // 3. Gestione e decriptazione del Codice Fiscale
    $meter_data['codice_fiscale_owner'] = null; // Inizializza
    $meter_data['is_meter_owner'] = $is_meter_owner; // Aggiungi lo status alla risposta

    if ($is_meter_owner && !empty($meter_data['cf_owner_encrypted'])) {
        try {
            // Decripta il CF solo per l'intestatario
            $decrypted_cf = EncryptionConfig::decrypt($meter_data['cf_owner_encrypted']);
            $meter_data['codice_fiscale_owner'] = $decrypted_cf;
        } catch (Exception $e) {
            error_log("Decryption failed for meter data ID {$meter_data['id']}: " . $e->getMessage());
        }
    }
    
    // Rimuovi sempre la versione criptata prima di inviare la risposta
    unset($meter_data['cf_owner_encrypted']);
    
    // Aggiungi flag chain2_active
    $meter_data['chain2_active'] = true;
    
    Response::success($meter_data, 'Dati contatore recuperati con successo');
    
} catch (Exception $e) {
    error_log("Get meter data error: " . $e->getMessage());
    Response::serverError('Errore durante il recupero dei dati contatore');
}
?>