<?php
session_start();
if (!isset($_SESSION['username'])) { header("Location: index.php"); exit; }

$feedbackFile = __DIR__ . '/feedbacks.json';
if (!file_exists($feedbackFile)) file_put_contents($feedbackFile, json_encode([], JSON_PRETTY_PRINT));
$data = json_decode(file_get_contents($feedbackFile), true);
$isAdmin = $_SESSION['is_admin'] ?? false;

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submitFeedback'])) {
    $from       = $_SESSION['username'];
    $agentId    = strtoupper(trim($_POST['agent_id']));
    $accountNum = trim($_POST['account_number']);
    $reason     = trim($_POST['reason']);
    $ack        = isset($_POST['acknowledge']);

    if (!preg_match('/^\d{12}$/', $accountNum)) {
        $_SESSION['feedback_message'] = "❌ Malformed Account Number. Must be exactly 12 digits.";
        header("Location: feedback.php"); exit;
    }

    if ($agentId && $accountNum && $reason && $ack) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $ref = '';
        for ($i = 0; $i < 6; $i++) $ref .= $chars[random_int(0, strlen($chars)-1)];
        $entry = [
            "ref"=>$ref,
            "from"=>$from,
            "agent_id"=>$agentId,
            "account_num"=>$accountNum,
            "reason"=>$reason,
            "date"=>date("Y-m-d H:i:s"),
            "status"=>"Received"
        ];
        $data[] = $entry;
        file_put_contents($feedbackFile, json_encode($data, JSON_PRETTY_PRINT));
        $_SESSION['feedback_message'] = "✅ Feedback submitted. Your reference is <strong>{$ref}</strong>";
        header("Location: feedback.php"); exit;
    } else {
        $_SESSION['feedback_message'] = "❌ All fields are mandatory and acknowledgment must be checked.";
        header("Location: feedback.php"); exit;
    }
}

// Non-admin status check
$statusCheck = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkStatus'])) {
    $ref = trim($_POST['ref_check']);
    $found = array_filter($data, fn($f) => $f['ref'] === $ref);
    if ($found) {
        $feedback = array_values($found)[0];
        $statusCheck = $feedback['status']==="Provided" 
            ? "<span class='status provided'>Provided</span>" 
            : "<span class='status received'>Received</span>";
    } else {
        $statusCheck = "<span class='status unknown'>Not Found</span>";
    }
}

// Admin filters
$filters = [
    'ref'=>$_GET['ref']??'',
    'from'=>$_GET['from']??'',
    'agent_id'=>$_GET['agent_id']??'',
    'account_num'=>$_GET['account_num']??'',
    'status'=>$_GET['status']??'',
    'date_from'=>$_GET['date_from']??'',
    'date_to'=>$_GET['date_to']??'',
];

$filtered = $data;
if($isAdmin){
    $filtered = array_filter($data,function($f) use($filters){
        $f_ts = strtotime($f['date']);
        if($filters['ref'] && stripos($f['ref'],$filters['ref'])===false) return false;
        if($filters['from'] && $f['from']!==$filters['from']) return false;
        if($filters['agent_id'] && strcasecmp($f['agent_id'],$filters['agent_id'])!==0) return false;
        if($filters['account_num'] && $f['account_num']!==$filters['account_num']) return false;
        if($filters['status'] && $f['status']!==$filters['status']) return false;
        if($filters['date_from'] && $f_ts < strtotime($filters['date_from'])) return false;
        if($filters['date_to'] && $f_ts > strtotime($filters['date_to'].' 23:59:59')) return false;
        return true;
    });
}

// Admin bulk actions
if($isAdmin && $_SERVER['REQUEST_METHOD']==='POST'){
    $selectedRefs = $_POST['selected'] ?? [];
    $action = $_POST['bulk_action'] ?? '';

    switch($action){
        case 'mark_selected_provided':
            foreach($data as &$f) if(in_array($f['ref'],$selectedRefs)) $f['status']="Provided";
            unset($f);
            break;

        case 'delete_selected':
            $data = array_filter($data, fn($f)=> !in_array($f['ref'],$selectedRefs));
            break;

        case 'delete_all':
            if(isset($_POST['confirm_delete_all'])) $data=[];
            break;

        case 'mark_filtered_provided':
            $filteredRefs = array_column($filtered,'ref');
            foreach($data as &$f) if(in_array($f['ref'],$filteredRefs)) $f['status']="Provided";
            unset($f);
            break;

        case 'delete_filtered':
            $filteredRefs = array_column($filtered,'ref');
            $data = array_filter($data, fn($f)=> !in_array($f['ref'],$filteredRefs));
            break;

        case 'export_filtered':
    $filtered_to_export = $filtered ?? [];
    exportCSV($filtered_to_export, "feedback_filtered.csv");
    break;

        case 'export_selected':
            exportCSV(array_filter($data, fn($f)=> in_array($f['ref'],$selectedRefs)), "feedback_selected.csv");
            break;

        case 'export_all':
            exportCSV($data,"feedback_all.csv");
            break;
    }
    file_put_contents($feedbackFile,json_encode(array_values($data),JSON_PRETTY_PRINT));
    header("Location: feedback.php"); exit;
}

