<?php
require __DIR__ . '/_bootstrap.php';

$pdo = admin_db();
$count = (int) $pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'admin'")->fetchColumn();
$message = '';
$error = '';

if ($count > 0) {
    http_response_code(403);
    exit('Admin kasutaja on juba olemas. Turvalisuse jaoks on setup suletud.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || strlen($password) < 10) {
        $error = 'Kasutajanimi on kohustuslik ja parool peab olema vähemalt 10 märki.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO admin_users (username, password, role) VALUES (:username, :password, "admin")');
        $stmt->execute([
            ':username' => $username,
            ':password' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $message = 'Admin kasutaja loodud. Mine login lehele.';
    }
}
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Loo admin kasutaja</title>
<style>
body{background:#f5f7fb;font-family:Arial,sans-serif;margin:0;padding:20px}.box{background:#fff;max-width:420px;margin:80px auto;padding:30px;border-radius:8px;box-shadow:0 12px 30px rgba(0,0,0,.08)}input,button{width:100%;box-sizing:border-box;padding:12px;margin:8px 0;border-radius:4px;border:1px solid #cfd6df;font-size:16px}button{background:#111;color:#fff;border:0}.ok{background:#1f7a1f;color:#fff;padding:10px;border-radius:4px}.error{background:#d9534f;color:#fff;padding:10px;border-radius:4px}
</style>
</head>
<body>
<main class="box">
  <h1>Loo admin kasutaja</h1>
  <?php if ($message): ?><p class="ok"><?= h($message) ?> <a href="login.php" style="color:#fff;text-decoration:underline">Logi sisse</a></p><?php endif; ?>
  <?php if ($error): ?><p class="error"><?= h($error) ?></p><?php endif; ?>
  <?php if (!$message): ?>
  <form method="post">
    <input type="text" name="username" placeholder="Kasutajanimi" required>
    <input type="password" name="password" placeholder="Parool" required minlength="10">
    <button type="submit">Loo admin</button>
  </form>
  <?php endif; ?>
</main>
</body>
</html>
