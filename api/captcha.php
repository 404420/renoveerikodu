<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$captchas = $_SESSION['contact_captchas'] ?? [];
$now = time();

foreach ($captchas as $key => $captcha) {
    if (($captcha['expires'] ?? 0) < $now) {
        unset($captchas[$key]);
    }
}

$first = random_int(2, 9);
$second = random_int(1, 9);
$token = bin2hex(random_bytes(16));

$captchas[$token] = [
    'answer' => (string) ($first + $second),
    'expires' => $now + 900,
];

$_SESSION['contact_captchas'] = $captchas;

echo json_encode([
    'success' => true,
    'token' => $token,
    'question' => sprintf('Kui palju on %d + %d?', $first, $second),
], JSON_UNESCAPED_UNICODE);
