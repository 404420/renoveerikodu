<?php
declare(strict_types=1);

$configPath = __DIR__ . '/../config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    exit('Serveri config.php puudub. Kopeeri config.example.php failiks config.php ja täida andmebaasi andmed.');
}

require_once $configPath;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function admin_db(): PDO
{
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        return $GLOBALS['pdo'];
    }

    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
        return new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    http_response_code(500);
    exit('Andmebaasi ühendus puudub.');
}

function ensure_newsletter_subscribers_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'subscribed',
            subscribed_at DATETIME DEFAULT NULL,
            unsubscribed_at DATETIME DEFAULT NULL,
            source VARCHAR(500) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_newsletter_subscribers_email (email),
            KEY idx_newsletter_subscribers_status (status),
            KEY idx_newsletter_subscribers_subscribed_at (subscribed_at),
            KEY idx_newsletter_subscribers_unsubscribed_at (unsubscribed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM newsletter_subscribers') as $column) {
        $columns[$column['Field']] = true;
    }

    $missingColumns = [
        'status' => "ALTER TABLE newsletter_subscribers ADD COLUMN status VARCHAR(40) NOT NULL DEFAULT 'subscribed' AFTER email",
        'subscribed_at' => 'ALTER TABLE newsletter_subscribers ADD COLUMN subscribed_at DATETIME DEFAULT NULL AFTER status',
        'unsubscribed_at' => 'ALTER TABLE newsletter_subscribers ADD COLUMN unsubscribed_at DATETIME DEFAULT NULL AFTER subscribed_at',
        'source' => 'ALTER TABLE newsletter_subscribers ADD COLUMN source VARCHAR(500) DEFAULT NULL AFTER unsubscribed_at',
        'created_at' => 'ALTER TABLE newsletter_subscribers ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER source',
        'updated_at' => 'ALTER TABLE newsletter_subscribers ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    ];

    foreach ($missingColumns as $column => $sql) {
        if (empty($columns[$column])) {
            $pdo->exec($sql);
        }
    }

    try {
        $pdo->exec('ALTER TABLE newsletter_subscribers CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    } catch (Throwable $error) {
        // Existing production tables may contain legacy data; do not block the admin panel.
    }

    try {
        $pdo->exec('ALTER TABLE newsletter_subscribers ADD UNIQUE KEY uniq_newsletter_subscribers_email (email)');
    } catch (Throwable $error) {
        // The key may already exist or duplicate legacy rows may need manual cleanup.
    }
}

function require_admin(): void
{
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: login.php');
        exit;
    }
}

function h($value): string
{
    return htmlspecialchars(repair_text((string) $value), ENT_QUOTES, 'UTF-8');
}

function repair_text(string $value): string
{
    if ($value === '' || !preg_match('/[ÃÂ�]/u', $value)) {
        return $value;
    }

    $bytes = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $value);
    $isUtf8 = is_string($bytes)
        && $bytes !== ''
        && (function_exists('mb_check_encoding') ? mb_check_encoding($bytes, 'UTF-8') : preg_match('//u', $bytes));

    if ($isUtf8) {
        return $bytes;
    }

    return $value;
}

function file_path_from_entry($file): string
{
    if (is_array($file)) {
        return (string) ($file['path'] ?? $file['url'] ?? '');
    }

    return (string) $file;
}

function file_label_from_entry($file): string
{
    if (is_array($file)) {
        $path = file_path_from_entry($file);
        return (string) ($file['original_name'] ?? $file['name'] ?? basename($path));
    }

    return basename((string) $file);
}

function is_preview_image(string $path): bool
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
}

function upload_url(string $path): string
{
    if ($path === '') {
        return '#';
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    if (str_starts_with($path, 'api/')) {
        return '../' . $path;
    }

    if (str_starts_with($path, 'uploads/')) {
        return '../' . $path;
    }

    return '../' . ltrim($path, '/');
}

function decode_files(?string $value): array
{
    if (!$value) {
        return [];
    }

    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    return [$value];
}
