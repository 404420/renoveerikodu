<?php
require "config.php";

/* ainult admin pääseb */
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "admin") {
    header("Location: worker.php");
    exit;
}

/* lae töölogid koos kasutaja nimega */
$result = $conn->query("
    SELECT w.*, u.username
    FROM work_logs w
    JOIN admin_users u ON w.user_id = u.id
    ORDER BY w.start_time DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Töölised</title>

<style>
body { font-family: Arial; padding:30px; }
table { border-collapse: collapse; width:100%; }
th, td { border:1px solid #ccc; padding:8px; }
th { background:#f2f2f2; }

.topbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}

.logout {
    background:#d9534f;
    color:white;
    padding:8px 12px;
    border-radius:4px;
    text-decoration:none;
}

.back {
    margin-right:10px;
}
</style>
</head>

<body>

<div class="topbar">
    <h2>Tööliste tööajalugu</h2>

    <div>
        <a href="admin.php" class="back">← Tagasi päringutele</a>
        <a href="logout.php" class="logout">Logi välja</a>
    </div>
</div>

<table>
<tr>
<th>Tööline</th>
<th>Alustas</th>
<th>Lõpetas</th>
<th>Asukoht</th>
</tr>

<?php while($row = $result->fetch_assoc()): ?>
<tr>

<td><?= htmlspecialchars($row["username"]) ?></td>

<td>
<?= date("d.m.Y H:i", strtotime($row["start_time"])) ?>
</td>

<td>
<?= $row["end_time"]
    ? date("d.m.Y H:i", strtotime($row["end_time"]))
    : "<b style='color:red;'>Pooleli</b>" ?>
</td>

<td>
<?php if ($row["start_lat"]): ?>
<a target="_blank"
   href="https://maps.google.com/?q=<?= $row["start_lat"] ?>,<?= $row["start_lng"] ?>">
   Vaata kaarti
</a>
<?php else: ?>
-
<?php endif; ?>
</td>

</tr>
<?php endwhile; ?>

</table>

</body>
</html>
