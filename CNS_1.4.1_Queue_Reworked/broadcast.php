<?php
session_start();
if (!isset($_SESSION['username']) || !$_SESSION['is_admin']) {
    header("Location: index.php");
    exit;
}

$messageFile = __DIR__ . "/broadcast.json";
$success = $error = "";

// Handle new broadcast or clear request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear'])) {
        if (file_exists($messageFile)) {
            unlink($messageFile);
        }
        $success = "Broadcast cleared successfully.";
    } else {
        $message = trim($_POST['message'] ?? '');
        $imageData = null;

        if ($message === '') {
            $error = "Message cannot be empty.";
        } else {
            // Handle image upload
            if (!empty($_FILES['image']['tmp_name'])) {
                $fileTmp  = $_FILES['image']['tmp_name'];
                $fileType = mime_content_type($fileTmp);
                if (strpos($fileType, 'image/') === 0) {
                    $imageData = 'data:' . $fileType . ';base64,' . base64_encode(file_get_contents($fileTmp));
                } else {
                    $error = "Uploaded file must be an image.";
                }
            }

            if (!$error) {
                $broadcast = [
                    'message'   => $message,
                    'timestamp' => time(),
                    'image'     => $imageData
                ];
                file_put_contents($messageFile, json_encode($broadcast, JSON_PRETTY_PRINT));
                $success = "Broadcast sent successfully!";
            }
        }
    }
}

// Load current broadcast if exists
$currentBroadcast = file_exists($messageFile) ? json_decode(file_get_contents($messageFile), true) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Broadcast Message</title>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f0f2f5;
    margin: 0;
    padding: 0;
}
.container {
    max-width: 600px;
    margin: 40px auto;
    background: #fff;
    padding: 30px 40px;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
h1 {
    text-align: center;
    color: #333;
    margin-bottom: 20px;
}
label {
    font-weight: bold;
    display: block;
    margin-top: 15px;
    margin-bottom: 5px;
}
input[type="text"], textarea, input[type="file"] {
    width: 100%;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 14px;
    box-sizing: border-box;
}
button {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    background: #4a90e2;
    color: #fff;
    font-size: 15px;
    cursor: pointer;
    margin-top: 15px;
}
button:hover {
    background: #357ABD;
}
.success, .error {
    padding: 10px;
    margin-top: 15px;
    border-radius: 6px;
    font-weight: bold;
    text-align: center;
}
.success {
    background: #d4edda;
    color: #155724;
}
.error {
    background: #f8d7da;
    color: #721c24;
}
.image-preview {
    margin-top: 10px;
    max-height: 150px;
    border-radius: 6px;
    display: none;
    object-fit: contain;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}
.back-button {
    display: inline-block;
    margin-top: 20px;
    background: #6c757d;
}
.back-button:hover {
    background: #5a6268;
}
.current-broadcast {
    background: #fff3cd;
    border: 1px solid #ffeeba;
    padding: 15px;
    border-radius: 6px;
    margin-top: 20px;
}
</style>
</head>
<body>
<div class="container">
<h1>Broadcast Message</h1>

<?php if($success): ?>
    <div class="success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- New Broadcast Form -->
<form method="post" enctype="multipart/form-data">
    <label>Message:</label>
    <textarea name="message" rows="4" placeholder="Enter your broadcast message" required><?= isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '' ?></textarea>

    <label>Attach Image (optional):</label>
    <input type="file" name="image" accept="image/*" id="imageInput">
    <img class="image-preview" id="imagePreview" alt="Image Preview">

    <button type="submit">Send Broadcast</button>
</form>

<!-- Current Broadcast -->
<?php if($currentBroadcast): ?>
<div class="current-broadcast">
    <h3>Current Broadcast</h3>
    <p><?= htmlspecialchars($currentBroadcast['message']) ?></p>
    <?php if(!empty($currentBroadcast['image'])): ?>
        <img src="<?= $currentBroadcast['image'] ?>" alt="Broadcast Image" style="max-width:100%; margin-top:10px; border-radius:6px;">
    <?php endif; ?>
    <form method="post" style="margin-top:10px;">
        <button type="submit" name="clear">Clear Broadcast</button>
    </form>
    <p style="margin-top:10px; font-size:13px; color:#856404;">
        All users will see this broadcast on login and must acknowledge it. This has to be done once.
    </p>
</div>
<?php endif; ?>

<a href="index.php"><button type="button" class="back-button">Back to Queue</button></a>
</div>

<script>
const imageInput = document.getElementById('imageInput');
const imagePreview = document.getElementById('imagePreview');

imageInput.addEventListener('change', () => {
    const file = imageInput.files[0];
    if(file) {
        const reader = new FileReader();
        reader.onload = e => {
            imagePreview.src = e.target.result;
            imagePreview.style.display = 'block';
        }
        reader.readAsDataURL(file);
    } else {
        imagePreview.style.display = 'none';
        imagePreview.src = '';
    }
});
</script>
</body>
</html>
