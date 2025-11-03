<?php
/**
 * Test Endpoint - Gefarm API
 * GET /api/test.php
 * Verifica che l'API sia operativa
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/response.php';

try {
    // Test connessione database
    $db = Database::getInstance()->getConnection();
    
    $test_data = [
        'api_status' => 'OK',
        'php_version' => phpversion(),
        'database_connection' => $db ? 'Connected' : 'Failed',
        'timezone' => date_default_timezone_get(),
        'timestamp' => date('Y-m-d H:i:s'),
        'extensions' => [
            'openssl' => extension_loaded('openssl'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'json' => extension_loaded('json')
        ]
    ];
    
    // Test query database
    if ($db) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM gefarm_users");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $test_data['users_count'] = $result['count'];
    }
    
    Response::success($test_data, 'Gefarm API is running');
    
} catch (Exception $e) {
    error_log("Test endpoint error: " . $e->getMessage());
    Response::serverError('Test failed: ' . $e->getMessage());
}
?>
