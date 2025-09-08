<?php
session_start();
if (!isset($_SESSION['username'])) {
    $_SESSION['username'] = "Zlatko.Ivanov"; // default for testing
}
$username = $_SESSION['username'];

// Load user info
$usersFile = __DIR__."/users_data.json";
$usersData = json_decode(file_get_contents($usersFile), true);
$userInfo = $usersData[$username] ?? ["color"=>"#3498db","work_shift_hours"=>8];
$formattedName = str_replace('.', ' ', $username);
$shiftHours = $userInfo['work_shift_hours'];
$userColor = $userInfo['color'];

// Banner text
$bannerText = "Logged in as: {$formattedName} | Shift: {$shiftHours}h";

// Schedule file
$scheduleFile = __DIR__."/schedule.json";
if(!file_exists($scheduleFile)) file_put_contents($scheduleFile, "{}");

$action = $_GET['action'] ?? '';

if($action === 'fetch') {
    header("Content-Type: application/json");
    echo file_get_contents($scheduleFile);
    exit;
}

if($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) exit;
    $data = json_decode(file_get_contents($scheduleFile), true);
    $data[$username] = $input;
    file_put_contents($scheduleFile, json_encode($data, JSON_PRETTY_PRINT));
    echo json_encode(["ok"=>true]);
    exit;
}

// Clear my breaks
if($action==='clear' && $_SERVER['REQUEST_METHOD']==='POST'){
    $data = json_decode(file_get_contents($scheduleFile),true);
    $data[$username] = [];
    file_put_contents($scheduleFile,json_encode($data,JSON_PRETTY_PRINT));
    echo json_encode(["ok"=>true]);
    exit;
}

