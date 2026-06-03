<?php
require "config.php";

// 🔒 ainult POST lubatud
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

// 📩 input
$email = trim($_POST["email"] ?? "");
$consent = isset($_POST["consent"]);

// ❌ valideerimine
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: index.php?subscribed=error");
    exit;
}

if (!$consent) {
    header("Location: index.php?subscribed=consent");
    exit;
}

// 💾 salvesta või uuenda subscriber
$stmt = $conn->prepare("
INSERT INTO newsletter_subscribers (email, status, created_at)
VALUES (?, 'subscribed', NOW())
ON DUPLICATE KEY UPDATE 
    status='subscribed',
    created_at=NOW(),
    unsubscribed_at=NULL
");

$stmt->bind_param("s", $email);

if ($stmt->execute()) {
    // ✅ success redirect
    header("Location: index.php?subscribed=1");
    exit;
} else {
    // ❌ DB error
    header("Location: index.php?subscribed=error");
    exit;
}
