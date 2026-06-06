<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Lubatud on ainult POST paring.']);
    exit;
}

function clean_value($value) {
    return trim(filter_var((string) $value, FILTER_SANITIZE_SPECIAL_CHARS));
}

function fail_response($message, $status = 400) {
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if (!empty($_POST['website'])) {
    fail_response('Paringut ei saadetud.', 422);
}

$name = clean_value($_POST['name'] ?? '');
$email = trim((string) ($_POST['email'] ?? ''));
$phone = clean_value($_POST['phone'] ?? '');
$address = clean_value($_POST['address'] ?? '');
$message = trim((string) ($_POST['message'] ?? ''));
$source = clean_value($_POST['source'] ?? ($_SERVER['HTTP_REFERER'] ?? ''));

if ($name === '') {
    fail_response('Palun sisesta nimi.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail_response('Palun sisesta korrektne email.');
}

if ($message === '') {
    fail_response('Palun sisesta sonum.');
}

$uploadDir = __DIR__ . '/uploads/contact';
$uploadedFiles = [];
$allowedExtensions = ['pdf','doc','docx','xls','xlsx','csv','txt','zip','jpg','jpeg','png','webp','heic','dwg','dxf'];
$maxFileSize = 10 * 1024 * 1024;

if (!empty($_FILES)) {
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        fail_response('Failide kausta loomine ebaonnestus.', 500);
    }

    foreach ($_FILES as $field) {
        $names = is_array($field['name']) ? $field['name'] : [$field['name']];
        $tmpNames = is_array($field['tmp_name']) ? $field['tmp_name'] : [$field['tmp_name']];
        $errors = is_array($field['error']) ? $field['error'] : [$field['error']];
        $sizes = is_array($field['size']) ? $field['size'] : [$field['size']];

        foreach ($names as $index => $originalName) {
            if ($errors[$index] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($errors[$index] !== UPLOAD_ERR_OK) {
                fail_response('Faili uleslaadimine ebaonnestus.');
            }

            if ($sizes[$index] > $maxFileSize) {
                fail_response('Uks fail on liiga suur. Maksimaalne suurus on 10 MB.');
            }

            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions, true)) {
                fail_response('Failituup ei ole lubatud.');
            }

            $safeName = bin2hex(random_bytes(12)) . '.' . $extension;
            $target = $uploadDir . '/' . $safeName;

            if (!move_uploaded_file($tmpNames[$index], $target)) {
                fail_response('Faili salvestamine ebaonnestus.', 500);
            }

            $uploadedFiles[] = [
                'original_name' => basename($originalName),
                'stored_name' => $safeName,
                'path' => 'api/uploads/contact/' . $safeName,
                'size' => (int) $sizes[$index],
            ];
        }
    }
}

$dbSaved = false;
$dbError = null;
$configPath = __DIR__ . '/config.php';

if (file_exists($configPath)) {
    require $configPath;

    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare('INSERT INTO contact_requests (name, email, phone, address, message, source, attachments, created_at) VALUES (:name, :email, :phone, :address, :message, :source, :attachments, NOW())');
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':phone' => $phone,
                ':address' => $address,
                ':message' => $message,
                ':source' => $source,
                ':attachments' => json_encode($uploadedFiles, JSON_UNESCAPED_UNICODE),
            ]);
            $dbSaved = true;
        } catch (Throwable $error) {
            $dbError = $error->getMessage();
        }
    }
}

$to = 'info@renoveerikodu.ee';
$subject = 'Uus paring - Renoveeri Kodu';
$bodyLines = [
    'Uus paring kodulehelt',
    '',
    'Nimi: ' . $name,
    'Email: ' . $email,
    'Telefon: ' . ($phone !== '' ? $phone : '-'),
    'Aadress: ' . ($address !== '' ? $address : '-'),
    'Allikas: ' . ($source !== '' ? $source : '-'),
    '',
    'Sonum:',
    $message,
];

if (!empty($uploadedFiles)) {
    $bodyLines[] = '';
    $bodyLines[] = 'Lisatud failid:';
    foreach ($uploadedFiles as $file) {
        $bodyLines[] = $file['original_name'] . ' - ' . $file['path'];
    }
}

$headers = [
    'From: Renoveeri Kodu <no-reply@renoveerikodu.ee>',
    'Reply-To: ' . $name . ' <' . $email . '>',
    'Content-Type: text/plain; charset=UTF-8',
];

$mailSent = @mail($to, $subject, implode("\n", $bodyLines), implode("\r\n", $headers));

if (!$mailSent && !$dbSaved) {
    fail_response('Paring joudis serverisse, aga salvestamine ega e-kiri ei onnestunud.', 500);
}

echo json_encode([
    'success' => true,
    'message' => 'Paring saadetud. Votame sinuga uhendust.',
    'mail_sent' => $mailSent,
    'db_saved' => $dbSaved,
    'db_error' => $dbError,
]);
