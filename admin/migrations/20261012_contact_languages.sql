-- Käivita olemasolevas andmebaasis (phpMyAdminis vali esmalt õige andmebaas).
-- Korduv käivitamine ja osaliselt lisatud väljad on lubatud: lisatakse ainult puuduvaid veerge.
-- Ühildub MySQLi ja MariaDBga; ei vaja salvestatud protseduuri loomise õigust.
-- Algne message ja source jäävad muutmata; vanade vormide vaikimisi keel on et.

SET @rk_contact_ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE contacts ADD COLUMN language VARCHAR(2) NOT NULL DEFAULT ''et''', 'SELECT 1')
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'contacts' AND COLUMN_NAME = 'language');
PREPARE rk_contact_migration FROM @rk_contact_ddl;
EXECUTE rk_contact_migration;
DEALLOCATE PREPARE rk_contact_migration;

SET @rk_contact_ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE contacts ADD COLUMN message_et TEXT DEFAULT NULL', 'SELECT 1')
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'contacts' AND COLUMN_NAME = 'message_et');
PREPARE rk_contact_migration FROM @rk_contact_ddl;
EXECUTE rk_contact_migration;
DEALLOCATE PREPARE rk_contact_migration;

SET @rk_contact_ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE contacts ADD COLUMN translation_source_hash CHAR(64) CHARACTER SET ascii DEFAULT NULL', 'SELECT 1')
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'contacts' AND COLUMN_NAME = 'translation_source_hash');
PREPARE rk_contact_migration FROM @rk_contact_ddl;
EXECUTE rk_contact_migration;
DEALLOCATE PREPARE rk_contact_migration;

SET @rk_contact_ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE contacts ADD COLUMN translated_at DATETIME DEFAULT NULL', 'SELECT 1')
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'contacts' AND COLUMN_NAME = 'translated_at');
PREPARE rk_contact_migration FROM @rk_contact_ddl;
EXECUTE rk_contact_migration;
DEALLOCATE PREPARE rk_contact_migration;

SET @rk_contact_ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE contacts ADD COLUMN translation_provider VARCHAR(30) DEFAULT NULL', 'SELECT 1')
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'contacts' AND COLUMN_NAME = 'translation_provider');
PREPARE rk_contact_migration FROM @rk_contact_ddl;
EXECUTE rk_contact_migration;
DEALLOCATE PREPARE rk_contact_migration;
