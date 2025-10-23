<?php
/**
 * Device Details Endpoint - GeFarm API
 * GET /api/devices/details.php?device_id=XXX
 * Ottieni dettagli dispositivo e dati contatore
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

// Solo GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Metodo non consentito', 405);
}

try {
    // Verifica autenticazione
    $auth_data = AuthMiddleware::authenticate();
    
    // Ottieni device_id
    if (empty($_GET['device_id'])) {
        Response::error('device_id è obbligatorio', 400);
    }
    
    $device_id = $_GET['device_id'];
    
    // Ottieni dispositivo
    $device = new Device();
    $device_data = $device->getByDeviceId($device_id);
    
    if (!$device_data) {
        Response::notFound('Dispositivo non trovato');
    }
    
    // Verifica che l'utente abbia accesso a questo dispositivo
    $user_devices = $device->getUserDevices($auth_data->user_id);
    $has_access = false;
    
    foreach ($user_devices as $ud) {
        if ($ud['id'] == $device_data['id']) {
            $has_access = true;
            $device_data['user_role'] = $ud['role'];
            $device_data['nickname'] = $ud['nickname'];
            $device_data['is_favorite'] = $ud['is_favorite'];
            break;
        }
    }
    
    if (!$has_access) {
        Response::unauthorized('Non hai accesso a questo dispositivo');
    }
    
    // Ottieni dati contatore attivo
    $meter = new DeviceMeterData();
    $meter_data = $meter->getActiveByDeviceId($device_data['id']);
    
    Response::success([
        'device' => $device_data,
        'meter_data' => $meter_data
    ], 'Dettagli dispositivo recuperati con successo');
    
} catch (Exception $e) {
    error_log("Device details error: " . $e->getMessage());
    Response::serverError('Errore durante il recupero dei dettagli');
}
?>
