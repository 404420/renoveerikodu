<?php
declare(strict_types=1);

// Ainult serveris kasutatav moodul; ühenduse saame rk_db() kaudu.
function rkw_input(string $name): string
{
    $value = $_POST[$name] ?? '';
    if (!is_string($value)) { throw new InvalidArgumentException('Vormi andmed ei ole korrektsed.'); }
    return trim($value);
}

function rkw_text(string $name, int $maxBytes, bool $required = false): string
{
    $v = rkw_input($name);
    if (($required && $v === '') || strlen($v) > $maxBytes || preg_match('//u', $v) !== 1
        || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $v)) {
        throw new InvalidArgumentException('Kontrolli nõutud väljade pikkust ja sisu.');
    }
    return $v;
}

function rkw_minutes(string $start, string $end, int $break): int
{
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $start)
        || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $end)) {
        throw new InvalidArgumentException('Sisesta korrektne algus- ja lõpuaeg.');
    }
    $a = ((int)substr($start, 0, 2)) * 60 + (int)substr($start, 3, 2);
    $b = ((int)substr($end, 0, 2)) * 60 + (int)substr($end, 3, 2);
    // Öövahetus lõpeb järgmisel kalendripäeval. Võrdne kellaaeg pole 24h vahetus.
    if ($a === $b) { throw new InvalidArgumentException('Algus ja lõpp ei saa olla sama kellaaeg.'); }
    $duration = ($b - $a + 1440) % 1440;
    if ($duration > 960) { throw new InvalidArgumentException('Ühe sisestuse pikkus võib olla kuni 16 tundi.'); }
    if ($break < 0 || $break >= $duration) {
        throw new InvalidArgumentException('Paus peab olema tööpäevast lühem.');
    }
    return $duration - $break;
}

function rkw_duration(int $minutes): string
{
    return intdiv($minutes, 60) . ' h ' . ($minutes % 60) . ' min';
}

function rkw_new_submission(): string
{
    $token = bin2hex(random_bytes(32));
    $tokens = $_SESSION['rkw_forms'] ?? [];
    if (!is_array($tokens)) { $tokens = []; }
    $tokens[$token] = time();
    $_SESSION['rkw_forms'] = array_slice($tokens, -20, null, true);
    return $token;
}

function rkw_save(array $user): void
{
    rk_require_csrf();
    $key = rkw_input('submission_key');
    $issued = $_SESSION['rkw_forms'][$key] ?? null;
    if (!preg_match('/^[a-f0-9]{64}$/D', $key) || !is_int($issued) || time() - $issued > RK_ABSOLUTE_SECONDS) {
        throw new InvalidArgumentException('Vorm aegus. Laadi leht uuesti ja proovi veel kord.');
    }
    $date = rkw_input('work_date');
    $tz = new DateTimeZone('Europe/Tallinn');
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
    $today = new DateTimeImmutable('today', $tz);
    if (!$parsed || $parsed->format('Y-m-d') !== $date || $parsed > $today || $parsed < $today->modify('-366 days')) {
        throw new InvalidArgumentException('Kuupäev peab olema viimase aasta jooksul ega tohi olla tulevikus.');
    }
    $start = rkw_input('start_time'); $end = rkw_input('end_time');
    $rawBreak = rkw_input('break_minutes');
    if (!preg_match('/^\d{1,3}$/D', $rawBreak)) { throw new InvalidArgumentException('Paus peab olema minutites, täisarvuna.'); }
    $break = (int)$rawBreak;
    rkw_minutes($start, $end, $break);
    $object = rkw_text('object_name', 190, true);
    $description = rkw_text('description', 2000, true);
    $db = rk_db();
    rkw_require_storage($db);
    if ($db->inTransaction() || !$db->beginTransaction()) { throw new RuntimeException('Transaction unavailable.'); }
    try {
        // Konto lukustamine serialiseerib sama töötaja sisestused ka eri sessioonidest.
        $account = rk_sql($db, 'SELECT username, role, password FROM admin_users WHERE id = ? FOR UPDATE', [(int)$user['id']])->fetch(PDO::FETCH_ASSOC);
        if (!$account || $account['role'] !== 'worker'
            || !hash_equals((string)($_SESSION['rk_auth']['credential'] ?? ''), hash('sha256', (string)$account['password']))) {
            throw new InvalidArgumentException('Konto õigused muutusid. Logi uuesti sisse.');
        }
        $existing = rk_sql($db, 'SELECT workday_id FROM admin_worker_workday_owners WHERE user_id = ? AND submission_key = ?', [(int)$user['id'], $key])->fetchColumn();
        if ($existing) {
            if (!$db->commit()) { throw new RuntimeException('Commit failed.'); }
            return; // Sama nupuvajutuse kordus ei loo teist tööpäeva.
        }
        $count = (int)rk_sql($db, 'SELECT COUNT(*) FROM admin_worker_workday_owners WHERE user_id = ? AND created_at >= CURRENT_TIMESTAMP - INTERVAL 1 DAY', [(int)$user['id']])->fetchColumn();
        if ($count >= 50) { throw new InvalidArgumentException('Tänane sisestuste piir on täis. Võta ühendust administraatoriga.'); }
        // Kaitse ka uue vormiga tehtud identse topeltsisestuse eest.
        $duplicate = rk_sql($db, 'SELECT w.id FROM admin_workdays w INNER JOIN admin_worker_workday_owners o ON o.workday_id = w.id WHERE o.user_id = ? AND w.work_date = ? AND w.start_time = ? AND w.end_time = ? AND w.object_name = ? LIMIT 1',
            [(int)$user['id'], $date, $start . ':00', $end . ':00', $object])->fetchColumn();
        if ($duplicate) { throw new InvalidArgumentException('Sama objekti, kuupäeva ja kellaaegadega tööpäev on juba salvestatud.'); }
        // Konto ID, nimi, hind ja staatus ei tule brauseri peidetud väljadest.
        // Admini olemasolev tööpäevade vaade ja kiirkinnitus töötavad sama tabeliga.
        rk_sql($db, "INSERT INTO admin_workdays (work_date, worker_name, object_id, object_name, address, start_time, end_time, break_minutes, work_type, notes, mileage_km, hourly_rate, payment_type, piece_quantity, piece_unit, piece_rate, piece_pricing_mode, piece_fixed_price, status) VALUES (?, ?, NULL, ?, NULL, ?, ?, ?, ?, ?, 0, 0, 'hourly', 0, 'm²', 0, 'unit', 0, 'pending')",
            [$date, rkw_name($user, rkw_profile($user)), $object, $start, $end, $break, 'Töötaja sisestus', $description]);
        $workdayId = (int)$db->lastInsertId();
        if ($workdayId < 1) { throw new RuntimeException('Missing inserted workday.'); }
        rk_sql($db, 'INSERT INTO admin_worker_workday_owners (workday_id, user_id, submission_key) VALUES (?, ?, ?)', [$workdayId, (int)$user['id'], $key]);
        if (!$db->commit()) { throw new RuntimeException('Commit failed.'); }
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        throw $e;
    }
}

