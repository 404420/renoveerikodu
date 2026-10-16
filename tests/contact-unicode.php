<?php
declare(strict_types=1);

// An isolated integration test; never loads the website's private config.php.
// Requires a disposable MySQL/MariaDB account allowed to CREATE/DROP DATABASE.
require_once __DIR__ . '/../api/_database.php';
require_once __DIR__ . '/../api/_contact-schema.php';

$dsn = getenv('RK_TEST_MYSQL_DSN') ?: '';
if (!str_starts_with($dsn, 'mysql:') || preg_match('/(?:^|;)dbname=/i', $dsn)) {
    throw new RuntimeException('Set RK_TEST_MYSQL_DSN to mysql:host=...;port=... without dbname, plus RK_TEST_MYSQL_USER/PASSWORD. Use a disposable server.');
}
$user = getenv('RK_TEST_MYSQL_USER') ?: 'root';
$password = getenv('RK_TEST_MYSQL_PASSWORD') ?: '';
$db = new PDO($dsn . ';charset=utf8mb4', $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'rk_unicode_test_' . bin2hex(random_bytes(8));
$checks = 0;
$process = null;
$temp = sys_get_temp_dir() . '/' . $database;

function unicode_check(bool $condition, string $label): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
}

function unicode_request(string $url, ?array $post = null, bool $multipart = false): array
{
    $headers = "Accept: application/json\r\n";
    $body = '';
    if ($post !== null) {
        if ($multipart) {
            $boundary = 'rk-' . bin2hex(random_bytes(8));
            foreach ($post as $key => $value) {
                $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$key\"\r\n\r\n$value\r\n";
            }
            $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"attachments[]\"; filename=\"Кухня.txt\"\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nРазмеры кухни: 12 м²\r\n--$boundary--\r\n";
            $headers .= "Content-Type: multipart/form-data; boundary=$boundary\r\n";
        } else {
            $headers .= "Content-Type: application/x-www-form-urlencoded; charset=UTF-8\r\n";
            $body = http_build_query($post);
        }
    }
    $context = stream_context_create(['http' => ['method' => $post === null ? 'GET' : 'POST', 'header' => $headers, 'content' => $body, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5]]);
    $response = file_get_contents($url, false, $context);
    preg_match('/\s(\d{3})\s/', $http_response_header[0] ?? '', $status);
    return ['status' => (int) ($status[1] ?? 0), 'body' => $response === false ? '' : $response];
}

