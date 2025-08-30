<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

// Check if user is admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    echo "<h2 style='text-align:center; margin-top:50px; color:red;'>Access Denied: Admins Only</h2>";
    exit;
}

$file = 'surveys.json';
$data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
if (!is_array($data)) $data = [];
// Handle Nuke Records
$nuke_message = $nuke_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuke_confirm'])) {
    if ($_POST['nuke_confirm'] === 'I AM AWARE') {
        file_put_contents($file, json_encode([]));
        $data = [];
        $nuke_message = "All surveys have been nuked!";
    } else {
        $nuke_error = "You must type 'I AM AWARE' to confirm.";
    }
}

// Extract unique options for dropdowns
function distinct($arr, $key) {
    $vals = [];
    foreach ($arr as $row) {
        if (!empty($row[$key])) {
            if (is_array($row[$key])) {
                $vals = array_merge($vals, $row[$key]); // merge all array values
            } else {
                $vals[] = $row[$key];
            }
        }
    }
    return array_values(array_unique($vals));
}

$serviceProviders = distinct($data, 'service_provider');
$services         = distinct($data, 'service');
$myphotos         = distinct($data, 'myphoto');
$outcomes         = distinct($data, 'outcome[]');
$feedbacks        = distinct($data, 'feedback');
$usernames        = distinct($data, 'username');

$filters = [
    'account_number'   => trim($_GET['account_number'] ?? ''),
    'cns_id'           => trim($_GET['cns_id'] ?? ''),
    'agent_id'         => trim($_GET['agent_id'] ?? ''),
    'service_provider' => trim($_GET['service_provider'] ?? ''),
    'myphoto'          => trim($_GET['myphoto'] ?? ''),
    'myphoto_reason'   => trim($_GET['myphoto_reason'] ?? ''),
    'service'          => trim($_GET['service'] ?? ''),
    'outcome'          => trim($_GET['outcome'] ?? ''),
    'feedback'         => trim($_GET['feedback'] ?? ''),
    'feedback_details' => trim($_GET['feedback_details'] ?? ''),
    'handling_min'     => trim($_GET['handling_min'] ?? ''),
    'handling_max'     => trim($_GET['handling_max'] ?? ''),
    'date_from'        => trim($_GET['date_from'] ?? ''),
    'date_to'          => trim($_GET['date_to'] ?? ''),
    'username'         => trim($_GET['username'] ?? ''),
    'sort_field'       => trim($_GET['sort_field'] ?? ''),
    'sort_order'       => trim($_GET['sort_order'] ?? 'asc'),
];

function contains_ci($haystack, $needle) {
    return $needle === '' || stripos((string)$haystack, (string)$needle) !== false;
}

// Apply filters
$results = array_filter($data, function($row) use ($filters) {
    if (!contains_ci($row['account_number'] ?? '', $filters['account_number'])) return false;
    if (!contains_ci($row['cns_id'] ?? '', $filters['cns_id'])) return false;
    if (!contains_ci($row['agent_id'] ?? '', $filters['agent_id'])) return false;
    if ($filters['service_provider'] !== '' && ($row['service_provider'] ?? '') !== $filters['service_provider']) return false;
    if ($filters['myphoto'] !== '' && ($row['myphoto'] ?? '') !== $filters['myphoto']) return false;
    if (!contains_ci($row['myphoto_reason'] ?? '', $filters['myphoto_reason'])) return false;
    if ($filters['service'] !== '' && ($row['service'] ?? '') !== $filters['service']) return false;
    if ($filters['outcome'] !== '' && (($row['outcome[]'] ?? '') !== $filters['outcome'])) return false;
    if ($filters['feedback'] !== '' && (($row['feedback'] ?? '') !== $filters['feedback'])) return false;
    if (!contains_ci($row['feedback_details'] ?? '', $filters['feedback_details'])) return false;

    $ht = (int)($row['handling_time'] ?? 0);
    if ($filters['handling_min'] !== '' && $ht < (int)$filters['handling_min']) return false;
    if ($filters['handling_max'] !== '' && $ht > (int)$filters['handling_max']) return false;

    if ($filters['date_from'] !== '' || $filters['date_to'] !== '') {
        $ts = strtotime($row['timestamp'] ?? '');
        if ($filters['date_from'] !== '' && $ts < strtotime($filters['date_from'].' 00:00:00')) return false;
        if ($filters['date_to'] !== '' && $ts > strtotime($filters['date_to'].' 23:59:59')) return false;
    }

    if ($filters['username'] !== '' && ($row['username'] ?? '') !== $filters['username']) return false;

    return true;
});

// Sorting
if ($filters['sort_field'] !== '') {
    usort($results, function($a, $b) use ($filters) {
        $f = $filters['sort_field'];
        $ord = $filters['sort_order'] === 'desc' ? -1 : 1;
        return $ord * strnatcasecmp($a[$f] ?? '', $b[$f] ?? '');
    });
}