// Admin single provide/delete
if($isAdmin){
    if(isset($_GET['provide'])){
        foreach($data as &$f) if($f['ref']===$_GET['provide']) $f['status']="Provided";
        unset($f);
        file_put_contents($feedbackFile,json_encode($data,JSON_PRETTY_PRINT));
        header("Location: feedback.php"); exit;
    }
    if(isset($_GET['delete'])){
        $data=array_values(array_filter($data, fn($f)=>$f['ref']!==$_GET['delete']));
        file_put_contents($feedbackFile,json_encode($data,JSON_PRETTY_PRINT));
        header("Location: feedback.php"); exit;
    }
}

// Reload data for display
$data = json_decode(file_get_contents($feedbackFile), true);

// Pagination
$perPage=10;
$total=count($filtered);
$page=max(1,intval($_GET['page']??1));
$totalPages=ceil($total/$perPage);
$offset=($page-1)*$perPage;
$paginated=array_slice($filtered,$offset,$perPage);

// CSV export helper
function exportCSV($array,$filename){
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $f=fopen('php://output','w');
    if($array) fputcsv($f,array_keys($array[0]));
    foreach($array as $row) fputcsv($f,$row);
    fclose($f); exit;
}

$message=$_SESSION['feedback_message']??'';
unset($_SESSION['feedback_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Feedback Portal</title>
<style>
body{font-family:'Segoe UI',sans-serif; background:#f4f6f8; margin:0; padding:20px; color:#333;}
.container { width: 80%; margin: 20px auto; background: #fff; padding: 25px 30px; border-radius: 10px; box-shadow: 0 3px 10px rgba(0,0,0,0.1);}
h1,h2{color:#2c3e50;margin-bottom:15px;}
form input, form textarea, form select{width:100%;padding:10px;margin:5px 0 15px 0;border-radius:6px;border:1px solid #ccc;font-size:14px;}
button{padding:8px 14px;margin:5px 3px;border:none;border-radius:6px;cursor:pointer;background:#3498db;color:#fff;font-weight:600;transition:0.2s;}
button:hover{background:#2980b9;}
.message{padding:10px 15px;margin-bottom:15px;border-radius:6px;background:#e8f0fe;color:#1a73e8;font-weight:500;}
.status.received{color:red;font-weight:bold;}
.status.provided{color:green;font-weight:bold;}
.status.unknown{color:gray;font-weight:bold;}
table{width:100%;border-collapse:collapse;margin-top:15px;}
th,td{border:1px solid #ddd;padding:10px;text-align:left;font-size:14px;}
th{background:#f0f0f0;}
tr:hover{background:#f9f9f9;}
.filters{display:flex;flex-wrap:wrap;gap:15px;margin-bottom:15px;padding:15px;background:#fafafa;border-radius:8px;border:1px solid #ddd;}
.filters label{margin-top:5px;font-weight:600;}
.filters input,.filters select{width:auto;flex:1 1 150px;}
.pagination{margin-top:15px;display:flex;gap:5px;flex-wrap:wrap;}
.pagination a{text-decoration:none;padding:6px 10px;border:1px solid #ccc;border-radius:4px;}
.pagination a.active{background:#3498db;color:#fff;border-color:#3498db;}
.checkbox-label{display:flex;align-items:center;gap:10px;margin-top:10px;font-weight:500;}
.checkbox-label input{width:auto;height:auto;}
.action-buttons button{margin:2px;}
.bulk-actions{margin-bottom:10px;}
</style>
</head>
<body>
<div class="container">
<h1>Feedback Submission</h1>
<?php if($message): ?><div class="message"><?= $message ?></div><?php endif; ?>

<form method="post">
    <label>From:</label>
    <input type="text" value="<?= htmlspecialchars($_SESSION['username']) ?>" readonly>
    <label>Agent ID:</label>
    <input type="text" name="agent_id" required>
    <label>Account Number (12 digits):</label>
    <input type="text" name="account_number" maxlength="12" pattern="\d{12}" required>
    <label>Reason:</label>
    <textarea name="reason" required></textarea>
    <label class="checkbox-label">
        <input type="checkbox" name="acknowledge" required>
        I acknowledge this feedback is reasonable.
    </label>
    <button type="submit" name="submitFeedback">Submit Feedback</button>
</form>

<h2>Check Feedback Status</h2>
<form method="post" style="margin-bottom:20px;">
    <label>Enter REF#:</label>
    <input type="text" name="ref_check" required>
    <button type="submit" name="checkStatus">Check Status</button>
</form>
<?php if($statusCheck): ?><p>Status: <?= $statusCheck ?></p><?php endif; ?>

<?php if($isAdmin): ?>
<h2>All Feedbacks (Admin View)</h2>
<form method="get" class="filters">
    <label>REF#:</label><input type="text" name="ref" value="<?= htmlspecialchars($filters['ref']) ?>">
    <label>From:</label>
    <select name="from"><option value="">All</option>
        <?php foreach(array_unique(array_column($data,'from')) as $val): ?>
        <option value="<?= htmlspecialchars($val) ?>" <?= $filters['from']===$val?'selected':'' ?>><?= htmlspecialchars($val) ?></option>
        <?php endforeach; ?>
    </select>
    <label>Agent ID:</label><input type="text" name="agent_id" value="<?= htmlspecialchars($filters['agent_id']) ?>">
    <label>Account Number:</label><input type="text" name="account_num" value="<?= htmlspecialchars($filters['account_num']) ?>">
    <label>Status:</label>
    <select name="status">
        <option value="">All</option>
        <option value="Received" <?= $filters['status']==="Received"?'selected':'' ?>>Received</option>
        <option value="Provided" <?= $filters['status']==="Provided"?'selected':'' ?>>Provided</option>
    </select>
    <label>Date From:</label><input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>">
    <label>Date To:</label><input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>">
    <button type="submit">Apply Filters</button>
    <button type="button" onclick="window.location='feedback.php'">Clear Filters</button>
</form>

<form method="post">
<div class="bulk-actions">
    <button type="submit" name="bulk_action" value="mark_filtered_provided" onclick="return confirm('Mark ALL filtered feedbacks as Provided?')">Mark All Filtered as Provided</button>
    <button type="submit" name="bulk_action" value="delete_filtered" onclick="return confirm('Delete ALL filtered feedbacks?')">Delete All Filtered</button>
    <button type="submit" name="bulk_action" value="mark_selected_provided">Mark Selected as Provided</button>
    <button type="submit" name="bulk_action" value="delete_selected" onclick="return confirm('Delete selected feedbacks?')">Delete Selected</button>
    <button type="submit" name="bulk_action" value="export_filtered">Export Filtered</button>
    <button type="submit" name="bulk_action" value="export_selected">Export Selected</button>
    <button type="submit" name="bulk_action" value="export_all">Export All</button>
</div>

<table>
<tr>
<th><input type="checkbox" onclick="for(c of document.getElementsByName('selected[]')) c.checked=this.checked"></th>
<th>REF</th><th>From</th><th>Agent ID</th><th>Account Number</th><th>Reason</th><th>Date</th><th>Status</th><th>Actions</th>
</tr>
<?php foreach($paginated as $f): ?>
<tr>
<td><input type="checkbox" name="selected[]" value="<?= $f['ref'] ?>"></td>
<td><?= htmlspecialchars($f['ref']) ?></td>
<td><?= htmlspecialchars($f['from']) ?></td>
<td><?= htmlspecialchars($f['agent_id']) ?></td>
<td><?= htmlspecialchars($f['account_num']) ?></td>
<td><?= htmlspecialchars($f['reason']) ?></td>
<td><?= htmlspecialchars($f['date']) ?></td>
<td class="<?= $f['status']==="Provided"?'status provided':'status received' ?>"><?= $f['status'] ?></td>
<td class="action-buttons">
<?php if($f['status']==="Received"): ?>
<a href="?provide=<?= urlencode($f['ref']) ?>"><button type="button">Mark as Provided</button></a>
<?php endif; ?>
<a href="?delete=<?= urlencode($f['ref']) ?>" onclick="return confirm('Delete this feedback?')"><button type="button" style="background:#e74c3c;">Delete</button></a>
</td>
</tr>
<?php endforeach; ?>
</table>
</form>

<div class="pagination">
<?php for($i=1;$i<=$totalPages;$i++): ?>
<a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="<?= $i==$page?'active':'' ?>"><?= $i ?></a>
<?php endfor; ?>
</div>
<?php endif; ?>
</div>
</body>
</html>