try {
    $db->exec("CREATE DATABASE `$database` CHARACTER SET latin1 COLLATE latin1_swedish_ci");
    $db->exec("USE `$database`");
    $schema = file_get_contents(__DIR__ . '/../database.sql');
    $legacySchema = str_replace(['DEFAULT CHARSET=utf8mb4', 'COLLATE=utf8mb4_unicode_ci'], ['DEFAULT CHARSET=latin1', 'COLLATE=latin1_swedish_ci'], $schema);
    $db->exec($legacySchema);
    // Match an older installation, including the missing optional source field.
    $db->exec('ALTER TABLE contacts DROP COLUMN language, DROP COLUMN message_et, DROP COLUMN translation_source_hash, DROP COLUMN translated_at, DROP COLUMN translation_provider, DROP COLUMN source');
    $db->exec("SET SESSION sql_mode = ''");
    $seed = $db->prepare('INSERT INTO contacts (name, email, message) VALUES (?, ?, ?)');
    $seed->execute(['Jüri', 'legacy@example.test', 'Vajan köögi remonti.']);
    $seed->execute(['Test', 'broken@example.test', 'Нужен ремонт кухни.']);
    unicode_check(str_contains((string) $db->query("SELECT message FROM contacts WHERE email='broken@example.test'")->fetchColumn(), '????'), 'reproduce Cyrillic loss in a legacy latin1 table');
    $db->exec("DELETE FROM contacts WHERE email='broken@example.test'");

    mkdir($temp . '/api', 0700, true);
    mkdir($temp . '/admin', 0700, true);
    foreach (['contact.php', 'contact-i18n.php', '_contact-schema.php', '_database.php'] as $file) {
        copy(__DIR__ . '/../api/' . $file, $temp . '/api/' . $file);
    }
    foreach (['_bootstrap.php', '_auth.php'] as $file) {
        copy(__DIR__ . '/../admin/' . $file, $temp . '/admin/' . $file);
    }
    // Both real request paths receive a reused connection with the wrong encoding.
    putenv('RK_TEST_RUNTIME_DSN=' . $dsn . ';dbname=' . $database . ';charset=latin1');
    file_put_contents($temp . '/config.php', <<<'PHP'
<?php
class RKUnicodePDO extends PDO {
    public function exec(string $statement): int|false {
        $failure = @file_get_contents(__DIR__ . '/deny-storage');
        if (($failure === 'schema' && str_starts_with($statement, 'ALTER TABLE contacts'))
            || ($failure === 'connection' && str_starts_with($statement, 'SET NAMES'))) {
            throw new PDOException('Simulated hosting charset configuration failure.');
        }
        return parent::exec($statement);
    }
}
$pdo = new RKUnicodePDO(getenv('RK_TEST_RUNTIME_DSN'), getenv('RK_TEST_MYSQL_USER') ?: 'root', getenv('RK_TEST_MYSQL_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec("SET SESSION sql_mode = ''");
PHP
    );
    file_put_contents($temp . '/router.php', <<<'PHP'
<?php
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/admin-read') {
    $_SERVER['HTTPS'] = 'on'; // Simulate the production TLS terminator locally.
    require __DIR__ . '/admin/_bootstrap.php';
    header('Content-Type: text/html; charset=UTF-8');
    // Use the real admin connection and HTML escaping on the persisted inquiry.
    foreach (rk_db()->query('SELECT * FROM contacts ORDER BY created_at DESC, id DESC') as $row) {
        echo '<article>' . h($row['name']) . '|' . h($row['address']) . '|' . h($row['message']) . '</article>';
    }
    return;
}
return false;
PHP
    );
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if ($socket === false) {
        throw new RuntimeException('Local test server socket unavailable.');
    }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    // Discard all test mail locally: this test cannot send a customer/admin email.
    $process = proc_open([PHP_BINARY, '-d', 'sendmail_path=/bin/true', '-d', 'session.save_path=' . $temp, '-S', $address, '-t', $temp, $temp . '/router.php'], [['pipe', 'r'], ['file', $temp . '/server.log', 'a'], ['file', $temp . '/server.log', 'a']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Local PHP test server unavailable.');
    }
    $ready = false;
    for ($attempt = 0; $attempt < 40; $attempt++) {
        $probe = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if (is_resource($probe)) {
            fclose($probe);
            $ready = true;
            break;
        }
        usleep(25000);
    }
    unicode_check($ready, 'local test server starts');
    $base = 'http://' . $address;
    $post = ['name' => 'Алексей Иванов', 'email' => 'unicode@example.test', 'phone' => '+372 55555555', 'address' => 'Таллинн, Примерная 12', 'message' => "Здравствуйте! Нужен ремонт кухни — 12 м². 😀\n<срочно> & 'кавычки'", 'language' => 'ru'];
    foreach (['connection', 'schema'] as $failure) {
        file_put_contents($temp . '/deny-storage', $failure);
        $rejected = unicode_request($base . '/api/contact.php', $post);
        $result = json_decode($rejected['body'], true);
        unicode_check($rejected['status'] === 500 && ($result['success'] ?? null) === false && !($result['db_saved'] ?? false), $failure . ' failure is reported without false success');
        unicode_check((int) $db->query("SELECT COUNT(*) FROM contacts WHERE email='unicode@example.test'")->fetchColumn() === 0, $failure . ' failure cannot save a damaged inquiry');
    }
    unlink($temp . '/deny-storage');
    $response = unicode_request($base . '/api/contact.php', $post, true);
    $result = json_decode($response['body'], true);
    unicode_check($response['status'] === 200 && ($result['db_saved'] ?? false) === true, 'real Russian form submission succeeds on legacy schema (HTTP ' . $response['status'] . ': ' . $response['body'] . '; ' . file_get_contents($temp . '/server.log') . ')');
    $row = $db->query("SELECT * FROM contacts WHERE email='unicode@example.test' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    foreach (['name', 'address', 'message', 'language'] as $field) {
        unicode_check($row[$field] === $post[$field], 'exact UTF-8 bytes stored for ' . $field);
    }
    $files = json_decode($row['file_path'], true);
    unicode_check($files[0]['original_name'] === 'Кухня.txt', 'Cyrillic attachment filename preserved');
    unicode_check(file_get_contents($temp . '/' . $files[0]['path']) === 'Размеры кухни: 12 м²', 'Cyrillic attachment contents preserved');
    $admin = unicode_request($base . '/admin-read');
    unicode_check($admin['status'] === 200 && str_contains($admin['body'], htmlspecialchars($post['message'], ENT_QUOTES, 'UTF-8')), 'admin reads and displays full Russian and emoji text through legacy config (HTTP ' . $admin['status'] . ': ' . $admin['body'] . '; ' . file_get_contents($temp . '/server.log') . ')');
    unicode_check(!str_contains($admin['body'], '<срочно>') && str_contains($admin['body'], '&lt;срочно&gt;'), 'admin retains HTML escaping');
    $legacy = $db->query("SELECT name, message FROM contacts WHERE email='legacy@example.test'")->fetch(PDO::FETCH_ASSOC);
    unicode_check($legacy === ['name' => 'Jüri', 'message' => 'Vajan köögi remonti.'], 'migration preserves existing Estonian inquiry');

    $db->exec('ALTER TABLE contacts ADD COLUMN source VARCHAR(500) DEFAULT NULL');
    foreach (['et' => 'Soovin köögi remonti. ÕÄÖÜ', 'en' => 'Please renovate my kitchen. €'] as $language => $message) {
        $next = array_replace($post, ['language' => $language, 'message' => $message, 'source' => 'https://example.test/ремонт']);
        $response = unicode_request($base . '/api/contact.php', $next);
        unicode_check($response['status'] === 200 && (json_decode($response['body'], true)['db_saved'] ?? false), $language . ' form succeeds');
        $row = $db->query("SELECT * FROM contacts WHERE email='unicode@example.test' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        unicode_check($row['message'] === $message && $row['language'] === $language && $row['source'] === $next['source'], $language . ' inquiry and Cyrillic source preserve exact bytes');
    }

    // A UTF-8 table default alone does not fix a separately overridden old column.
    $db->exec('ALTER TABLE contacts ADD COLUMN message_et TEXT CHARACTER SET latin1 DEFAULT NULL');
    rk_ensure_contact_translation_schema($db);
    $db->prepare('UPDATE contacts SET message_et=? WHERE id=?')->execute(['Перевод сохраняется 😀', $row['id']]);
    unicode_check($db->query('SELECT message_et FROM contacts WHERE id=' . (int) $row['id'])->fetchColumn() === 'Перевод сохраняется 😀', 'mixed legacy column charset upgraded as well');
    rk_ensure_contact_translation_schema($db);
    $db->exec('ALTER TABLE contacts ADD COLUMN migration_probe TEXT CHARACTER SET latin1 DEFAULT NULL');
    $db->exec(file_get_contents(__DIR__ . '/../admin/migrations/20261016_contact_utf8mb4.sql'));
    $db->prepare('UPDATE contacts SET migration_probe=? WHERE id=?')->execute(['Миграция работает 😀', $row['id']]);
    unicode_check($db->query('SELECT migration_probe FROM contacts WHERE id=' . (int) $row['id'])->fetchColumn() === 'Миграция работает 😀', 'SQL migration converts existing latin1 columns');
    $db->exec(file_get_contents(__DIR__ . '/../admin/migrations/20261016_contact_utf8mb4.sql'));
    unicode_check($db->query('SELECT message_et FROM contacts WHERE id=' . (int) $row['id'])->fetchColumn() === 'Перевод сохраняется 😀', 'automatic and SQL migrations are repeatable and preserve Unicode');

    foreach (['latin1', 'utf8'] as $charset) {
        $db->exec('SET NAMES ' . $charset);
        rk_database_utf8mb4($db);
        $encodings = $db->query('SELECT @@character_set_client, @@character_set_connection, @@character_set_results')->fetch(PDO::FETCH_NUM);
        unicode_check($encodings === ['utf8mb4', 'utf8mb4', 'utf8mb4'], 'normalize all ' . $charset . ' connection directions');
        unicode_check(!$db->getAttribute(PDO::ATTR_EMULATE_PREPARES), 'native prepared statements enabled');
        unicode_check($db->query("SELECT name FROM contacts WHERE email='unicode@example.test' LIMIT 1")->fetchColumn() === $post['name'], 'normalized connection reads Cyrillic without question marks');
    }
    // New installations must work without any compatibility schema conversion.
    $db->exec('DROP TABLE contacts');
    $db->exec($schema);
    rk_ensure_contact_translation_schema($db);
    $db->prepare('INSERT INTO contacts (name,email,message,language) VALUES (?,?,?,?)')->execute([$post['name'], $post['email'], $post['message'], 'ru']);
    unicode_check($db->query('SELECT message FROM contacts')->fetchColumn() === $post['message'], 'new installation preserves Russian and four-byte Unicode');
    echo 'PASS: ' . $checks . " Unicode checks with real HTTP/PHP/MySQL/admin rendering; no real emails.\n";
} finally {
    if (is_resource($process)) {
        proc_terminate($process);
        proc_close($process);
    }
    $db->exec("DROP DATABASE IF EXISTS `$database`");
    if (is_dir($temp)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($temp);
    }
}
