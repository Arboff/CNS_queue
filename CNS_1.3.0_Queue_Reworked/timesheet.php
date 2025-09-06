<?php
date_default_timezone_set('Europe/Sofia');

session_start();

if (!isset($_SESSION['username']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: index.php");
    exit;
}

$timesheetFile = __DIR__ . "/timesheet.txt";
$usersFile = __DIR__ . "/users.json";
$unplannedFile = __DIR__ . "/unplanned_breaks.json";

$users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
$unplannedBreaks = file_exists($unplannedFile) ? json_decode(file_get_contents($unplannedFile), true) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Reset timesheet
    if (isset($_POST['reset'])) {
        file_put_contents($timesheetFile, "");
        header("Location: timesheet.php");
        exit;
    }

    // Clear unplanned breaks
    if (isset($_POST['clear_unplanned'])) {
        file_put_contents($unplannedFile, json_encode([], JSON_PRETTY_PRINT));
        header("Location: timesheet.php");
        exit;
    }

    // Clear overrides
    if (isset($_POST['clear_overrides'])) {
        file_put_contents(__DIR__ . "/overrides.json", json_encode([], JSON_PRETTY_PRINT));
        header("Location: timesheet.php");
        exit;
    }

    // Create new user
    if (isset($_POST['create_user'])) {
        $newUser = trim($_POST['new_username']);
        $newPass = $_POST['new_password'];
        $isAdmin = isset($_POST['new_is_admin']);
        if ($newUser && $newPass) {
            $users[$newUser] = ["password"=>$newPass,"is_admin"=>$isAdmin];
            file_put_contents($usersFile,json_encode($users,JSON_PRETTY_PRINT));
            header("Location: timesheet.php");
            exit;
        }
    }

    // Change password
    if (isset($_POST['change_pass'])) {
        $selectedUser = $_POST['select_user'];
        $newPass = $_POST['change_password'];
        if ($selectedUser && $newPass && isset($users[$selectedUser])) {
            $users[$selectedUser]['password'] = $newPass;
            file_put_contents($usersFile,json_encode($users,JSON_PRETTY_PRINT));
            header("Location: timesheet.php");
            exit;
        }
    }

    // Promote to admin
    if (isset($_POST['make_admin'])) {
        $selectedUser = $_POST['select_admin_user'];
        if ($selectedUser && isset($users[$selectedUser])) {
            $users[$selectedUser]['is_admin'] = true;
            file_put_contents($usersFile,json_encode($users,JSON_PRETTY_PRINT));
            header("Location: timesheet.php");
            exit;
        }
    }

    // Demote admin
    if (isset($_POST['demote_admin'])) {
        $selectedUser = $_POST['select_demote_user'];
        if ($selectedUser && isset($users[$selectedUser])) {
            $users[$selectedUser]['is_admin'] = false;
            file_put_contents($usersFile,json_encode($users,JSON_PRETTY_PRINT));
            header("Location: timesheet.php");
            exit;
        }
    }

    // Delete user
    if (isset($_POST['delete_user'])) {
        $selectedUser = $_POST['select_delete_user'];
        if ($selectedUser && isset($users[$selectedUser])) {
            unset($users[$selectedUser]);
            file_put_contents($usersFile,json_encode($users,JSON_PRETTY_PRINT));
            header("Location: timesheet.php");
            exit;
        }
    }
}

// --- Helpers ---
function formatDuration($seconds) {
    $hours = floor($seconds/3600);
    $minutes = floor(($seconds%3600)/60);
    $secs = $seconds%60;
    return sprintf("%02d:%02d:%02d",$hours,$minutes,$secs);
}

function getTimesheetEntries() {
    global $timesheetFile;
    $rawEntries = [];
    $stats = [];
    $usersList = [];

    if (file_exists($timesheetFile)) {
        $lines = file($timesheetFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            list($user,$start,$end,$duration) = explode("|",$line);
            $duration = (int)$duration;
            $start = (int)$start;
            $end = (int)$end;
            $rawEntries[] = ["user"=>$user,"start"=>$start,"end"=>$end,"duration"=>$duration];
            if (!isset($stats[$user])) $stats[$user] = ["count"=>0,"total"=>0];
            $stats[$user]["count"]++;
            $stats[$user]["total"] += $duration;
            if (!in_array($user,$usersList)) $usersList[] = $user;
        }
        usort($rawEntries, fn($a,$b)=>$b['start']-$a['start']); // Newest first
    }
    return [$rawEntries,$stats,$usersList];
}

