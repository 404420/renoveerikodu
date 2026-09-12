<?php
declare(strict_types=1);

// See moodul EI loo ühendusi ega muuda PDO atribuute.
// Ühendus tuleb muutmata admin_db() funktsioonist / olemasolevast $pdo objektist.
const RK_IDLE_SECONDS = 900;
const RK_ABSOLUTE_SECONDS = 28800;
const RK_LOCK_SECONDS = 900;
const RK_FAILURE_LIMIT = 5;

function rk_error(): void
{
    // Ära logi exception'i sisu: see võib sisaldada SQL-i või ühenduse andmeid.
    error_log('RK_AUTH: security operation failed; inspect server configuration.');
    http_response_code(503);
    exit('Sisselogimisteenus ei ole hetkel saadaval. Proovi hiljem uuesti.');
}

function rk_session_start(): void
{
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    if (PHP_SAPI === 'cli') {
        return;
    }
    // Proxy korral peab HTTPS tõeväärtuse seadma USALDATUD serverikonfiguratsioon.
    // Kliendi saadetud X-Forwarded-Proto / X-Forwarded-For päiseid ei usaldata.
    if (empty($_SERVER['HTTPS']) || strtolower((string) $_SERVER['HTTPS']) === 'off') {
        http_response_code(400);
        exit('Admin-paneel nõuab HTTPS-ühendust.');
    }
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    // Teadlikult eraldi sessioon avaliku veebilehe omast. Vana sisselogimine aegub.
    if (session_status() !== PHP_SESSION_NONE || headers_sent()) {
        rk_error();
    }
    session_name('__Host-RKAdmin');
    if (!session_start([
        'use_strict_mode' => true,
        'use_only_cookies' => true,
        'cookie_lifetime' => 0,
        'cookie_path' => '/',
        'cookie_domain' => '',
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'gc_maxlifetime' => RK_IDLE_SECONDS,
    ])) {
        rk_error();
    }
}

function rk_db(): PDO
{
    // Kasuta index.php poolt juba hangitud ühendust või admin_db() tulemust.
    // Funktsiooni admin_db() enda ühenduse loogika jääb muutmata.
    static $connection = null;
    if ($connection === null) {
        $connection = isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO
            ? $GLOBALS['pdo'] : admin_db();
    }
    return $connection;
}

function rk_sql(PDO $db, string $sql, array $params = []): PDOStatement
{
    // Ka PDO silent-režiimis ebaõnnestub turvakontroll kinniselt.
    $stmt = $db->prepare($sql);
    if ($stmt === false || !$stmt->execute($params)) {
        throw new RuntimeException('Authentication database operation failed.');
    }
    return $stmt;
}

function rk_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rk_csrf(): string
{
    if (!isset($_SESSION['rk_csrf']) || !is_string($_SESSION['rk_csrf'])) {
        $_SESSION['rk_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['rk_csrf'];
}

function rk_csrf_valid(): bool
{
    $given = $_POST['rk_csrf'] ?? null;
    return is_string($given) && strlen($given) === 64
        && isset($_SESSION['rk_csrf']) && is_string($_SESSION['rk_csrf'])
        && hash_equals($_SESSION['rk_csrf'], $given);
}

function rk_csrf_field(): string
{
    return '<input type="hidden" name="rk_csrf" value="' . rk_escape(rk_csrf()) . '">';
}

function rk_require_csrf(): void
{
    if (!rk_csrf_valid()) {
        http_response_code(403);
        exit('Päring aegus või ei ole kehtiv. Laadi leht uuesti.');
    }
}

function rk_permissions(string $role): array
{
    // Vana admin roll säilib. Tundmatu roll ei saa ühtegi õigust.
    // Moderaator saab ainult konto vaate; ärimoodulite õigusi ei oletata.
    return [
        'admin' => ['account.view', 'panel.full'],
        'superadmin' => ['account.view', 'panel.full'],
        'moderaator' => ['account.view'],
        'worker' => ['account.view', 'worker.panel'],
    ][$role] ?? [];
}

function rk_reset_session(): void
{
    $_SESSION = [];
    if (!session_regenerate_id(true)) {
        rk_error();
    }
}

function rk_current_user(): ?array
{
    $state = $_SESSION['rk_auth'] ?? null;
    if (!is_array($state)) {
        // Vanad user_id/role väärtused üksi ei tõenda autentimist.
        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);
        return null;
    }
    $now = time();
    if ($now - (int)($state['last'] ?? 0) >= RK_IDLE_SECONDS
        || $now - (int)($state['started'] ?? 0) >= RK_ABSOLUTE_SECONDS) {
        rk_reset_session();
        return null;
    }
    try {
        // Roll ja parooli muutus jõustuvad järgmisel kaitstud päringul.
        $user = rk_sql(rk_db(), 'SELECT id, username, password, role FROM admin_users WHERE id = ?',
            [(int)($state['id'] ?? 0)])->fetch(PDO::FETCH_ASSOC);
        if (!$user || !rk_permissions((string)$user['role'])
            || !hash_equals((string)($state['credential'] ?? ''), hash('sha256', (string)$user['password']))) {
            rk_reset_session();
            return null;
        }
        $_SESSION['rk_auth']['last'] = $now;
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = (string)$user['username'];
        $_SESSION['role'] = (string)$user['role'];
        unset($user['password']);
        return $user;
    } catch (Throwable $e) {
        rk_error();
    }
    return null;
}

function rk_require_permission(string $permission): array
{
    $user = rk_current_user();
    if ($user === null) {
        header('Location: /admin/login.php', true, 303);
        exit;
    }
    if (!in_array($permission, rk_permissions((string)$user['role']), true)) {
        http_response_code(403);
        exit('Sul puudub selle toimingu õigus.');
    }
    return $user;
}

function rk_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_string($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return 'unknown';
    }
    return (string)inet_ntop(inet_pton($ip));
}

