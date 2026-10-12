<?php
declare(strict_types=1);

require_once __DIR__ . '/contact-i18n.php';
require_once __DIR__ . '/_contact-schema.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => rk_contact_text('post_only')], JSON_UNESCAPED_UNICODE);
    exit;
}

function wants_json_response(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

    return stripos($accept, 'application/json') !== false
        || strtolower($requestedWith) === 'xmlhttprequest';
}

function clean_value($value): string
{
    return trim(filter_var((string) $value, FILTER_SANITIZE_SPECIAL_CHARS));
}

function finish_response(bool $success, string $message, int $status = 200, array $extra = []): void
{
    http_response_code($status);

    if (wants_json_response()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message,
        ], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $fallback = ['et' => '/kontakt', 'en' => '/en/contact', 'ru' => '/ru/kontakty'][rk_contact_language($_POST['language'] ?? 'et')];
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $parts = is_string($referer) ? parse_url($referer) : false;
    $requestHost = parse_url('https://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    if (is_array($parts) && is_string($requestHost)
        && strtolower((string)($parts['host'] ?? '')) === strtolower($requestHost)
        && preg_match('#^/(?!/)[^\r\n]*$#', (string)($parts['path'] ?? '')) === 1) {
        $fallback = $parts['path'];
    }

    $separator = str_contains($fallback, '?') ? '&' : '?';
    header('Location: ' . $fallback . $separator . ($success ? 'success=1' : 'error=1'), true, 303);
    exit;
}

function encode_mime_header(string $value): string
{
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function send_contact_emails(
    string $name,
    string $email,
    string $phone,
    string $address,
    string $message,
    array $uploadedFiles,
    string $language = 'et'
): void {
    $to = 'info@renoveerikodu.ee';
    $fromEmail = 'info@renoveerikodu.ee';
    $fromName = 'RK Meistrid';

    $attachmentLines = [];
    foreach ($uploadedFiles as $file) {
        $originalName = $file['original_name'] ?? 'fail';
        $path = $file['path'] ?? '';
        $attachmentLines[] = '- ' . $originalName . ($path !== '' ? ' (' . $path . ')' : '');
    }

    $adminSubject = 'Uus päring kodulehelt';
    $adminMessage = "Uus päring kodulehelt\n\n";
    $adminMessage .= "Vormi keel: {$language}\n";
    $adminMessage .= "Nimi: {$name}\n";
    $adminMessage .= "Email: {$email}\n";
    $adminMessage .= 'Telefon: ' . ($phone !== '' ? $phone : '-') . "\n";
    $adminMessage .= 'Aadress: ' . ($address !== '' ? $address : '-') . "\n\n";
    $adminMessage .= "Sõnum:\n{$message}\n";

    if ($attachmentLines) {
        $adminMessage .= "\nLisatud failid:\n" . implode("\n", $attachmentLines) . "\n";
    }

    $adminHeaders = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . encode_mime_header($fromName) . " <{$fromEmail}>",
        "Reply-To: {$email}",
    ];

    mail(
        $to,
        encode_mime_header($adminSubject),
        $adminMessage,
        implode("\r\n", $adminHeaders)
    );

    $autoSubject = rk_contact_text('auto_subject', $language);
    $autoMessage = rk_contact_text('auto_body', $language);

    $autoHeaders = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . encode_mime_header($fromName) . " <{$fromEmail}>",
        "Reply-To: {$fromEmail}",
    ];

    mail(
        $email,
        encode_mime_header($autoSubject),
        $autoMessage,
        implode("\r\n", $autoHeaders)
    );
}

function load_config(): void
{
    $paths = [
        __DIR__ . '/../config.php',
        __DIR__ . '/config.php',
    ];

    foreach ($paths as $path) {
        if (is_file($path)) {
            require_once $path;
            return;
        }
    }

    finish_response(false, rk_contact_text('config_missing'), 500);
}

function db_pdo(): PDO
{
    load_config();

    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        return $GLOBALS['pdo'];
    }

    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
        return new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        $host = defined('DB_HOST') ? DB_HOST : null;
        $db = defined('DB_NAME') ? DB_NAME : null;
        $user = defined('DB_USER') ? DB_USER : null;
        $pass = defined('DB_PASS') ? DB_PASS : null;

        if ($host && $db && $user !== null && $pass !== null) {
            return new PDO(
                "mysql:host={$host};dbname={$db};charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        }
    }

    finish_response(false, rk_contact_text('connection_missing'), 500);
}