// --- AJAX fetch timesheet entries ---
if (isset($_GET['action']) && $_GET['action']==='entries') {
    list($rawEntries,,) = getTimesheetEntries();
    $filterUser = $_GET['user'] ?? '';
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    if ($filterUser) $rawEntries = array_filter($rawEntries, fn($e)=>$e['user']===$filterUser);
    if ($startDate) $rawEntries = array_filter($rawEntries, fn($e)=>$e['start']>=strtotime($startDate));
    if ($endDate) $rawEntries = array_filter($rawEntries, fn($e)=>$e['start']<=strtotime($endDate.' 23:59:59'));
    foreach($rawEntries as $entry){
        $startFormatted = date("d-m-Y H:i:s",$entry['start']);
        $endFormatted = date("d-m-Y H:i:s",$entry['end']);
        echo "<tr>
            <td>".htmlspecialchars($entry['user'])."</td>
            <td>$startFormatted</td>
            <td>$endFormatted</td>
            <td>".formatDuration($entry['duration'])."</td>
        </tr>";
    }
    if(empty($rawEntries)) echo "<tr><td colspan='4'>No data available</td></tr>";
    exit;
}

// --- AJAX fetch unplanned breaks ---
if (isset($_GET['action']) && $_GET['action']==='unplanned') {
    $filterUser = $_GET['user'] ?? '';
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    $filtered = $unplannedBreaks;

    if ($filterUser) $filtered = array_filter($filtered, fn($b)=>$b['username']===$filterUser);
    if ($startDate) $filtered = array_filter($filtered, fn($b)=>$b['start'] >= strtotime($startDate));
    if ($endDate) $filtered = array_filter($filtered, fn($b)=>$b['end'] <= strtotime($endDate.' 23:59:59'));

    // Sort newest first
    usort($filtered, fn($a,$b)=>$b['start']-$a['start']);

    foreach($filtered as $b){
        $startFormatted = date("d-m-Y H:i:s",$b['start']);
        $endFormatted = date("d-m-Y H:i:s",$b['end']);
        $durationFormatted = formatDuration($b['duration']);
        echo "<tr>
            <td>".htmlspecialchars($b['username'])."</td>
            <td>$startFormatted</td>
            <td>$endFormatted</td>
            <td>$durationFormatted</td>
        </tr>";
    }
    if(empty($filtered)) echo "<tr><td colspan='4'>No data available</td></tr>";
    exit;
}

// --- Export timesheet ---
if (isset($_GET['action']) && $_GET['action']==='export') {
    list($rawEntries,,) = getTimesheetEntries();
    $filterUser = $_GET['user'] ?? '';
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    if ($filterUser) $rawEntries = array_filter($rawEntries, fn($e)=>$e['user']===$filterUser);
    if ($startDate) $rawEntries = array_filter($rawEntries, fn($e)=>$e['start']>=strtotime($startDate));
    if ($endDate) $rawEntries = array_filter($rawEntries, fn($e)=>$e['start']<=strtotime($endDate.' 23:59:59'));
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="timesheet.txt"');
    foreach($rawEntries as $entry){
        $startFormatted = date("d-m-Y H:i:s",$entry['start']);
        $endFormatted = date("d-m-Y H:i:s",$entry['end']);
        echo "{$entry['user']}|{$startFormatted}|{$endFormatted}|{$entry['duration']}\n";
    }
    exit;
}

// --- Export unplanned breaks ---
if (isset($_GET['action']) && $_GET['action']==='export_unplanned') {
    $filtered = $unplannedBreaks;
    $filterUser = $_GET['user'] ?? '';
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    if ($filterUser) $filtered = array_filter($filtered, fn($b)=>$b['username']===$filterUser);
    if ($startDate) $filtered = array_filter($filtered, fn($b)=>$b['start'] >= strtotime($startDate));
    if ($endDate) $filtered = array_filter($filtered, fn($b)=>$b['end'] <= strtotime($endDate.' 23:59:59'));
    usort($filtered, fn($a,$b)=>$b['start']-$a['start']);
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="unplanned_breaks.txt"');
    foreach($filtered as $b){
        $startFormatted = date("d-m-Y H:i:s",$b['start']);
        $endFormatted = date("d-m-Y H:i:s",$b['end']);
        echo "{$b['username']}|{$startFormatted}|{$endFormatted}|{$b['duration']}\n";
    }
    exit;
}

