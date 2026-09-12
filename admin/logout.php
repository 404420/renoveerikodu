<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
header("Content-Security-Policy: default-src 'none'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST') {
    rk_require_csrf();
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600, 'path' => $params['path'], 'domain' => $params['domain'],
        'secure' => true, 'httponly' => true, 'samesite' => 'Strict',
    ]);
    if (!session_destroy()) { rk_error(); }
    header('Location: login.php', true, 303);
    exit;
}
if ($method !== 'GET') {
    header('Allow: GET, POST'); http_response_code(405); exit;
}
// Olemasolev menüülink töötab: GET näitab kinnitust, väljalogimine on POST.
?>
<!doctype html><html lang="et"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>Logi välja</title></head>
<body><h1>Kas soovid välja logida?</h1>
<form method="post" action="logout.php"><?= rk_csrf_field() ?><button>Logi välja</button></form>
<a href="admin.php">Tagasi</a></body></html>
