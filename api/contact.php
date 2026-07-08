<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Lubatud on ainult POST päring.']);
    exit;
}

function wants_json_response(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

    return stripos($accept, 'application/json') !== false || strtolower($requestedWith) === 'xmlhttprequest';
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

    $fallback = '../kontakt.html';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer !== '' && preg_match('#^https?://[^/]+/#', $referer) === 1) {
        $fallback = $referer;
    }

    $separator = str_contains($fallback, '?') ? '&' : '?';
    header('Location: ' . $fallback . $separator . ($success ? 'success=1' : 'error=1'));
    exit;
}

function encode_mime_header(string $value): string
{
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function send_contact_emails(string $name, string $email, string $phone, string $address, string $message, array $uploadedFiles): void
{
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
    $adminMessage .= "Nimi: {$name}\n";
    $adminMessage .= "Email: {$email}\n";
    $adminMessage .= "Telefon: " . ($phone !== '' ? $phone : '-') . "\n";
    $adminMessage .= "Aadress: " . ($address !== '' ? $address : '-') . "\n\n";
    $adminMessage .= "Sõnum:\n{$message}\n";

    if ($attachmentLines) {
        $adminMessage .= "\nLisatud failid:\n" . implode("\n", $attachmentLines) . "\n";
    }

    $adminHeaders = [];
    $adminHeaders[] = 'MIME-Version: 1.0';
    $adminHeaders[] = 'Content-Type: text/plain; charset=UTF-8';
    $adminHeaders[] = 'From: ' . encode_mime_header($fromName) . " <{$fromEmail}>";
    $adminHeaders[] = "Reply-To: {$email}";

    mail(
        $to,
        encode_mime_header($adminSubject),
        $adminMessage,
        implode("\r\n", $adminHeaders)
    );

    $autoSubject = 'Täname päringu eest';
    $autoMessage = "Tere!\n\n";
    $autoMessage .= "Täname päringu eest. Oleme päringu kätte saanud ja vastame esimesel võimalusel.\n\n";
    $autoMessage .= "Lugupidamisega\n";
    $autoMessage .= "RK Meistrid OÜ\n";
    $autoMessage .= "info@renoveerikodu.ee\n";

    $autoHeaders = [];
    $autoHeaders[] = 'MIME-Version: 1.0';
    $autoHeaders[] = 'Content-Type: text/plain; charset=UTF-8';
    $autoHeaders[] = 'From: ' . encode_mime_header($fromName) . " <{$fromEmail}>";
    $autoHeaders[] = "Reply-To: {$fromEmail}";

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

    finish_response(false, 'Serveri andmebaasi seadistus puudub.', 500);
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

    finish_response(false, 'Serveri andmebaasi ühendus puudub.', 500);
}

function verify_recaptcha_if_required(): void
{
    $secret = defined('RECAPTCHA_SECRET') ? (string) RECAPTCHA_SECRET : ($GLOBALS['recaptchaSecret'] ?? '');
    $required = defined('REQUIRE_RECAPTCHA') ? (bool) REQUIRE_RECAPTCHA : $secret !== '';

    if (!$required) {
        return;
    }

    $response = $_POST['g-recaptcha-response'] ?? '';
    if ($response === '' || $secret === '') {
        finish_response(false, 'Captcha kontroll puudub.', 422);
    }

    $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode($secret) . '&response=' . urlencode((string) $response);
    $verify = @file_get_contents($verifyUrl);
    $data = $verify ? json_decode($verify) : null;

    if (!$data || empty($data->success)) {
        finish_response(false, 'Captcha kontroll ebaõnnestus.', 422);
    }
}

if (!empty($_POST['website'])) {
    finish_response(false, 'Päringut ei saadetud.', 422);
}

$name = clean_value($_POST['name'] ?? '');
$email = trim((string) ($_POST['email'] ?? ''));
$phone = clean_value($_POST['phone'] ?? '');
$address = clean_value($_POST['address'] ?? '');
$message = trim((string) ($_POST['message'] ?? ''));
$source = clean_value($_POST['source'] ?? ($_SERVER['HTTP_REFERER'] ?? ''));

if ($name === '') {
    finish_response(false, 'Palun sisesta nimi.', 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    finish_response(false, 'Palun sisesta korrektne email.', 400);
}

if ($message === '') {
    finish_response(false, 'Palun sisesta sõnum.', 400);
}

verify_recaptcha_if_required();

$uploadDir = __DIR__ . '/uploads/contact';
$publicUploadPath = 'api/uploads/contact/';
$uploadedFiles = [];
$adminFilePaths = [];
$allowedExtensions = ['pdf','doc','docx','xls','xlsx','csv','txt','zip','jpg','jpeg','png','webp','heic','dwg','dxf'];
$maxFileSize = defined('MAX_UPLOAD_SIZE') ? (int) MAX_UPLOAD_SIZE : 10 * 1024 * 1024;

if (!empty($_FILES)) {
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        finish_response(false, 'Failide kausta loomine ebaõnnestus.', 500);
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
                finish_response(false, 'Faili üleslaadimine ebaõnnestus.', 400);
            }

            if ((int) $sizes[$index] > $maxFileSize) {
                finish_response(false, 'Üks fail on liiga suur. Maksimaalne suurus on 10 MB.', 400);
            }

            $extension = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions, true)) {
                finish_response(false, 'Failitüüp ei ole lubatud.', 400);
            }

            $safeName = bin2hex(random_bytes(12)) . '.' . $extension;
            $target = $uploadDir . '/' . $safeName;

            if (!move_uploaded_file($tmpNames[$index], $target)) {
                finish_response(false, 'Faili salvestamine ebaõnnestus.', 500);
            }

            $path = $publicUploadPath . $safeName;
            $adminFilePaths[] = $path;
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

    try {
        $stmt = $pdo->prepare('
        INSERT INTO contacts (name, email, phone, address, message, file_path, source, created_at)
        VALUES (:name, :email, :phone, :address, :message, :file_path, :source, NOW())
        ');
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':address' => $address,
            ':message' => $message,
            ':file_path' => $uploadedFiles ? json_encode($uploadedFiles, JSON_UNESCAPED_UNICODE) : null,
            ':source' => $source,
        ]);
    } catch (Throwable $insertError) {
        $stmt = $pdo->prepare('
            INSERT INTO contacts (name, email, phone, address, message, file_path)
            VALUES (:name, :email, :phone, :address, :message, :file_path)
        ');
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':address' => $address,
            ':message' => $message,
            ':file_path' => $uploadedFiles ? json_encode($uploadedFiles, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }
} catch (Throwable $error) {
    finish_response(false, 'Päring jõudis serverisse, aga andmebaasi salvestamine ebaõnnestus.', 500, [
        'debug' => defined('APP_DEBUG') && APP_DEBUG ? $error->getMessage() : null,
    ]);
}

send_contact_emails($name, $email, $phone, $address, $message, $uploadedFiles);

finish_response(true, 'Aitäh! Päring on saadetud ja kinnituskiri saadeti teie emailile.', 200, [
    'db_saved' => true,
    'attachments' => $uploadedFiles,
]);
