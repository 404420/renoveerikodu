<?php
require __DIR__ . '/_bootstrap.php';
require_admin();

$pdo = admin_db();
ensure_newsletter_subscribers_table($pdo);

$view = $_GET['view'] ?? 'requests';
if (!in_array($view, ['requests', 'subscribers', 'unsubscribers'], true)) {
    $view = 'requests';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id'];

    $stmt = $pdo->prepare('SELECT file_path FROM contacts WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if ($row) {
        foreach (decode_files($row['file_path'] ?? null) as $file) {
            $relative = ltrim(file_path_from_entry($file), '/');
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
    header('Location: index.php?view=requests');
    exit;
}

$requests = [];
$subscribers = [];
$unsubscribers = [];

$subscriberCount = (int) $pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'subscribed'")->fetchColumn();
$unsubscriberCount = (int) $pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'unsubscribed'")->fetchColumn();

if ($view === 'requests') {
    $requests = $pdo->query('SELECT * FROM contacts ORDER BY created_at DESC')->fetchAll();
} elseif ($view === 'subscribers') {
    $subscribers = $pdo->query("
        SELECT id, email, status, subscribed_at, unsubscribed_at, source, created_at, updated_at
        FROM newsletter_subscribers
        WHERE status = 'subscribed'
        ORDER BY COALESCE(updated_at, subscribed_at, created_at) DESC, id DESC
    ")->fetchAll();
} else {
    $unsubscribers = $pdo->query("
        SELECT id, email, status, subscribed_at, unsubscribed_at, source, created_at, updated_at
        FROM newsletter_subscribers
        WHERE status = 'unsubscribed'
        ORDER BY COALESCE(unsubscribed_at, updated_at, created_at) DESC, id DESC
    ")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - <?= $view === 'requests' ? 'Päringud' : ($view === 'subscribers' ? 'Subscriberid' : 'Unsubscriberid') ?></title>
<link rel="stylesheet" href="../assets/css/admin.css">
<style>
body{background:#f5f7fb;font-family:Arial,sans-serif;margin:0;padding:24px;color:#17202a}.topbar{display:flex;justify-content:space-between;gap:16px;align-items:center;background:#fff;padding:18px 20px;border-radius:8px;box-shadow:0 6px 22px rgba(0,0,0,.06);margin-bottom:20px}.topbar h1{font-size:24px;margin:0}.topbar-actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.admin-tabs{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.admin-tab{border:1px solid #cfd8e3;border-radius:4px;color:#17202a;padding:9px 12px;text-decoration:none;background:#fff}.admin-tab.active{background:#17202a;border-color:#17202a;color:#fff}.admin-tab-count{color:#697386;font-size:13px;margin-left:4px}.admin-tab.active .admin-tab-count{color:#d7dde5}.logout,.button-delete{background:#d9534f;color:#fff;border:0;border-radius:4px;padding:9px 12px;text-decoration:none;cursor:pointer}.table-wrap{overflow:auto;background:#fff;border-radius:8px;box-shadow:0 6px 22px rgba(0,0,0,.06)}table{border-collapse:collapse;width:100%;min-width:960px}th,td{border-bottom:1px solid #e7ebf0;padding:10px;text-align:left;vertical-align:top}th{background:#f1f4f8}.message{max-width:320px;white-space:pre-wrap}.preview-img{width:120px;height:90px;object-fit:cover;display:block;margin:0 0 6px;border:1px solid #d7dde5;border-radius:6px;background:#eef2f7}.lightbox-item{display:block;width:max-content}.lightbox-item:hover .preview-img{border-color:#17202a;box-shadow:0 4px 14px rgba(0,0,0,.16)}.admin-lightbox{position:fixed;inset:0;z-index:9999;display:none;align-items:center;justify-content:center;background:rgba(9,15,23,.88);padding:28px}.admin-lightbox.open{display:flex}.lightbox-panel{position:relative;display:flex;align-items:center;justify-content:center;width:100%;height:100%;max-width:1180px;max-height:92vh}.lightbox-image{max-width:100%;max-height:82vh;object-fit:contain;border-radius:6px;background:#111;box-shadow:0 16px 50px rgba(0,0,0,.45)}.lightbox-close,.lightbox-prev,.lightbox-next{position:absolute;border:0;border-radius:4px;background:rgba(255,255,255,.92);color:#17202a;cursor:pointer;font-size:24px;line-height:1}.lightbox-close{top:0;right:0;padding:10px 14px}.lightbox-prev,.lightbox-next{top:50%;transform:translateY(-50%);padding:16px 18px}.lightbox-prev{left:0}.lightbox-next{right:0}.lightbox-prev:disabled,.lightbox-next:disabled{opacity:.28;cursor:not-allowed}.lightbox-caption{position:absolute;left:0;right:0;bottom:0;color:#fff;text-align:center;font-size:15px;padding:10px 48px}.muted{color:#697386}.file-link{display:block;margin-bottom:6px;max-width:180px;overflow-wrap:anywhere}.actions{white-space:nowrap}@media(max-width:700px){body{padding:12px}.topbar{align-items:flex-start;flex-direction:column}.topbar-actions{align-items:flex-start;flex-direction:column}.admin-lightbox{padding:16px}.lightbox-prev,.lightbox-next{padding:12px 14px}.lightbox-caption{padding:10px 40px}}
</style>
</head>
<body>
<header class="topbar">
  <div>
    <h1><?= $view === 'requests' ? 'Päringud' : ($view === 'subscribers' ? 'Subscriberid' : 'Unsubscriberid') ?></h1>
    <div class="muted">Sisse logitud: <?= h($_SESSION['username'] ?? '') ?></div>
  </div>
  <div class="topbar-actions">
    <nav class="admin-tabs" aria-label="Admin vaated">
      <a class="admin-tab <?= $view === 'requests' ? 'active' : '' ?>" href="index.php?view=requests">Päringud</a>
      <a class="admin-tab <?= $view === 'subscribers' ? 'active' : '' ?>" href="index.php?view=subscribers">Subscriberid <span class="admin-tab-count"><?= $subscriberCount ?></span></a>
      <a class="admin-tab <?= $view === 'unsubscribers' ? 'active' : '' ?>" href="index.php?view=unsubscribers">Unsubscriberid <span class="admin-tab-count"><?= $unsubscriberCount ?></span></a>
    </nav>
    <a href="logout.php" class="logout">Logi välja</a>
  </div>
</header>

<main class="table-wrap">
<?php if ($view === 'requests'): ?>
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
          $path = file_path_from_entry($file);
          $label = file_label_from_entry($file);
          $url = upload_url($path);
          ?>
          <?php if ($path !== '' && is_preview_image($path)): ?>
            <?php echo '<a ' . 'hr' . 'ef="' . h($url) . '" class="lightbox-item" data-request-id="' . (int) $row['id'] . '" data-label="' . h($label) . '"><img ' . 'sr' . 'c="' . h($url) . '" alt="' . h($label) . '" class="preview-img" loading="lazy"></a>'; ?>
          <?php endif; ?>
          <?php if ($path !== ''): ?>
            <?php echo '<a ' . 'hr' . 'ef="' . h($url) . '" target="_blank" rel="noopener" class="file-link">' . h($label) . '</a>'; ?>
          <?php endif; ?>
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
<?php elseif ($view === 'subscribers'): ?>
<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Email</th>
      <th>Staatus</th>
      <th>Liitus</th>
      <th>Allikas</th>
    </tr>
  </thead>
  <tbody>
  <?php if (!$subscribers): ?>
    <tr><td colspan="5" class="muted">Subscribereid ei ole veel.</td></tr>
  <?php endif; ?>
  <?php foreach ($subscribers as $row): ?>
    <tr>
      <td><?= (int) $row['id'] ?></td>
      <td><a href="mailto:<?= h($row['email']) ?>"><?= h($row['email']) ?></a></td>
      <td><?= h($row['status']) ?></td>
      <td><?= h($row['subscribed_at'] ?? $row['created_at'] ?? '') ?></td>
      <td><?= h($row['source'] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Email</th>
      <th>Staatus</th>
      <th>Liitus</th>
      <th>Loobus</th>
      <th>Allikas</th>
    </tr>
  </thead>
  <tbody>
  <?php if (!$unsubscribers): ?>
    <tr><td colspan="6" class="muted">Unsubscribereid ei ole veel.</td></tr>
  <?php endif; ?>
  <?php foreach ($unsubscribers as $row): ?>
    <tr>
      <td><?= (int) $row['id'] ?></td>
      <td><a href="mailto:<?= h($row['email']) ?>"><?= h($row['email']) ?></a></td>
      <td><?= h($row['status']) ?></td>
      <td><?= h($row['subscribed_at'] ?? '') ?></td>
      <td><?= h($row['unsubscribed_at'] ?? $row['created_at'] ?? '') ?></td>
      <td><?= h($row['source'] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</main>
<div class="admin-lightbox" id="adminLightbox" aria-hidden="true">
  <div class="lightbox-panel" role="dialog" aria-modal="true" aria-label="Pildi eelvaade">
    <button type="button" class="lightbox-close" id="lightboxClose" aria-label="Sulge">×</button>
    <button type="button" class="lightbox-prev" id="lightboxPrev" aria-label="Eelmine pilt">‹</button>
    <img src="" alt="" class="lightbox-image" id="lightboxImage">
    <button type="button" class="lightbox-next" id="lightboxNext" aria-label="Järgmine pilt">›</button>
    <div class="lightbox-caption" id="lightboxCaption"></div>
  </div>
</div>
<script>
(function () {
  const lightbox = document.getElementById("adminLightbox");
  const image = document.getElementById("lightboxImage");
  const caption = document.getElementById("lightboxCaption");
  const closeButton = document.getElementById("lightboxClose");
  const prevButton = document.getElementById("lightboxPrev");
  const nextButton = document.getElementById("lightboxNext");
  let gallery = [];
  let currentIndex = 0;

  function renderImage() {
    const item = gallery[currentIndex];
    if (!item) {
      return;
    }

    image.src = item.href;
    image.alt = item.label;
    caption.textContent = item.label + " (" + (currentIndex + 1) + "/" + gallery.length + ")";
    prevButton.disabled = currentIndex === 0;
    nextButton.disabled = currentIndex === gallery.length - 1;
  }

  function openLightbox(link) {
    const requestId = link.dataset.requestId;
    const links = Array.from(document.querySelectorAll('.lightbox-item[data-request-id="' + requestId + '"]'));
    gallery = links.map((item) => ({
      href: item.getAttribute("href"),
      label: item.dataset.label || item.textContent.trim() || "Pilt"
    }));
    currentIndex = links.indexOf(link);
    lightbox.classList.add("open");
    lightbox.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
    renderImage();
    closeButton.focus();
  }

  function closeLightbox() {
    lightbox.classList.remove("open");
    lightbox.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
    image.src = "";
    gallery = [];
    currentIndex = 0;
  }

  function showPrevious() {
    if (currentIndex > 0) {
      currentIndex -= 1;
      renderImage();
    }
  }

  function showNext() {
    if (currentIndex < gallery.length - 1) {
      currentIndex += 1;
      renderImage();
    }
  }

  document.querySelectorAll(".lightbox-item").forEach((link) => {
    link.addEventListener("click", function (event) {
      event.preventDefault();
      openLightbox(link);
    });
  });

  closeButton.addEventListener("click", closeLightbox);
  prevButton.addEventListener("click", showPrevious);
  nextButton.addEventListener("click", showNext);
  lightbox.addEventListener("click", function (event) {
    if (event.target === lightbox) {
      closeLightbox();
    }
  });

  document.addEventListener("keydown", function (event) {
    if (!lightbox.classList.contains("open")) {
      return;
    }

    if (event.key === "Escape") {
      closeLightbox();
    } else if (event.key === "ArrowLeft") {
      showPrevious();
    } else if (event.key === "ArrowRight") {
      showNext();
    }
  });
})();
</script>
</body>
</html>
