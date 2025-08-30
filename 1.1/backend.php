<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(["error" => "Not authenticated"]);
    exit;
}

$filename = 'queue.json';
$timesheetFile = 'timesheet.txt';

define('LUNCH_MINUTES', 30);
define('BREAK_MINUTES', 15);

function ensureFilesExist() {
    global $filename, $timesheetFile;
    if (!file_exists($filename)) file_put_contents($filename, '[]');
    if (!file_exists($timesheetFile)) file_put_contents($timesheetFile, '');
}

function loadQueue() {
    global $filename;
    ensureFilesExist();
    $queue = json_decode(file_get_contents($filename), true);
    if (!is_array($queue)) $queue = [];
    $now = time();

    foreach ($queue as &$user) {
        // Auto-return from break/lunch
        if (in_array($user['status'] ?? '', ['lunch', 'break'])) {
            if (isset($user['expires_at']) && $now >= $user['expires_at']) {
                $user['status'] = 'available';
                unset($user['expires_at']);
            }
        }
    }
    return $queue;
}

function saveQueue($queue) {
    global $filename;
    file_put_contents($filename, json_encode($queue, JSON_PRETTY_PRINT));
}

function findUserIndex(&$queue, $name) {
    $lower = mb_strtolower($name);
    foreach ($queue as $i => $u) {
        if (mb_strtolower($u['name']) === $lower) return $i;
    }
    return -1;
}

function logTimesheet($username, $startTs, $endTs, $duration) {
    global $timesheetFile;
    $line = $username . '|' . $startTs . '|' . $endTs . '|' . $duration . PHP_EOL;
    file_put_contents($timesheetFile, $line, FILE_APPEND | LOCK_EX);
}

function handleAction($action, $name) {
    $queue = loadQueue();
    $index = findUserIndex($queue, $name);
    $now = time();

    switch ($action) {
        case 'enter_chat':
            if ($index !== -1) {
                $user = $queue[$index];
                array_splice($queue, $index, 1);
                $user['status'] = 'available';
                $user['timestamp'] = $now;
                unset($user['expires_at']);
                $queue[] = $user;
            } else {
                $queue[] = ['name'=>$name, 'status'=>'available','timestamp'=>$now];
            }
            break;

        case 'lunch':
        case 'break':
            if ($index !== -1) {
                $queue[$index]['status'] = $action === 'lunch' ? 'lunch' : 'break';
                $queue[$index]['timestamp'] = $now;
                $queue[$index]['expires_at'] = $now + ($action === 'lunch' ? LUNCH_MINUTES*60 : BREAK_MINUTES*60);
            }
            break;

        case 'took_chat':
            if ($index === -1) {
                // User not in queue, add and start chat
                $queue[] = ['name'=>$name,'status'=>'on_chat','timestamp'=>$now,'chat_start'=>$now];
            } else {
                // User already exists
                $queue[$index]['status'] = 'on_chat';
                if (empty($queue[$index]['chat_start'])) {
                    $queue[$index]['chat_start'] = $now;
                }
                unset($queue[$index]['expires_at']);
            }
            break;

        case 'end_chat_survey':
            if ($index !== -1 && !empty($queue[$index]['chat_start'])) {
                $chatStart = $queue[$index]['chat_start'];
                $duration = $now - $chatStart;
                logTimesheet($name, $chatStart, $now, $duration);

                // Move user back to available queue
                $user = $queue[$index];
                $user['status'] = 'available';
                unset($user['chat_start'],$user['expires_at']);
                array_splice($queue, $index, 1);
                $queue[] = $user;
            }
            break;

        case 'leave_queue':
            if ($index !== -1) array_splice($queue, $index, 1);
            break;
    }

    saveQueue($queue);
    echo json_encode(['status'=>'ok']);
    exit;
}

// POST handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $isAdmin = $_SESSION['is_admin'] ?? false;
    $name = $isAdmin ? ($_POST['name'] ?? $_SESSION['username']) : $_SESSION['username'];

    if ($action && $name) handleAction($action, $name);
    else echo json_encode(["error"=>"Missing action or name."]);
    exit;
}

// GET queue
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get_queue') {
    echo json_encode(loadQueue());
    exit;
}

echo json_encode(["error"=>"Invalid request"]);
