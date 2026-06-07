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
