const surveyContainer = document.getElementById('surveyContainer');
const surveyForm = document.getElementById('surveyForm');
const notificationContainer = document.getElementById('notificationContainer');
const myphotoRadios = surveyForm.querySelectorAll('input[name="myphoto"]');
const feedbackRadios = surveyForm.querySelectorAll('input[name="feedback"]');
const myphotoReason = document.getElementById('myphotoReason');
const myphotoReasonLabel = document.getElementById('myphotoReasonLabel');
const feedbackDetails = document.getElementById('feedbackDetails');
const feedbackDetailsLabel = document.getElementById('feedbackDetailsLabel');

function showNotification(message, type='info', timeout=4000){
    const div = document.createElement('div');
    div.className = `notification ${type}`;
    div.innerText = message;
    notificationContainer.appendChild(div);
    setTimeout(()=> div.remove(), timeout);
}

myphotoRadios.forEach(radio=>{
    radio.addEventListener('change', ()=>{
        if(radio.value==='No' || radio.value==='Not Required'){
            myphotoReason.style.display='inline-block';
            myphotoReasonLabel.style.display='inline-block';
            myphotoReason.required=true;
        } else {
            myphotoReason.style.display='none';
            myphotoReasonLabel.style.display='none';
            myphotoReason.required=false;
        }
    });
});

feedbackRadios.forEach(radio=>{
    radio.addEventListener('change', ()=>{
        if(radio.value==='Yes'){
            feedbackDetails.style.display='inline-block';
            feedbackDetailsLabel.style.display='inline-block';
            feedbackDetails.required=true;
        } else {
            feedbackDetails.style.display='none';
            feedbackDetailsLabel.style.display='none';
            feedbackDetails.required=false;
        }
    });
});

surveyForm.addEventListener('submit', function(e){
    e.preventDefault();
    const formData = new FormData(surveyForm);
    fetch('save_survey.php', {
        method:'POST',
        body:formData
    }).then(r=>r.text()).then(res=>{
        if(res.trim()=='OK'){
            showNotification('Survey submitted successfully!', 'info');
            surveyForm.reset();
            surveyContainer.style.display='none';
            endChat(); // call backend end chat
        } else {
            showNotification('Failed to save survey','error');
        }
    }).catch(err=>{
        showNotification('Error saving survey','error');
    });
});

function showSurvey(){
    surveyForm.reset();
    surveyContainer.style.display='block';
    window.scrollTo({top:surveyContainer.offsetTop, behavior:'smooth'});
}
