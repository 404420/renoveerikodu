<?php
declare(strict_types=1);

require_once __DIR__ . '/_database.php';
require_once __DIR__ . '/_contact-schema.php';

/**
 * Parketipaigaldaja.ee avalik hinnapäringu vastuvõtt.
 * Ainult usaldatud brauseri päritolu; serveripoolne valideerimine, honeypot ja IP-põhine piirang.
 * Päringud salvestatakse olemasolevasse contacts tabelisse.
 */
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = ['https://parketipaigaldaja.ee', 'https://www.parketipaigaldaja.ee'];
if (!in_array($origin, $allowed, true)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Päringu päritolu ei ole lubatud.']);
    exit;
}
header('Access-Control-Allow-Origin: ' . $origin);
header('Vary: Origin');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function result_json(int $code, bool $success, string $message): never
{
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    result_json(405, false, 'Lubatud on ainult POST-päring.');
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 16000) {
    result_json(413, false, 'Päring on liiga mahukas.');
}
if (($_POST['website'] ?? '') !== '') {
    result_json(422, false, 'Päringut ei saadetud.');
}

function field(string $key, int $max): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        result_json(422, false, 'Vigased päringu andmed.');
    }
    $value = trim($value);
    if (mb_strlen($value, 'UTF-8') > $max) {
        result_json(422, false, 'Mõni väli on liiga pikk.');
    }
    return $value;
}

$name = field('name', 100);
$email = field('email', 180);
$phone = field('phone', 40);
$location = field('location', 150);
$timing = field('timing', 100);
$area = field('area', 20);
$floorType = field('floor_type', 70);
$floorTone = field('floor_tone', 70);
$pattern = field('pattern', 70);
$finish = field('finish', 70);
$design = field('design', 1300);
$notes = field('message', 3000);
$consent = field('consent', 10);
$started = filter_var($_POST['form_started_at'] ?? null, FILTER_VALIDATE_INT);
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $location === '' || $consent !== 'yes') {
    result_json(422, false, 'Palun täida vajalikud kontaktandmed ja kinnita andmete töötlemine.');
}
if ($started === false || (time() - $started) < 2 || (time() - $started) > 86400) {
    result_json(422, false, 'Palun ava päringuvorm uuesti ja proovi veel kord.');
}
$areaNumber = filter_var(str_replace(',', '.', $area), FILTER_VALIDATE_FLOAT);
if ($areaNumber === false || $areaNumber < 1 || $areaNumber > 10000) {
    result_json(422, false, 'Sisesta pindala vahemikus 1 kuni 10 000 m².');
}
// Üks IP ei saa päringusüsteemi üle koormata. Piirang on lisakaitse, mitte autentimine.
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$slot = sys_get_temp_dir() . '/rk-parketi-' . hash('sha256', $ip . ':' . date('Y-m-d'));
$handle = @fopen($slot, 'c+');
if (!$handle || !flock($handle, LOCK_EX)) {
    result_json(503, false, 'Päringute vastuvõtt on ajutiselt häiritud. Palun kirjuta meile e-postiga.');
}
$stored = stream_get_contents($handle);
$limit = json_decode($stored ?: '{}', true) ?: [];
$now = time();
if (($limit['count'] ?? 0) >= 10 || ($now - (int) ($limit['last'] ?? 0)) < 60) {
    flock($handle, LOCK_UN);
    fclose($handle);
    result_json(429, false, 'Liiga palju päringuid. Palun proovi mõne aja pärast uuesti.');
}

$summary = "Allikas: parketipaigaldaja.ee\n"
    . "Pindala: " . $areaNumber . " m²\n"
    . "Põrandakate: " . ($floorType ?: 'täpsustamisel') . "\n"
    . "Toon: " . ($floorTone ?: 'täpsustamisel') . "\n"
    . "Muster: " . ($pattern ?: 'täpsustamisel') . "\n"
    . "Viimistlus: " . ($finish ?: 'täpsustamisel') . "\n"
    . "Soovitud aeg: " . ($timing ?: 'täpsustamisel') . "\n\n"
    . "Disainivalik: " . ($design ?: 'pole lisatud') . "\n\n"
    . "Kliendi kirjeldus:\n" . ($notes ?: 'pole lisatud');
$summary = str_replace(["\r\n", "\r"], "\n", $summary);

try {
    $config = __DIR__ . '/../config.php';
    if (!is_file($config)) {
        throw new RuntimeException('Config puudub.');
    }
    require_once $config;
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
    } elseif (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } else {
        throw new RuntimeException('Andmebaasi ühendus puudub.');
    }
    $pdo = rk_database_utf8mb4($pdo);
    rk_ensure_contact_utf8mb4($pdo);
    try {
        $stmt = $pdo->prepare('INSERT INTO contacts (name, email, phone, address, message, file_path, source, created_at) VALUES (:name, :email, :phone, :address, :message, NULL, :source, NOW())');
        $stmt->execute([':name' => $name, ':email' => $email, ':phone' => $phone, ':address' => $location, ':message' => $summary, ':source' => 'parketipaigaldaja.ee']);
    } catch (PDOException $e) {
        // Vanematel andmebaasidel võib source-veerg puududa.
        $stmt = $pdo->prepare('INSERT INTO contacts (name, email, phone, address, message, file_path) VALUES (:name, :email, :phone, :address, :message, NULL)');
        $stmt->execute([':name' => $name, ':email' => $email, ':phone' => $phone, ':address' => $location, ':message' => $summary]);
    }
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode(['count' => (int) ($limit['count'] ?? 0) + 1, 'last' => $now]));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
} catch (Throwable $e) {
    flock($handle, LOCK_UN);
    fclose($handle);
    error_log('Parketi päringu salvestamise tõrge: ' . get_class($e));
    result_json(500, false, 'Päringu salvestamine ebaõnnestus. Palun kirjuta info@renoveerikodu.ee.');
}

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'From: RK Meistrid <info@renoveerikodu.ee>',
    'Reply-To: ' . $email,
];
@mail('info@renoveerikodu.ee', 'Uus parketitööde hinnapäring', "Nimi: $name\nE-post: $email\nTelefon: $phone\nAsukoht: $location\n\n$summary", implode("\r\n", $headers));
result_json(200, true, 'Aitäh! Sinu hinnapäring on edastatud.');
