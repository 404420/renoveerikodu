<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_translation.php';

// Samad sessiooni aegumis-, rolli- ja paroolimuutuse kontrollid nagu põhipaneelis.
rk_require_permission('panel.full');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Lubatud on ainult POST-päring.');
}
rk_require_csrf();

$idValue = $_POST['contact_id'] ?? '';
$id = is_string($idValue) ? filter_var($idValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
$site = $_POST['site'] ?? 'all';
if (!is_string($site) || !in_array($site, ['all', 'renoveerikodu', 'parketipaigaldaja', 'other'], true)) {
    $site = 'all';
}
$reason = 'missing';
try {
    if ($id !== false) {
        $db = rk_db();
        try {
            rk_ensure_contact_translation_schema($db);
        } catch (Throwable $error) {
            throw new RKTranslationException('schema');
        }
        $reason = rk_translate_contact($db, $id);
    }
} catch (RKTranslationException $error) {
    $reason = $error->reason;
} catch (Throwable $error) {
    // Ei väljasta ega logi kliendi sõnumit, võtmeid, SQL-i ega teenuse vastust.
    $reason = 'service';
}

$_SESSION['rk_translation_notice'] = ['reason' => $reason, 'contact_id' => $id ?: 0];
header('Location: /admin/index.php?view=requests&site=' . rawurlencode($site) . ($id ? '#request-' . $id : ''), true, 303);
exit;