// Change color
if($action==='change_color' && $_SERVER['REQUEST_METHOD']==='POST'){
    $input = json_decode(file_get_contents("php://input"),true);
    if(isset($input['color'])){
        $usersData[$username]['color']=$input['color'];
        file_put_contents($usersFile,json_encode($usersData,JSON_PRETTY_PRINT));
        echo json_encode(["ok"=>true]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Break Planner</title>
<style>
body {font-family:"Segoe UI", Roboto, Arial; background:#f0f2f5; margin:0; padding:20px;}
h1{text-align:center; margin-bottom:5px;}
#banner{text-align:center; font-weight:600; margin-bottom:15px; color:#333;}
#clock{text-align:center;font-size:1.2em;margin-bottom:10px;color:#444;}
.toolbar{text-align:center;margin-bottom:20px;}
.draggable{display:inline-block;margin:5px;padding:10px 16px;border-radius:8px;background:#4a90e2;color:#fff;font-weight:600;cursor:grab;transition:transform 0.2s; user-select:none;}
.draggable:hover{transform:scale(1.05);}

/* Schedule adjustments */
.grid-container{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:10px;
    width:70vw;      /* 70% of viewport width */
    margin:0 auto;   /* center schedule */
}
.grid{background:#fff;border-radius:12px;padding:8px;box-shadow:0 6px 20px rgba(0,0,0,0.08);}
.row{display:grid;grid-template-columns:120px 1fr;border-bottom:1px solid #e0e0e0;}
.row:last-child{border-bottom:0;}
.time{padding:12px;background:#f7f8fa;font-weight:600;border-right:1px solid #e0e0e0;}
.slot{position:relative;cursor:pointer;}
.halfSlot{height:50px;border-top:1px dashed #e0e0e0;position:relative; transition: background 0.2s;}
.halfSlot:first-child{border-top:none;}
.halfSlot.hovered{background:rgba(52,152,219,0.3);}
.event{position:absolute;inset:2px;border-radius:8px;color:#fff;display:flex;flex-direction:column;justify-content:center;padding:6px;font-size:16px;animation:fadeIn 0.4s ease; cursor:default;}
.event .head{font-weight:700;display:flex;justify-content:space-between;align-items:center; font-size:16px;}
.event .kill{border:none;background:rgba(255,255,255,0.25);border-radius:6px;color:#fff;padding:2px 6px;cursor:pointer;font-size:14px;}
@keyframes fadeIn{from{opacity:0;transform:translateY(4px);}to{opacity:1;transform:translateY(0);} }
#clear-my-slots,#change-color{padding:8px 16px;border-radius:6px;border:none;cursor:pointer;font-weight:600;margin-bottom:15px;}
#clear-my-slots{background:#e74c3c;color:#fff;}
#change-color{background:#3498db;color:#fff;}
</style>

</head>
<body>
<h1>Break Planner</h1>
<div id="banner"><?=htmlspecialchars($bannerText)?></div>
<div id="clock"></div>

<div style="text-align:center;">
    <button id="clear-my-slots">Clear My Breaks/Lunch</button>
    <button id="change-color">Change Color</button>
</div>

<div class="toolbar">
    <div class="draggable" draggable="true" data-type="Break 1" data-duration="15">Break 1 (15m)</div>
    <div class="draggable" draggable="true" data-type="Break 2" data-duration="15">Break 2 (15m)</div>
    <?php if($shiftHours==10): ?>
        <div class="draggable" draggable="true" data-type="Break 3" data-duration="15">Break 3 (15m)</div>
    <?php endif; ?>
    <div class="draggable" draggable="true" data-type="Lunch" data-duration="30">Lunch (30m)</div>
</div>
<div id="drag-instruction" style="text-align:center; font-size:0.9em; font-style:italic; color:#555; margin-bottom:15px;">
    Drag and drop your breaks/lunch onto the schedule
</div>

<div class="grid-container">
    <div id="col1" class="grid"></div>
    <div id="col2" class="grid"></div>
</div>

<script>
const username = "<?=htmlspecialchars($username)?>";
let userColor = "<?= $userColor ?>";
const shiftHours = <?= $shiftHours ?>;
const START=10*60; const END=22*60+30; const STEP=30;
let slotsIndex={};
const usersData = <?= json_encode($usersData) ?>;

// Clock
function updateClock(){
    const now=new Date();
    const options={hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false,timeZone:'Europe/Sofia'};
    document.getElementById("clock").textContent="Current time: "+now.toLocaleTimeString('bg-BG',options);
}
setInterval(updateClock,1000); updateClock();

function getAllowance(){
    if(shiftHours===8) return {breaks:2,lunch:1};
    if(shiftHours===10) return {breaks:3,lunch:1};
    return {breaks:2,lunch:1};
}
const allowance = getAllowance();

function addMinutes(h,m,add){let total=h*60+m+add; return String(Math.floor(total/60)).padStart(2,"0")+":"+String(total%60).padStart(2,"0");}

// User color from JSON
function getUserColor(user){ return usersData[user]?.color || "#3498db"; }

function buildCalendar() {
    const col1=document.getElementById("col1"), col2=document.getElementById("col2");
    col1.innerHTML=""; col2.innerHTML="";
    let rows=[];
    for(let t=START;t<END;t+=STEP){
        const row=document.createElement("div"); row.className="row";
        const th=document.createElement("div"); th.className="time";
        const h=Math.floor(t/60), m=t%60;
        th.textContent=`${String(h).padStart(2,"0")}:${String(m).padStart(2,"0")}`;
        const td=document.createElement("div"); td.className="slot drop";

        for(let half=0;half<2;half++){
            const halfDiv=document.createElement("div"); halfDiv.className="halfSlot";
            halfDiv.dataset.time=addMinutes(h,m,half*15);

            halfDiv.addEventListener("dragover", e=>e.preventDefault());

            halfDiv.addEventListener("dragenter", e=>{
                const type=e.dataTransfer.getData("x-type");
                const [hh,mm]=halfDiv.dataset.time.split(":").map(Number);
                const nextTime=addMinutes(hh,mm,15);
                halfDiv.classList.add("hovered");
                if(type.includes("Lunch") && slotsIndex[nextTime]) slotsIndex[nextTime].classList.add("hovered");
            });
            halfDiv.addEventListener("dragleave", e=>{
                const type=e.dataTransfer.getData("x-type");
                const [hh,mm]=halfDiv.dataset.time.split(":").map(Number);
                const nextTime=addMinutes(hh,mm,15);
                halfDiv.classList.remove("hovered");
                if(type.includes("Lunch") && slotsIndex[nextTime]) slotsIndex[nextTime].classList.remove("hovered");
            });

            halfDiv.addEventListener("drop", e=>{
                e.preventDefault();
                halfDiv.classList.remove("hovered");
                const type=e.dataTransfer.getData("x-type");
                const duration=parseInt(e.dataTransfer.getData("x-duration")||"0",10);
                if(!type||!duration) return;
                const timesToUse=[halfDiv.dataset.time];
                if(duration>15){
                    const [hh,mm]=halfDiv.dataset.time.split(":").map(Number);
                    const nextTime=addMinutes(hh,mm,15);
                    if(!slotsIndex[nextTime]){ alert("Cannot place Lunch here!"); return; }
                    const existingNext=slotsIndex[nextTime].querySelector(".event");
                    if(existingNext && existingNext.dataset.user!==username){ alert("Next slot occupied!"); return; }
                    timesToUse.push(nextTime);
                }
                for(const t of timesToUse){ 
    const existing = slotsIndex[t].querySelector(".event"); 
    if(existing){ 
        alert("Cannot place " + type + " here: slot already occupied by " + existing.dataset.user); 
        return; 
    } 
}

                if(type.includes("Lunch")) placeEventMerged(timesToUse[0],username,type);
                else placeEvent(timesToUse[0],username,type);

                // PERMANENTLY hide draggable once placed
                const dragEl=document.querySelector(`.draggable[data-type="${type}"]`);
                if(dragEl) dragEl.style.display="none";

                saveSchedule();
            });

            td.appendChild(halfDiv);
            slotsIndex[halfDiv.dataset.time]=halfDiv;
        }

        row.appendChild(th); row.appendChild(td); rows.push(row);
    }
    const half=Math.ceil(rows.length/2);
    rows.slice(0,half).forEach(r=>col1.appendChild(r));
    rows.slice(half).forEach(r=>col2.appendChild(r));
}
buildCalendar();

// Draggable start
document.querySelectorAll(".draggable").forEach(el=>{
    el.addEventListener("dragstart", e=>{
        e.dataTransfer.setData("x-type", el.dataset.type);
        e.dataTransfer.setData("x-duration", el.dataset.duration);
    });
});

function placeEvent(time,user,type){
    const td=slotsIndex[time]; if(!td) return;
    let duration=type.includes("Lunch")?30:15;
    let timesToUse=[time];
    if(duration>15){ const [hh,mm]=time.split(":").map(Number); const nextTime=addMinutes(hh,mm,15); if(slotsIndex[nextTime]) timesToUse.push(nextTime);}
    for(const t of timesToUse){ const existing=slotsIndex[t].querySelector(".event"); if(existing && existing.dataset.user!==username){alert("Slot taken!"); return;}}
    td.innerHTML="";
    const div=document.createElement("div"); div.className="event";
    div.style.background=user===username?userColor:getUserColor(user);
    div.dataset.user=user; div.dataset.type=type; div.dataset.time=time;
    const [hh,mm]=time.split(":").map(Number); const endTime=addMinutes(hh,mm,duration);
    div.style.position="absolute"; div.style.top="0"; div.style.left="0"; div.style.right="0"; div.style.height=duration>15?"calc(100% * 2)":"100%";
    div.innerHTML=`<div class="head">${user}${user===username?'<button class="kill">✕</button>':''}</div><div style="display:flex;justify-content:space-between;font-size:14px;"><span>${type}</span><span>${time} - ${endTime}</span></div>`;
    td.appendChild(div);
    if(duration>15 && timesToUse[1]){ const secondSlot=slotsIndex[timesToUse[1]]; secondSlot.innerHTML=""; secondSlot.style.borderTop="none"; }

    if(user===username){
        div.querySelector(".kill")?.addEventListener("click", ()=>{
            timesToUse.forEach(t=>{ const slot=slotsIndex[t]; slot.innerHTML=""; slot.style.borderTop="1px dashed #e0e0e0"; });
            const dragEl=document.querySelector(`.draggable[data-type="${type}"]`);
            if(dragEl) dragEl.style.display="inline-block";
            saveSchedule();
        });
    }
}

function placeEventMerged(time,user,type){ placeEvent(time,user,type); }

function saveSchedule(){
    let slots=[];
    document.querySelectorAll(".event").forEach(ev=>{ if(ev.dataset.user===username) slots.push({time:ev.dataset.time,type:ev.dataset.type}); });
    fetch("?action=save",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(slots)});
    updateToolbarDraggables();
}

function loadSchedule(){
    fetch("?action=fetch").then(r=>r.json()).then(data=>{
        Object.values(slotsIndex).forEach(td=>td.innerHTML="");
        for(let user in data){ data[user].forEach(ev=>placeEvent(ev.time,user,ev.type)); }
        updateToolbarDraggables();
    });
}
loadSchedule(); setInterval(loadSchedule,10000);

document.getElementById("clear-my-slots").addEventListener("click", ()=>{
    fetch("?action=clear",{method:"POST"}).then(()=>{
        loadSchedule();
        location.reload();
    });
});

document.getElementById("change-color").addEventListener("click", ()=>{
    // Create a temporary color input
    const colorInput = document.createElement("input");
    colorInput.type = "color";
    colorInput.value = userColor; // current color
    colorInput.style.position = "fixed"; // keep it out of view
    colorInput.style.left = "-9999px"; 
    document.body.appendChild(colorInput);

    // When user picks a color
    colorInput.addEventListener("input", () => {
        const newColor = colorInput.value;
        userColor = newColor;
        fetch("?action=change_color",{
            method:"POST",
            headers:{"Content-Type":"application/json"},
            body: JSON.stringify({color:newColor})
        }).then(()=>loadSchedule());
    });

    // Simulate a click to open picker
    colorInput.click();

    // Remove input after use
    colorInput.addEventListener("change", ()=> document.body.removeChild(colorInput));
});


function updateToolbarDraggables(){
    fetch("?action=fetch").then(r=>r.json()).then(data=>{
        const myEvents = data[username] || [];
        let anyVisible = false;

        document.querySelectorAll(".draggable").forEach(drag=>{
            const type = drag.dataset.type;
            const exists = myEvents.some(ev => ev.type === type);
            drag.style.display = exists ? "none" : "inline-block";
            if(!exists) anyVisible = true;
        });

        // Show/hide instruction
        document.getElementById("drag-instruction").style.display = anyVisible ? "block" : "none";
    });
}


</script>
</body>
</html>
