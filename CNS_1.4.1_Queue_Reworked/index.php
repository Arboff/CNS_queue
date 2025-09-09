<?php
// SESSION CHECK ENDPOINT (AJAX)
if (isset($_GET['check_session'])) {
    session_start();
    if (!isset($_SESSION['username'])) {
        echo json_encode(['expired' => true]);
    } else {
        echo json_encode(['expired' => false]);
    }
    exit;
}
?>

<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}
$username = $_SESSION['username'];
$isAdmin  = $_SESSION['is_admin'] ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" href="favicon.ico" />
  <meta charset="UTF-8" />
  <title>CNS Chat Queue System</title>
  <link rel="stylesheet" href="style.css" />
  <style>
    /* --- Modal Styles --- */
    #overrideModal, #broadcastModal {
      display: none;
      position: fixed;
      z-index: 9999;
      left: 0; top: 0;
      width: 100%; height: 100%;
      overflow: auto;
      background-color: rgba(0,0,0,0.5);
    }
    #overrideModalContent, #broadcastModalContent {
      background-color: #fff;
      margin: 10% auto;
      padding: 20px;
      border-radius: 8px;
      width: 400px;
      text-align: center;
    }
    #overrideModal h2 { color: red; margin-bottom: 10px; }
    #broadcastModal h2 { color: green; margin-bottom: 10px; }
    #overrideModal textarea {
      width: 100%;
      height: 80px;
      margin: 10px 0;
      padding: 5px;
    }
    #overrideModal button, #broadcastModal button {
      margin: 5px;
      padding: 8px 16px;
      border-radius: 4px;
      cursor: pointer;
    }
    #overrideModal .submit { background-color: green; color: #fff; border: none; }
    #overrideModal .cancel { background-color: gray; color: #fff; border: none; }
    #broadcastModal button { background:#4a90e2; color:#fff; border:none; }
    #broadcastModal button:hover { background:#357ABD; }
    .container { max-width: 1000px; margin: auto; }
    .section { background:#f7f7f7; padding:15px; border-radius:8px; margin-bottom:20px; }
    .display-box { background:#fff; padding:10px; border-radius:6px; min-height:50px; margin-top:10px; }
    .buttons button { margin:5px; padding:8px 16px; border-radius:5px; cursor:pointer; }
    .buttons .danger { background:#e74c3c; color:#fff; }
  </style>
</head>
<body>
  <!-- Broadcast Modal -->
  <div id="broadcastModal">
    <div id="broadcastModalContent">
      <h2>ADMINISTRATOR SENT A MESSAGE</h2>
      <p id="broadcastMessage"></p>
      <img id="broadcastImage" src="" alt="" style="max-width:100%; display:none; margin-top:10px; border-radius:6px;">
      <br>
      <button onclick="closeBroadcastModal()">Okay</button>
    </div>
  </div>

  <!-- Override Modal -->
  <div id="overrideModal">
    <div id="overrideModalContent">
      <h2>OVERRIDE ATTEMPT DETECTED</h2>
      <p id="overrideText">
        Override attempt detected. Currently next in queue is <strong>USER</strong>.<br>
        If you are sure you want to override queue and take chat, submit a reason below and click Override.
        If this was a mistake, click Cancel.
      </p>
      <textarea id="overrideReason" placeholder="Enter reason for override"></textarea><br>
      <button class="submit" onclick="submitOverride()">Override</button>
      <button class="cancel" onclick="cancelOverride()">Cancel</button>
    </div>
  </div>

  <div class="container">
    <h1>Chat Queue Management</h1>

    <!-- Controls -->
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
        <button onclick="goUnplanned()">Unplanned Break</button>
        <button class="danger" onclick="leaveQueue()">Leave Queue</button>

        <?php if ($isAdmin): ?>
          <button onclick="window.location.href='timesheet.php'">Admin Panel</button>
        <?php endif; ?>

        <!-- Logout button -->
<button class="danger" type="button" onclick="logout()">Logout</button>

      </div>

      <button id="chatToggleBtn" onclick="toggleChat()">Take Chat</button>
    </div>

    <!-- Queue Sections -->
    <div class="section">
      <h2>Active Queue</h2>
      <div id="nextInQueue" class="next-in-queue">Loading...</div>
      <div id="queueDisplay" class="display-box">Loading...</div>
    </div>

    <div class="section">
      <h2>Non-Productive (Lunch / Break / Unplanned)</h2>
      <div id="breakDisplay" class="display-box">Loading...</div>
    </div>

    <div class="section">
      <h2>Currently on Chat</h2>
      <div id="onChatDisplay" class="display-box">Loading...</div>
    </div>

    <div class="footer">Developed by <b>Nikola Arbov</b></div>
  </div>

  <script>

    function logout() {
    // End chat if currently on chat
    if (currentlyOnChat) {
        fetch('backend.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=end_chat'
        });
    }

    // Leave queue
    fetch('backend.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=leave_queue'
    }).finally(() => {
        // Redirect to logout.php after cleanup
        window.location.href = 'logout.php';
    });
}

    const USERNAME  = <?= json_encode($username) ?>;
    const IS_ADMIN  = <?= $isAdmin ? 'true' : 'false' ?>;
    let overrideData = null;
    let currentlyOnChat = false;

    function showOverrideModal(firstUser) {
      overrideData = { first_in_queue: firstUser };
      document.getElementById('overrideText').innerHTML = `
        Override attempt detected. Currently next in queue is <strong>${firstUser}</strong>.<br>
        If you are sure you want to override queue and take chat, submit a reason below and click Override.
        If this was a mistake, click Cancel.`;
      document.getElementById('overrideReason').value = '';
      document.getElementById('overrideModal').style.display = 'block';
    }

    function submitOverride() {
      const reason = document.getElementById('overrideReason').value.trim();
      if (!reason) { alert("Reason is required to override."); return; }
      fetch('backend.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=took_chat&override_reason=${encodeURIComponent(reason)}&first_in_queue=${encodeURIComponent(overrideData.first_in_queue)}`
      })
      .then(res => res.json())
      .then(resp => {
        if (resp.status === 'ok') { document.getElementById('overrideModal').style.display = 'none'; loadQueue(); currentlyOnChat = true; }
        else if (resp.error) { alert(resp.error); }
      });
    }

    function cancelOverride() { document.getElementById('overrideModal').style.display = 'none'; }

    window.onclick = function(event) {
      const modal = document.getElementById('overrideModal');
      if (event.target === modal) modal.style.display = 'none';
    };

    // Broadcast handling
    let lastBroadcastTimestamp = parseInt(localStorage.getItem('lastBroadcastSeen')) || 0;

    function checkBroadcast() {
      fetch('broadcast.json?' + Date.now())
      .then(res => res.json())
      .then(data => {
        if (!data.timestamp) return;
        if (data.timestamp > lastBroadcastTimestamp) {
          document.getElementById('broadcastMessage').innerText = data.message;
          if (data.image) {
            const imgEl = document.getElementById('broadcastImage');
            imgEl.src = data.image;
            imgEl.style.display = 'block';
          } else {
            document.getElementById('broadcastImage').style.display = 'none';
          }
          document.getElementById('broadcastModal').style.display = 'block';
          lastBroadcastTimestamp = data.timestamp;
        }
      }).catch(err => {});
    }

    function closeBroadcastModal() {
      document.getElementById('broadcastModal').style.display = 'none';
      localStorage.setItem('lastBroadcastSeen', lastBroadcastTimestamp);
    }

    setInterval(checkBroadcast, 5000);

    // --- HARD AUTO-LOGOUT TIMER: 15 SECONDS AFTER LOGIN ---
    const LOGIN_EXPIRY_SECONDS = 43200;
    setTimeout(() => {
      // End chat if currently on chat
      if (currentlyOnChat) {
        fetch('backend.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: 'action=end_chat'
        });
      }

      // Leave queue
      fetch('backend.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=leave_queue'
      });

      // Redirect silently to login.php
      window.location.href = 'login.php';
    }, LOGIN_EXPIRY_SECONDS * 1000);
  </script>

  <script src="script.js"></script>
</body>
</html>
