<?php
session_start();

$usersFile = __DIR__ . "/users.json";
$users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputUser = trim($_POST['username']);
    $inputPass = $_POST['password'];

    if ($inputUser && $inputPass) {
        $found = false;
        foreach($users as $storedUser => $info) {
            if (strcasecmp($storedUser, $inputUser) === 0) { 
                $found = true;
                if ($info['password'] === $inputPass) {
                    $_SESSION['username'] = ucwords(str_replace('.', ' ', strtolower($storedUser))); 
                    $_SESSION['raw_username'] = $storedUser;
                    $_SESSION['is_admin'] = $info['is_admin'];
                    if(isset($info['is_admin']) && $info['is_admin']){
                        header("Location: index.php");
                    } else {
                        header("Location: index.php");
                    }
                    exit;
                } else {
                    $error = 'Invalid password.';
                }
                break;
            }
        }
        if (!$found) $error = 'User not found.';
    } else {
        $error = 'Please enter username and password.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="icon" href="favicon.ico" />
    <title>Chat Queue Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: url("background.png") no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            overflow: hidden;
            position: relative;
        }
        .login-box {
            background: #fff; 
            padding: 54px;
            border-radius: 21.6px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
            width: 540px;
            text-align: center;
            font-size: 1.2em;
            opacity: 0;
            animation: slideUpBounce 1s ease-out 1.5s forwards;
        }

        @keyframes slideUpBounce {
            0% { transform: translateY(100vh); opacity: 0; }
            60% { transform: translateY(-20px); opacity: 1; }
            80% { transform: translateY(10px); opacity: 1; }
            100% { transform: translateY(0); opacity: 1; }
        }

        input {
            width: 90%;
            padding: 10px;
            margin: 10px 0;
            font-size: 14px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }
        button {
            padding: 10px 20px;
            font-size: 14px;
            background: #4a90e2;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        button:hover {
            background: #357abd;
            transform: scale(1.05);
        }
        .error {
            color: red;
            font-size: 14px;
            margin-bottom: 10px;
        }

        /* Footer at bottom of page */
        .page-footer {
            position: fixed;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 12px;
            color: #fff;
            text-align: center;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Chat Queue Login</h2>
        <?php if($error): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif; ?>
        <form method="post">
            <input type="text" name="username" placeholder="Username" required><br>
            <input type="password" name="password" placeholder="Password" required><br>
            <button type="submit">Login</button>
        </form>
    </div>

    <!-- Footer at bottom of page -->
    <div class="page-footer">
        Version 1.3.2 | Build: 2025-09-06
    </div>
</body>
</html>
