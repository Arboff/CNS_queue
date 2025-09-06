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
<meta charset="UTF-8">
<title>Chat Queue System</title>
<link rel="stylesheet" href="style.css">
<style>
/* Minimal survey modal styling */
.modal {
  display: none; 
  position: fixed; 
  z-index: 9999;
  left: 0; top: 0; 
  width: 100%; height: 100%;
  background-color: rgba(0,0,0,0.6); 
  padding-top: 50px;
}
.modal-content {
  background-color: #fff; 
  margin: 0 auto; 
  padding: 30px;
  border-radius: 12px; 
  max-width: 700px;
  display:flex; 
  flex-direction:column; 
  gap:12px; 
  position:relative;
}
.modal h2 { text-align:center; margin:0; }
.ctrl { width:100%; padding:12px; border:1px solid #d1d8e0; border-radius:8px; }
.radio-row, .check-col { display:flex; gap:12px; flex-wrap:wrap; }
.check-col {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 8px;
  border: 1px dashed #d1d8e0;
  border-radius: 8px;
  max-height: none;
  overflow: visible;
}
.check-col label {
  flex: 1 1 45%;
  min-width: 150px;
  display: flex;
  align-items: center;
  gap: 6px;
}
.actions { display:flex; justify-content:flex-end; gap:10px; margin-top:8px; }
.queue-entry { padding:4px 0; }
.status { font-style:italic; color:#555; }
.display-box { min-height:50px; padding:8px; border:1px solid #ccc; border-radius:8px; background:#f9f9f9; margin-top:6px; }
.buttons button { margin:4px; padding:6px 12px; border-radius:6px; }
.danger { background:#e74c3c; color:#fff; border:none; cursor:pointer; }
</style>
</head>
<body>
<div class="container">
  <h1>Chat Queue Management</h1>

  <div class="user-controls">
    <div style="flex-grow:1; text-align:center; font-weight:bold;">
      Logged in as: <?= htmlspecialchars($username) ?><?= $isAdmin ? " (Admin)" : "" ?>
    </div>
    <div class="buttons" id="queueButtons">
      <?php if ($isAdmin): ?>
        <input type="text" id="username" placeholder="Enter a name">
      <?php endif; ?>
      <button onclick="enterChat()">Enter Queue</button>
      <button onclick="goLunch()">Lunch (30 min)</button>
      <button onclick="goBreak()">Break (15 min)</button>
      <button onclick="takeChat()">Take Chat</button>
      <?php if ($isAdmin): ?>
        <button onclick="endChat()">End Chat</button>
      <?php endif; ?>
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

  <div class="footer">Developed by Nikola Arbov</div>
</div>

<!-- Survey Modal -->
<div class="modal" id="surveyModal">
  <div class="modal-content">
    <h2>Escalation Survey</h2>
    <form id="surveyForm">
      <div class="field"><label class="label req">Account Number</label><input class="ctrl" name="account_number" required></div>
      <div class="field"><label class="label req">CNS ID</label><input class="ctrl" name="cns_id" required></div>
      <div class="field"><label class="label req">Agent ID</label><input class="ctrl" name="agent_id" required></div>
      <div class="field">
        <label class="label req">Service Provider</label>
        <div class="radio-row">
          <label><input type="radio" name="service_provider" value="Openreach" required> Openreach</label>
          <label><input type="radio" name="service_provider" value="CityFibre" required> CityFibre</label>
        </div>
      </div>
      <div class="field">
        <label class="label req">Was MyPhoto used?</label>
        <div class="radio-row">
          <label><input type="radio" name="myphoto" value="Yes" required> Yes</label>
          <label><input type="radio" name="myphoto" value="No" required> No</label>
          <label><input type="radio" name="myphoto" value="Not Required" required> Not Required</label>
        </div>
        <input class="ctrl" name="myphoto_reason" id="myphoto_reason" placeholder="Reason if not used" style="display:none; margin-top:6px;">
      </div>
      <div class="field">
        <label class="label req">Service</label>
        <div class="radio-row">
          <label><input type="radio" name="service" value="FTTP" required> FTTP</label>
          <label><input type="radio" name="service" value="VOIP" required> VOIP</label>
          <label><input type="radio" name="service" value="NOW FTTP (only with Issue)" required> NOW FTTP</label>
          <label><input type="radio" name="service" value="SC Issues" required> SC Issues</label>
          <label><input type="radio" name="service" value="Not a Proper escalation" required> Not a Proper escalation</label>
        </div>
      </div>
      <div class="field">
        <label class="label req">Outcome</label>
        <div class="check-col" id="outcomeCol">
          <?php
          $outcomes = [
            "Engineer Booked after t/s was done","No PSU - Engineer Booked","ONT Missing - Engineer Booked",
            "Troubleshooting Advised","Customer to monitor / Outage","Escalated to Network",
            "CRF Raised","Equipment Replaced","ONT Serial Number Missmatch",
            "SC Issue - Assistance booking OR Engineer","Feedback Provided","No Response","Customer Hang Up"
          ];
          foreach($outcomes as $o){
            echo '<label><input type="checkbox" name="outcome[]" value="'.htmlspecialchars($o).'"> '.$o.'</label>';
          }
          ?>
        </div>
      </div>
      <div class="field">
        <label class="label req">Feedback provided by CNS?</label>
        <div class="radio-row">
          <label><input type="radio" name="feedback" value="Yes" required> Yes</label>
          <label><input type="radio" name="feedback" value="No" required> No</label>
        </div>
        <input class="ctrl" name="feedback_details" id="feedback_details" placeholder="Feedback details" style="display:none; margin-top:6px;">
      </div>
      <div class="field">
        <label class="label req">Handling time (minutes)</label>
        <input class="ctrl" type="number" name="handling_time" min="1" step="1" required>
      </div>
      <div class="actions">
        <button type="submit" class="btn">Submit & Back to Queue</button>
      </div>
    </form>
  </div>
</div>

<script>
const USERNAME = <?= json_encode($username) ?>;
const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;

function getName() {
  if(IS_ADMIN){
    const el = document.getElementById('username');
    const val = el?.value.trim();
    if(!val){ alert("Please enter a name."); return null; }
    return val;
  }
  return USERNAME;
}

function setQueueButtonsState(enabled){
  document.querySelectorAll('#queueButtons button, #queueButtons input[type=text]').forEach(el=>el.disabled=!enabled);
}

function postAction(action){
  const name = getName();
  if(!name) return;
  const body = IS_ADMIN ? `action=${encodeURIComponent(action)}&name=${encodeURIComponent(name)}` : `action=${encodeURIComponent(action)}`;
  fetch('backend.php',{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body
  }).then(()=>loadQueue());
}

function enterChat(){ postAction('enter_chat'); }
function goLunch(){ postAction('lunch'); }
function goBreak(){ postAction('break'); }
function endChat(){ postAction('end_chat'); }
function leaveQueue(){ postAction('leave_queue'); }

function takeChat() {
    const name = getName();
    if (!name) return;
    fetch('backend.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=took_chat&name=${encodeURIComponent(name)}`
    }).then(res => res.json())
      .then(res => {
          if(res.status === 'ok'){
              loadQueue();
              surveyModal.style.display='block';
              setQueueButtonsState(false); // disable buttons while modal open
          } else alert('Failed to take chat.');
      });
}

function loadQueue(){
    fetch('backend.php?action=get_queue')
    .then(res=>res.json())
    .then(data=>{
        const active=document.getElementById('queueDisplay');
        const breaks=document.getElementById('breakDisplay');
        const chats=document.getElementById('onChatDisplay');
        const nextInQueue=document.getElementById('nextInQueue');
        active.innerHTML=''; breaks.innerHTML=''; chats.innerHTML='';
        if(!Array.isArray(data)){ active.innerHTML='Error'; breaks.innerHTML='Error'; chats.innerHTML='Error'; nextInQueue.textContent='Error'; return; }

        const now=Math.floor(Date.now()/1000);
        const activeUsers=data.filter(u=>u.status==='available');
        const nonProd=data.filter(u=>u.status==='lunch'||u.status==='break');
        const onChatUsers=data.filter(u=>u.status==='on_chat');

        nextInQueue.textContent = activeUsers[0]?.name ? `Next in Queue: ${activeUsers[0].name}` : 'Queue is empty.';

        if(activeUsers.length===0) active.innerHTML='<p>No users in queue.</p>';
        else activeUsers.forEach((u,i)=>{ const div=document.createElement('div'); div.className='queue-entry'; div.innerHTML=`<strong>${i+1}. ${u.name}</strong>`; active.appendChild(div); });

        if(nonProd.length===0) breaks.innerHTML='<p>No users on lunch or break.</p>';
        else nonProd.forEach(u=>{ const remaining=(u.expires_at??now)-now; const mins=Math.floor(remaining/60); const secs=remaining%60; const div=document.createElement('div'); div.className='queue-entry'; div.innerHTML=`<strong>${u.name}</strong> <span class="status">(${u.status}, ends in ${mins}m ${secs}s)</span>`; breaks.appendChild(div); });

        if(onChatUsers.length===0) chats.innerHTML='<p>No users currently on chat.</p>';
        else onChatUsers.forEach(u=>{ const div=document.createElement('div'); div.className='queue-entry'; div.innerHTML=`<strong>${u.name}</strong> <span class="status">(on chat)</span>`; chats.appendChild(div); });
    });
}

// Check if user is already on chat on page load
function checkChatModalOnLoad(){
    fetch('backend.php?action=get_queue')
    .then(res => res.json())
    .then(data => {
        const name = getName();
        if(!name) return;
        const user = data.find(u => u.name === name && u.status === 'on_chat');
        if(user){
            surveyModal.style.display = 'block';
            setQueueButtonsState(false);
        }
    });
}

setInterval(loadQueue,5000);
window.onload = () => {
    loadQueue();
    checkChatModalOnLoad();
};

// Survey modal logic
const surveyModal=document.getElementById('surveyModal');
const surveyForm=document.getElementById('surveyForm');
const myphotoRadios=surveyForm.querySelectorAll('input[name="myphoto"]');
const feedbackRadios=surveyForm.querySelectorAll('input[name="feedback"]');
const myphotoReason=document.getElementById('myphoto_reason');
const feedbackDetails=document.getElementById('feedback_details');

myphotoRadios.forEach(r=>r.addEventListener('change',()=>{ 
    myphotoReason.style.display=(r.value==='No'||r.value==='Not Required')?'block':'none'; 
    myphotoReason.required=(r.value==='No'||r.value==='Not Required'); 
    if(!myphotoReason.required) myphotoReason.value=''; 
}));
feedbackRadios.forEach(r=>r.addEventListener('change',()=>{ 
    feedbackDetails.style.display=(r.value==='Yes')?'block':'none'; 
    feedbackDetails.required=(r.value==='Yes'); 
    if(!feedbackDetails.required) feedbackDetails.value=''; 
}));

surveyForm.addEventListener('submit', e=>{
    e.preventDefault();
    const anyOutcome=surveyForm.querySelector('input[name="outcome[]"]:checked');
    if(!anyOutcome){ alert('Please select at least one Outcome.'); return; }
    const formData=new FormData(surveyForm);
    const obj={};
    formData.forEach((v,k)=>{ if(obj[k]){ if(Array.isArray(obj[k])) obj[k].push(v); else obj[k]=[obj[k],v]; } else obj[k]=v; });

    fetch('survey_save.php',{method:'POST',body:JSON.stringify(obj)})
    .then(res=>res.json())
    .then(res=>{
        if(res.status==='ok'){
            // End the chat timer only
            const name = getName();
            fetch('backend.php',{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:`action=end_chat_survey&name=${encodeURIComponent(name)}`
            }).then(()=>loadQueue());

            surveyModal.style.display='none';
            surveyForm.reset();
            myphotoReason.style.display='none';
            feedbackDetails.style.display='none';
            setQueueButtonsState(true); // re-enable buttons
        } else alert('Survey submission failed.');
    });
});
</script>
</body>
</html>
