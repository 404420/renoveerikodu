<?php
declare(strict_types=1);

/** Older installations can still have latin1 columns despite a UTF-8 DSN. */
function rk_ensure_contact_utf8mb4(PDO $db): void
{
    $table = $db->query("SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contacts'");
    $columns = $db->query('SHOW FULL COLUMNS FROM contacts');
    if ($table === false || $columns === false) {
        throw new RuntimeException('Contact character set could not be inspected.');
    }
    $tableCollation = $table->fetchColumn();
    if (!is_string($tableCollation)) {
        throw new RuntimeException('Contact table could not be inspected.');
    }
    $convert = !str_starts_with($tableCollation, 'utf8mb4_');
    foreach ($columns->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $collation = $column['Collation'] ?? null;
        // Hashes deliberately use ASCII; numeric/date/binary columns have no collation.
        if ($column['Field'] !== 'translation_source_hash' && is_string($collation)
            && !str_starts_with($collation, 'utf8mb4_')) {
            $convert = true;
        }
    }
    if ($convert && $db->exec('ALTER TABLE contacts CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci') === false) {
        // Do not accept a message if storage would silently replace Cyrillic with ?.
        throw new RuntimeException('Contact character set could not be updated.');
    }
}

// Lisanduvad väljad ei muuda olemasolevaid päringuid ega nende allikaid.
function rk_ensure_contact_columns(PDO $db, array $definitions): void
{
    $query = $db->query('SHOW COLUMNS FROM contacts');
    if ($query === false) {
        throw new RuntimeException('Contact schema could not be inspected.');
    }
    $columns = array_column($query->fetchAll(PDO::FETCH_ASSOC), 'Field');
    foreach ($definitions as $column => $definition) {
        if (in_array($column, $columns, true)) {
            continue;
        }
        try {
            if ($db->exec("ALTER TABLE contacts ADD COLUMN `$column` $definition") === false) {
                throw new RuntimeException('Contact schema could not be updated.');
            }
        } catch (Throwable $error) {
            // Kaks samaaegset päringut võivad sama puuduva veeru avastada.
            $recheck = $db->query('SHOW COLUMNS FROM contacts');
            if ($recheck === false || !in_array($column, array_column($recheck->fetchAll(PDO::FETCH_ASSOC), 'Field'), true)) {
                throw $error;
            }
        }
    }
}

function rk_ensure_contact_language_schema(PDO $db): void
{
    rk_ensure_contact_utf8mb4($db);
    rk_ensure_contact_columns($db, ['language' => "VARCHAR(2) NOT NULL DEFAULT 'et'"]);
}

function rk_ensure_contact_translation_schema(PDO $db): void
{
    rk_ensure_contact_utf8mb4($db);
    rk_ensure_contact_columns($db, [
        'language' => "VARCHAR(2) NOT NULL DEFAULT 'et'",
        'message_et' => 'TEXT DEFAULT NULL',
        'translation_source_hash' => 'CHAR(64) CHARACTER SET ascii DEFAULT NULL',
        'translated_at' => 'DATETIME DEFAULT NULL',
        'translation_provider' => 'VARCHAR(30) DEFAULT NULL',
    ]);
}
