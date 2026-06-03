<?php
session_start();
require "config.php";

$user_id = $_SESSION["user_id"];

$data = json_decode(file_get_contents("php://input"), true);

$type = $data["event_type"];
$lat = $data["latitude"];
$lng = $data["longitude"];
$acc = $data["accuracy"];

$ip = $_SERVER["REMOTE_ADDR"];
$user_agent = $_SERVER["HTTP_USER_AGENT"];

// 1️⃣ Võta viimane event ENNE kontrolli
$stmt = $conn->prepare("
SELECT event_type 
FROM work_events 
WHERE user_id = ?
ORDER BY event_time DESC 
LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$last = $res->fetch_assoc();
$last_event = $last["event_type"] ?? null;


// 2️⃣ Loogika kontroll
$allowed = false;

if ($type === "start_day" && !$last_event) {
    $allowed = true;
}

if ($type === "start_break" && $last_event === "start_day") {
    $allowed = true;
}

if ($type === "end_break" && $last_event === "start_break") {
    $allowed = true;
}

if ($type === "end_day" && $last_event === "start_day") {
    $allowed = true;
}

if ($type === "end_day" && $last_event === "end_break") {
    $allowed = true;
}

if (!$allowed) {
    die("Lubamatu tegevus");
}


// 3️⃣ INSERT
$stmt = $conn->prepare("
INSERT INTO work_events 
(user_id, event_type, lat, lng, accuracy, ip, user_agent)
VALUES (?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "isddiss",
    $user_id,
    $type,
    $lat,
    $lng,
    $acc,
    $ip,
    $user_agent
);

$stmt->execute();

echo "OK";
