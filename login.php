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
            if (strcasecmp($storedUser, $inputUser) === 0) { // case-insensitive match
                $found = true;
                if ($info['password'] === $inputPass) {
                    $_SESSION['username'] = ucwords(str_replace('.', ' ', strtolower($storedUser))); // format: Nikola Arbov
                    $_SESSION['raw_username'] = $storedUser; // keep original
                    $_SESSION['is_admin'] = $info['is_admin'];
                    if(isset($info['is_admin']) && $info['is_admin']){
    header("Location: index.php"); // Admin goes to timesheet
} else {
    header("Location: index.php");     // Normal user goes to index
}
exit;
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
    <title>Login</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f4f4f9;display:flex;justify-content:center;align-items:center;height:100vh;margin:0}
        .login-box{background:white;padding:30px;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,0.2);width:300px;text-align:center}
        input{width:90%;padding:10px;margin:10px 0;font-size:14px;border-radius:6px;border:1px solid #ccc}
        button{padding:10px 20px;font-size:14px;background:#4a90e2;color:white;border:none;border-radius:6px;cursor:pointer}
        button:hover{background:#357abd}
        .error{color:red;font-size:14px}
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Login</h2>
        <?php if($error): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif; ?>
        <form method="post">
            <input type="text" name="username" placeholder="Username" required><br>
            <input type="password" name="password" placeholder="Password" required><br>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