// --- Load main timesheet data ---
list($rawEntries,$stats,$usersList) = getTimesheetEntries();
$filterUser = $_GET['user'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
<title>CNS Queue Admin Panel</title>
<style>
body{font-family:Arial,sans-serif;background:#f5f6fa;margin:0;padding:20px}
h2{text-align:center;color:#333;margin-bottom:10px}
.section{margin-bottom:40px;background:white;padding:15px 20px;border-radius:10px;box-shadow:0 3px 8px rgba(0,0,0,0.1)}
table{width:100%;border-collapse:collapse;margin-top:10px}
th,td{padding:10px;border:1px solid #ccc;text-align:center}
th{background:#667eea;color:white}
select,input[type=date],input[type=text],input[type=password]{padding:6px 10px;margin:0 5px;border-radius:5px;border:1px solid #ccc}
button{padding:6px 12px;border-radius:5px;border:none;background:#4a90e2;color:white;cursor:pointer;font-weight:bold;margin-left:5px}
button:hover{background:#357abd}
button.reset-btn{background:#e53e3e} 
button.reset-btn:hover{background:#c53030}
.user-management h3{margin-bottom:8px;margin-top:15px;text-align:left;color:#444}
.flex-row{display:flex;flex-wrap:wrap;align-items:center;margin-bottom:8px}
.pagination button{margin:2px;padding:2px 6px;cursor:pointer;}
</style>
</head>
<body>

<h2>Admin Panel - Logged in as: <?=htmlspecialchars($_SESSION['username'])?></h2>

<!-- Recent Chats + Stats -->
<div class="section">
<h3>Recent Chats</h3>
<div class="flex-row">
    <label>User:</label>
    <select id="user">
        <option value="">-- All Users --</option>
        <?php foreach($usersList as $user): ?>
            <option value="<?=htmlspecialchars($user)?>" <?= $filterUser===$user?'selected':'' ?>><?=htmlspecialchars($user)?></option>
        <?php endforeach; ?>
    </select>
    <label>Start:</label><input type="date" id="start_date" value="<?=htmlspecialchars($startDate)?>">
    <label>End:</label><input type="date" id="end_date" value="<?=htmlspecialchars($endDate)?>">
    <button onclick="loadEntries()">Apply</button>
    <button type="button" id="exportBtn">Export View</button>
</div>
<table id="entriesTable">
<tr><th>User</th><th>Start</th><th>End</th><th>Duration</th></tr>
</table>
<div id="entriesPagination" class="pagination"></div>

<h3>Chat Statistics per User</h3>
<table id="statsTable">
<tr><th>User</th><th>Total Chats</th><th>Total Time</th><th>Average Duration</th></tr>
<?php if(!empty($stats)):
    foreach($stats as $user=>$data):
        $avg = $data['count']>0?formatDuration($data['total']/$data['count']):'00:00:00';
?>
<tr>
    <td><?=htmlspecialchars($user)?></td>
    <td><?=$data['count']?></td>
    <td><?=formatDuration($data['total'])?></td>
    <td><?=$avg?></td>
</tr>
<?php endforeach; endif; ?>
</table>

<form method="post" onsubmit="return confirm('Reset all timesheet data?');" style="margin-top:10px;">
    <button type="submit" name="reset" class="reset-btn">Reset Timesheet</button>
</form>
</div>

<!-- Unplanned Breaks -->
<div class="section">
<h3>Unplanned Breaks</h3>
<div class="flex-row">
    <label>User:</label>
    <select id="unplanned_user">
        <option value="">-- All Users --</option>
        <?php foreach($usersList as $user): ?>
            <option value="<?=htmlspecialchars($user)?>"><?=htmlspecialchars($user)?></option>
        <?php endforeach; ?>
    </select>
    <label>Start:</label><input type="date" id="unplanned_start">
    <label>End:</label><input type="date" id="unplanned_end">
    <button onclick="loadUnplanned()">Apply</button>
    <button type="button" id="exportUnplanned">Export Unplanned</button>
</div>
<table id="unplannedTable">
<tr><th>User</th><th>Start</th><th>End</th><th>Duration</th></tr>
</table>
<div id="unplannedPagination" class="pagination"></div>

<form method="post" onsubmit="return confirm('Clear all overrides?');" style="margin-top:10px;">
    <button type="submit" name="clear_overrides" class="reset-btn">Clear All Overrides</button>
</form>

</div>

<!-- Overrides Table -->
<div class="section">
<h3>Overrides History</h3>

<div class="flex-row">
    <label>User (Overrider):</label>
    <select id="override_user">
        <option value="">-- All --</option>
        <?php foreach($usersList as $user): ?>
        <option value="<?=htmlspecialchars($user)?>"><?=htmlspecialchars($user)?></option>
        <?php endforeach; ?>
    </select>
    <label>Skipped User:</label>
    <select id="skipped_user">
        <option value="">-- All --</option>
        <?php foreach($usersList as $user): ?>
        <option value="<?=htmlspecialchars($user)?>"><?=htmlspecialchars($user)?></option>
        <?php endforeach; ?>
    </select>
    <label>Start:</label><input type="date" id="override_start">
    <label>End:</label><input type="date" id="override_end">
    <button onclick="loadOverrides()">Apply</button>
</div>

<table id="overridesTable">
<tr>
    <th>Timestamp</th>
    <th>Overrider</th>
    <th>Skipped User</th>
    <th>Reason</th>
</tr>
</table>
</div>

<script>
function loadOverrides() {
    const overrider = document.getElementById('override_user').value;
    const skipped = document.getElementById('skipped_user').value;
    const start_date = document.getElementById('override_start').value;
    const end_date = document.getElementById('override_end').value;

    fetch('overrides.json')
        .then(res => res.json())
        .then(data => {
            let filtered = data;
            if(overrider) filtered = filtered.filter(d => d.overrider === overrider);
            if(skipped) filtered = filtered.filter(d => d.skipped_user === skipped);
            if(start_date) filtered = filtered.filter(d => d.timestamp >= new Date(start_date).getTime()/1000);
            if(end_date) filtered = filtered.filter(d => d.timestamp <= new Date(end_date + ' 23:59:59').getTime()/1000);

            const tableBody = filtered.map(d => {
                const time = new Date(d.timestamp*1000).toLocaleString();
                return `<tr>
                    <td>${time}</td>
                    <td>${d.overrider}</td>
                    <td>${d.skipped_user}</td>
                    <td>${d.reason}</td>
                </tr>`;
            }).join('');

            document.getElementById('overridesTable').innerHTML = `
                <tr>
                    <th>Timestamp</th>
                    <th>Overrider</th>
                    <th>Skipped User</th>
                    <th>Reason</th>
                </tr>
                ${tableBody || '<tr><td colspan="4">No overrides found</td></tr>'}`;
        });
}

const rowsPerPage = 10;

function paginateTable(tableId, paginationId) {
    const table = document.getElementById(tableId);
    const pagination = document.getElementById(paginationId);
    const rows = Array.from(table.querySelectorAll('tr')).slice(1); // exclude header
    let currentPage = 1;
    const totalPages = Math.ceil(rows.length / rowsPerPage);

    function showPage(page) {
        currentPage = page;
        rows.forEach((row, i) => row.style.display = (i >= (page-1)*rowsPerPage && i < page*rowsPerPage) ? '' : 'none');
        renderPagination();
    }

    function renderPagination() {
        if (totalPages <= 1) {
            pagination.innerHTML = '';
            return;
        }
        let html = '';
        for(let i=1;i<=totalPages;i++){
            html += `<button onclick="showPage${tableId}(${i})" ${i===currentPage?'style="font-weight:bold;"':''}>${i}</button>`;
        }
        pagination.innerHTML = html;
    }

    window['showPage'+tableId] = showPage;
    showPage(1);
}

function loadEntries(){
    const user = document.getElementById('user').value;
    const start_date = document.getElementById('start_date').value;
    const end_date = document.getElementById('end_date').value;
    fetch(`timesheet.php?action=entries&user=${encodeURIComponent(user)}&start_date=${start_date}&end_date=${end_date}`)
        .then(res=>res.text())
        .then(html=>{
            document.getElementById('entriesTable').innerHTML = "<tr><th>User</th><th>Start</th><th>End</th><th>Duration</th></tr>"+html;
            paginateTable('entriesTable','entriesPagination');
        });
}

function loadUnplanned(){
    const user = document.getElementById('unplanned_user').value;
    const start_date = document.getElementById('unplanned_start').value;
    const end_date = document.getElementById('unplanned_end').value;
    fetch(`timesheet.php?action=unplanned&user=${encodeURIComponent(user)}&start_date=${start_date}&end_date=${end_date}`)
        .then(res=>res.text())
        .then(html=>{
            document.getElementById('unplannedTable').innerHTML = "<tr><th>User</th><th>Start</th><th>End</th><th>Duration</th></tr>"+html;
            paginateTable('unplannedTable','unplannedPagination');
        });
}

document.getElementById('exportBtn').addEventListener('click',()=>{
    const user = document.getElementById('user').value;
    const start_date = document.getElementById('start_date').value;
    const end_date = document.getElementById('end_date').value;
    window.location.href = `timesheet.php?action=export&user=${encodeURIComponent(user)}&start_date=${start_date}&end_date=${end_date}`;
});

document.getElementById('exportUnplanned').addEventListener('click',()=>{
    const user = document.getElementById('unplanned_user').value;
    const start_date = document.getElementById('unplanned_start').value;
    const end_date = document.getElementById('unplanned_end').value;
    window.location.href = `timesheet.php?action=export_unplanned&user=${encodeURIComponent(user)}&start_date=${start_date}&end_date=${end_date}`;
});

window.onload = ()=>{
    loadEntries();
    loadUnplanned();
    loadOverrides();
};
</script>

<!-- USER MANAGEMENT -->
<div class="section user-management">
<h2>User Management</h2>
<h3>Create New User</h3>
<form method="post" class="flex-row">
<input type="text" name="new_username" placeholder="Username" required>
<input type="password" name="new_password" placeholder="Password" required>
<label><input type="checkbox" name="new_is_admin"> Admin</label>
<button type="submit" name="create_user">Create User</button>
</form>

<h3>Change Password</h3>
<form method="post" class="flex-row">
<select name="select_user" required>
    <option value="">--Select User--</option>
    <?php foreach($users as $u=>$info): ?><option value="<?=htmlspecialchars($u)?>"><?=htmlspecialchars($u)?></option><?php endforeach; ?>
</select>
<input type="password" name="change_password" placeholder="New Password" required>
<button type="submit" name="change_pass">Change Password</button>
</form>

<h3>Promote to Admin</h3>
<form method="post" class="flex-row">
<select name="select_admin_user" required>
    <option value="">--Select User--</option>
    <?php foreach($users as $u=>$info): if(!$info['is_admin']): ?><option value="<?=htmlspecialchars($u)?>"><?=htmlspecialchars($u)?></option><?php endif; endforeach; ?>
</select>
<button type="submit" name="make_admin">Promote</button>
</form>

<h3>Demote Admin</h3>
<form method="post" class="flex-row">
<select name="select_demote_user" required>
    <option value="">--Select User--</option>
    <?php foreach($users as $u=>$info): if($info['is_admin']): ?><option value="<?=htmlspecialchars($u)?>"><?=htmlspecialchars($u)?></option><?php endif; endforeach; ?>
</select>
<button type="submit" name="demote_admin">Demote</button>
</form>

<h3>Delete User</h3>
<form method="post" class="flex-row" onsubmit="return confirm('Are you sure to delete this user?');">
<select name="select_delete_user" required>
    <option value="">--Select User--</option>
    <?php foreach($users as $u=>$info): ?><option value="<?=htmlspecialchars($u)?>"><?=htmlspecialchars($u)?></option><?php endforeach; ?>
</select>
<button type="submit" name="delete_user">Delete User</button>
</form>
</div>

</body>
</html>
