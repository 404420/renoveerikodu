<?php
require "config.php";
header("Content-Type: application/json");

$email = trim($_POST["email"] ?? "");

if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
echo json_encode(["success"=>false,"message"=>"Vigane email"]);
exit;
}

$ip = $_SERVER["REMOTE_ADDR"];

$stmt = $conn->prepare("
INSERT INTO newsletter_events (email,event_type,ip_address)
VALUES (?, 'subscribe', ?)
");

$stmt->bind_param("ss",$email,$ip);
$stmt->execute();

echo json_encode(["success"=>true]);