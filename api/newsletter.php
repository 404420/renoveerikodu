<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../admin/_bootstrap.php';

function newsletter_response(bool $success, string $message, int $status = 200, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    newsletter_response(false, 'Lubatud on ainult POST paring.', 405);
}

$action = trim((string) ($_POST['action'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? $_POST['unsubscribe_email'] ?? '')));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    newsletter_response(false, 'Palun sisesta korrektne email.', 400);
}

if ($action === 'subscribe' && empty($_POST['consent'])) {
    newsletter_response(false, 'Uudiskirjaga liitumiseks on vaja noustuda uudiskirjade saamisega.', 422);
}

if (!in_array($action, ['subscribe', 'unsubscribe'], true)) {
    newsletter_response(false, 'Tundmatu tegevus.', 400);
}

$pdo = admin_db();
ensure_newsletter_subscribers_table($pdo);

$source = (string) ($_SERVER['HTTP_REFERER'] ?? '');
$existing = $pdo->prepare('SELECT id FROM newsletter_subscribers WHERE email = :email ORDER BY id DESC LIMIT 1');
$existing->execute([':email' => $email]);
$subscriber = $existing->fetch();

if ($action === 'subscribe') {
    if ($subscriber) {
        $stmt = $pdo->prepare("
            UPDATE newsletter_subscribers
            SET status = 'subscribed',
                subscribed_at = NOW(),
                unsubscribed_at = NULL,
                source = :source
            WHERE id = :id
        ");
        $stmt->execute([
            ':id' => $subscriber['id'],
            ':source' => $source,
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO newsletter_subscribers (email, status, subscribed_at, unsubscribed_at, source)
            VALUES (:email, 'subscribed', NOW(), NULL, :source)
        ");
        $stmt->execute([
            ':email' => $email,
            ':source' => $source,
        ]);
    }

    newsletter_response(true, 'Liitusid edukalt uudiskirjaga.');
}

if ($subscriber) {
    $stmt = $pdo->prepare("
        UPDATE newsletter_subscribers
        SET status = 'unsubscribed',
            unsubscribed_at = NOW(),
            source = :source
        WHERE id = :id
    ");
    $stmt->execute([
        ':id' => $subscriber['id'],
        ':source' => $source,
    ]);
} else {
    $stmt = $pdo->prepare("
        INSERT INTO newsletter_subscribers (email, status, subscribed_at, unsubscribed_at, source)
        VALUES (:email, 'unsubscribed', NULL, NOW(), :source)
    ");
    $stmt->execute([
        ':email' => $email,
        ':source' => $source,
    ]);
}

newsletter_response(true, 'Oled uudiskirjast loobunud.');
