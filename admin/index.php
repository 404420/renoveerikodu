<?php
require __DIR__ . '/_bootstrap.php';
require_admin();

$pdo = admin_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id'];

    $stmt = $pdo->prepare('SELECT file_path FROM contacts WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if ($row) {
        foreach (decode_files($row['file_path'] ?? null) as $file) {
            $relative = ltrim((string) $file, '/');
            if (str_starts_with($relative, 'api/uploads/') || str_starts_with($relative, 'uploads/')) {
                $absolute = realpath(__DIR__ . '/../' . $relative);
                $root = realpath(__DIR__ . '/..');
                if ($absolute && $root && str_starts_with($absolute, $root) && is_file($absolute)) {
                    unlink($absolute);
                }
            }
        }
    }

    $delete = $pdo->prepare('DELETE FROM contacts WHERE id = :id');
    $delete->execute([':id' => $id]);
    header('Location: index.php');
    exit;
}

$requests = $pdo->query('SELECT * FROM contacts ORDER BY created_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - Päringud</title>
<link rel="stylesheet" href="../assets/css/admin.css">
<style>
body{background:#f5f7fb;font-family:Arial,sans-serif;margin:0;padding:24px;color:#17202a}.topbar{display:flex;justify-content:space-between;gap:16px;align-items:center;background:#fff;padding:18px 20px;border-radius:8px;box-shadow:0 6px 22px rgba(0,0,0,.06);margin-bottom:20px}.topbar h1{font-size:24px;margin:0}.logout,.button-delete{background:#d9534f;color:#fff;border:0;border-radius:4px;padding:9px 12px;text-decoration:none;cursor:pointer}.table-wrap{overflow:auto;background:#fff;border-radius:8px;box-shadow:0 6px 22px rgba(0,0,0,.06)}table{border-collapse:collapse;width:100%;min-width:960px}th,td{border-bottom:1px solid #e7ebf0;padding:10px;text-align:left;vertical-align:top}th{background:#f1f4f8}.message{max-width:320px;white-space:pre-wrap}.preview-img{max-width:120px;max-height:120px;display:block;margin:0 0 6px;border:1px solid #d7dde5;border-radius:4px}.muted{color:#697386}.file-link{display:block;margin-bottom:6px}.actions{white-space:nowrap}@media(max-width:700px){body{padding:12px}.topbar{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body>
<header class="topbar">
  <div>
    <h1>Päringud</h1>
    <div class="muted">Sisse logitud: <?= h($_SESSION['username'] ?? '') ?></div>
  </div>
  <a href="logout.php" class="logout">Logi välja</a>
</header>

<main class="table-wrap">
<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Nimi</th>
      <th>Email</th>
      <th>Telefon</th>
      <th>Aadress</th>
      <th>Sõnum</th>
      <th>Failid</th>
      <th>Allikas</th>
      <th>Kuupäev</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php if (!$requests): ?>
    <tr><td colspan="10" class="muted">Päringuid ei ole veel.</td></tr>
  <?php endif; ?>
  <?php foreach ($requests as $row): ?>
    <tr>
      <td><?= (int) $row['id'] ?></td>
      <td><?= h($row['name']) ?></td>
      <td><a href="mailto:<?= h($row['email']) ?>"><?= h($row['email']) ?></a></td>
      <td><?= h($row['phone'] ?? '') ?></td>
      <td><?= h($row['address'] ?? '') ?></td>
      <td class="message"><?= h($row['message']) ?></td>
      <td>
        <?php $files = decode_files($row['file_path'] ?? null); ?>
        <?php if (!$files): ?>
          <span class="muted">-</span>
        <?php endif; ?>
        <?php foreach ($files as $file): ?>
          <?php
          $path = is_array($file) ? ($file['path'] ?? '') : (string) $file;
          $label = is_array($file) ? ($file['original_name'] ?? basename($path)) : basename($path);
          $url = upload_url($path);
          $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
          ?>
          <?php if (in_array($ext, ['jpg','jpeg','png','gif','webp'], true)): ?>
            <?php echo '<a ' . 'hr' . 'ef="' . h($url) . '" target="_blank" rel="noopener"><img ' . 'sr' . 'c="' . h($url) . '" alt="' . h($label) . '" class="preview-img"></a>'; ?>
          <?php endif; ?>
          <?php echo '<a ' . 'hr' . 'ef="' . h($url) . '" target="_blank" rel="noopener" class="file-link">' . h($label) . '</a>'; ?>
        <?php endforeach; ?>
      </td>
      <td><?= h($row['source'] ?? '') ?></td>
      <td><?= h($row['created_at']) ?></td>
      <td class="actions">
        <form method="post" onsubmit="return confirm('Kustutan selle päringu?');">
          <input type="hidden" name="delete_id" value="<?= (int) $row['id'] ?>">
          <button type="submit" class="button-delete">Kustuta</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</main>
</body>
</html>
