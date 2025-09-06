<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['status'=>'error','message'=>'Not logged in']);
    exit;
}

$username = $_SESSION['username'];

// --- Save survey data ---
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['status'=>'error','message'=>'Invalid input']);
    exit;
}

$input['username'] = $username;
$input['timestamp'] = date('Y-m-d H:i:s');

$surveyFile = __DIR__ . '/surveys.json';  // <-- corrected
$surveys = file_exists($surveyFile) ? json_decode(file_get_contents($surveyFile), true) : [];
$surveys[] = $input;
file_put_contents($surveyFile, json_encode($surveys, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// --- Move user to back of queue (end_chat logic from backend.php) ---
$queueFile = __DIR__ . '/queue.json';
$queue = file_exists($queueFile) ? json_decode(file_get_contents($queueFile), true) : [];
$now = time();

$foundIndex = -1;
foreach ($queue as $i => $u) {
    if (mb_strtolower($u['name']) === mb_strtolower($username)) {
        $foundIndex = $i;
        break;
    }
}

if ($foundIndex !== -1) {
    $user = $queue[$foundIndex];
    $start = isset($user['chat_start']) ? (int)$user['chat_start'] : null;

    // Remove from current position
    array_splice($queue, $foundIndex, 1);

    // Move to back as available
    $user['status'] = 'available';
    $user['timestamp'] = $now;
    unset($user['expires_at'], $user['chat_start']);
    $queue[] = $user;

    // Log timesheet
    if ($start && $now >= $start) {
        $timesheetFile = __DIR__ . '/timesheet.txt';
        $duration = $now - $start;
        $line = $username . '|' . $start . '|' . $now . '|' . $duration . PHP_EOL;
        file_put_contents($timesheetFile, $line, FILE_APPEND | LOCK_EX);
    }
} else {
    // If user not in queue, add as available
    $queue[] = [
        'name' => $username,
        'status' => 'available',
        'timestamp' => $now
    ];
}

file_put_contents($queueFile, json_encode($queue, JSON_PRETTY_PRINT));

echo json_encode(['status' => 'ok']);
