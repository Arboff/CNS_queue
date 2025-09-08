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

  // --- New: Prevent actions while on chat ---
  if (action !== 'end_chat' && USER_STATUS === 'on_chat') {
    alert("Your status is currently on chat. Use the End Chat button before engaging another status.");
    return;
  }

  const body = IS_ADMIN
    ? `action=${encodeURIComponent(action)}&name=${encodeURIComponent(name)}`
    : `action=${encodeURIComponent(action)}`;

  fetch('backend.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body
  }).then(res => res.json())
    .then(resp => {
      if (resp.error) alert(resp.error);
      loadQueue();
    });
}

// Original actions
function enterChat() { postAction('enter_chat'); }
function goLunch() { postAction('lunch'); }
function goBreak() { postAction('break'); }
function goUnplanned() { postAction('unplanned'); }
function tookChat() { postAction('took_chat'); }
function endChat() { postAction('end_chat'); }
function leaveQueue() { postAction('leave_queue'); }

// --- NEW GLOBAL VAR for override ---
let CURRENT_FIRST_IN_QUEUE = null;

// New: Toggle button for Take Chat / End Chat
function toggleChat() {
  const btn = document.getElementById('chatToggleBtn');
  const status = btn.getAttribute('data-status');
  if (status === 'on_chat') {
    postAction('end_chat');
  } else {
    // --- Check if user is NOT first in queue ---
    if (CURRENT_FIRST_IN_QUEUE && CURRENT_FIRST_IN_QUEUE !== USERNAME) {
      showOverrideModal(CURRENT_FIRST_IN_QUEUE);
    } else {
      postAction('took_chat');
    }
  }
}

let USER_STATUS = 'available'; // track current user's status

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
      const nonProductive = data.filter(u => ['lunch','break','unplanned'].includes(u.status));
      const onChatUsers = data.filter(u => u.status === 'on_chat');

      if (activeUsers.length === 0) {
        nextInQueue.textContent = "Queue is empty.";
        CURRENT_FIRST_IN_QUEUE = null; // --- NEW: reset when empty
      } else {
        nextInQueue.textContent = `Next in Queue is: ${activeUsers[0].name}`;
        CURRENT_FIRST_IN_QUEUE = activeUsers[0].name; // --- NEW: track first user
      }

      // Active queue display
      if (activeUsers.length === 0) {
        active.innerHTML = '<p>No users in queue.</p>';
      } else {
        activeUsers.forEach((user,index) => {
          const div = document.createElement('div');
          div.className = 'queue-entry';
          div.innerHTML = `<strong>${index+1}. ${user.name}</strong>`;
          active.appendChild(div);
        });
      }

      // Non-productive display
      if (nonProductive.length === 0) {
        breaks.innerHTML = '<p>No users on lunch, break, or unplanned.</p>';
      } else {
        nonProductive.forEach(user => {
          let duration = 0;
          if (user.status === 'unplanned' && user.break_start) {
            duration = now - user.break_start;
          } else if (user.expires_at) {
            duration = Math.max(0, user.expires_at - now);
          }
          let mins = Math.floor(duration/60);
          let secs = duration % 60;
          const div = document.createElement('div');
          div.className = 'queue-entry';
          div.innerHTML = `<strong>${user.name}</strong> <span class="status">(${user.status}${user.status==='unplanned'?', duration: ':' ends in '}${String(mins).padStart(2,'0')}:${String(secs).padStart(2,'0')})</span>`;
          breaks.appendChild(div);
        });
      }

      // On-chat display
      if (onChatUsers.length === 0) {
        chats.innerHTML = '<p>No users currently on chat.</p>';
      } else {
        onChatUsers.forEach(user => {
          let duration = 0;
          if (user.chat_start) duration = now - user.chat_start;
          const mins = Math.floor(duration/60);
          const secs = duration % 60;
          const div = document.createElement('div');
          div.className = 'queue-entry';
          div.innerHTML = `<strong>${user.name}</strong> <span class="status">(on chat duration: ${String(mins).padStart(2,'0')}:${String(secs).padStart(2,'0')})</span>`;
          chats.appendChild(div);
        });
      }

      // Update chat toggle button & current user status
      const chatBtn = document.getElementById('chatToggleBtn');
      const me = data.find(u => u.name === USERNAME);
      if (chatBtn && me) {
        USER_STATUS = me.status;
        if (me.status === 'on_chat') {
          chatBtn.textContent = 'End Chat';
          chatBtn.setAttribute('data-status', 'on_chat');
        } else {
          chatBtn.textContent = 'Take Chat';
          chatBtn.setAttribute('data-status', 'available');
        }
      }
    });
}

// Refresh every second to update timers and button
setInterval(loadQueue, 1000);
window.onload = loadQueue;
