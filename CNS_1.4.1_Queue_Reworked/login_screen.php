<?php
session_start();
if (!isset($_SESSION['username']) || !$_SESSION['is_admin']) {
    header("Location: index.php");
    exit;
}

$backgroundFile = __DIR__ . "/background.png";
$galleryFolder  = __DIR__ . "/login_images";
$success = $error = "";

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {

        // Upload custom background
        if ($_POST['action'] === 'upload') {
            if (!empty($_FILES['custom_bg']['tmp_name'])) {
                $fileTmp  = $_FILES['custom_bg']['tmp_name'];
                $fileType = mime_content_type($fileTmp);
                list($width, $height) = getimagesize($fileTmp);

                if ($fileType === 'image/png' && $width == 1920 && $height == 1080) {
                    if (move_uploaded_file($fileTmp, $backgroundFile)) {
                        $success = "Custom background uploaded successfully!";
                    } else {
                        $error = "Failed to move uploaded file.";
                    }
                } else {
                    $error = "File must be PNG and 1920x1080.";
                }
            } else {
                $error = "No file selected.";
            }
        }

        // Select from gallery
        if ($_POST['action'] === 'select') {
            $selected = basename($_POST['gallery_bg'] ?? '');
            $fullPath = $galleryFolder . "/" . $selected;
            if (file_exists($fullPath)) {
                if (copy($fullPath, $backgroundFile)) {
                    $success = "Background updated from gallery!";
                } else {
                    $error = "Failed to copy image from gallery.";
                }
            } else {
                $error = "Selected image does not exist.";
            }
        }

        // Clear background
        if ($_POST['action'] === 'clear') {
            if (file_exists($backgroundFile)) {
                unlink($backgroundFile);
                $success = "Background cleared.";
            } else {
                $error = "No background to clear.";
            }
        }
    }
}

// Load gallery images
$galleryImages = array_filter(scandir($galleryFolder), function($f) use ($galleryFolder) {
    return preg_match('/\.(png)$/i', $f) && is_file($galleryFolder . "/" . $f);
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login Background Management</title>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f0f2f5;
    margin: 0;
    padding: 0;
}
.container {
    max-width: 900px;
    margin: 40px auto;
    background: #fff;
    padding: 30px 40px;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
h1 { text-align: center; color: #333; margin-bottom: 25px; }
section { margin-bottom: 30px; }
label { font-weight: bold; display: block; margin-bottom: 8px; }
input[type="file"], select { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ccc; }
button {
    margin-top: 10px;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    background: #4a90e2;
    color: #fff;
    cursor: pointer;
}
button:hover { background: #357ABD; }
.success, .error {
    padding: 10px; border-radius: 6px; font-weight: bold; text-align: center; margin-bottom: 15px;
}
.success { background: #d4edda; color: #155724; }
.error { background: #f8d7da; color: #721c24; }
.image-preview { max-width: 100%; margin-top: 10px; border-radius: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
.gallery-images { display: flex; flex-wrap: wrap; gap: 10px; }
.gallery-images img { width: 200px; cursor: pointer; border: 2px solid transparent; border-radius: 6px; }
.gallery-images img.selected { border-color: #4a90e2; }
</style>
</head>
<body>
<div class="container">
<h1>Login Background Management</h1>

<?php if($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Current Background -->
<section>
    <h2>Current Background</h2>
    <?php if(file_exists($backgroundFile)): ?>
        <img src="background.png?<?= time() ?>" class="image-preview" alt="Current Background">
    <?php else: ?>
        <p>No background set.</p>
    <?php endif; ?>
</section>

<!-- Gallery Selection -->
<section>
    <h2>Gallery</h2>
    <form method="post">
        <div class="gallery-images">
            <?php foreach($galleryImages as $img): ?>
                <label>
                    <input type="radio" name="gallery_bg" value="<?= htmlspecialchars($img) ?>" style="display:none;">
                    <img src="login_images/<?= htmlspecialchars($img) ?>" alt="" onclick="selectGallery(this)">
                </label>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="action" value="select">
        <button type="submit">Set Selected Gallery Image</button>
    </form>
</section>

<!-- Upload Custom -->
<section>
    <h2>Upload Custom Background</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="custom_bg" accept="image/png" required>
        <input type="hidden" name="action" value="upload">
        <p>Warning: PNG only, 1920 x 1080 required.</p>
        <button type="submit">Upload</button>
    </form>
</section>

<!-- Clear Background -->
<section>
    <form method="post">
        <input type="hidden" name="action" value="clear">
        <button type="submit" style="background:#e74c3c;">Clear Background</button>
    </form>
</section>

<a href="index.php"><button type="button" style="background:#6c757d;">Back to Queue</button></a>
</div>

<script>
function selectGallery(imgEl) {
    document.querySelectorAll('.gallery-images img').forEach(i => i.classList.remove('selected'));
    imgEl.classList.add('selected');
    imgEl.previousElementSibling.checked = true;
}
</script>
</body>
</html>
