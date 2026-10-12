<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin/_auth.php';
require_once __DIR__ . '/../admin/_translation.php';
require_once __DIR__ . '/../api/contact-i18n.php';
require_once __DIR__ . '/fixtures/contact-db.php';

$checks = 0;
function check(bool $condition, string $label): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
}

function translation_error(string $reason, callable $operation): void
{
    try {
        $operation();
    } catch (RKTranslationException $error) {
        check($error->reason === $reason, 'safe error ' . $reason);
        check(!str_contains($error->getMessage(), 'test-secret'), 'no credential in error');
        return;
    }
    throw new RuntimeException('Expected translation error ' . $reason);
}

$settings = ['key' => 'test-secret', 'plan' => 'free'];
$called = 0;
$transport = static function (string $url, string $key, string $body) use (&$called): array {
    $called++;
    check($url === 'https://api-free.deepl.com/v2/translate', 'fixed Free endpoint');
    check($key === 'test-secret', 'key supplied to server transport');
    $request = json_decode($body, true);
    check($request['text'] === ['Нужен ремонт кухни.'] && $request['target_lang'] === 'ET', 'original message and ET target');
    check(!isset($request['source_lang']), 'automatic source language');
    return ['status' => 200, 'body' => '{"translations":[{"text":"Vajan köögi remonti."}]}'];
};
check(rk_translate_message('Нужен ремонт кухни.', $settings, $transport) === 'Vajan köögi remonti.', 'translated text');
check(rk_translation_endpoint(['plan' => 'pro']) === 'https://api.deepl.com/v2/translate', 'fixed Pro endpoint');
translation_error('unavailable', static fn() => rk_translate_message('text', ['key' => '', 'plan' => 'free'], $transport));
translation_error('configuration', static fn() => rk_translate_message('text', ['key' => 'test-secret', 'plan' => 'https://example.com'], $transport));
translation_error('configuration', static fn() => rk_translate_message('text', ['key' => "test-secret\r\nheader", 'plan' => 'free'], $transport));
translation_error('message', static fn() => rk_translate_message(str_repeat('x', 60001), $settings, $transport));
translation_error('message', static fn() => rk_translate_message("\xff", $settings, $transport));
check($called === 1, 'invalid input never reaches transport');
foreach ([429 => 'busy', 456 => 'quota', 403 => 'configuration', 500 => 'service', 302 => 'service'] as $status => $reason) {
    translation_error($reason, static fn() => rk_translate_message('text', $settings, static fn() => ['status' => $status, 'body' => 'test-secret']));
}
foreach (['not JSON', '{}', '{"translations":[{"text":""}]}', '{"translations":[{"text":123}]}'] as $body) {
    translation_error('service', static fn() => rk_translate_message('text', $settings, static fn() => ['status' => 200, 'body' => $body]));
}
translation_error('service', static fn() => rk_translate_message('text', $settings, static fn() => ['status' => 200, 'body' => json_encode(['translations' => [['text' => str_repeat('x', 65536)]]])]));

$db = new RKTestPDO();
$original = $db->contacts[1];
$translate = static fn(string $message): string => rk_translate_message($message, $settings, $transport);
check(rk_translate_contact($db, 1, $translate) === 'success', 'save translation');
check($db->contacts[1]['message'] === $original['message'] && $db->contacts[1]['source'] === $original['source'], 'preserve original and source');
check($db->contacts[1]['message_et'] === 'Vajan köögi remonti.' && $db->contacts[1]['translation_provider'] === 'DeepL', 'separate translated fields');
check(rk_contact_translation_cached($db->contacts[1]), 'translation source hash valid');
check(rk_translate_contact($db, 1, static function (): string { throw new RuntimeException('Cache must skip provider.'); }) === 'cached', 'reuse cache without provider');
$db->contacts[1]['message'] .= ' ';
check(!rk_contact_translation_cached($db->contacts[1]), 'byte change invalidates cache');
check(rk_translate_contact($db, 1, static function () use ($db): string {
    $db->contacts[1]['message'] .= 'changed';
    return 'Stale translation';
}) === 'changed', 'reject concurrent source change');
check($db->contacts[1]['message_et'] === 'Vajan köögi remonti.' && $db->updates === 1, 'no stale overwrite');
check(rk_translate_contact($db, 404, $translate) === 'missing', 'missing inquiry');
check(rk_translate_contact($db, 2, $translate) === 'ineligible', 'Estonian inquiry skips provider');
check(rk_contact_needs_translation(['language' => 'et', 'message' => 'Здравствуйте']), 'legacy Cyrillic inquiry');
foreach (['et', 'en', 'ru'] as $language) {
    check(rk_contact_language($language) === $language, 'accepted form language ' . $language);
    check(rk_contact_text('success', $language) !== '' && rk_contact_text('auto_body', $language) !== '', 'localized response and confirmation ' . $language);
}
foreach (['de', 'RU', ['ru'], null] as $language) {
    check(rk_contact_language($language) === 'et', 'unsupported language defaults safely');
}

