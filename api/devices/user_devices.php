<?php
/**
 * Debug User Devices Endpoint - GeFarm API
 * GET /api/debug/user_devices.php
 * Mostra tutte le associazioni utente-dispositivi (SOLO DEBUG!)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';

// ⚠️ RIMUOVI QUESTO ENDPOINT IN PRODUZIONE!

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Metodo non consentito', 405);
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Conta dispositivi
    $stmt = $db->query("SELECT COUNT(*) as count FROM gefarm_devices");
    $devices_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Conta associazioni
    $stmt = $db->query("SELECT COUNT(*) as count FROM gefarm_user_devices");
    $associations_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Lista tutte le associazioni con dettagli
    $query = "SELECT 
                ud.id as association_id,
                ud.user_id,
                u.email as user_email,
                ud.device_id as internal_device_id,
                d.device_id as device_code,
                d.device_type,
                d.nome_dispositivo,
                ud.role,
                ud.nickname,
                ud.is_favorite,
                ud.added_at,
                ud.is_meter_owner  /* ✅ AGGIUNTO: Nuovo campo per debug */
              FROM gefarm_user_devices ud
              LEFT JOIN gefarm_users u ON ud.user_id = u.id
              LEFT JOIN gefarm_devices d ON ud.device_id = d.id
              ORDER BY ud.added_at DESC";
    
    $stmt = $db->query($query);
    $associations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Lista dispositivi senza associazioni
    $query_orphans = "SELECT d.* FROM gefarm_devices d
                      LEFT JOIN gefarm_user_devices ud ON d.id = ud.device_id
                      WHERE ud.device_id IS NULL";
    
    $stmt = $db->query($query_orphans);
    $orphan_devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    Response::success([
        'summary' => [
            'total_devices' => $devices_count,
            'total_associations' => $associations_count,
            'orphan_devices' => count($orphan_devices)
        ],
        'associations' => $associations,
        'orphan_devices' => $orphan_devices
    ], 'Dati associazioni recuperati');
    
} catch (PDOException $e) {
    error_log("Debug user_devices error: " . $e->getMessage());
    Response::serverError('Errore del server: ' . $e->getMessage());
}
?>