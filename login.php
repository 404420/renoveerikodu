<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require "config.php";

/* Kui juba sisse logitud */
if (isset($_SESSION["user_id"])) {
    if ($_SESSION["role"] === "admin") {
        header("Location: admin.php");
    } else {
        header("Location: worker.php");
    }
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    $stmt = $conn->prepare("SELECT id, username, password, role FROM admin_users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user["password"])) {

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["role"] = $user["role"];

        /* SIIN ON SUUNAMINE */
        if ($user["role"] === "admin") {
            header("Location: admin.php");
        } else {
            header("Location: worker.php");
        }
        exit;

    } else {
        $error = "Vale kasutajanimi või parool";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Logi sisse</title>

<style>
body {
    font-family: Arial;
    background:#f4f4f4;
    margin:0;
    padding:20px;
}

.login-box {
    background:white;
    max-width:350px;
    margin:80px auto;
    padding:30px;
    border-radius:8px;
    box-shadow:0 0 15px rgba(0,0,0,0.1);
}

h2 { text-align:center; }

input {
    width:100%;
    padding:12px;
    margin-bottom:15px;
    border:1px solid #ccc;
    border-radius:4px;
    font-size:16px;
}

button {
    width:100%;
    padding:12px;
    background:#111;
    color:white;
    border:none;
    border-radius:4px;
    font-size:16px;
}

.error {
    color:red;
    margin-bottom:10px;
    text-align:center;
}
</style>

</head>
<body>

<div class="login-box">
    <h2>Logi sisse</h2>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="username" placeholder="Kasutajanimi" required>
        <input type="password" name="password" placeholder="Parool" required>
        <button type="submit">Logi sisse</button>
    </form>
</div>

</body>
</html>