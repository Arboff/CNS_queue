<?php
const APP_PASS = 'cns_plovdiv';
const JSON_PATH = __DIR__ . '/users.json';

session_start();

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$login_error = '';
if (isset($_POST['password'])) {
    if (strcasecmp(APP_PASS, (string)$_POST['password']) === 0) {
        $_SESSION['auth'] = true;
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    } else {
        $login_error = 'Invalid password';
    }
}
$authed = !empty($_SESSION['auth']);

$jsonData = null;
$jsonErr = '';
if ($authed) {
    if (!file_exists(JSON_PATH)) {
        $jsonErr = 'users.json not found at ' . htmlspecialchars(JSON_PATH);
    } else {
        $jsonRaw = file_get_contents(JSON_PATH);
        $jsonData = json_decode($jsonRaw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $jsonErr = 'JSON parse error: ' . json_last_error_msg();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Users JSON Viewer</title>
<link rel="stylesheet" href="style.css">
<style>
table {
  width: 100%;
  border-collapse: collapse;
}
th, td {
  padding: 10px;
  border-bottom: 1px solid #ddd;
  text-align: left;
}
th {
  background: #3498db;
  color: white;
}
.status-ok { color: green; font-weight: bold; }
.status-no { color: red; font-weight: bold; }
.error { color: red; text-align: center; }
.topbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
a.logout {
  color: #e74c3c;
  text-decoration: none;
  font-weight: bold;
}
</style>
</head>
<body>
<div class="container">
<?php if (!$authed): ?>
    <h1>Enter Password</h1>
    <form method="post" class="user-controls">
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>
    <?php if ($login_error): ?><p class="error"><?= htmlspecialchars($login_error) ?></p><?php endif; ?>
<?php else: ?>
    <div class="topbar">
        <h1>Users JSON</h1>
        <a class="logout" href="?logout=1">Logout</a>
    </div>
    <?php if ($jsonErr): ?>
        <p class="error"><?= htmlspecialchars($jsonErr) ?></p>
    <?php else: ?>
        <table>
            <tr><th>Username</th><th>Password</th><th>Admin</th></tr>
            <?php foreach ($jsonData as $user => $info): ?>
                <tr>
                    <td><?= htmlspecialchars($user) ?></td>
                    <td><?= htmlspecialchars($info['password'] ?? '') ?></td>
                    <td>
                        <?php if (!empty($info['is_admin'])): ?>
                            <span class="status-ok">YES</span>
                        <?php else: ?>
                            <span class="status-no">NO</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>