function rk_audit(PDO $db, string $username, string $outcome): void
{
    // Parooli ei edastata sellele funktsioonile. UTC aeg tekib MySQL-is.
    // Binaarne väli säilitab ka vigase UTF-8 ilma logi/SQL süstimiseta.
    rk_sql($db, 'INSERT INTO admin_auth_attempts (ip_address, username, outcome, occurred_at) VALUES (?, ?, ?, UTC_TIMESTAMP())',
        [rk_ip(), substr($username, 0, 255), $outcome]);
}

function rk_login(string $username, string $password, bool $inputValid): bool
{
    $db = rk_db();
    if ($db->inTransaction()) {
        throw new RuntimeException('Authentication requires its own transaction.');
    }
    // Leia konto MySQL enda kollatsiooni järgi. Nii jagavad nt Mari/mari sama
    // piirangut, kui olemasolev tabel käsitleb neid sama kasutajana.
    $user = $inputValid ? rk_sql($db,
        'SELECT id, username, password, role FROM admin_users WHERE username = ? LIMIT 1',
        [$username])->fetch(PDO::FETCH_ASSOC) : false;
    $keys = [hash('sha256', 'ip:' . rk_ip()),
        hash('sha256', $user ? 'user:' . $user['id'] : 'unknown:' . strtolower($username))];
    sort($keys, SORT_STRING); // Kõik päringud lukustavad read samas järjekorras.
    if (!$db->beginTransaction()) {
        throw new RuntimeException('Cannot begin authentication transaction.');
    }
    try {
        $rows = [];
        foreach ($keys as $key) {
            rk_sql($db, 'INSERT INTO admin_auth_limits (bucket_key, failures, last_failure, blocked_until) VALUES (?, 0, 0, 0) ON DUPLICATE KEY UPDATE bucket_key = VALUES(bucket_key)', [$key]);
            $rows[$key] = rk_sql($db, 'SELECT failures, last_failure, blocked_until FROM admin_auth_limits WHERE bucket_key = ? FOR UPDATE', [$key])->fetch(PDO::FETCH_ASSOC);
            if (!$rows[$key]) {
                throw new RuntimeException('Missing limiter state.');
            }
        }
        // Võta aeg PÄRAST lukustuse saamist, kasutades DB kella.
        $now = (int)rk_sql($db, 'SELECT UNIX_TIMESTAMP()')->fetchColumn();
        foreach ($rows as $row) {
            if ((int)$row['blocked_until'] > $now) {
                rk_audit($db, $username, 'blocked');
                if (!$db->commit()) { throw new RuntimeException('Commit failed.'); }
                return false; // Blokeeritud katse ei pikenda lukustust.
            }
        }
        // Tundmatu kasutaja korral kontrolli fiktiivset bcrypt räsi.
        $dummy = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $hash = $user ? (string)$user['password'] : $dummy;
        $info = password_get_info($hash);
        $supported = in_array($info['algoName'] ?? '', ['bcrypt', 'argon2id'], true);
        $lengthOK = strlen($password) <= 4096
            && (($info['algoName'] ?? '') !== 'bcrypt' || strlen($password) <= 72);
        // Parooli EI trimmi ega HTML-kodeeri. Kontrollitakse algseid baite.
        $verified = password_verify($lengthOK && $inputValid ? $password : '', $supported ? $hash : $dummy);
        $ok = $user && $supported && $lengthOK && $inputValid && $verified
            && in_array('account.view', rk_permissions((string)$user['role']), true);
        foreach ($rows as $key => $row) {
            $expired = ((int)$row['blocked_until'] > 0 && (int)$row['blocked_until'] <= $now)
                || $now - (int)$row['last_failure'] >= RK_LOCK_SECONDS;
            $failures = $ok ? 0 : ($expired ? 0 : (int)$row['failures']) + 1;
            $blocked = $failures >= RK_FAILURE_LIMIT ? $now + RK_LOCK_SECONDS : 0;
            rk_sql($db, 'UPDATE admin_auth_limits SET failures = ?, last_failure = ?, blocked_until = ? WHERE bucket_key = ?',
                [$failures, $ok ? 0 : $now, $blocked, $key]);
        }
        rk_audit($db, $username, $ok ? 'success' : 'failed');
        if (!$db->commit()) { throw new RuntimeException('Commit failed.'); }
        if (!$ok) { return false; }
        rk_reset_session(); // Session fixation kaitse ENNE autentimisandmete salvestamist.
        $_SESSION['rk_auth'] = ['id' => (int)$user['id'], 'started' => time(), 'last' => time(),
            'credential' => hash('sha256', (string)$user['password'])];
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = (string)$user['username'];
        $_SESSION['role'] = (string)$user['role'];
        rk_csrf();
        return true;
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        throw $e;
    }
}
