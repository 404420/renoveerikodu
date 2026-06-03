<?php
session_start();
require "config.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "worker") {
    die("Ligipääs keelatud");
}

$user_id = $_SESSION["user_id"];

/* FUNKTSIOON – arvutab töötunnid */
function calcHours($row){
    global $conn;

    $start = strtotime($row["start_time"]);
    $end   = $row["end_time"] ? strtotime($row["end_time"]) : time();

    // arvuta pauside summa
    $breakSeconds = 0;

    $breaks = $conn->query("
        SELECT *
        FROM work_breaks
        WHERE work_log_id=".$row["id"]
    );

    while($b = $breaks->fetch_assoc()){

        $bs = strtotime($b["break_start"]);
        $be = $b["break_end"] ? strtotime($b["break_end"]) : time();

        $breakSeconds += ($be - $bs);
    }

    $seconds = ($end - $start) - $breakSeconds;

    if ($seconds < 0) $seconds = 0;

    $hours = floor($seconds / 3600);
    $mins  = floor(($seconds % 3600) / 60);

    return $hours."h ".$mins."min";
}

/* ALUSTA TÖÖPÄEV */
if (isset($_POST["start_day"])) {
    $stmt = $conn->prepare("INSERT INTO work_logs (user_id, start_time) VALUES (?, NOW())");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
}

