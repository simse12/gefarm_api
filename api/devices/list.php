<?php
/**
 * List Devices Endpoint - Gefarm API
 * GET /api/devices/list.php
 * Ottieni lista dispositivi dell'utente autenticato
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
require_once __DIR__ . '/../../utils/response.php';

// Solo GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Metodo non consentito', 405);
}

try {
    // Verifica autenticazione
    $auth_data = AuthMiddleware::authenticate();
    
    // Ottieni dispositivi
    $device = new Device();
    // ✅ ASSUNZIONE: Il metodo getUserDevices ora include 'is_meter_owner' nei risultati
    $devices = $device->getUserDevices($auth_data->user_id); 
    
    Response::success([
        'devices' => $devices,
        'count' => count($devices)
    ], 'Dispositivi recuperati con successo');
    
} catch (Exception $e) {
    error_log("List devices error: " . $e->getMessage());
    Response::serverError('Errore durante il recupero dei dispositivi');
}
?>