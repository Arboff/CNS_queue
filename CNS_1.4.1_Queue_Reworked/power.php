<?php
session_start();

// Hardcoded password
$adminPassword = 'cns_plovdiv';
$error = '';
$success = '';

// Path to maintenance.json
$maintenanceFile = __DIR__ . "/maintenance.json";

// Handle login
if (!isset($_SESSION['power_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if ($_POST['password'] === $adminPassword) {
            $_SESSION['power_logged_in'] = true;
        } else {
            $error = "Incorrect password.";
        }
    }

    // Show login form if not logged in
    if (!isset($_SESSION['power_logged_in'])) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Power Control Login</title>
            <style>
                body { font-family: Arial; display:flex; justify-content:center; align-items:center; height:100vh; background:#222; color:#fff; }
                .box { background:#333; padding:30px; border-radius:10px; text-align:center; }
                input { padding:10px; margin:10px 0; width:80%; border-radius:5px; border:none; }
                button { padding:10px 20px; border:none; border-radius:5px; cursor:pointer; background:#4a90e2; color:#fff; }
                .error { color:red; margin-bottom:10px; }
            </style>
        </head>
        <body>
            <div class="box">
                <h2>Enter Password</h2>
                <?php if($error) echo "<div class='error'>".htmlspecialchars($error)."</div>"; ?>
                <form method="post">
                    <input type="password" name="password" placeholder="Password" required><br>
                    <button type="submit">Login</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Handle toggling maintenance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $current = file_exists($maintenanceFile) ? json_decode(file_get_contents($maintenanceFile), true) : ['maintenance' => false];
    if ($_POST['action'] === 'start') {
        $current['maintenance'] = true;
        $success = "Maintenance started!";
    } elseif ($_POST['action'] === 'stop') {
        $current['maintenance'] = false;
        $success = "Maintenance stopped!";
    }
    file_put_contents($maintenanceFile, json_encode($current, JSON_PRETTY_PRINT));
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Power Control</title>
    <style>
        body { font-family: Arial; display:flex; justify-content:center; align-items:center; height:100vh; background:#222; color:#fff; }
        .box { background:#333; padding:30px; border-radius:10px; text-align:center; width:300px; }
        button { padding:10px 20px; border:none; border-radius:5px; cursor:pointer; margin:10px; font-size:16px; }
        .start { background:#e74c3c; color:#fff; }
        .stop { background:#2ecc71; color:#fff; }
        .success { color:#2ecc71; margin-bottom:10px; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Maintenance Control</h2>
        <?php if($success) echo "<div class='success'>".htmlspecialchars($success)."</div>"; ?>
        <form method="post">
            <button type="submit" name="action" value="start" class="start">Start Maintenance</button>
            <button type="submit" name="action" value="stop" class="stop">Stop Maintenance</button>
        </form>
    </div>
</body>
</html>
