<?php
declare(strict_types=1);

/** Normalize connections supplied by older private config.php files as well. */
function rk_database_utf8mb4(PDO $db): PDO
{
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Native parameters do not depend on the client's original escaping charset.
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    // Set client, connection AND result encoding before any contact reads/writes.
    if ($db->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci') === false) {
        throw new RuntimeException('Database UTF-8 connection could not be configured.');
    }
    return $db;
}
