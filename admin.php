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
   KUSTUTA KÕIK PÄRINGUD
=========================== */
if (isset($_POST["delete_all"])) {

    $resultFiles = $conn->query("SELECT file_path FROM contacts");

    while ($row = $resultFiles->fetch_assoc()) {

        if (!empty($row["file_path"])) {

            $files = json_decode($row["file_path"], true);

            if (is_array($files)) {
                foreach ($files as $file) {
                    if (file_exists(__DIR__ . "/" . $file)) {
                        unlink(__DIR__ . "/" . $file);
                    }
                }
            } else {
                if (file_exists(__DIR__ . "/" . $row["file_path"])) {
                    unlink(__DIR__ . "/" . $row["file_path"]);
                }
            }
        }
    }

    $conn->query("TRUNCATE TABLE contacts");

    header("Location: admin.php");
    exit;
}

/* ===========================
   LAE PÄRINGUD
=========================== */
$result = $conn->query("SELECT * FROM contacts ORDER BY created_at DESC");


/* ===========================
   TÄNANE TÖÖPÄEVA ÜLEVAADE
=========================== */

$today = date("Y-m-d");

$workQuery = "
SELECT 
    u.id,
    u.username,

    MAX(CASE WHEN e.event_type='start_day' THEN e.event_time END) AS start_day_time,
    MAX(CASE WHEN e.event_type='start_break' THEN e.event_time END) AS start_break_time,
    MAX(CASE WHEN e.event_type='end_break' THEN e.event_time END) AS end_break_time,
    MAX(CASE WHEN e.event_type='end_day' THEN e.event_time END) AS end_day_time,

    MAX(CASE WHEN e.event_type='start_day' THEN CONCAT(e.lat, ',', e.lng) END) AS start_day_loc,
    MAX(CASE WHEN e.event_type='start_break' THEN CONCAT(e.lat, ',', e.lng) END) AS start_break_loc,
    MAX(CASE WHEN e.event_type='end_break' THEN CONCAT(e.lat, ',', e.lng) END) AS end_break_loc,
    MAX(CASE WHEN e.event_type='end_day' THEN CONCAT(e.lat, ',', e.lng) END) AS end_day_loc

FROM users u
LEFT JOIN work_events e 
    ON u.id = e.user_id 
    AND DATE(e.event_time) = '$today'

WHERE u.role = 'worker'

GROUP BY u.id
ORDER BY u.username ASC
";

$workResult = $conn->query($workQuery);

