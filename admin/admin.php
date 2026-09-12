<?php
declare(strict_types=1);
// OLEMASOLEV ÜHENDUS: _bootstrap.php sisaldab require_once $configPath;
// $configPath = __DIR__ . '/../config.php'; seda ühendusloogikat ei asendata.
require_once __DIR__ . '/_bootstrap.php';
$user = rk_require_permission('account.view');
if ((string)$user['role'] === 'worker') {
    header('Location: /paneel', true, 303);
    exit;
}
if (in_array('panel.full', rk_permissions((string)$user['role']), true)) {
    header('Location: index.php', true, 303);
    exit;
}
header("Content-Security-Policy: default-src 'none'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
?>
<!doctype html><html lang="et"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>Minu konto</title></head>
<body><main><h1>Minu konto</h1>
<p>Kasutajanimi: <?= rk_escape((string)$user['username']) ?></p>
<p>Roll: <?= rk_escape((string)$user['role']) ?></p>
<p>Sinu kontol on ligipääs konto vaatele. Teiste moodulite jaoks peab administraator määrama vastavad õigused.</p>
<form action="logout.php" method="post"><?= rk_csrf_field() ?><button>Logi välja</button></form>
</main></body></html>
