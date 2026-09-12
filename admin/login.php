<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

// Rangem CSP ainult autentimisvaadetes: vana paneel kasutab inline JavaScripti.
$nonce = base64_encode(random_bytes(24));
header("Content-Security-Policy: default-src 'none'; style-src 'nonce-{$nonce}'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$user = rk_current_user();
if ($user !== null) {
    header('Location: admin.php', true, 303);
    exit;
}
$error = '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}
if ($method === 'POST') {
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $valid = is_string($_POST['username'] ?? null) && is_string($_POST['password'] ?? null)
        && $username !== '' && strlen($username) <= 255 && preg_match('//u', $username) === 1
        && !preg_match('/[\x00-\x1F\x7F]/', $username)
        && $password !== '' && strlen($password) <= 4096 && !str_contains($password, "\0");
    try {
        if (!rk_csrf_valid()) {
            rk_audit(rk_db(), $username, 'csrf_rejected');
        } elseif (rk_login(substr($username, 0, 255), $password, (bool)$valid)) {
            header('Location: admin.php', true, 303);
            exit;
        }
        // Sama sõnum ja HTTP staatus: vale parool, puuduv konto, vale roll, lukk.
        http_response_code(200);
        $error = 'Vale kasutajanimi või parool. Kui proovisid mitu korda, oota 15 minutit. Vajadusel laadi leht uuesti.';
    } catch (Throwable $e) {
        rk_error();
    } finally {
        unset($password, $_POST['password']);
    }
}
?>
<!doctype html>
<html lang="et">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Logi sisse – Renoveeri Kodu</title>
<style nonce="<?= rk_escape($nonce) ?>">
body{margin:0;padding:24px;background:#f5f7fb;color:#172330;font:16px Arial,sans-serif}.login-box{max-width:380px;margin:10vh auto;background:#fff;padding:30px;border-radius:12px;box-shadow:0 12px 30px #0001}h1{font-size:25px}label{display:block;margin-top:18px}input,button{box-sizing:border-box;width:100%;padding:12px;margin-top:8px;border:1px solid #b8c2cf;border-radius:6px;font:inherit}button{margin-top:24px;background:#172330;color:#fff;cursor:pointer}.error{background:#fff0f0;color:#852020;padding:12px;border-radius:6px;line-height:1.5}
</style>
</head>
<body><main class="login-box">
<h1>Logi sisse</h1>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= rk_escape($error) ?></p><?php endif; ?>
<form method="post" action="login.php">
<?= rk_csrf_field() ?>
<label for="username">Kasutajanimi</label>
<input id="username" name="username" autocomplete="username" maxlength="255" required>
<label for="password">Parool</label>
<input id="password" name="password" type="password" autocomplete="current-password" maxlength="4096" required>
<button type="submit">Logi sisse</button>
</form></main></body></html>
