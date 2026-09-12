<?php
declare(strict_types=1);
require_once __DIR__ . '/admin/_bootstrap.php';
require_once __DIR__ . '/admin/_worker_panel.php';
$user = rk_require_permission('worker.panel');
if (($user['role'] ?? '') !== 'worker') { http_response_code(403); exit('See vaade on ainult töötajale.'); }
header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) { header('Allow: GET, POST'); http_response_code(405); exit; }
$tz = new DateTimeZone('Europe/Tallinn');
$today = new DateTimeImmutable('today', $tz);
$error = '';
if ($method === 'POST') {
    try {
        if (rkw_input('action') === 'profile') {
            rkw_save_profile($user);
            $_SESSION['rkw_profile_saved'] = true;
            header('Location: /paneel', true, 303);
            exit;
        }
        if (rkw_input('action') !== 'workday') { throw new InvalidArgumentException('Tundmatu toiming.'); }
        rkw_save($user);
        $_SESSION['rkw_saved'] = true;
        header('Location: /paneel?month=' . substr(rkw_input('work_date'), 0, 7), true, 303);
        exit;
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
        http_response_code(422);
    } catch (Throwable $e) {
        error_log('RK_WORKER_SAVE: failed; code=' . (int)$e->getCode());
        $error = 'Salvestamine ei õnnestunud. Sinu sisestus on vormis alles. Proovi uuesti või võta ühendust administraatoriga.';
        http_response_code(503);
    }
}
$success = !empty($_SESSION['rkw_saved']); unset($_SESSION['rkw_saved']);
$rawMonth = $_GET['month'] ?? $today->format('Y-m');
$month = is_string($rawMonth) && preg_match('/^20\d{2}-(?:0[1-9]|1[0-2])$/D', $rawMonth) ? $rawMonth : $today->format('Y-m');
$from = new DateTimeImmutable($month . '-01', $tz);
$until = $from->modify('+1 month')->format('Y-m-d');
$rawPage = $_GET['page'] ?? '1';
$page = is_string($rawPage) && ctype_digit($rawPage) ? max(1, min(10000, (int)$rawPage)) : 1;
$listError = false; $rows = []; $count = 0; $sum = ['minutes' => 0, 'pending' => 0];
$last = false; $profile = ['first_name' => '', 'last_name' => ''];
try {
    rkw_require_storage(rk_db());
    $profile = rkw_profile($user);
    $_SESSION['worker_display_name'] = rkw_name($user, $profile);
    $db = rk_db(); $params = [(int)$user['id'], $from->format('Y-m-d'), $until];
    // Ainult omaniku ID järgi. Nime järgi vanu ridu automaatselt ei seota.
    $where = ' FROM admin_workdays w INNER JOIN admin_worker_workday_owners o ON o.workday_id = w.id WHERE o.user_id = ? AND w.work_date >= ? AND w.work_date < ?';
    $count = (int)rk_sql($db, 'SELECT COUNT(*)' . $where, $params)->fetchColumn();
    $page = min($page, max(1, (int)ceil($count / 12))); $offset = ($page - 1) * 12;
    $rows = rk_sql($db, 'SELECT w.id, w.work_date, w.object_name, w.start_time, w.end_time, w.break_minutes, w.notes, w.status' . $where . ' ORDER BY w.work_date DESC, w.id DESC LIMIT 12 OFFSET ' . $offset, $params)->fetchAll(PDO::FETCH_ASSOC);
    $sum = rk_sql($db, "SELECT COALESCE(SUM(GREATEST(0, MOD(TIME_TO_SEC(w.end_time)-TIME_TO_SEC(w.start_time)+86400,86400)/60-w.break_minutes)),0) AS minutes, COALESCE(SUM(w.status='pending'),0) AS pending" . $where, $params)->fetch(PDO::FETCH_ASSOC);
    $last = rk_sql($db, 'SELECT w.object_name FROM admin_workdays w INNER JOIN admin_worker_workday_owners o ON o.workday_id = w.id WHERE o.user_id = ? ORDER BY w.id DESC LIMIT 1', [(int)$user['id']])->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('RK_WORKER_READ: failed; code=' . (int)$e->getCode());
    $listError = true; http_response_code(503);
}
$values = ['work_date' => $today->format('Y-m-d'), 'object_name' => $last ? (string)$last['object_name'] : '', 'start_time' => '08:00', 'end_time' => '17:00', 'break_minutes' => '30', 'description' => ''];
if ($method === 'POST') {
    foreach ($values as $field => $default) {
        if (is_string($_POST[$field] ?? null)) { $values[$field] = substr($_POST[$field], 0, 4096); }
    }
}
$profileError = $method === 'POST' && ($_POST['action'] ?? null) === 'profile' && $error !== '';
$profileValues = $profile;
if ($profileError) {
    foreach (['first_name','last_name'] as $field) {
        if (is_string($_POST[$field] ?? null)) { $profileValues[$field] = substr($_POST[$field], 0, 400); }
    }
}
$submission = rkw_new_submission();
$statuses = ['pending' => 'Ootab kinnitamist', 'confirmed' => 'Kinnitatud', 'paid' => 'Makstud', 'draft' => 'Mustand'];
$months = ['01'=>'jaanuar','02'=>'veebruar','03'=>'märts','04'=>'aprill','05'=>'mai','06'=>'juuni','07'=>'juuli','08'=>'august','09'=>'september','10'=>'oktoober','11'=>'november','12'=>'detsember'];
$monthLabel = $months[$from->format('m')] . ' ' . $from->format('Y');
?>
<!doctype html>
<html lang="et"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Minu tööpäevad · Renoveeri Kodu</title>
<link rel="stylesheet" href="/assets/worker/paneel.css"><script src="/assets/worker/paneel.js" defer></script>
</head><body>
<header class="top"><a class="brand" href="/paneel"><span class="brand-mark">RK<span>↗</span></span><span>Renoveeri Kodu<small>TÖÖTAJA PANEEL</small></span></a>
<div class="account"><span class="account-name"><?= rk_escape(rkw_name($user, $profile)) ?><small><?= rk_escape((string)$user['username']) ?> · Töötaja</small></span>
<form action="/admin/logout.php" method="post"><?= rk_csrf_field() ?><button class="logout" type="submit">Logi välja <span aria-hidden="true">↗</span></button></form>
<button type="button" id="profile-toggle" aria-expanded="false" aria-controls="profile-menu" class="avatar" title="<?= rk_escape((string)$user['username']) ?>" aria-label="Profiil: <?= rk_escape((string)$user['username']) ?>">RK</button><div id="profile-menu" class="profile-menu" hidden><button type="button" id="open-settings">Seaded</button></div></div></header>
<dialog data-reopen="<?= $profileError ? 'yes' : 'no' ?>" id="profile-settings" aria-labelledby="settings-title">
<form method="post" action="/paneel" id="profile-form">
<?= rk_csrf_field() ?><input type="hidden" name="action" value="profile">
<div class="settings-heading"><h2 id="settings-title">Seaded</h2><button type="button" id="close-settings" aria-label="Sulge seaded">×</button></div>
<?php if ($profileError): ?><div class="notice error" role="alert"><?= rk_escape($error) ?></div><?php endif; ?><h3>Nimi</h3><p>Nimi kuvatakse administraatorile sinu tööpäevade juures. Kasutajanimi jääb samaks.</p>
<div class="field"><label for="first-name">Eesnimi</label><input id="first-name" name="first_name" autocomplete="given-name" maxlength="90" required value="<?= rk_escape((string)($profileValues['first_name'])) ?>"></div>
<div class="field"><label for="last-name">Perekonnanimi</label><input id="last-name" name="last_name" autocomplete="family-name" maxlength="90" required value="<?= rk_escape((string)($profileValues['last_name'])) ?>"></div>
<button class="submit" type="submit" <?= $listError ? 'disabled' : '' ?>>Salvesta nimi</button>
</form></dialog>
<main>
<?php if (!empty($_SESSION['rkw_profile_saved'])): unset($_SESSION['rkw_profile_saved']); ?><div class="notice success" role="status">Nimi salvestatud. Admin näeb uut nime tööpäevade vaate järgmisel laadimisel.</div><?php endif; ?>
<div class="intro"><div><p class="eyebrow">SINU TÖÖ. SELGE ÜLEVAADE.</p><h1>Minu tööpäevad<span>.</span></h1><p class="muted">Märgi tehtud töö ja hoia oma tundidel silm peal.</p></div><span class="date-label"><?= $today->format('d.m.Y') ?></span></div>
<?php if ($success): ?><div class="notice success" role="status"><strong>Tööpäev salvestatud.</strong> Sinu sisestus ootab administraatori kinnitust.</div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice error" role="alert"><?= rk_escape($error) ?></div><?php endif; ?>
<?php if ($listError): ?><div class="notice error" role="alert">Tööpäevade laadimine ei õnnestunud. Proovi uuesti või teavita administraatorit.</div><?php endif; ?>
<section class="stats" aria-label="Valitud kuu kokkuvõte">
<div class="stat"><span>Töötunde <small>· <?= rk_escape($monthLabel) ?></small></span><strong><?= $listError ? '—' : rk_escape(rkw_duration((int)$sum['minutes'])) ?></strong><p>Pausid on maha arvestatud</p></div>
<div class="stat"><span>Logitud tööpäevi</span><strong><?= $listError ? '—' : $count ?></strong><p>Valitud kuu sisestused</p></div>
<div class="stat"><span>Ootab kinnitamist</span><strong><?= $listError ? '—' : (int)$sum['pending'] ?><i class="dot"></i></strong><p>Administraatori ülevaatusel</p></div>
</section>
<div class="columns"><section class="card entry" aria-labelledby="entry-title"><div class="card-head"><div><span class="section-tag">01 / SISESTA</span><h2 id="entry-title">Lisa tööpäev</h2></div><span class="plus" aria-hidden="true">+</span></div>
<form action="/paneel" method="post" id="workday-form">
<?= rk_csrf_field() ?><input type="hidden" name="action" value="workday"><input type="hidden" name="submission_key" value="<?= rk_escape($submission) ?>">
<div class="field"><div class="label-row"><label for="work-date">Kuupäev</label><div class="quick"><button type="button" data-date="<?= $today->format('Y-m-d') ?>">Täna</button><button type="button" data-date="<?= $today->modify('-1 day')->format('Y-m-d') ?>">Eile</button></div></div><input id="work-date" type="date" name="work_date" min="<?= $today->modify('-366 days')->format('Y-m-d') ?>" max="<?= $today->format('Y-m-d') ?>" value="<?= rk_escape($values['work_date']) ?>" required></div>
<div class="field"><label for="object">Objekt või aadress</label><input id="object" name="object_name" maxlength="190" placeholder="Näiteks Sõudebaasi tee 15" value="<?= rk_escape($values['object_name']) ?>" required><small>Viimati kasutatud objekt on järgmisel korral eeltäidetud.</small></div>
<div class="times"><div class="field"><label for="start">Algus</label><input id="start" type="time" name="start_time" value="<?= rk_escape($values['start_time']) ?>" required></div><div class="field"><label for="end">Lõpp</label><input id="end" type="time" name="end_time" value="<?= rk_escape($values['end_time']) ?>" required></div><div class="field"><label for="pause">Paus · min</label><input id="pause" type="number" name="break_minutes" min="0" max="959" step="1" inputmode="numeric" value="<?= rk_escape($values['break_minutes']) ?>" required></div></div>
<div class="calculated"><div><span>Tööaeg pausita</span><small id="time-hint">Paus arvestatakse tööajast maha</small></div><output id="duration" aria-live="polite">—</output></div>
<div class="field"><label for="description">Mida täna tegid?</label><textarea id="description" name="description" rows="3" maxlength="2000" placeholder="Näiteks: fassaadi ettevalmistus ja esimese kihi värvimine." required><?= rk_escape($values['description']) ?></textarea></div>
<button class="submit" type="submit" <?= $listError ? 'disabled' : '' ?>>Salvesta tööpäev <span aria-hidden="true">↗</span></button><p class="form-note">Sisestus saadetakse administraatorile kinnitamiseks.</p>
</form></section>
<section class="card history" aria-labelledby="history-title"><div class="card-head"><div><span class="section-tag">02 / AJALUGU</span><h2 id="history-title">Minu sisestused</h2></div><form class="month-form" method="get" action="/paneel"><label class="sr-only" for="month">Vali kuu</label><input id="month" type="month" name="month" value="<?= rk_escape($month) ?>" required><button type="submit">Näita</button></form></div>
<?php if (!$rows && !$listError): ?><div class="empty"><span aria-hidden="true">↗</span><h3>Siin algab sinu ülevaade</h3><p>Selles kuus pole veel sisestusi.<br>Lisa tööpäev kõrval olevas vormis.</p></div><?php endif; ?>
<div class="entries">
<?php foreach ($rows as $row):
$startMin = (int)substr((string)$row['start_time'],0,2)*60+(int)substr((string)$row['start_time'],3,2);
$endMin = (int)substr((string)$row['end_time'],0,2)*60+(int)substr((string)$row['end_time'],3,2);
$net = max(0,($endMin-$startMin+1440)%1440-(int)$row['break_minutes']);
$state = array_key_exists((string)$row['status'],$statuses) ? (string)$row['status'] : 'unknown';
$day = DateTimeImmutable::createFromFormat('!Y-m-d',(string)$row['work_date'],$tz);
?>
<article class="workday"><div class="workday-top"><span class="day"><?= $day ? $day->format('d.m.Y') : rk_escape((string)$row['work_date']) ?></span><span class="badge <?= rk_escape($state) ?>"><?= rk_escape($statuses[$state] ?? 'Ülevaatamisel') ?></span></div>
<h3><?= rk_escape((string)$row['object_name']) ?></h3><p class="description"><?= rk_escape((string)($row['notes'] ?? '')) ?></p>
<div class="workday-bottom"><span><?= rk_escape(substr((string)$row['start_time'],0,5)) ?>–<?= rk_escape(substr((string)$row['end_time'],0,5)) ?> <span class="muted">· Paus <?= (int)$row['break_minutes'] ?> min</span></span><strong><?= rk_escape(rkw_duration($net)) ?></strong></div></article>
<?php endforeach; ?></div>
<?php if ($count > 12): ?><nav class="pagination" aria-label="Ajaloo leheküljed"><?php if ($page > 1): ?><a href="/paneel?month=<?= rk_escape($month) ?>&amp;page=<?= $page-1 ?>">← Eelmised</a><?php endif; ?><span><?= $page ?> / <?= (int)ceil($count/12) ?></span><?php if ($page * 12 < $count): ?><a href="/paneel?month=<?= rk_escape($month) ?>&amp;page=<?= $page+1 ?>">Järgmised →</a><?php endif; ?></nav><?php endif; ?>
<p class="history-note">Näed ainult selle konto kaudu lisatud tööpäevi. Paranduse tegemiseks võta ühendust administraatoriga.</p>
</section></div><footer>Renoveeri Kodu <span>Vähem paberit. Rohkem tehtud tööd.</span></footer>
</main></body></html>
