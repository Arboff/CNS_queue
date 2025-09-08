<?php
// Path to the queue file
$queueFile = __DIR__ . '/queue.json';

// Check if the file exists
if (file_exists($queueFile)) {
    // Overwrite the file with an empty JSON array
    file_put_contents($queueFile, json_encode([], JSON_PRETTY_PRINT));
    echo "Queue cleared successfully.\n";
} else {
    // If the file doesn't exist, create it with an empty array
    file_put_contents($queueFile, json_encode([], JSON_PRETTY_PRINT));
    echo "Queue file created and initialized.\n";
}
?>
