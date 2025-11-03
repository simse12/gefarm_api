-- =========================================
-- GEFARM API - MIGRATION v2.1 → v2.2
-- Data: 2025-10-28
-- Descrizione: Performance indexes, cleanup utilities
-- =========================================

-- Nota: Eseguire questi comandi uno alla volta e verificare il risultato
-- Tempo stimato: 1-2 minuti su DB piccolo, più lungo su DB con molti dati

-- =========================================
-- 1. BACKUP REMINDER
-- =========================================
-- ⚠️ IMPORTANTE: Esegui backup PRIMA di procedere!
-- mysqldump -u gefarmdb -p my_gefarmdb > backup_before_migration_v2.2_$(date +%Y%m%d).sql

-- =========================================
-- 2. PERFORMANCE INDEXES
-- =========================================

-- Indice per ricerche veloci su is_meter_owner
CREATE INDEX IF NOT EXISTS idx_user_devices_meter_owner 
ON gefarm_user_devices(is_meter_owner, user_id);

-- Indice per dati contatore attivi
CREATE INDEX IF NOT EXISTS idx_meter_data_active 
ON gefarm_device_meter_data(is_active, device_id);

-- Indice per sessioni scadute (cleanup)
CREATE INDEX IF NOT EXISTS idx_sessions_expiry 
ON gefarm_user_sessions(expires_at, user_id);

-- Indice per token verifica/reset
CREATE INDEX IF NOT EXISTS idx_tokens_type_used 
ON gefarm_password_reset_tokens(type, used, expires_at);

-- Indice per device_id lookup (se non esiste già)
CREATE INDEX IF NOT EXISTS idx_devices_device_id 
ON gefarm_devices(device_id);

-- Indice composito per query frequenti su user_devices
CREATE INDEX IF NOT EXISTS idx_user_devices_lookup 
ON gefarm_user_devices(user_id, device_id, is_favorite);

-- =========================================
-- 3. TIMEZONE CONFIGURATION
-- =========================================

-- Imposta timezone server a UTC (raccomandato per API)
SET GLOBAL time_zone = '+00:00';
SET time_zone = '+00:00';

-- Verifica timezone corrente
SELECT @@global.time_zone, @@session.time_zone;

-- =========================================
-- 4. CLEANUP STORED PROCEDURES
-- =========================================

-- Procedura per pulire token scaduti
DELIMITER $$

DROP PROCEDURE IF EXISTS cleanup_expired_tokens$$

CREATE PROCEDURE cleanup_expired_tokens()
BEGIN
    DECLARE deleted_count INT;
    
    -- Elimina token scaduti
    DELETE FROM gefarm_password_reset_tokens 
    WHERE expires_at < NOW();
    
    SET deleted_count = ROW_COUNT();
    
    SELECT CONCAT('Cleaned up ', deleted_count, ' expired tokens') AS result;
END$$

DELIMITER ;

-- Procedura per pulire sessioni scadute
DELIMITER $$

DROP PROCEDURE IF EXISTS cleanup_expired_sessions$$

CREATE PROCEDURE cleanup_expired_sessions()
BEGIN
    DECLARE deleted_count INT;
    
    -- Elimina sessioni scadute
    DELETE FROM gefarm_user_sessions 
    WHERE expires_at < NOW();
    
    SET deleted_count = ROW_COUNT();
    
    SELECT CONCAT('Cleaned up ', deleted_count, ' expired sessions') AS result;
END$$

DELIMITER ;

-- Procedura per statistiche database
DELIMITER $$

DROP PROCEDURE IF EXISTS get_database_stats$$

CREATE PROCEDURE get_database_stats()
BEGIN
    SELECT 
        (SELECT COUNT(*) FROM gefarm_users) AS total_users,
        (SELECT COUNT(*) FROM gefarm_users WHERE email_verified = 1) AS verified_users,
        (SELECT COUNT(*) FROM gefarm_devices) AS total_devices,
        (SELECT COUNT(*) FROM gefarm_user_devices) AS device_associations,
        (SELECT COUNT(*) FROM gefarm_device_meter_data WHERE is_active = 1) AS active_meter_configs,
        (SELECT COUNT(*) FROM gefarm_user_sessions WHERE expires_at > NOW()) AS active_sessions,
        (SELECT COUNT(*) FROM gefarm_password_reset_tokens WHERE used = 0 AND expires_at > NOW()) AS pending_tokens;
END$$

DELIMITER ;