// CSV export
if (isset($_GET['export']) && $_GET['export']==='csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="surveys_filtered.csv"');
    $out = fopen('php://output', 'w');
    if (!empty($results)) {
        $headers = array_keys(reset($results));
        fputcsv($out, $headers);
        foreach ($results as $row) {
            fputcsv($out, array_map(function($v){
                return is_array($v) ? implode(', ', $v) : $v;
            }, $row));
        }
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Survey Parser</title>
<link rel="stylesheet" href="style.css">
<style>
body{font-family:sans-serif; background:#f5f7fa; color:#2c3e50; line-height:1.5;}
.page{width:90%; margin:30px auto;}
h1{margin-bottom:20px;}
.filters{
    background:#fff; 
    padding:20px; 
    border-radius:12px; 
    margin-bottom:25px; 
    display:flex; 
    flex-wrap:wrap; 
    gap:18px;
}
.filters label{
    font-weight:600; 
    display:flex; 
    flex-direction:column; 
    margin-bottom:10px; 
    flex:1 1 180px;
}
.filters label span{
    margin-bottom:6px;
}
.filters .ctrl, .filters select{
    padding:8px 10px; 
    border:1px solid #ccc; 
    border-radius:6px; 
    font-size:0.95rem;
    width:100%;
}
.filters button, .filters a{
    margin-top:20px; 
    padding:10px 16px; 
    border-radius:8px; 
    border:none; 
    cursor:pointer; 
    background:#3498db; 
    color:#fff; 
    text-decoration:none;
    font-weight:600;
}
.filters button:hover, .filters a:hover{background:#2980b9;}
.table-wrap{overflow:auto; background:#fff; border-radius:12px; padding:12px;}
table{width:100%; border-collapse:collapse;}
th, td{padding:12px; border-bottom:1px solid #ccc; text-align:left;}
th{background:#ecf5fb; position:sticky; top:0;}
tbody tr:nth-child(even){background:#f9fbfd;}
.sort-select{
    padding:4px 6px; border-radius:4px; border:1px solid #ccc; font-size:0.85rem; margin-left:6px;
}
/* Nuke Button Styles */
.nuke-btn{
    background:#e74c3c;  /* Red button */
    color:#fff;
    padding:10px 16px;
    border-radius:8px;
    border:none;
    font-weight:600;
    cursor:pointer;
}
.nuke-btn:hover{
    background:#c0392b;
}
.nuke-form input{
    width:300px; padding:8px; margin-top:8px; border:1px solid #c0392b; border-radius:6px;
}
.nuke-form label{
    font-weight:600; color:#c0392b;
}
</style>
</head>
<body>
<div class="page">
  <h1>Survey Parser</h1>

  <form class="filters" method="get">
    <label><span>Account Number</span><input class="ctrl" type="text" name="account_number" value="<?=htmlspecialchars($filters['account_number'])?>"></label>
    <label><span>CNS ID</span><input class="ctrl" type="text" name="cns_id" value="<?=htmlspecialchars($filters['cns_id'])?>"></label>
    <label><span>Agent ID</span><input class="ctrl" type="text" name="agent_id" value="<?=htmlspecialchars($filters['agent_id'])?>"></label>
    <label><span>Service Provider</span>
        <select class="ctrl" name="service_provider">
            <option value="">— Any —</option>
            <?php foreach ($serviceProviders as $p): ?>
                <option value="<?=htmlspecialchars($p)?>" <?= $filters['service_provider']===$p?'selected':''?>><?=htmlspecialchars($p)?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label><span>MyPhoto</span>
        <select class="ctrl" name="myphoto">
            <option value="">— Any —</option>
            <?php foreach ($myphotos as $mp): ?>
                <option value="<?=htmlspecialchars($mp)?>" <?= $filters['myphoto']===$mp?'selected':''?>><?=htmlspecialchars($mp)?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label><span>MyPhoto Reason</span><input class="ctrl" type="text" name="myphoto_reason" value="<?=htmlspecialchars($filters['myphoto_reason'])?>"></label>
    <label><span>Service</span>
        <select class="ctrl" name="service">
            <option value="">— Any —</option>
            <?php foreach ($services as $s): ?>
                <option value="<?=htmlspecialchars($s)?>" <?= $filters['service']===$s?'selected':''?>><?=htmlspecialchars($s)?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label><span>Outcome</span>
        <select class="ctrl" name="outcome">
            <option value="">— Any —</option>
            <?php foreach ($outcomes as $o): ?>
                <option value="<?=htmlspecialchars($o)?>" <?= $filters['outcome']===$o?'selected':''?>><?=htmlspecialchars($o)?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label><span>Feedback</span>
        <select class="ctrl" name="feedback">
            <option value="">— Any —</option>
            <?php foreach ($feedbacks as $f): ?>
                <option value="<?=htmlspecialchars($f)?>" <?= $filters['feedback']===$f?'selected':''?>><?=htmlspecialchars($f)?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label><span>Feedback Details</span><input class="ctrl" type="text" name="feedback_details" value="<?=htmlspecialchars($filters['feedback_details'])?>"></label>
    <label><span>Handling Time Min</span><input class="ctrl" type="number" min="0" name="handling_min" value="<?=htmlspecialchars($filters['handling_min'])?>"></label>
    <label><span>Handling Time Max</span><input class="ctrl" type="number" min="0" name="handling_max" value="<?=htmlspecialchars($filters['handling_max'])?>"></label>
    <label><span>Date From</span><input class="ctrl" type="date" name="date_from" value="<?=htmlspecialchars($filters['date_from'])?>"></label>
    <label><span>Date To</span><input class="ctrl" type="date" name="date_to" value="<?=htmlspecialchars($filters['date_to'])?>"></label>
    <label><span>Username</span>
        <select class="ctrl" name="username">
            <option value="">— Any —</option>
            <?php foreach ($usernames as $u): ?>
                <option value="<?=htmlspecialchars($u)?>" <?= $filters['username']===$u?'selected':''?>><?=htmlspecialchars($u)?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label><span>Sort Field</span>
        <select class="sort-select" name="sort_field">
            <option value="">— None —</option>
            <?php
            $columns = ['account_number','cns_id','agent_id','service_provider','myphoto','myphoto_reason','service','outcome[]','feedback','feedback_details','handling_time','username','timestamp'];
            foreach ($columns as $c) {
                echo '<option value="'.$c.'"'.($filters['sort_field']===$c?' selected':'').'>'.$c.'</option>';
            }
            ?>
        </select>
    </label>
    <label><span>Sort Order</span>
        <select class="sort-select" name="sort_order">
            <option value="asc" <?=$filters['sort_order']==='asc'?'selected':''?>>Ascending</option>
            <option value="desc" <?=$filters['sort_order']==='desc'?'selected':''?>>Descending</option>
        </select>
    </label>

    <div style="flex-basis:100%; display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;">
        <button type="submit">Apply Filters</button>
        <a href="json_parser.php">Reset</a>
        <button type="submit" name="export" value="csv">Export CSV</button>
        <span style="margin-left:auto; align-self:center; color:#555; font-weight:600;"><?=count($results)?> result(s)</span>
    </div>
  </form>

  <!-- Nuke Section -->
  <div style="flex-basis:100%; margin-top:20px;">
      <button type="button" class="nuke-btn" id="show-nuke">Nuke Records</button>

      <?php if($nuke_message): ?>
          <div style="color:green; margin-top:10px; font-weight:600;"><?=$nuke_message?></div>
      <?php endif; ?>
      <?php if($nuke_error): ?>
          <div style="color:red; margin-top:10px; font-weight:600;"><?=$nuke_error?></div>
      <?php endif; ?>

      <form method="post" class="nuke-form" id="nuke-form" style="display:none; margin-top:10px;">
          <label>
              Warning: This action is irreversible and will delete all stored surveys.
              Type "I AM AWARE" below and press Nuke to proceed.
          </label>
          <input type="text" name="nuke_confirm">
          <button type="submit" class="nuke-btn" style="margin-top:10px;">Nuke</button>
      </form>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Account Number</th>
          <th>CNS ID</th>
          <th>Agent ID</th>
          <th>Service Provider</th>
          <th>MyPhoto</th>
          <th>MyPhoto Reason</th>
          <th>Service</th>
          <th>Outcome</th>
          <th>Feedback</th>
          <th>Feedback Details</th>
          <th>Handling Time</th>
          <th>Username</th>
          <th>Timestamp</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($results)): ?>
          <tr><td colspan="13" style="text-align:center; color:#7f8c8d;">No results.</td></tr>
        <?php else: ?>
          <?php foreach ($results as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['account_number'] ?? '') ?></td>
              <td><?= htmlspecialchars($row['cns_id'] ?? '') ?></td>
              <td><?= htmlspecialchars($row['agent_id'] ?? '') ?></td>
              <td><?= htmlspecialchars($row['service_provider'] ?? '') ?></td>
              <td><?= htmlspecialchars($row['myphoto'] ?? '') ?></td>
              <td><?= htmlspecialchars($row['myphoto_reason'] ?? '') ?></td>
              <td><?= htmlspecialchars($row['service'] ?? '') ?></td>
              <td>
<?= 
isset($row['outcome[]']) 
    ? (is_array($row['outcome[]']) 
        ? htmlspecialchars(implode(', ', $row['outcome[]'])) 
        : htmlspecialchars($row['outcome[]'])) 
    : '' 
?>
</td>
              <td><?= htmlspecialchars($row['feedback'] ?? '') ?></td>
              <td><?= htmlspecialchars($row['feedback_details'] ?? '') ?></td>
              <td><?= htmlspecialchars($row['handling_time'] ?? '') ?></td>
              <td><?= htmlspecialchars($row['username'] ?? '') ?></td>
              <td><?= htmlspecialchars($row['timestamp'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.getElementById('show-nuke').addEventListener('click', function(){
    document.getElementById('nuke-form').style.display = 'block';
    this.style.display = 'none';
});
</script>
</body>
</html>
