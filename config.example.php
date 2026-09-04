<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Europe/Tallinn');

define('DB_HOST', 'localhost');
define('DB_NAME', 'andmebaasi_nimi');
define('DB_USER', 'andmebaasi_kasutaja');
define('DB_PASS', 'andmebaasi_parool');

define('APP_DEBUG', false);
define('REQUIRE_RECAPTCHA', false);
// Public site key and private secret must belong to the same reCAPTCHA v2 checkbox site.
define('RECAPTCHA_SITE_KEY', '');
define('RECAPTCHA_SECRET', '');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    throw new RuntimeException('DB ühendus ebaõnnestus.');
}
$conn->set_charset('utf8mb4');
