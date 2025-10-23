<?php
/**
 * Add Device Endpoint - GeFarm API
 * POST /api/devices/add.php
 * Aggiungi/associa dispositivo all'utente autenticato
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
    
    // Campi richiesti
    if (empty($input['device_id'])) {
        Response::error('device_id è obbligatorio', 400);
    }
    
    // Verifica se dispositivo esiste
    $device = new Device();
    $existing_device = $device->getByDeviceId($input['device_id']);
    
    if (!$existing_device) {
        Response::notFound('Dispositivo non trovato. Verificare il codice dispositivo.');
    }
    
    // Associa dispositivo a utente
    $role = $input['role'] ?? 'user'; // owner, user, technician
    $nickname = $input['nickname'] ?? null;
    
    if ($device->addToUser($auth_data->user_id, $existing_device['id'], $role, $nickname)) {
        
        // Ottieni dispositivo completo
        $device_data = $device->getById($existing_device['id']);
        
        Response::success([
            'device' => $device_data,
            'role' => $role,
            'nickname' => $nickname
        ], 'Dispositivo aggiunto con successo', 201);
        
    } else {
        Response::serverError('Errore durante l\'aggiunta del dispositivo');
    }
    
} catch (Exception $e) {
    error_log("Add device error: " . $e->getMessage());
    Response::serverError('Errore durante l\'aggiunta del dispositivo');
}
?>