function verify_recaptcha_if_configured(): void
{
    $secret = defined('RECAPTCHA_SECRET')
        ? trim((string) RECAPTCHA_SECRET)
        : trim((string) ($GLOBALS['recaptchaSecret'] ?? ''));

    // CAPTCHA on valikuline. Kui võtit pole seadistatud, jätkub päringu
    // töötlemine ning spämmikaitseks jääb kasutusele honeypot-väli "website".
    if ($secret === '') {
        return;
    }

    $response = trim((string) ($_POST['g-recaptcha-response'] ?? ''));
    if ($response === '') {
        finish_response(false, rk_contact_text('captcha_required'), 422);
    }

    $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
    $postData = http_build_query([
        'secret' => $secret,
        'response' => $response,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $postData,
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);

    $verify = @file_get_contents($verifyUrl, false, $context);
    $data = is_string($verify) ? json_decode($verify) : null;

    if (!$data || empty($data->success)) {
        finish_response(false, rk_contact_text('captcha_failed'), 422);
    }
}

if (!empty($_POST['website'])) {
    finish_response(false, rk_contact_text('not_sent'), 422);
}

$name = clean_value($_POST['name'] ?? '');
$email = trim((string) ($_POST['email'] ?? ''));
$phone = clean_value($_POST['phone'] ?? '');
$address = clean_value($_POST['address'] ?? '');
$message = trim((string) ($_POST['message'] ?? ''));
$source = clean_value($_POST['source'] ?? ($_SERVER['HTTP_REFERER'] ?? ''));
$language = rk_contact_language($_POST['language'] ?? 'et');

if ($name === '') {
    finish_response(false, rk_contact_text('name_required'), 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    finish_response(false, rk_contact_text('email_invalid'), 400);
}

if ($message === '') {
    finish_response(false, rk_contact_text('message_required'), 400);
}

// Laadime config.php enne CAPTCHA kontrolli, sest võti võib asuda selles failis.
load_config();
verify_recaptcha_if_configured();

$uploadDir = __DIR__ . '/uploads/contact';
$publicUploadPath = 'api/uploads/contact/';
$uploadedFiles = [];
$allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'dwg', 'dxf'];
$maxFileSize = defined('MAX_UPLOAD_SIZE') ? (int) MAX_UPLOAD_SIZE : 10 * 1024 * 1024;

if (!empty($_FILES)) {
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        finish_response(false, rk_contact_text('upload_directory'), 500);
    }

    foreach ($_FILES as $field) {
        $names = is_array($field['name']) ? $field['name'] : [$field['name']];
        $tmpNames = is_array($field['tmp_name']) ? $field['tmp_name'] : [$field['tmp_name']];
        $errors = is_array($field['error']) ? $field['error'] : [$field['error']];
        $sizes = is_array($field['size']) ? $field['size'] : [$field['size']];

        foreach ($names as $index => $originalName) {
            if (($errors[$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if (($errors[$index] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                finish_response(false, rk_contact_text('upload_failed'), 400);
            }

            if ((int) $sizes[$index] > $maxFileSize) {
                finish_response(false, rk_contact_text('file_large'), 400);
            }

            $extension = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions, true)) {
                finish_response(false, rk_contact_text('file_type'), 400);
            }

            $safeName = bin2hex(random_bytes(12)) . '.' . $extension;
            $target = $uploadDir . '/' . $safeName;

            if (!move_uploaded_file($tmpNames[$index], $target)) {
                finish_response(false, rk_contact_text('file_save'), 500);
            }

            $path = $publicUploadPath . $safeName;
            $uploadedFiles[] = [
                'original_name' => basename((string) $originalName),
                'stored_name' => $safeName,
                'path' => $path,
                'size' => (int) $sizes[$index],
            ];
        }
    }
}

try {
    $pdo = db_pdo();
    rk_ensure_contact_language_schema($pdo);

    try {
        $stmt = $pdo->prepare('
            INSERT INTO contacts (name, email, phone, address, message, language, file_path, source, created_at)
            VALUES (:name, :email, :phone, :address, :message, :language, :file_path, :source, NOW())
        ');
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':address' => $address,
            ':message' => $message,
            ':language' => $language,
            ':file_path' => $uploadedFiles ? json_encode($uploadedFiles, JSON_UNESCAPED_UNICODE) : null,
            ':source' => $source,
        ]);
    } catch (Throwable $insertError) {
        $stmt = $pdo->prepare('
            INSERT INTO contacts (name, email, phone, address, message, language, file_path)
            VALUES (:name, :email, :phone, :address, :message, :language, :file_path)
        ');
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':address' => $address,
            ':message' => $message,
            ':language' => $language,
            ':file_path' => $uploadedFiles ? json_encode($uploadedFiles, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }
} catch (Throwable $error) {
    finish_response(false, rk_contact_text('database_failed'), 500, [
        'debug' => defined('APP_DEBUG') && APP_DEBUG ? $error->getMessage() : null,
    ]);
}

send_contact_emails($name, $email, $phone, $address, $message, $uploadedFiles, $language);

finish_response(true, rk_contact_text('success'), 200, [
    'db_saved' => true,
    'attachments' => $uploadedFiles,
]);