/* PAUS TOGGLE */
if (isset($_POST["toggle_break"])) {

    // leia aktiivne tööpäev
    $activeLog = $conn->query("
        SELECT id 
        FROM work_logs 
        WHERE user_id=$user_id AND end_time IS NULL
        ORDER BY id DESC LIMIT 1
    ")->fetch_assoc();

    if ($activeLog) {

        $logId = $activeLog["id"];

        // mitu pausi juba tehtud?
        $count = $conn->query("
            SELECT COUNT(*) as total
            FROM work_breaks
            WHERE work_log_id=$logId
        ")->fetch_assoc()["total"];

        // kas on aktiivne paus?
        $openBreak = $conn->query("
            SELECT *
            FROM work_breaks
            WHERE work_log_id=$logId
            AND break_end IS NULL
            ORDER BY id DESC LIMIT 1
        ")->fetch_assoc();

        if ($openBreak) {
            // LÕPETA PAUS
            $conn->query("
                UPDATE work_breaks
                SET break_end = NOW()
                WHERE id=".$openBreak["id"]
            );

        } else {

            if ($count >= 5) {
                echo "<script>alert('Maksimaalne pauside arv (5) täis!');</script>";
            } else {
                // ALUSTA PAUS
                $conn->query("
                    INSERT INTO work_breaks (work_log_id, break_start)
                    VALUES ($logId, NOW())
                ");
            }
        }
    }
}

/* LÕPETA TÖÖPÄEV */
if (isset($_POST["end_day"])) {
    $conn->query("
        UPDATE work_logs
        SET end_time = NOW()
        WHERE user_id=$user_id AND end_time IS NULL
        ORDER BY id DESC LIMIT 1
    ");
}

/* LAE AKTIIVNE TÖÖPÄEV */
$stmt = $conn->prepare("
    SELECT *
    FROM work_logs
    WHERE user_id=? AND end_time IS NULL
    ORDER BY id DESC LIMIT 1
");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$active = $stmt->get_result()->fetch_assoc();

/* LAE TÖÖAJALUGU */
$stmt = $conn->prepare("
SELECT *
FROM work_logs
WHERE user_id=?
ORDER BY start_time DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$logs = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Tööaja logimine</title>

<style>
body {
    font-family: Arial;
    background:#f4f6f9;
    padding:30px;
}

.header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:30px;
}

.logout {
    padding:8px 15px;
    background:#d9534f;
    color:white;
    text-decoration:none;
    border-radius:5px;
    font-weight:bold;
}

.card {
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 3px 10px rgba(0,0,0,0.1);
    margin-bottom:25px;
}

.live {
    background:#ffecec;
    border-left:6px solid red;
}

button {
    padding:10px 18px;
    border:none;
    border-radius:6px;
    cursor:pointer;
    font-weight:bold;
    margin-bottom:10px;
}

.btn-start { background:#28a745; color:white; }
.btn-break { background:#ffc107; }
.btn-end { background:#dc3545; color:white; }

table {
    width:100%;
    border-collapse:collapse;
    background:white;
}

th, td {
    padding:10px;
    border:1px solid #ddd;
    text-align:center;
}

th {
    background:#f2f2f2;
}
</style>
</head>
<body>

<div class="header">
    <h2>Tööaja logimine</h2>
    <a href="logout.php" class="logout">Logi välja</a>
</div>

<?php if ($active): ?>
<div class="card live">
    <h3 style="color:red;">LIVE tööpäev</h3>

    <p><b>Algus:</b> 
        <span id="startTime" data-time="<?= $active["start_time"] ?>">
            <?= date("H:i:s", strtotime($active["start_time"])) ?>
        </span>
    </p>

    <?php if ($active["break_start"] && !$active["break_end"]): ?>
        <p style="color:red;"><b>Pausil alates:</b> <?= date("H:i:s", strtotime($active["break_start"])) ?></p>
    <?php endif; ?>

    <p><b>Töötatud:</b> <span id="workedTime"></span></p>
</div>
<?php endif; ?>

<div class="card">

<form method="POST">
<?php if (!$active): ?>
    <button class="btn-start" name="start_day">Alusta tööpäev</button>
<?php else: ?>

    <?php 
        $onBreak = ($active["break_start"] && !$active["break_end"]);
    ?>

    <button class="btn-break" name="toggle_break">
        <?= $onBreak ? "Lõpeta paus" : "Alusta paus" ?>
    </button>

    <br>

    <button class="btn-end" name="end_day">Lõpeta tööpäev</button>

<?php endif; ?>
</form>

</div>

<h3>Tööaja ajalugu</h3>

<table>
<tr>
<th>Kuupäev</th>
<th>Algus</th>
<th>Paus algus</th>
<th>Paus lõpp</th>
<th>Lõpp</th>
<th>Tunnid</th>
</tr>

<?php while ($row = $logs->fetch_assoc()): ?>
<tr>
<td><?= date("d.m.Y", strtotime($row["start_time"])) ?></td>
<td><?= date("H:i", strtotime($row["start_time"])) ?></td>

<td><?= $row["break_start"] ? date("H:i", strtotime($row["break_start"])) : "-" ?></td>

<td><?= $row["break_end"] ? date("H:i", strtotime($row["break_end"])) : "-" ?></td>

<td><?= $row["end_time"]
    ? date("H:i", strtotime($row["end_time"]))
    : "<span style='color:red;'>Pooleli</span>" ?></td>

<td><?= calcHours($row) ?></td>
</tr>
<?php endwhile; ?>

</table>

<script>
let pauseStart = <?= ($active && $active["break_start"] && !$active["break_end"]) 
    ? '"'.$active["break_start"].'"' 
    : "null" ?>;

let totalPaused = 0;

<?php
// kui paus on juba lõppenud, arvutame serveris pauside summa
if ($active && $active["break_start"] && $active["break_end"]) {
    $pausedSeconds = strtotime($active["break_end"]) - strtotime($active["break_start"]);
} else {
    $pausedSeconds = 0;
}
?>

totalPaused = <?= $pausedSeconds ?>;

function updateLiveTime() {

    const startEl = document.getElementById("startTime");
    if (!startEl) return;

    const start = new Date(startEl.dataset.time);
    const now = new Date();

    let diff = Math.floor((now - start) / 1000);

    // kui pausil, arvesta paus kuni praeguse hetkeni
    if (pauseStart) {
        const pauseDate = new Date(pauseStart);
        diff -= Math.floor((now - pauseDate) / 1000);
    }

    diff -= totalPaused;

    if (diff < 0) diff = 0;

    const h = Math.floor(diff / 3600);
    const m = Math.floor((diff % 3600) / 60);
    const s = diff % 60;

    document.getElementById("workedTime").innerText =
        h + "h " + m + "m " + s + "s";
}

setInterval(updateLiveTime, 1000);
updateLiveTime();
</script>


</body>
</html>
