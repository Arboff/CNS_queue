<?php
// Check maintenance status on every load
$maintenanceFile = __DIR__ . "/maintenance.json";
$maintenance = file_exists($maintenanceFile) ? json_decode(file_get_contents($maintenanceFile), true) : ['maintenance' => false];

// If maintenance is turned off, redirect to login page
if (empty($maintenance['maintenance'])) {
    header("Location: login.php");
    exit;
}

// Optional: display a custom maintenance message
$message = !empty($maintenance['message']) ? $maintenance['message'] : "The system is currently under maintenance. Please check back later.";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Maintenance</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #222;
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            text-align: center;
        }
        .maintenance-box {
            background: #333;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
        h1 {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="maintenance-box">
        <h1>Maintenance Mode</h1>
        <p><?= htmlspecialchars($message) ?></p>
    </div>
</body>
</html>
