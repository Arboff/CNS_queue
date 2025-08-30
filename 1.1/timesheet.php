<?php
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: index.php");
    exit;
}

$timesheetFile = __DIR__ . "/timesheet.txt";
$usersFile = __DIR__ . "/users.json";
$users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Reset timesheet
    if (isset($_POST['reset'])) {
        file_put_contents($timesheetFile, "");
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
        usort($rawEntries, fn($a,$b)=>$b['start']-$a['start']);
    }
    return [$rawEntries,$stats,$usersList];
}

// --- AJAX fetch entries ---
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

// --- Export current view ---
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


list($rawEntries,$stats,$usersList) = getTimesheetEntries();
$filterUser = $_GET['user'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Timesheet Admin Panel</title>
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
    </style>
</head>
<body>
<h2>Timesheet - Admin: <?=htmlspecialchars($_SESSION['username'])?></h2>



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
         <button type="button" onclick="window.location.href='json_parser.php'">Json_Parser</button>
    </div>
    <table id="entriesTable">
        <tr><th>User</th><th>Start</th><th>End</th><th>Duration</th></tr>
    </table>
</div>

<div class="section">
    <h3>Summary Statistics per User</h3>
    <table>
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
</div>

<div class="section">
    <form method="post" onsubmit="return confirm('Reset all timesheet data?');">
        <button type="submit" name="reset" class="reset-btn">Reset Timesheet</button>
    </form>
</div>

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

<script>
function loadEntries(){
    const user = document.getElementById('user').value;
    const start_date = document.getElementById('start_date').value;
    const end_date = document.getElementById('end_date').value;
    fetch(`timesheet.php?action=entries&user=${encodeURIComponent(user)}&start_date=${start_date}&end_date=${end_date}`)
        .then(res=>res.text())
        .then(html=>document.getElementById('entriesTable').innerHTML = "<tr><th>User</th><th>Start</th><th>End</th><th>Duration</th></tr>"+html);
}

document.getElementById('exportBtn').addEventListener('click',()=>{
    const user = document.getElementById('user').value;
    const start_date = document.getElementById('start_date').value;
    const end_date = document.getElementById('end_date').value;
    window.location.href = `timesheet.php?action=export&user=${encodeURIComponent(user)}&start_date=${start_date}&end_date=${end_date}`;
});

setInterval(loadEntries,5000);
window.onload = loadEntries;
</script>
</body>
</html>
