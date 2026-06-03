<?php

require "config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: kontakt.php");
    exit;
}

// 🛑 Honeypot
if (!empty($_POST['website'])) {
    exit("Bot detected");
}

// 🔒 CAPTCHA
$response = $_POST['g-recaptcha-response'] ?? '';

if (empty($response)) {
    exit("Captcha required");
}

$verify = file_get_contents(
    "https://www.google.com/recaptcha/api/siteverify?secret=" . urlencode($recaptchaSecret) . "&response=" . urlencode($response)
);

$data = json_decode($verify);

if (!$data || !$data->success) {
    exit("Captcha failed");
}

$name = $_POST["name"] ?? "";
$email = $_POST["email"] ?? "";
$phone = $_POST["phone"] ?? "";
$address = $_POST["address"] ?? "";
$message = $_POST["message"] ?? "";

$file_paths = [];

$uploadDir = __DIR__ . "/uploads/";
$publicPath = "uploads/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!empty($_FILES["attachments"]["name"][0])) {

    foreach ($_FILES["attachments"]["tmp_name"] as $key => $tmp_name) {

        if ($_FILES["attachments"]["error"][$key] === 0) {

            $ext = pathinfo($_FILES["attachments"]["name"][$key], PATHINFO_EXTENSION);
            $filename = time() . "_" . uniqid() . "." . $ext;

            $target = $uploadDir . $filename;

            if (move_uploaded_file($tmp_name, $target)) {
                $file_paths[] = $publicPath . $filename;
            }

        }
    }

}

$file_json = !empty($file_paths) ? json_encode($file_paths) : null;

$stmt = $conn->prepare("
INSERT INTO contacts (name,email,phone,address,message,file_path)
VALUES (?,?,?,?,?,?)
");

$stmt->bind_param("ssssss",$name,$email,$phone,$address,$message,$file_json);
$stmt->execute();

header("Location: kontakt.php?success=1");
exit;