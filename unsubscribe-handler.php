<?php
require "config.php";
header("Content-Type: application/json");

if(empty($_POST["unsubscribe_email"])){
echo json_encode(["success"=>false,"message"=>"Email puudub"]);
exit;
}

$email = trim($_POST["unsubscribe_email"]);

if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
echo json_encode(["success"=>false,"message"=>"Vigane email"]);
exit;
}

/* INSERT unsubscribe */
$stmt = $conn->prepare("
INSERT INTO newsletter_subscribers (email, status, created_at)
VALUES (?, 'unsubscribed', NOW())
");

$stmt->bind_param("s",$email);

if($stmt->execute()){
echo json_encode(["success"=>true]);
}else{
echo json_encode(["success"=>false,"message"=>"DB error"]);
}