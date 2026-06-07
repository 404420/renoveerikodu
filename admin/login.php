<?php
require __DIR__ . '/_bootstrap.php';

if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = admin_db()->prepare('SELECT id, username, password, role FROM admin_users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        header('Location: index.php');
        exit;
    }

    $error = 'Vale kasutajanimi või parool';
}
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin login - Renoveeri Kodu</title>
<link rel="stylesheet" href="../assets/css/admin.css">
<style>
body{background:#f5f7fb;font-family:Arial,sans-serif;margin:0;padding:20px}.login-box{background:#fff;max-width:380px;margin:90px auto;padding:30px;border-radius:8px;box-shadow:0 12px 30px rgba(0,0,0,.08)}h1{text-align:center;font-size:24px}input,button{width:100%;box-sizing:border-box;padding:12px;margin:8px 0;border-radius:4px;border:1px solid #cfd6df;font-size:16px}button{background:#111;color:#fff;border:0;cursor:pointer}.error{background:#d9534f;color:#fff;padding:10px;border-radius:4px;margin-bottom:12px;text-align:center}
</style>
</head>
<body>
<main class="login-box">
  <h1>Logi sisse</h1>
  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="text" name="username" placeholder="Kasutajanimi" required autocomplete="username">
    <input type="password" name="password" placeholder="Parool" required autocomplete="current-password">
    <button type="submit">Logi sisse</button>
  </form>
</main>
</body>
</html>
