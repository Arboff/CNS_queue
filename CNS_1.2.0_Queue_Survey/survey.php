<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$saveMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $account_number   = trim($_POST['account_number'] ?? '');
    $cns_id           = trim($_POST['cns_id'] ?? '');
    $agent_id         = trim($_POST['agent_id'] ?? '');
    $service_provider = $_POST['service_provider'] ?? '';
    $myphoto          = $_POST['myphoto'] ?? '';
    $myphoto_reason   = trim($_POST['myphoto_reason'] ?? '');
    $service          = $_POST['service'] ?? '';
    $outcome          = $_POST['outcome'] ?? []; // array
    $feedback         = $_POST['feedback'] ?? '';
    $feedback_details = trim($_POST['feedback_details'] ?? '');
    $handling_time    = trim($_POST['handling_time'] ?? '');

    // Enforce conditional fields server-side too
    if (!in_array($myphoto, ['Not Required', 'No'])) {
        $myphoto_reason = '';
    }
    if ($feedback !== 'Yes') {
        $feedback_details = '';
    }

    // Build row
    $survey = [
        "account_number"   => $account_number,
        "cns_id"           => $cns_id,
        "agent_id"         => $agent_id,
        "service_provider" => $service_provider,
        "myphoto"          => $myphoto,
        "myphoto_reason"   => $myphoto_reason,
        "service"          => $service,
        "outcome"          => array_values((array)$outcome),
        "feedback"         => $feedback,
        "feedback_details" => $feedback_details,
        "handling_time"    => $handling_time,
        "timestamp"        => date("Y-m-d H:i:s")
    ];

    // Save
    $file = 'surveys.json';
    $data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    if (!is_array($data)) $data = [];
    $data[] = $survey;
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

    $saveMessage = 'Survey submitted successfully!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Survey</title>
