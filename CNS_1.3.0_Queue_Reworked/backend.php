<?php
header('Content-Type: application/json');

date_default_timezone_set('Europe/Sofia');
session_start();

if (!isset($_SESSION['username'])) {
    echo json_encode(["error" => "Not authenticated"]);
    exit;
}

$filename = 'queue.json';
$timesheetFile = 'timesheet.txt';
$unplannedFile = 'unplanned_breaks.json';
$overrideFile = 'overrides.json'; // --- NEW FILE for overrides ---

define('LUNCH_MINUTES', 30);
define('BREAK_MINUTES', 15);

function ensureFilesExist() {
    global $filename, $timesheetFile, $unplannedFile, $overrideFile;
    if (!file_exists($filename)) file_put_contents($filename, '[]');
    if (!file_exists($timesheetFile)) file_put_contents($timesheetFile, '');
    if (!file_exists($unplannedFile)) file_put_contents($unplannedFile, '[]');
    if (!file_exists($overrideFile)) file_put_contents($overrideFile, '[]'); 
}

function loadQueue() {
    global $filename;
    ensureFilesExist();
    $queue = json_decode(file_get_contents($filename), true);
    if (!is_array($queue)) $queue = [];
    $now = time();

    foreach ($queue as &$user) {
        // Auto-return from lunch/break
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
    // Convert timestamps to Sofia time
    $tz = new DateTimeZone('Europe/Sofia');
    $start = (new DateTime("@$startTs"))->setTimezone($tz)->getTimestamp();
    $end = (new DateTime("@$endTs"))->setTimezone($tz)->getTimestamp();
    $line = $username . '|' . $start . '|' . $end . '|' . $duration . PHP_EOL;
    file_put_contents($timesheetFile, $line, FILE_APPEND | LOCK_EX);
}

function logUnplannedBreak($username, $startTs, $endTs, $duration) {
    global $unplannedFile;
    $tz = new DateTimeZone('Europe/Sofia');
    $start = (new DateTime("@$startTs"))->setTimezone($tz)->getTimestamp();
    $end = (new DateTime("@$endTs"))->setTimezone($tz)->getTimestamp();

    $breaks = json_decode(file_get_contents($unplannedFile), true);
    if (!is_array($breaks)) $breaks = [];
    $breaks[] = [
        'username' => $username,
        'start' => $start,
        'end' => $end,
        'duration' => $duration
    ];
    file_put_contents($unplannedFile, json_encode($breaks, JSON_PRETTY_PRINT));
}

// --- NEW: Log override actions ---
function logOverride($username, $reason, $firstInQueue) {
    global $overrideFile;
    $overrides = json_decode(file_get_contents($overrideFile), true);
    if (!is_array($overrides)) $overrides = [];

    $overrides[] = [
        'overrider' => $username,
        'reason' => $reason,
        'skipped_user' => $firstInQueue,
        'timestamp' => time()
    ];

    file_put_contents($overrideFile, json_encode($overrides, JSON_PRETTY_PRINT));
}

function isOnChat($queue, $name) {
    $index = findUserIndex($queue, $name);
    if ($index === -1) return false;
    return ($queue[$index]['status'] ?? '') === 'on_chat';
}

function handleAction($action, $name) {
    $queue = loadQueue();
    $index = findUserIndex($queue, $name);
    $now = time();

    $blockedActions = ['enter_chat','lunch','break','unplanned','leave_queue','logout'];
    if (isOnChat($queue, $name) && in_array($action, $blockedActions) && $action !== 'end_chat') {
        echo json_encode([
            "error" => "Your status is currently on chat. Use the End Chat button before engaging another status."
        ]);
        exit;
    }

    if ($index !== -1 && isset($queue[$index]['status']) && $queue[$index]['status'] === 'unplanned' && isset($queue[$index]['break_start'])) {
        $start = $queue[$index]['break_start'];
        $end = $now;
        $duration = $end - $start;
        logUnplannedBreak($queue[$index]['name'], $start, $end, $duration);
        unset($queue[$index]['break_start']);
    }

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
                $queue[] = [
                    'name' => $name,
                    'status' => 'available',
                    'timestamp' => $now
                ];
            }
            break;

        case 'lunch':
            if ($index !== -1) {
                $queue[$index]['status'] = 'lunch';
                $queue[$index]['timestamp'] = $now;
                $queue[$index]['expires_at'] = $now + (LUNCH_MINUTES * 60);
            }
            break;

        case 'break':
            if ($index !== -1) {
                $queue[$index]['status'] = 'break';
                $queue[$index]['timestamp'] = $now;
                $queue[$index]['expires_at'] = $now + (BREAK_MINUTES * 60);
            }
            break;

        case 'unplanned':
            if ($index !== -1) {
                $queue[$index]['status'] = 'unplanned';
                $queue[$index]['timestamp'] = $now;
                if (empty($queue[$index]['break_start'])) {
                    $queue[$index]['break_start'] = $now;
                }
                unset($queue[$index]['expires_at']);
            } else {
                $queue[] = [
                    'name' => $name,
                    'status' => 'unplanned',
                    'timestamp' => $now,
                    'break_start' => $now
                ];
            }
            break;

        case 'took_chat':
    $reason = $_POST['override_reason'] ?? null;
    $firstInQueue = $_POST['first_in_queue'] ?? null;
    if ($reason && $firstInQueue && strcasecmp($firstInQueue, $name) !== 0) {
        logOverride($name, $reason, $firstInQueue);
    }

    if ($index !== -1) {
        $queue[$index]['status'] = 'on_chat';
        $queue[$index]['timestamp'] = $now;
        if (empty($queue[$index]['chat_start'])) $queue[$index]['chat_start'] = $now;
        unset($queue[$index]['expires_at']);
    } else {
        $queue[] = [
            'name' => $name,
            'status' => 'on_chat',
            'timestamp' => $now,
            'chat_start' => $now
        ];
    }
    break;

        case 'end_chat':
            if ($index !== -1) {
                $user = $queue[$index];
                $start = $user['chat_start'] ?? null;
                $end = $now;

                array_splice($queue, $index, 1);
                $user['status'] = 'available';
                $user['timestamp'] = $now;
                unset($user['expires_at'], $user['chat_start']);
                $queue[] = $user;

                if ($start && $end >= $start) {
                    $duration = $end - $start;
                    logTimesheet($user['name'], $start, $end, $duration);
                }
            } else {
                $queue[] = [
                    'name' => $name,
                    'status' => 'available',
                    'timestamp' => $now
                ];
            }
            break;

        case 'leave_queue':
            if ($index !== -1) {
                array_splice($queue, $index, 1);
            }
            break;
    }

    saveQueue($queue);
    echo json_encode(["status" => "ok"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $isAdmin = $_SESSION['is_admin'] ?? false;
    $name = $isAdmin ? ($_POST['name'] ?? $_SESSION['username']) : $_SESSION['username'];

    if ($action && $name) {
        handleAction($action, $name);
    } else {
        echo json_encode(["error" => "Missing action or name."]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get_queue') {
    echo json_encode(loadQueue());
    exit;
}

echo json_encode(["error" => "Invalid request"]);
