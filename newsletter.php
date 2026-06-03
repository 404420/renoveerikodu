<?php
session_start();
require "config.php";

/* kontroll kas sisse logitud */
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

/* ainult admin pääseb */
if ($_SESSION["role"] !== "admin") {
    header("Location: worker.php");
    exit;
}

/* ===========================
   EXPORT CSV (peab olema enne HTML)
=========================== */

if(isset($_GET["export"]) && isset($_GET["status"])){

    $status = $_GET["status"];

    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=newsletter_$status.csv");

    $out = fopen("php://output", "w");

    // UTF8 Excel fix
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($out, ["Email","Status","Liitus","Loobus"]);

    $stmt = $conn->prepare("SELECT * FROM newsletter_subscribers WHERE status=?");
    $stmt->bind_param("s",$status);
    $stmt->execute();
    $res = $stmt->get_result();

    while($row = $res->fetch_assoc()){
        fputcsv($out, [
            $row["email"],
            $row["status"],
            $row["created_at"],
            $row["unsubscribed_at"]
        ]);
    }

    fclose($out);
    exit;
}

/* ===========================
   LAE SUBSCRIBERID
=========================== */

$subs = $conn->query("
SELECT * FROM newsletter_subscribers 
WHERE status='subscribed' 
ORDER BY created_at DESC
");

$unsubs = $conn->query("
SELECT * FROM newsletter_subscribers 
WHERE status='unsubscribed' 
ORDER BY created_at DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Uudiskirjad</title>

<style>
body { font-family: Arial; padding: 30px; }

.topbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:30px;
}

.back-btn {
    background:#5bc0de;
    color:white;
    padding:8px 12px;
    border-radius:4px;
    text-decoration:none;
}

.container {
    display:flex;
    gap:30px;
}

.column {
    flex:1;
    background:#f7f7f7;
    padding:20px;
    border-radius:8px;
}

.email-row {
    padding:10px;
    border-bottom:1px solid #ddd;
}

.export-btn {
    display:inline-block;
    margin-bottom:15px;
    padding:6px 10px;
    background:#2ecc71;
    color:white;
    border-radius:4px;
    text-decoration:none;
}
</style>
</head>

<body>

<div class="topbar">
    <h1>Uudiskirjad</h1>
    <a href="admin.php" class="back-btn">← Tagasi admini</a>
</div>

<div class="container">

<!-- SUBSCRIBERS -->
<div class="column">
<h2>Subscriberid</h2>

<a href="?export=1&status=subscribed" class="export-btn">
Export CSV
</a>
<br>
<div>Kokku: <?= $subs->num_rows ?></div>

<?php if ($subs->num_rows > 0): ?>
<?php while($row = $subs->fetch_assoc()): ?>
<div class="email-row">
<b><?= htmlspecialchars($row["email"]) ?></b><br>
Liitus: <?= $row["created_at"] ?>
</div>
<?php endwhile; ?>
<?php else: ?>
Pole subscriber'eid.
<?php endif; ?>

</div>

<!-- UNSUBSCRIBED -->
<div class="column">
<h2>Unsubscribed</h2>

<a href="?export=1&status=unsubscribed" class="export-btn">
Export CSV
</a>

<div>Kokku: <?= $unsubs->num_rows ?></div>

<?php if ($unsubs->num_rows > 0): ?>
<?php while($row = $unsubs->fetch_assoc()): ?>
<div class="email-row">
<b><?= htmlspecialchars($row["email"]) ?></b><br>
Liitus: <?= $row["created_at"] ?><br>
Loobus: <?= $row["unsubscribed_at"] ?>
</div>
<?php endwhile; ?>
<?php else: ?>
Pole ühtegi.
<?php endif; ?>

</div>

</div>

</body>
</html>