if (!$workResult) {
    die("SQL error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Admin - Päringud</title>

<style>
body { font-family: Arial; padding: 30px; }
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #ccc; padding: 8px; vertical-align: top; }
th { background: #f2f2f2; }
a { color: blue; text-decoration:none; }
a:hover { text-decoration:underline; }

.topbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}

.button-delete {
    background:#f0ad4e;
    color:white;
    border:none;
    padding:8px 12px;
    border-radius:4px;
    cursor:pointer;
    margin-right:10px;
}

.button-delete:hover {
    background:#ec971f;
}

.logout {
    text-decoration:none;
    background:#d9534f;
    color:white;
    padding:8px 12px;
    border-radius:4px;
}

.logout:hover {
    background:#c9302c;
}

.preview-img {
    max-width:120px;
    max-height:120px;
    display:block;
    margin-bottom:6px;
    border:1px solid #ccc;
}

.work-section {
    margin-top:60px;
}

.status-open { color:green; font-weight:bold; }
.status-break { color:orange; font-weight:bold; }
.status-closed { color:red; font-weight:bold; }
/* Uudiskirja nupp */
.btn-newsletter {
    background: #2ecc71;
    color: white;
    padding: 8px 12px;
    border-radius: 4px;
    text-decoration: none;
    margin-right: 10px;
}

.btn-newsletter:hover {
    background: #27ae60;
}
</style>

</head>
<body>

<div class="topbar">
    <h2>Päringud</h2>



    <div>
        <a href="newsletter.php" class="btn btn-newsletter">Uudiskirjad</a>
        <a href="workers.php" style="
            background:#5bc0de;
            color:white;
            padding:8px 12px;
            border-radius:4px;
            text-decoration:none;
            margin-right:10px;
        ">
            Töölised
        </a>

        <form method="POST" style="display:inline;"
              onsubmit="return confirm('Kas oled kindel, et soovid kõik päringud kustutada?');">
            <button type="submit" name="delete_all" class="button-delete">
                Kustuta kõik
            </button>
        </form>

        <a href="logout.php" class="logout">Logi välja</a>
    </div>
</div>

<table>
<tr>
<th>ID</th>
<th>Nimi</th>
<th>Email</th>
<th>Telefon</th>
<th>Aadress</th>
<th>Sõnum</th>
<th>Failid</th>
<th>Kuupäev</th>
</tr>

<?php while($row = $result->fetch_assoc()): ?>
<tr>

<td><?= $row["id"] ?></td>
<td><?= htmlspecialchars($row["name"]) ?></td>
<td><?= htmlspecialchars($row["email"]) ?></td>
<td><?= htmlspecialchars($row["phone"]) ?></td>
<td><?= htmlspecialchars($row["address"]) ?></td>
<td><?= nl2br(htmlspecialchars($row["message"])) ?></td>

<td>
<?php
if (!empty($row["file_path"])) {

    $files = json_decode($row["file_path"], true);

    if (is_array($files)) {
        foreach ($files as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $imageExtensions = ['jpg','jpeg','png','gif','webp'];

            if (in_array($ext, $imageExtensions)) {
                echo '<a href="'.htmlspecialchars($file).'" target="_blank">';
                echo '<img src="'.htmlspecialchars($file).'" class="preview-img">';
                echo '</a>';
            } else {
                echo '<a href="'.htmlspecialchars($file).'" target="_blank">Ava fail</a><br>';
            }
        }
    } else {
        echo '<a href="'.htmlspecialchars($row["file_path"]).'" target="_blank">Ava fail</a>';
    }

} else {
    echo "-";
}
?>
</td>

<td><?= $row["created_at"] ?></td>

</tr>
<?php endwhile; ?>
</table>


<!-- ===========================
     TÖÖPÄEVA ÜLEVAADE
=========================== -->

<div class="work-section">
<h2>Tänane tööpäeva ülevaade (<?= $today ?>)</h2>

<table>
<tr>
<th>Tööline</th>
<th>Alustas tööpäeva</th>
<th>Alustas pausi</th>
<th>Lõpetas pausi</th>
<th>Lõpetas tööpäeva</th>
<th>Staatus</th>
</tr>

<?php while($w = $workResult->fetch_assoc()): ?>
<tr>

<td><?= htmlspecialchars($w["username"]) ?></td>

<td>
<?php
if ($w["start_day_time"]) {
    echo $w["start_day_time"] . "<br>";
    echo "<a target='_blank' href='https://maps.google.com/?q={$w["start_day_loc"]}'>Kaardil</a>";
} else echo "-";
?>
</td>

<td>
<?php
if ($w["start_break_time"]) {
    echo $w["start_break_time"] . "<br>";
    echo "<a target='_blank' href='https://maps.google.com/?q={$w["start_break_loc"]}'>Kaardil</a>";
} else echo "-";
?>
</td>

<td>
<?php
if ($w["end_break_time"]) {
    echo $w["end_break_time"] . "<br>";
    echo "<a target='_blank' href='https://maps.google.com/?q={$w["end_break_loc"]}'>Kaardil</a>";
} else echo "-";
?>
</td>

<td>
<?php
if ($w["end_day_time"]) {
    echo $w["end_day_time"] . "<br>";
    echo "<a target='_blank' href='https://maps.google.com/?q={$w["end_day_loc"]}'>Kaardil</a>";
} else echo "-";
?>
</td>

<td>
<?php
if (!$w["start_day_time"]) echo "-";
elseif ($w["start_day_time"] && !$w["end_day_time"] && !$w["start_break_time"]) 
    echo "<span class='status-open'>Tööl</span>";
elseif ($w["start_break_time"] && !$w["end_break_time"]) 
    echo "<span class='status-break'>Pausil</span>";
elseif ($w["end_day_time"]) 
    echo "<span class='status-closed'>Lõpetanud</span>";
?>
</td>

</tr>
<?php endwhile; ?>
</table>
</div>

</body>
</html>
