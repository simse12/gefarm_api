<?php
/**
 * Database Structure Endpoint - GeFarm API
 * GET /api/debug/database_structure.php
 * Mostra struttura tabelle gefarm_* (SOLO DEBUG - RIMUOVERE IN PRODUZIONE!)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';

// ⚠️ RIMUOVI QUESTO ENDPOINT IN PRODUZIONE!
// Decommentare per disabilitare in produzione:
// Response::error('Endpoint non disponibile', 404);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Metodo non consentito', 405);
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Lista tabelle gefarm_*
    $stmt = $db->query("SHOW TABLES LIKE 'gefarm_%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $structure = [];
    
    foreach ($tables as $table) {
        // Struttura tabella
        $stmt = $db->query("DESCRIBE `$table`");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Conta record
        $stmt = $db->query("SELECT COUNT(*) as count FROM `$table`");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Esempio record (senza dati sensibili)
        $stmt = $db->query("SELECT * FROM `$table` LIMIT 1");
        $sample = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Rimuovi dati sensibili dall'esempio
        if ($sample) {
            unset($sample['password_hash']);
            unset($sample['cf']);
            unset($sample['token']);
            unset($sample['token_hash']);
        }
        
        $structure[$table] = [
            'columns' => $columns,
            'record_count' => $count,
            'sample_record' => $sample
        ];
    }
    
    Response::success([
        'database' => 'my_simonaserra',
        'tables_count' => count($tables),
        'tables' => $structure
    ], 'Struttura database recuperata');
    
} catch (PDOException $e) {
    error_log("Database structure error: " . $e->getMessage());
    Response::serverError('Errore del server: ' . $e->getMessage());
}
?>