-- =========================================
-- 5. MAINTENANCE EVENTS (Optional - MySQL 5.7+)
-- =========================================

-- Abilita event scheduler (se supportato)
-- SET GLOBAL event_scheduler = ON;

-- Event per cleanup automatico notturno (alle 3:00 AM)
-- DELIMITER $$
-- 
-- DROP EVENT IF EXISTS daily_cleanup$$
-- 
-- CREATE EVENT daily_cleanup
-- ON SCHEDULE EVERY 1 DAY
-- STARTS (TIMESTAMP(CURRENT_DATE) + INTERVAL 1 DAY + INTERVAL 3 HOUR)
-- DO
-- BEGIN
--     CALL cleanup_expired_tokens();
--     CALL cleanup_expired_sessions();
-- END$$
-- 
-- DELIMITER ;

-- =========================================
-- 6. DATA INTEGRITY CHECKS
-- =========================================

-- Verifica foreign keys
SELECT 
    TABLE_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'my_gefarmdb'
AND REFERENCED_TABLE_NAME IS NOT NULL;

-- Verifica orphan records in user_devices
SELECT COUNT(*) as orphan_user_devices
FROM gefarm_user_devices ud
LEFT JOIN gefarm_users u ON ud.user_id = u.id
LEFT JOIN gefarm_devices d ON ud.device_id = d.id
WHERE u.id IS NULL OR d.id IS NULL;

-- Verifica duplicati email (non dovrebbero esistere con UNIQUE)
SELECT email, COUNT(*) as count
FROM gefarm_users
GROUP BY email
HAVING count > 1;

-- =========================================
-- 7. QUERY PERFORMANCE TESTING
-- =========================================

-- Test performance query comuni
EXPLAIN SELECT d.*, ud.role, ud.nickname, ud.is_favorite, ud.is_meter_owner
FROM gefarm_devices d
INNER JOIN gefarm_user_devices ud ON d.id = ud.device_id
WHERE ud.user_id = 1;

EXPLAIN SELECT * 
FROM gefarm_device_meter_data 
WHERE device_id = 1 AND is_active = 1;

EXPLAIN SELECT *
FROM gefarm_password_reset_tokens
WHERE token = 'test' AND type = 'verify' AND used = 0 AND expires_at > NOW();

-- =========================================
-- 8. STATISTICS & VERIFICATION
-- =========================================

-- Esegui stored procedure stats
CALL get_database_stats();

-- Verifica dimensione tabelle
SELECT 
    TABLE_NAME,
    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS size_mb,
    TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'my_gefarmdb'
AND TABLE_NAME LIKE 'gefarm_%'
ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC;

-- Verifica indici creati
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'my_gefarmdb'
AND TABLE_NAME LIKE 'gefarm_%'
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- =========================================
-- 9. MANUAL CLEANUP (Esegui se necessario)
-- =========================================

-- Cleanup token scaduti (manuale)
-- DELETE FROM gefarm_password_reset_tokens WHERE expires_at < NOW();

-- Cleanup sessioni scadute (manuale)
-- DELETE FROM gefarm_user_sessions WHERE expires_at < NOW();

-- Cleanup vecchi dati meter inattivi (più di 1 anno)
-- DELETE FROM gefarm_device_meter_data 
-- WHERE is_active = 0 AND valid_to < DATE_SUB(NOW(), INTERVAL 1 YEAR);

-- =========================================
-- 10. POST-MIGRATION VERIFICATION
-- =========================================

-- Verifica che tutti gli indici siano stati creati
SELECT COUNT(*) AS total_indexes
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'my_gefarmdb'
AND TABLE_NAME LIKE 'gefarm_%'
AND INDEX_NAME != 'PRIMARY';

-- Dovrebbe restituire almeno 10+ indici (inclusi i nuovi)

-- Verifica integrità referenziale
SELECT 
    COUNT(*) AS total_foreign_keys
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = 'my_gefarmdb'
AND CONSTRAINT_TYPE = 'FOREIGN KEY';

-- Dovrebbe restituire 7-8 foreign keys

-- =========================================
-- MIGRATION COMPLETED
-- =========================================

-- Se tutti i test sono passati, la migration è completata con successo!
-- Annotare:
-- - Data migration: _________________
-- - Eseguita da: ____________________
-- - Risultato: ⬜ Successo  ⬜ Errori (specificare nei log)

SELECT 'Migration v2.1 → v2.2 completed successfully!' AS status,
       NOW() AS completed_at,
       API_VERSION AS version;
