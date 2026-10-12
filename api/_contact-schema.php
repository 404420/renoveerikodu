<?php
declare(strict_types=1);

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
    rk_ensure_contact_columns($db, ['language' => "VARCHAR(2) NOT NULL DEFAULT 'et'"]);
}

function rk_ensure_contact_translation_schema(PDO $db): void
{
    rk_ensure_contact_columns($db, [
        'language' => "VARCHAR(2) NOT NULL DEFAULT 'et'",
        'message_et' => 'TEXT DEFAULT NULL',
        'translation_source_hash' => 'CHAR(64) CHARACTER SET ascii DEFAULT NULL',
        'translated_at' => 'DATETIME DEFAULT NULL',
        'translation_provider' => 'VARCHAR(30) DEFAULT NULL',
    ]);
}
