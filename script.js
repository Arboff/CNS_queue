function getName() {
  if (IS_ADMIN) {
    const el = document.getElementById('username');
    if (!el) return USERNAME;
    const input = el.value.trim();
    if (!input) {
      alert("Please enter a name.");
      return null;
    }
    return input;
  }
  return USERNAME;
}

function postAction(action) {
  const name = getName();
  if (!name) return;

  const body = IS_ADMIN ? `action=${encodeURIComponent(action)}&name=${encodeURIComponent(name)}`
                        : `action=${encodeURIComponent(action)}`;

  fetch('backend.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body
  }).then(() => loadQueue());
}

function enterChat() { postAction('enter_chat'); }
function goLunch() { postAction('lunch'); }
function goBreak() { postAction('break'); }
function tookChat() { postAction('took_chat'); }
function endChat() { postAction('end_chat'); }
function leaveQueue() { postAction('leave_queue'); }

function loadQueue() {
  fetch('backend.php?action=get_queue')
    .then(response => response.json())
    .then(data => {
      const active = document.getElementById('queueDisplay');
      const breaks = document.getElementById('breakDisplay');
      const chats = document.getElementById('onChatDisplay');
      const nextInQueue = document.getElementById('nextInQueue');

      active.innerHTML = '';
      breaks.innerHTML = '';
      chats.innerHTML = '';

      const now = Math.floor(Date.now() / 1000);

      const activeUsers = data.filter(u => u.status === 'available');
      const nonProductive = data.filter(u => u.status === 'lunch' || u.status === 'break');
      const onChatUsers = data.filter(u => u.status === 'on_chat');

      if (activeUsers.length === 0) {
        nextInQueue.textContent = "Queue is empty.";
      } else {
        nextInQueue.textContent = `Next in Queue is: ${activeUsers[0].name}`;
      }

      if (activeUsers.length === 0) {
        active.innerHTML = '<p>No users in queue.</p>';
      } else {
        activeUsers.forEach((user, index) => {
          const div = document.createElement('div');
          div.className = 'queue-entry';
          div.innerHTML = `<strong>${index + 1}. ${user.name}</strong>`;
          active.appendChild(div);
        });
      }

      if (nonProductive.length === 0) {
        breaks.innerHTML = '<p>No users on lunch or break.</p>';
      } else {
        nonProductive.forEach(user => {
          const remaining = (user.expires_at ?? now) - now;
          const secsLeft = Math.max(0, remaining);
          const mins = Math.floor(secsLeft / 60);
          const secs = secsLeft % 60;
          const div = document.createElement('div');
          div.className = 'queue-entry';
          div.innerHTML = `<strong>${user.name}</strong> <span class="status">(${user.status}, ends in ${mins}m ${secs}s)</span>`;
          breaks.appendChild(div);
        });
      }

      if (onChatUsers.length === 0) {
        chats.innerHTML = '<p>No users currently on chat.</p>';
      } else {
        onChatUsers.forEach(user => {
          const div = document.createElement('div');
          div.className = 'queue-entry';
          div.innerHTML = `<strong>${user.name}</strong> <span class="status">(on chat)</span>`;
          chats.appendChild(div);
        });
      }
    });
}

setInterval(loadQueue, 5000);
window.onload = loadQueue;
