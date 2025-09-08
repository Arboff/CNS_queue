<?php
session_start();
if (!isset($_SESSION['username']) || !$_SESSION['is_admin']) {
    header("Location: index.php");
    exit;
}

// Load JSON data
$unplannedBreaks = json_decode(file_get_contents(__DIR__ . '/Unplanned_breaks.json'), true);
$overrides       = json_decode(file_get_contents(__DIR__ . '/overrides.json'), true);
$users           = json_decode(file_get_contents(__DIR__ . '/Users.json'), true);
$userData        = json_decode(file_get_contents(__DIR__ . '/users_data.json'), true);

// Prepare Unplanned Breaks data
$breaksCount = [];
foreach ($unplannedBreaks as $break) {
    $user = $break['username'];
    if (!isset($breaksCount[$user])) $breaksCount[$user] = 0;
    $breaksCount[$user] += $break['duration'];
}

// Prepare Overrides data
$overriderCount = [];
$skippedCount   = [];
foreach ($overrides as $o) {
    $overrider = $o['overrider'];
    $skipped  = $o['skipped_user'];
    if (!isset($overriderCount[$overrider])) $overriderCount[$overrider] = 0;
    if (!isset($skippedCount[$skipped])) $skippedCount[$skipped] = 0;
    $overriderCount[$overrider]++;
    $skippedCount[$skipped]++;
}

// Users for admin status
$registeredUsers = [];
foreach ($users as $username => $info) {
    $registeredUsers[] = ['username' => $username, 'admin' => $info['is_admin']];
}

// Users for break schedule colors
$userColors = [];
foreach ($userData as $username => $info) {
    $userColors[] = ['username' => $username, 'color' => $info['color']];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Graphs Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* Global Styles */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f4f6f8;
    color: #333;
    margin: 0;
    padding: 20px;
}

/* Container */
.container {
    max-width: 1200px;
    margin: auto;
}

/* Headings */
h1 {
    text-align: center;
    color: #2c3e50;
    margin-bottom: 40px;
    font-size: 28px;
}
h2 {
    margin-top: 0;
    color: #34495e;
    font-size: 20px;
    margin-bottom: 15px;
}

/* Charts Grid */
.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-bottom: 40px;
}
.chart-card {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    display: flex;
    flex-direction: column;
    align-items: center;
}
.chart-card h3 {
    margin-bottom: 15px;
    color: #34495e;
    font-size: 18px;
    text-align: center;
}
.chart-card canvas {
    max-width: 100%;
    height: 250px;
}

/* Tables Grid */
.tables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 30px;
}

/* Table Card */
.table-card {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.08);
}
.table-card h2 {
    margin-top: 0;
}

/* Tables */
table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    margin-top: 10px;
}
th, td {
    padding: 12px 15px;
    text-align: left;
    font-size: 14px;
}
th {
    background: #ecf0f1;
    color: #2c3e50;
    font-weight: 600;
}
tr:nth-child(even) { background: #f9f9f9; }
tr:hover { background: #e1f0ff; }

/* Color boxes */
.color-box {
    width: 22px;
    height: 22px;
    display: inline-block;
    border-radius: 4px;
    margin-right: 8px;
    vertical-align: middle;
    border: 1px solid #ccc;
}

/* Responsive */
@media (max-width: 768px) {
    .chart-card canvas { height: 200px; }
    th, td { font-size: 13px; padding: 10px; }
}
</style>
</head>
<body>
<div class="container">
    <h1>Graphs Dashboard</h1>

    <!-- Charts Grid -->
    <div class="charts-grid">
        <div class="chart-card">
            <h3>Unplanned Breaks by User</h3>
            <canvas id="breaksChart"></canvas>
        </div>
        <div class="chart-card">
            <h3>Overrides by Overrider</h3>
            <canvas id="overridersChart"></canvas>
        </div>
        <div class="chart-card">
            <h3>Overrides by Skipped User</h3>
            <canvas id="skippedChart"></canvas>
        </div>
    </div>

    <!-- Tables Grid -->
    <div class="tables-grid">
        <div class="table-card">
            <h2>Registered Users (Admin Status)</h2>
            <table>
                <tr><th>Username</th><th>Admin</th></tr>
                <?php foreach($registeredUsers as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['username']) ?></td>
                        <td><?= $u['admin'] ? 'Yes' : 'No' ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="table-card">
            <h2>Break Schedule Colors</h2>
            <table>
                <tr><th>Username</th><th>Color</th></tr>
                <?php foreach($userColors as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['username']) ?></td>
                        <td><span class="color-box" style="background: <?= htmlspecialchars($u['color']) ?>;"></span><?= htmlspecialchars($u['color']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>

<script>
// Helper function to extract labels & values
function chartData(obj) {
    return { labels: Object.keys(obj), data: Object.values(obj) };
}

// Charts Data
const breaksData = chartData(<?= json_encode($breaksCount) ?>);
const overridersData = chartData(<?= json_encode($overriderCount) ?>);
const skippedData = chartData(<?= json_encode($skippedCount) ?>);

// Generate random colors
function randomColors(n) {
    return Array.from({length:n}, ()=>`hsl(${Math.random()*360},70%,60%)`);
}

// Generate Charts
new Chart(document.getElementById('breaksChart').getContext('2d'), {
    type:'pie',
    data:{ labels:breaksData.labels, datasets:[{data:breaksData.data, backgroundColor: randomColors(breaksData.labels.length)}] },
    options:{ responsive:true }
});

new Chart(document.getElementById('overridersChart').getContext('2d'), {
    type:'pie',
    data:{ labels:overridersData.labels, datasets:[{data:overridersData.data, backgroundColor: randomColors(overridersData.labels.length)}] },
    options:{ responsive:true }
});

new Chart(document.getElementById('skippedChart').getContext('2d'), {
    type:'pie',
    data:{ labels:skippedData.labels, datasets:[{data:skippedData.data, backgroundColor: randomColors(skippedData.labels.length)}] },
    options:{ responsive:true }
});
</script>
</body>
</html>
