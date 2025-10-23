<?php
/**
 * Register Device Endpoint - GeFarm API
 * POST /api/devices/register.php
 * Registra un nuovo dispositivo nel sistema e lo associa all'utente
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gestisci preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../models/Device.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validator.php';

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Metodo non consentito', 405);
}

try {
    // Verifica autenticazione
    $auth_data = AuthMiddleware::authenticate();
    
    // Leggi input JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        Response::error('Dati JSON non validi', 400);
    }
    
    // Validazione campi richiesti
    $required_fields = ['device_id', 'device_type', 'nome_dispositivo'];
    $missing = [];
    
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        Response::validationError($missing, 'Campi obbligatori mancanti: ' . implode(', ', $missing));
    }
    
    // Validazione device_type (ENUM)
    $valid_types = ['emcengine', 'emcinverter', 'emcbox'];
    if (!in_array($input['device_type'], $valid_types)) {
        Response::validationError(
            ['device_type' => 'Tipo dispositivo non valido. Valori ammessi: ' . implode(', ', $valid_types)]
        );
    }
    
    // Validazione device_id (formato EMC-XXX-NNNNNN)
    if (!preg_match('/^EMC-[A-Z0-9]{3}-[A-Z0-9]+$/i', $input['device_id'])) {
        Response::validationError(
            ['device_id' => 'Formato device_id non valido. Esempio: EMC-001-123456']
        );
    }
    
    // Verifica se dispositivo già esiste
    $device = new Device();
    $existing = $device->getByDeviceId($input['device_id']);
    
    if ($existing) {
        Response::error('Dispositivo già registrato nel sistema', 409);
    }
    
    // Crea dispositivo
    $device->device_id = $input['device_id'];
    $device->device_type = $input['device_type'];
    $device->nome_dispositivo = $input['nome_dispositivo'];
    $device->ssid_ap = $input['ssid_ap'] ?? null;
    $device->chain2_active = isset($input['chain2_active']) ? (int)$input['chain2_active'] : 0;
    $device->firmware_version = $input['firmware_version'] ?? null;
    
    // Log per debug
    error_log("Creating device: " . json_encode([
        'device_id' => $device->device_id,
        'device_type' => $device->device_type,
        'nome_dispositivo' => $device->nome_dispositivo,
        'ssid_ap' => $device->ssid_ap,
        'chain2_active' => $device->chain2_active,
        'firmware_version' => $device->firmware_version
    ]));
    
    if (!$device->create()) {
        error_log("Device creation failed for: " . $input['device_id']);
        Response::serverError('Errore durante la creazione del dispositivo nel database');
    }
    
    error_log("Device created with ID: {$device->id}");
    
    // Associa automaticamente il dispositivo all'utente come "owner"
    $nickname = $input['nickname'] ?? null;
    
    if (!$device->addToUser($auth_data->user_id, $device->id, 'owner', $nickname)) {
        error_log("Failed to associate device {$device->id} to user {$auth_data->user_id}");
        Response::serverError('Dispositivo creato ma errore nell\'associazione all\'utente. Contatta il supporto.');
    }
    
    error_log("Device {$device->id} associated to user {$auth_data->user_id} as owner");
    
    // Ottieni dispositivo completo
    $device_data = $device->getById($device->id);
    
    Response::success([
        'device' => $device_data,
        'association' => [
            'role' => 'owner',
            'nickname' => $nickname
        ]
    ], 'Dispositivo registrato e associato con successo', 201);
    
} catch (Exception $e) {
    error_log("Register device error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    Response::serverError('Errore durante la registrazione: ' . $e->getMessage());
}
?>
