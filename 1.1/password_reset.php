<?php
session_start();

$usersFile = "users.json";
$users = json_decode(file_get_contents($usersFile), true);

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $currentPassword = trim($_POST["current_password"]);
    $newPassword = trim($_POST["new_password"]);
    $confirmPassword = trim($_POST["confirm_password"]);

    if (isset($users[$username])) {
        if ($users[$username]["password"] === $currentPassword) {
            if ($newPassword === $confirmPassword) {
                // Update password
                $users[$username]["password"] = $newPassword;
                file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));

                $message = "<p style='color:green; font-weight:bold;'>✅ Password successfully updated for $username</p>";
            } else {
                $message = "<p style='color:red;'>❌ New passwords do not match.</p>";
            }
        } else {
            $message = "<p style='color:red;'>❌ Current password is incorrect.</p>";
        }
    } else {
        $message = "<p style='color:red;'>❌ Username not found.</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link rel="stylesheet" href="style.css" />
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        .container {
            max-width: 450px;
            margin: 60px auto;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }
        h1 {
            text-align: center;
            color: #333;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        input[type="text"], input[type="password"] {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
        }
        button {
            padding: 10px;
            background: #4a148c;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            cursor: pointer;
            transition: 0.2s;
        }
        button:hover {
            background: #6a1b9a;
        }
        .message {
            margin-top: 15px;
            text-align: center;
            font-size: 14px;
        }
        .back-link {
            display: block;
            margin-top: 10px;
            text-align: center;
            text-decoration: none;
            color: #4a148c;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Reset Password</h1>
        <form method="POST">
            <input type="text" name="username" placeholder="Enter Username" required>
            <input type="password" name="current_password" placeholder="Current Password" required>
            <input type="password" name="new_password" placeholder="New Password" required>
            <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
            <button type="submit">Reset Password</button>
        </form>
        <div class="message"><?= $message ?></div>
        <a class="back-link" href="index.php">⬅ Back to Home</a>
    </div>
</body>
</html>