function rkw_profile(array $user): array
{
    $row = rk_sql(rk_db(), 'SELECT first_name, last_name FROM admin_worker_profiles WHERE user_id = ?', [(int)$user['id']])->fetch(PDO::FETCH_ASSOC);
    return $row ?: ['first_name' => '', 'last_name' => ''];
}

function rkw_name(array $user, array $profile): string
{
    $name = trim($profile['first_name'] . ' ' . $profile['last_name']);
    return $name !== '' ? $name : (string)$user['username'];
}

function rkw_save_profile(array $user): void
{
    rk_require_csrf();
    $first = rkw_text('first_name', 90, true);
    $last = rkw_text('last_name', 90, true);
    foreach ([$first, $last] as $name) {
        if (preg_match('/[\x00-\x1F\x7F]/', $name)) {
            throw new InvalidArgumentException('Nimi ei tohi sisaldada reavahetusi ega juhtmärke.');
        }
    }
    $db = rk_db();
    rkw_require_storage($db);
    if ($db->inTransaction() || !$db->beginTransaction()) { throw new RuntimeException('Transaction unavailable.'); }
    try {
        $account = rk_sql($db, 'SELECT role, password FROM admin_users WHERE id = ? FOR UPDATE', [(int)$user['id']])->fetch(PDO::FETCH_ASSOC);
        if (!$account || $account['role'] !== 'worker' || !hash_equals((string)($_SESSION['rk_auth']['credential'] ?? ''), hash('sha256', (string)$account['password']))) {
            throw new InvalidArgumentException('Konto õigused muutusid. Logi uuesti sisse.');
        }
        rk_sql($db, 'INSERT INTO admin_worker_profiles (user_id, first_name, last_name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name = VALUES(last_name)', [(int)$user['id'], $first, $last]);
        if (!$db->commit()) { throw new RuntimeException('Commit failed.'); }
        $_SESSION['worker_display_name'] = $first . ' ' . $last;
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        throw $e;
    }
}

function rkw_require_storage(PDO $db): void
{
    static $checked = false;
    if ($checked) { return; }
    $rows = rk_sql($db, "SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('admin_users','admin_workdays','admin_worker_workday_owners','admin_worker_profiles')")->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== 4) { throw new RuntimeException('Worker migration missing.'); }
    foreach ($rows as $row) {
        if (strtoupper((string)$row['ENGINE']) !== 'INNODB') { throw new RuntimeException('Transactional storage required.'); }
    }
    $checked = true;
}
