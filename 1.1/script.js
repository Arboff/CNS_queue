// Global constants
const USERNAME = <?= json_encode($username) ?>;
const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;

// Helper: get user name
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

// POST action to backend
function postAction(action, extraData = {}) {
  const name = getName();
  if (!name) return;

  const data = { action, name, ...extraData };
  const body = Object.keys(data).map(k => `${encodeURIComponent(k)}=${encodeURIComponent(data[k])}`).join('&');

  return fetch('backend.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body
  }).then(res => res.json());
}

// Queue buttons
function enterChat() { postAction('enter_chat').then(loadQueue); }
function goLunch() { postAction('lunch').then(loadQueue); }
function goBreak() { postAction('break').then(loadQueue); }
function leaveQueue() { postAction('leave_queue').then(loadQueue); }

// Take chat -> show survey
function tookChat() {
  postAction('took_chat').then(() => {
    loadQueue();
    openSurveyModal();
  });
}

// Load queue display
function loadQueue() {
  fetch('backend.php?action=get_queue')
    .then(res => res.json())
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

      nextInQueue.textContent = activeUsers.length > 0
        ? `Next in Queue is: ${activeUsers[0].name}`
        : "Queue is empty.";

      if (activeUsers.length === 0) active.innerHTML = '<p>No users in queue.</p>';
      else activeUsers.forEach((u, i) => {
        const div = document.createElement('div');
        div.className = 'queue-entry';
        div.innerHTML = `<strong>${i+1}. ${u.name}</strong>`;
        active.appendChild(div);
      });

      if (nonProductive.length === 0) breaks.innerHTML = '<p>No users on lunch or break.</p>';
      else nonProductive.forEach(u => {
        const remaining = (u.expires_at ?? now) - now;
        const mins = Math.floor(Math.max(0, remaining)/60);
        const secs = Math.max(0, remaining)%60;
        const div = document.createElement('div');
        div.className = 'queue-entry';
        div.innerHTML = `<strong>${u.name}</strong> <span class="status">(${u.status}, ends in ${mins}m ${secs}s)</span>`;
        breaks.appendChild(div);
      });

      if (onChatUsers.length === 0) chats.innerHTML = '<p>No users currently on chat.</p>';
      else onChatUsers.forEach(u => {
        const div = document.createElement('div');
        div.className = 'queue-entry';
        div.innerHTML = `<strong>${u.name}</strong> <span class="status">(on chat)</span>`;
        chats.appendChild(div);
      });
    });
}

// Survey modal
function openSurveyModal() {
  // Create modal overlay
  const modal = document.createElement('div');
  modal.id = 'surveyModal';
  Object.assign(modal.style, {
    position:'fixed',top:0,left:0,width:'100%',height:'100%',background:'rgba(0,0,0,0.6)',
    display:'flex',alignItems:'center',justifyContent:'center',zIndex:9999
  });

  // Modal content (clone existing form from index)
  const formTemplate = document.getElementById('surveyForm');
  const form = formTemplate.cloneNode(true);
  form.id = 'modalSurveyForm';
  form.style.width = '600px';
  form.querySelectorAll('input,textarea,select').forEach(i => i.value=''); // reset

  modal.appendChild(form);
  document.body.appendChild(modal);

  // Remove ability to close by click
  modal.addEventListener('click', e => {
    if(e.target===modal) e.stopPropagation();
  });

  // Handle submit
  form.addEventListener('submit', e => {
    e.preventDefault();
    const formData = new FormData(form);
    const jsonData = {};
    formData.forEach((v,k)=> {
      if (jsonData[k]) {
        if (!Array.isArray(jsonData[k])) jsonData[k]=[jsonData[k]];
        jsonData[k].push(v);
      } else jsonData[k]=v;
    });

    // Save survey
    fetch('survey_save.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(jsonData)
    }).then(res=>res.json()).then(surveyRes=>{
      if(surveyRes.status==='ok'){
        // Move user to back of queue
        postAction('took_chat',{survey_submitted:true}).then(()=>{
          loadQueue();
          modal.remove();
        });
      } else {
        alert('Error saving survey');
      }
    });
  });
}

// Initialize
setInterval(loadQueue,5000);
window.onload = loadQueue;
