<?php
require "config.php";

$email = $_GET["email"] ?? "";

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Vigane link");
}

$stmt = $conn->prepare("
UPDATE newsletter_subscribers 
SET status='unsubscribed', unsubscribed_at=NOW()
WHERE email=?
");

$stmt->bind_param("s", $email);
$stmt->execute();

echo "Oled edukalt uudiskirjadest loobunud.";