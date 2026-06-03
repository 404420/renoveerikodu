<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = new mysqli(
    "localhost",
    "suurvali_admin",
    "valipalveluhannes23",
    "suurvali_renoveerikodu"
);

if ($conn->connect_error) {
    die("DB ERROR: " . $conn->connect_error);
}

date_default_timezone_set("Europe/Tallinn");
// 🔒 reCAPTCHA secret key
$recaptchaSecret = "6LemXdosAAAAAIZgG2IbT8m18JxYATZEjF6bVU3r";