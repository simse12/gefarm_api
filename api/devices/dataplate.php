<?php
/**
 * Endpoint: POST /api/devices/dataplate.php
 * Descrizione: Sincronizza dati dataplate (du, k1, k2, fiv) dopo connessione AP
 * Autenticazione: Basic Auth (firmware_username:firmware_password)
 * Chiamato dall'app dopo che l'utente si è connesso al WiFi del dispositivo
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gestisci preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito. Usa POST.']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Device.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../config/encryption_config.php';

try {
    // 1. Estrai credenziali Basic Auth
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Basic\s+(.*)$/i', $auth_header, $matches)) {
        Response::unauthorized('Richiesta Basic Auth mancante');
    }

    $credentials = base64_decode($matches[1]);
    if (!$credentials || !strpos($credentials, ':')) {
        Response::unauthorized('Credenziali Basic Auth non valide');
    }

    list($username, $password) = explode(':', $credentials, 2);
    if (!$username || !$password) {
        Response::unauthorized('Username o password mancanti');
    }

    // 2. Recupera input JSON
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['device_id'])) {
        Response::error('Dati JSON non validi o device_id mancante', 400);
    }

    $device_id = $input['device_id'];

    // 3. Trova dispositivo per device_id
    $device_model = new Device();
    $device_record = $device_model->getByDeviceId($device_id);
    if (!$device_record) {
        Response::error('Dispositivo non trovato', 404);
    }

    // 4. Verifica credenziali firmware
    if (
        empty($device_record['firmware_username']) ||
        empty($device_record['firmware_password_hash']) ||
        $device_record['firmware_username'] !== $username ||
        !EncryptionConfig::verifyPassword($password, $device_record['firmware_password_hash'])
    ) {
        Response::unauthorized('Credenziali firmware non valide');
    }

    // 5. Prepara dati da aggiornare
    $update_data = [
        'du' => $input['du'] ?? null,
        'k1' => $input['k1'] ?? null,
        'k2' => $input['k2'] ?? null,
        'fiv' => $input['fiv'] ?? null,
        'dataplate_synced_at' => date('Y-m-d H:i:s'),
        'first_setup_completed' => 1
    ];

    // 6. Aggiorna dispositivo
    $updated = $device_model->update($device_record['id'], $update_data);
    if (!$updated) {
        Response::serverError('Impossibile aggiornare i dati del dispositivo');
    }

    // 7. Risposta di successo
    Response::success([
        'device_id' => $device_id,
        'dataplate_synced_at' => $update_data['dataplate_synced_at'],
        'first_setup_completed' => true
    ], 'Dati dataplate sincronizzati con successo', 200);

} catch (Exception $e) {
    error_log("Dataplate sync error: " . $e->getMessage());
    Response::serverError('Errore durante la sincronizzazione dei dati');
}
?>