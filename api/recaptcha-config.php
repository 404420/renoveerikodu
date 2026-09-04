<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

$paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/config.php',
];

try {
    foreach ($paths as $path) {
        if (is_file($path)) {
            require_once $path;
            break;
        }
    }
} catch (Throwable $error) {
    error_log('Contact CAPTCHA configuration could not be loaded.');
    http_response_code(503);
    echo json_encode(['success' => false]);
    exit;
}

$siteKey = defined('RECAPTCHA_SITE_KEY') ? (string) RECAPTCHA_SITE_KEY : ($GLOBALS['recaptchaSiteKey'] ?? '');

if ($siteKey === '') {
    http_response_code(500);
    echo json_encode([
        'success' => false,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'siteKey' => $siteKey,
], JSON_UNESCAPED_UNICODE);
