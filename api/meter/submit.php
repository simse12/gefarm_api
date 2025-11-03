<?php
/**
 * Endpoint: POST /api/meter/submit.php
 * Descrizione: Invia dati contatore (Chain2) per un dispositivo
 * Autenticazione: Richiesta (JWT)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gestisci preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include necessari
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../models/Device.php';
require_once __DIR__ . '/../../models/DeviceMeterData.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/mailer.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validator.php';
require_once __DIR__ . '/../../config/encryption_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Metodo non consentito. Usa POST.', 405);
    exit;
}

try {
    // 1. Autenticazione
    $auth_data = AuthMiddleware::authenticate();
    $user_id = $auth_data->user_id;

    // 2. Recupera dati utente (per email/telefono)
    $user_model = new User();
    $user_data = $user_model->getById($user_id);
    if (!$user_data) {
        Response::unauthorized('Utente non trovato');
    }

    // 3. Leggi input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        Response::error('Dati JSON non validi', 400);
    }

    // 4. Campi obbligatori
    $required_fields = ['device_id', 'cf', 'nome', 'cognome', 'indirizzo', 'zip_code', 'citta', 'provincia'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            Response::error("Il campo '$field' è obbligatorio", 400);
        }
    }

    // 5. Validazioni
    $cf_validation = Validator::validCF($input['cf']);
    if (!$cf_validation['valid']) {
        Response::validationError(['cf' => $cf_validation['error']]);
    }

    if (!empty($input['pod'])) {
        if (!Validator::validPOD($input['pod'])) {
            Response::validationError(['pod' => 'Codice POD non valido']);
        }
    }

    // 6. Verifica associazione device
    $device_model = new Device();
    $stmt = $device_model->getConnection()->prepare("
        SELECT d.id, ud.is_meter_owner 
        FROM gefarm_devices d
        INNER JOIN gefarm_user_devices ud ON d.id = ud.device_id
        WHERE d.device_id = :device_id AND ud.user_id = :user_id
        LIMIT 1
    ");
    $stmt->bindValue(':device_id', $input['device_id'], PDO::PARAM_STR);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $device_assoc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device_assoc) {
        Response::error('Dispositivo non trovato o non associato al tuo account', 404);
    }
    
    $device_id = $device_assoc['id'];
    
    // --- INIZIO LOGICA DI CONTROLLO CF/POD CORRETTA ---
    
    // Criptiamo il CF in arrivo *prima* di cercarlo
    $cf_encrypted_check = EncryptionConfig::encrypt(strtoupper($input['cf'])); // Aggiunto strtoupper per sicurezza
    
    // 7. Controlla se questa combinazione POD + CF esiste già
    if (!empty($input['pod'])) {
        $check_pod = strtoupper($input['pod']);

        $stmt_check = $device_model->getConnection()->prepare("
            SELECT d.chain2_active
            FROM gefarm_device_meter_data md
            INNER JOIN gefarm_devices d ON md.device_id = d.id
            WHERE md.pod = :pod AND md.cf_owner_encrypted = :cf
            LIMIT 1
        ");
        $stmt_check->bindValue(':pod', $check_pod, PDO::PARAM_STR);
        $stmt_check->bindValue(':cf', $cf_encrypted_check, PDO::PARAM_STR); // Usa il CF criptato
        $stmt_check->execute();
        $existing_meter = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($existing_meter) {
            if ($existing_meter['chain2_active'] == 1) {
                Response::error('La lettura per questo POD e Codice Fiscale è già attiva.', 409);
            } else {
                Response::error('La richiesta per questo POD e Codice Fiscale è già stata inviata ed è in attesa di attivazione.', 409);
            }
        }
    }

    // 8. Controlla se questo CF è già su *questo* device
    $meter_model = new DeviceMeterData();
    // (Questa funzione usa già la crittografia interna)
    if ($meter_model->cfAlreadyExistsForDevice($device_id, $input['cf'])) {
        Response::error('Questo dispositivo ha già un intestatario registrato con questo Codice Fiscale', 409);
    }
    
    // --- FINE LOGICA DI CONTROLLO ---

    // 9. Prepara dati meter (usando la proprietà 'cf' in chiaro, come il modello si aspetta)
    $meter_model->device_id = $device_id;
    $meter_model->cf = $input['cf']; // Il modello gestirà strtoupper e crittografia
    $meter_model->nome = $input['nome'];
    $meter_model->cognome = $input['cognome'];
    $meter_model->indirizzo = $input['indirizzo'];
    $meter_model->zip_code = $input['zip_code'];
    $meter_model->citta = $input['citta'];
    $meter_model->provincia = $input['provincia'];
    $meter_model->pod = $input['pod'] ?? null; // Il modello gestirà strtoupper
    $meter_model->inserted_by_user_id = $user_id;

    // Email e telefono FORZATI dall'utente loggato
    $meter_model->setUserData($user_data['email'], $user_data['telefono'] ?? null);

    // 10. Salva
    if (!$meter_model->create()) {
        Response::serverError('Impossibile salvare i dati del contatore');
    }

    // 11. Attiva Chain2 sul dispositivo
    $stmt = $device_model->getConnection()->prepare("
        UPDATE gefarm_devices SET chain2_active = 1 WHERE id = :device_id
    ");
    $stmt->bindValue(':device_id', $device_id, PDO::PARAM_INT);
    $stmt->execute();


   $templateData = [
    '{{DATA_RICHIESTA}}'    => date('d/m/Y H:i:s'),
    '{{NOME_COGNOME}}'      => strtoupper($input['nome'] . ' ' . $input['cognome']),
    '{{CODICE_FISCALE}}'    => strtoupper($input['cf']),
    '{{EMAIL}}'             => $user_data['email'],
    '{{TELEFONO}}'          => $user_data['telefono'] ?? 'N/D',
    '{{POD_SCAMBIO}}'       => strtoupper($input['pod'] ?? 'N/D'),
    '{{INDIRIZZO_FORNITURA}}' => $input['indirizzo'] . ', ' . $input['zip_code'] . ', ' . strtoupper($input['citta']) . ' (' . strtoupper($input['provincia']) . ')'
];


try {
        Mailer::sendChain2Request($user_data['email'], $templateData); 
    } catch (Exception $e) {
        // --- MODIFICA PER DEBUG ---
        // Ora l'errore dell'email bloccherà lo script e lo mostrerà in Postman
        error_log('ERRORE INVIO EMAIL CHAIN2: ' . $e->getMessage());
        Response::serverError('DEBUG EMAIL FALLITA: ' . $e->getMessage());
        exit; // Stoppiamo
    }
    // --- FINE BLOCCO EMAIL ---

    // Invia la risposta di successo all'app
    Response::success([
        'message' => 'Dati contatore inviati con successo. Riceverai una copia della richiesta via email.',
        'meter_data_id' => $meter_model->id
    ], 201);

} catch (Exception $e) {
    error_log("Meter submit error: " . $e->getMessage());
    // Mostra il vero errore per il debug
    Response::serverError('Errore durante il salvataggio: ' . $e->getMessage());
}
?>