<link rel="stylesheet" href="style.css">
<style>
  :root {
    --primary: #3498db;
    --primary-dark: #2980b9;
    --bg: #f5f7fa;
    --card: #ffffff;
    --text: #2c3e50;
    --muted: #7f8c8d;
    --border: #e1e8f0;
    --ok: #2ecc71;
    --warn: #e67e22;
    --error: #e74c3c;
    --shadow: 0 10px 24px rgba(0,0,0,.08);
  }
  body { background: var(--bg); color: var(--text); }
  .page-wrap { max-width: 980px; margin: 40px auto; padding: 0 16px; }
  .card {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 14px; box-shadow: var(--shadow); padding: 22px;
  }
  .header {
    display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px;
  }
  .header h1 { margin:0; font-size:1.6rem; }
  .nav { display:flex; gap:10px; flex-wrap:wrap; }
  .btn {
    display:inline-flex; align-items:center; justify-content:center; gap:6px;
    padding:10px 14px; border-radius:10px; border:1px solid var(--border);
    background: var(--primary); color:#fff; font-weight:600; cursor:pointer; text-decoration:none;
    box-shadow: 0 6px 16px rgba(52,152,219,.25);
  }
  .btn:hover { background: var(--primary-dark); }
  .btn.secondary { background:#fff; color:var(--text); }
  .btn.secondary:hover { background:#f2f6fa; }
  .success {
    background: #eafaf1; border:1px solid #bff0d1; color:#1e7e34;
    padding:10px 14px; border-radius:10px; margin-bottom:14px; font-weight:600;
  }

  .form-grid {
    display:grid; grid-template-columns: repeat(12, 1fr); gap:14px;
  }
  .field { grid-column: span 6; display:flex; flex-direction:column; }
  .field.full { grid-column: 1 / -1; }
  .label { font-weight:700; margin-bottom:6px; }
  .hint { font-size:.9rem; color: var(--muted); margin-top:4px; }
  .ctrl {
    width:100%; padding:12px; border:1px solid var(--border); border-radius:10px;
    background:#fff; font-size:1rem;
  }
  .radio-row, .check-col {
    display:flex; gap:18px; flex-wrap:wrap; align-items:center;
  }
  .check-col { flex-direction:column; align-items:flex-start; max-height:260px; overflow:auto; padding:8px 10px; border:1px dashed var(--border); border-radius:10px; }
  .actions { display:flex; gap:12px; justify-content:flex-end; margin-top:10px; }
  .req::after { content:" *"; color: var(--error); font-weight:700; }

  @media (max-width: 720px) {
    .field { grid-column: 1 / -1; }
    .nav { width:100%; }
  }
</style>
</head>
<body>
<div class="page-wrap">
  <div class="header">
    <h1>Escalation Survey</h1>
    <div class="nav">
      <a class="btn secondary" href="index.php">← Back to Queue</a>
      <a class="btn secondary" href="json_parser.php">📊 Parser</a>
    </div>
  </div>

  <div class="card">
    <?php if ($saveMessage): ?>
      <div class="success"><?= htmlspecialchars($saveMessage) ?></div>
    <?php endif; ?>

    <form method="post" id="surveyForm" novalidate>
      <div class="form-grid">
        <div class="field">
          <label class="label req">1. Account Number</label>
          <input class="ctrl" type="text" name="account_number" required>
        </div>

        <div class="field">
          <label class="label req">2. Your ID (CNS)</label>
          <input class="ctrl" type="text" name="cns_id" required>
        </div>

        <div class="field">
          <label class="label req">3. Agent's ID</label>
          <input class="ctrl" type="text" name="agent_id" required>
        </div>

        <div class="field">
          <label class="label req">4. Service Provider</label>
          <div class="radio-row">
            <label><input type="radio" name="service_provider" value="Openreach" required> Openreach</label>
            <label><input type="radio" name="service_provider" value="CityFibre" required> CityFibre</label>
          </div>
        </div>

        <div class="field full">
          <label class="label req">5. Was MyPhoto used prior to posting?</label>
          <div class="radio-row" id="myphotoGroup">
            <label><input type="radio" name="myphoto" value="Yes" required> Yes</label>
            <label><input type="radio" name="myphoto" value="Not Required" required> Not Required</label>
            <label><input type="radio" name="myphoto" value="No" required> No</label>
          </div>
          <div class="field" id="myphotoReasonWrap" style="display:none; margin-top:8px;">
            <label class="label req">Why was MyPhoto not used?</label>
            <input class="ctrl" type="text" name="myphoto_reason" id="myphoto_reason">
          </div>
        </div>

        <div class="field full">
          <label class="label req">6. Service</label>
          <div class="radio-row">
            <label><input type="radio" name="service" value="FTTP" required> FTTP</label>
            <label><input type="radio" name="service" value="VOIP" required> VOIP</label>
            <label><input type="radio" name="service" value="NOW FTTP (only with Issue)" required> NOW FTTP (only with Issue)</label>
            <label><input type="radio" name="service" value="SC Issues" required> SC Issues</label>
            <label><input type="radio" name="service" value="Not a Proper escalation" required> Not a Proper escalation</label>
          </div>
        </div>

        <div class="field full">
          <label class="label req">8. Outcome (select at least one)</label>
          <div class="check-col" id="outcomeCol">
            <?php
              $outcomes = [
                "Engineer Booked after t/s was done",
                "No PSU - Engineer Booked",
                "ONT Missing - Engineer Booked",
                "Troubleshooting Advised",
                "Customer to monitor / Outage",
                "Escalated to Network",
                "CRF Raised",
                "Equipment Replaced",
                "ONT Serial Number Missmatch",
                "SC Issue - Assistance booking OR Engineer",
                "Feedback Provided",
                "No Response",
                "Customer Hang Up"
              ];
              foreach ($outcomes as $o) {
                echo '<label><input type="checkbox" name="outcome[]" value="'.htmlspecialchars($o).'"> '.$o.'</label>';
              }
            ?>
          </div>
          <div class="hint">Hold Ctrl/Cmd to select quickly; at least one must be checked.</div>
        </div>

        <div class="field">
          <label class="label req">9. Was Feedback provided by CNS?</label>
          <div class="radio-row" id="feedbackGroup">
            <label><input type="radio" name="feedback" value="Yes" required> Yes</label>
            <label><input type="radio" name="feedback" value="No" required> No</label>
          </div>
          <div class="field" id="feedbackWrap" style="display:none; margin-top:8px;">
            <label class="label req">What feedback was provided?</label>
            <input class="ctrl" type="text" name="feedback_details" id="feedback_details">
          </div>
        </div>

        <div class="field">
          <label class="label req">10. Handling time (minutes)</label>
          <input class="ctrl" type="number" name="handling_time" min="1" step="1" inputmode="numeric" required>
        </div>
      </div>

      <div class="actions">
        <button type="submit" class="btn">Submit Survey</button>
      </div>
    </form>
  </div>
</div>

<script>
  // Conditional fields (MyPhoto + Feedback)
  const myphotoGroup = document.getElementById('myphotoGroup');
  const myphotoReasonWrap = document.getElementById('myphotoReasonWrap');
  const myphotoReason = document.getElementById('myphoto_reason');

  const feedbackGroup = document.getElementById('feedbackGroup');
  const feedbackWrap = document.getElementById('feedbackWrap');
  const feedbackDetails = document.getElementById('feedback_details');

  function updateMyPhoto() {
    const val = (document.querySelector('input[name="myphoto"]:checked')||{}).value;
    const needsReason = (val === 'Not Required' || val === 'No');
    myphotoReasonWrap.style.display = needsReason ? 'block' : 'none';
    myphotoReason.required = needsReason;
    if (!needsReason) myphotoReason.value = '';
  }
  function updateFeedback() {
    const val = (document.querySelector('input[name="feedback"]:checked')||{}).value;
    const needsDetails = (val === 'Yes');
    feedbackWrap.style.display = needsDetails ? 'block' : 'none';
    feedbackDetails.required = needsDetails;
    if (!needsDetails) feedbackDetails.value = '';
  }
  myphotoGroup.addEventListener('change', updateMyPhoto);
  feedbackGroup.addEventListener('change', updateFeedback);

  // Require at least one outcome
  const form = document.getElementById('surveyForm');
  form.addEventListener('submit', (e) => {
    const anyOutcome = !!document.querySelector('input[name="outcome[]"]:checked');
    if (!anyOutcome) {
      e.preventDefault();
      alert('Please select at least one Outcome.');
    }
  });
</script>
</body>
</html>