// Testi päris endpointi, turvamoodulit ja HTTP päiseid ajutises rakenduses.
$temp = sys_get_temp_dir() . '/rk-translation-test-' . bin2hex(random_bytes(8));
mkdir($temp . '/admin', 0700, true);
mkdir($temp . '/api', 0700, true);
foreach (['_auth.php', '_bootstrap.php', '_translation.php', 'translate-contact.php'] as $file) {
    copy(__DIR__ . '/../admin/' . $file, $temp . '/admin/' . $file);
}
copy(__DIR__ . '/../api/_contact-schema.php', $temp . '/api/_contact-schema.php');
copy(__DIR__ . '/fixtures/contact-db.php', $temp . '/fixture-db.php');
file_put_contents($temp . '/config.php', "<?php require_once __DIR__ . '/fixture-db.php'; \$pdo = new RKTestPDO(); define('DEEPL_API_KEY', '');");
file_put_contents($temp . '/router.php', <<<'PHP'
<?php
$_SERVER['HTTPS'] = 'on';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/seed' || $path === '/notice') {
    require __DIR__ . '/admin/_bootstrap.php';
    if ($path === '/seed') {
        $_SESSION = [];
        $_SESSION['test_role'] = $_GET['role'] ?? 'admin';
        $_SESSION['rk_auth'] = ['id' => 1, 'started' => time(), 'last' => time(), 'credential' => hash('sha256', 'test-credential-hash')];
        echo json_encode(['csrf' => rk_csrf()]);
    } else {
        echo json_encode($_SESSION['rk_translation_notice'] ?? null);
    }
    return;
}
require __DIR__ . '/admin/translate-contact.php';
PHP
);

function endpoint_request(string $url, string $method = 'GET', array $post = [], string $cookie = ''): array
{
    $context = stream_context_create(['http' => ['method' => $method, 'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: " . $cookie . "\r\n", 'content' => http_build_query($post), 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 3]]);
    $body = @file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $headers[0] ?? '', $status);
    $result = ['status' => (int)($status[1] ?? 0), 'body' => is_string($body) ? $body : '', 'cookie' => $cookie, 'location' => ''];
    foreach ($headers as $header) {
        if (stripos($header, 'Set-Cookie: ') === 0) {
            $result['cookie'] = explode(';', substr($header, 12), 2)[0];
        }
        if (stripos($header, 'Location: ') === 0) {
            $result['location'] = substr($header, 10);
        }
    }
    return $result;
}

$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($socket === false) {
    throw new RuntimeException('Local test server socket unavailable.');
}
$address = stream_socket_get_name($socket, false);
fclose($socket);
$process = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $temp, '-S', $address, '-t', $temp, $temp . '/router.php'], [['pipe', 'r'], ['file', $temp . '/server.log', 'a'], ['file', $temp . '/server.log', 'a']], $pipes);
if (!is_resource($process)) {
    throw new RuntimeException('Local PHP test server unavailable.');
}
$base = 'http://' . $address;
try {
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
    check($ready, 'local test server starts');
    $url = $base . '/admin/translate-contact.php';
    $response = endpoint_request($url, 'POST', ['contact_id' => '1']);
    check($response['status'] === 303 && $response['location'] === '/admin/login.php', 'unauthenticated endpoint blocked');
    foreach (['worker', 'moderaator'] as $role) {
        $seed = endpoint_request($base . '/seed?role=' . $role);
        $csrf = json_decode($seed['body'], true)['csrf'];
        $response = endpoint_request($url, 'POST', ['contact_id' => '1', 'rk_csrf' => $csrf], $seed['cookie']);
        check($response['status'] === 403, 'role blocked: ' . $role);
    }
    foreach (['admin', 'superadmin'] as $role) {
        $seed = endpoint_request($base . '/seed?role=' . $role);
        $csrf = json_decode($seed['body'], true)['csrf'];
        $cookie = $seed['cookie'];
        check(endpoint_request($url, 'GET', [], $cookie)['status'] === 405, 'GET blocked: ' . $role);
        foreach ([null, str_repeat('0', 64), ['invalid']] as $token) {
            $post = ['contact_id' => '1'];
            if ($token !== null) {
                $post['rk_csrf'] = $token;
            }
            check(endpoint_request($url, 'POST', $post, $cookie)['status'] === 403, 'CSRF blocked: ' . $role);
        }
        foreach ([['1'], '-1', '1.5'] as $id) {
            $response = endpoint_request($url, 'POST', ['contact_id' => $id, 'rk_csrf' => $csrf], $cookie);
            $notice = json_decode(endpoint_request($base . '/notice', 'GET', [], $cookie)['body'], true);
            check($response['status'] === 303 && $notice['reason'] === 'missing', 'invalid inquiry id rejected');
        }
        foreach ([1 => 'unavailable', 2 => 'ineligible', 3 => 'cached', 404 => 'missing'] as $id => $reason) {
            $response = endpoint_request($url, 'POST', ['contact_id' => (string)$id, 'rk_csrf' => $csrf, 'site' => '//external.example'], $cookie);
            $notice = json_decode(endpoint_request($base . '/notice', 'GET', [], $cookie)['body'], true);
            check($response['status'] === 303 && $notice['reason'] === $reason, 'endpoint result ' . $reason);
            check(str_starts_with($response['location'], '/admin/index.php?view=requests&site=all'), 'redirect stays in admin');
        }
    }
} finally {
    proc_terminate($process);
    proc_close($process);
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($temp);
}

echo 'PASS: ' . $checks . " contact translation and endpoint checks; no real API or database calls.\n";
