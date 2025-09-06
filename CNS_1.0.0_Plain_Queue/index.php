<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}
$username = $_SESSION['username'];
$isAdmin = $_SESSION['is_admin'] ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Chat Queue System</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>
  <div class="container">
    <h1>Chat Queue Management</h1>

    <div class="user-controls">
      <div style="flex-grow:1; text-align:center; font-weight:bold;">
        Logged in as: <?= htmlspecialchars($username) ?><?= $isAdmin ? " (Admin)" : "" ?>
      </div>
      <div class="buttons">
        <?php if ($isAdmin): ?>
          <input type="text" id="username" placeholder="Enter a name" />
        <?php endif; ?>
        <button onclick="enterChat()">Enter Queue</button>
        <button onclick="goLunch()">Lunch (30 min)</button>
        <button onclick="goBreak()">Break (15 min)</button>
        <button onclick="tookChat()">Take Chat</button>
        <button onclick="endChat()">End Chat</button>
        <button class="danger" onclick="leaveQueue()">Leave Queue</button>
        <?php if ($isAdmin): ?>
          <button onclick="window.location.href='timesheet.php'">Admin Panel</button>
        <?php endif; ?>
        <a href="logout.php"><button class="danger" type="button">Logout</button></a>
      </div>
    </div>

    <div class="section">
      <h2>Active Queue</h2>
      <div id="nextInQueue" class="next-in-queue">Loading...</div>
      <div id="queueDisplay" class="display-box">Loading...</div>
    </div>

    <div class="section">
      <h2>Non-Productive (Lunch / Break)</h2>
      <div id="breakDisplay" class="display-box">Loading...</div>
    </div>

    <div class="section">
      <h2>Currently on Chat</h2>
      <div id="onChatDisplay" class="display-box">Loading...</div>
    </div>

    <div class="footer">
      Developed by Nikola Arbov
    </div>
  </div>

  <script>
    const USERNAME = <?= json_encode($username) ?>;
    const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
  </script>
  <script src="script.js"></script>
</body>
</html>
