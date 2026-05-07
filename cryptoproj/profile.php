<?php
session_start();

if (!isset($_SESSION['my_name'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "whatsapp_clone");
$my_name = $_SESSION['my_name'];
$status_msg = "";

// Fetch User Data
$stmt_current = $conn->prepare("SELECT profile_pic FROM users WHERE username = ?");
$stmt_current->bind_param("s", $my_name);
$stmt_current->execute();
$user_data = $stmt_current->get_result()->fetch_assoc();
$current_avatar = $user_data['profile_pic'] ?? "";

// Upload Logic
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
    $target_dir = "uploads/avatars/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    
    $file_ext = strtolower(pathinfo($_FILES["avatar"]["name"], PATHINFO_EXTENSION));
    $file_name = "id_" . md5($my_name . time()) . "." . $file_ext;
    $target_file = $target_dir . $file_name;

    if (getimagesize($_FILES["avatar"]["tmp_name"])) {
        if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file)) {
            // GD Filter - Scanline Effect
            $img = null;
            if ($file_ext == 'jpg' || $file_ext == 'jpeg') $img = @imagecreatefromjpeg($target_file);
            elseif ($file_ext == 'png') $img = @imagecreatefrompng($target_file);

            if ($img) {
                $width = imagesx($img); $height = imagesy($img);
                $line_color = imagecolorallocatealpha($img, 194, 163, 93, 80); 
                for ($y = 0; $y < $height; $y += 6) {
                    imagefilledrectangle($img, 0, $y, $width, $y + 1, $line_color);
                }
                if ($file_ext == 'png') imagepng($img, $target_file);
                else imagejpeg($img, $target_file, 90);
                imagedestroy($img);
            }

            $update = $conn->prepare("UPDATE users SET profile_pic = ? WHERE username = ?");
            $update->bind_param("ss", $target_file, $my_name);
            $update->execute();
            header("Location: profile.php?success=1");
            exit();
        }
    }
}
if(isset($_GET['success'])) $status_msg = "PROTOCOL: IDENTITY_SYNC_COMPLETE";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SecureVault | Operative Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;700;800&family=JetBrains+Mono&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

    <style>
        /* 1. THEME FOUNDATION */
        body { 
            background-color: #f1f5f9; 
            color: #1e293b;
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-x: hidden;
        }

        body.dark-theme {
            background-color: #060a13;
            color: #f8fafc;
        }

        /* 2. MAIN LAYOUT */
        .main-wrapper {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            background: radial-gradient(circle at top right, rgba(194, 163, 93, 0.05), transparent);
        }

        /* 3. GLASS PROFILE CONTAINER */
        .profile-container {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 50px 40px;
            border-radius: 40px;
            width: 100%;
            max-width: 450px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        body.dark-theme .profile-container {
            background: rgba(17, 24, 39, 0.8);
            border: 1px solid rgba(194, 163, 93, 0.15);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        /* 4. AVATAR VAULT WITH GLOW & GLITCH */
        .avatar-vault {
            width: 180px;
            height: 180px;
            margin: 0 auto 30px;
            border-radius: 30px;
            position: relative;
            padding: 8px;
            background: linear-gradient(135deg, #c2a35d, #8e7336);
            box-shadow: 0 0 20px rgba(194, 163, 93, 0.3);
            animation: pulse-glow 3s infinite;
            overflow: hidden;
        }

        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 15px rgba(194, 163, 93, 0.2); }
            50% { box-shadow: 0 0 30px rgba(194, 163, 93, 0.5); }
        }

        .avatar-vault img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 22px;
            background: #000;
        }

        /* GLITCH ANIMATION */
        .glitch-active {
            animation: glitch-frame 0.2s infinite;
            filter: hue-rotate(90deg) contrast(1.5);
        }

        @keyframes glitch-frame {
            0% { transform: translate(0); }
            20% { transform: translate(-3px, 2px); }
            40% { transform: translate(-3px, -2px); }
            60% { transform: translate(3px, 2px); }
            80% { transform: translate(3px, -2px); }
            100% { transform: translate(0); }
        }

        /* 5. SYSTEM DIAGNOSTICS PANEL */
        .diag-panel {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 20px;
            padding: 15px;
            margin-bottom: 30px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            text-align: left;
        }

        body.dark-theme .diag-panel { background: rgba(0, 0, 0, 0.3); color: #c2a35d; }
        .diag-row { display: flex; justify-content: space-between; margin-bottom: 5px; opacity: 0.8; }

        /* 6. BUTTONS & INPUTS */
        .upload-trigger {
            background: #c2a35d; color: #000; padding: 16px 32px; border-radius: 16px;
            font-weight: 800; cursor: pointer; display: inline-block; text-transform: uppercase;
            letter-spacing: 1px; transition: all 0.3s ease; border: none;
            box-shadow: 0 10px 15px -3px rgba(194, 163, 93, 0.3);
        }

        .status-text { color: #c2a35d; font-family: 'JetBrains Mono', monospace; font-size: 11px; margin: 15px 0; height: 20px; font-weight: bold; }

        /* CROPPING MODAL STYLES */
        #cropModal {
            display: none; position: fixed; z-index: 999999; left: 0; top: 0;
            width: 100%; height: 100%; background: rgba(0,0,0,0.9); backdrop-filter: blur(10px);
        }
        .crop-container {
            width: 90%; max-width: 500px; margin: 50px auto; background: #111827;
            padding: 20px; border-radius: 20px; border: 1px solid #c2a35d;
        }
        .img-area { max-height: 400px; margin-bottom: 20px; overflow: hidden; }
        .crop-btn { 
            background: #c2a35d; color: #000; border: none; padding: 10px 20px; 
            border-radius: 10px; font-weight: bold; cursor: pointer; margin: 5px;
        }

        .scanner-line {
            position: absolute; top: 0; left: 0; width: 100%; height: 3px;
            background: #fff; box-shadow: 0 0 15px #fff;
            display: none; animation: scanning 2s infinite ease-in-out; z-index: 5;
        }

        @keyframes scanning { 0% { top: 5%; opacity: 0; } 50% { opacity: 1; } 100% { top: 95%; opacity: 0; } }

        .back-btn { color: #64748b; text-decoration: none; font-size: 13px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.3s; }
        input[type="file"] { display: none; }
    </style>
</head>
<body>

<div id="cropModal">
    <div class="crop-container">
        <h3 style="color: #c2a35d; margin-top: 0; font-family: 'JetBrains Mono';">ADJUST BIOMETRICS</h3>
        <div class="img-area">
            <img id="imageToCrop" style="max-width: 100%;">
        </div>
        <div style="text-align: center;">
            <button type="button" class="crop-btn" onclick="cropper.rotate(90)"><i class="fa-solid fa-rotate-right"></i></button>
            <button type="button" class="crop-btn" onclick="confirmCrop()">APPLY & SYNC</button>
            <button type="button" class="crop-btn" style="background: #ef4444;" onclick="closeModal()">CANCEL</button>
        </div>
    </div>
</div>

<div class="main-wrapper">
    <div class="profile-container">
        <div style="margin-bottom: 30px;">
            <i class="fa-solid fa-shield-halved" style="color: #c2a35d; font-size: 2rem; margin-bottom: 10px;"></i>
            <h2 style="margin: 0; letter-spacing: -1px;">Operative Profile</h2>
            <p style="font-size: 12px; opacity: 0.5; font-weight: 700;">SECURE ENCRYPTED NODE: <?php echo strtoupper($my_name); ?></p>
        </div>
        
        <div class="avatar-vault">
            <div class="scanner-line" id="scanner"></div>
            <img id="preview" src="<?php echo $current_avatar ? $current_avatar : 'https://ui-avatars.com/api/?name='.$my_name.'&background=111827&color=c2a35d'; ?>">
        </div>

        <div class="diag-panel">
            <div class="diag-row"><span>STATUS:</span> <span>ACTIVE</span></div>
            <div class="diag-row"><span>ENCRYPTION:</span> <span>AES-256-XTS</span></div>
            <div class="diag-row"><span>BIO-SIG:</span> <span><?php echo strtoupper(substr(md5($my_name), 0, 8)); ?></span></div>
        </div>

        <div class="status-text" id="status"><?php echo $status_msg; ?></div>

        <form method="POST" enctype="multipart/form-data" id="profileForm">
            <label for="avatar_input" class="upload-trigger">
                <i class="fa-solid fa-fingerprint"></i> Sync Identity
            </label>
            <input type="file" name="avatar" id="avatar_input" accept="image/*" onchange="startScan()">
        </form>
        
        <div style="margin-top: 40px; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 20px;">
            <a href="index.php" class="back-btn">
                <i class="fa-solid fa-chevron-left"></i> RETURN TO COMMAND CENTER
            </a>
        </div>
    </div>
</div>

<script>
    let cropper;
    const modal = document.getElementById('cropModal');
    const imageToCrop = document.getElementById('imageToCrop');
    const fileInput = document.getElementById('avatar_input');
    const preview = document.getElementById('preview');

    function startScan() {
        if (fileInput.files && fileInput.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                imageToCrop.src = e.target.result;
                modal.style.display = 'block';
                if (cropper) cropper.destroy();
                cropper = new Cropper(imageToCrop, {
                    aspectRatio: 1,
                    viewMode: 1,
                    background: false,
                });
            }
            reader.readAsDataURL(fileInput.files[0]);
        }
    }

    function closeModal() { modal.style.display = 'none'; fileInput.value = ''; }

    function confirmCrop() {
        const canvas = cropper.getCroppedCanvas({ width: 400, height: 400 });
        canvas.toBlob((blob) => {
            const file = new File([blob], "cropped_avatar.jpg", { type: "image/jpeg" });
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            fileInput.files = dataTransfer.files;

            // Start animations
            modal.style.display = 'none';
            preview.src = canvas.toDataURL();
            preview.classList.add('glitch-active'); // TRIGGER GLITCH
            document.getElementById('scanner').style.display = 'block';
            document.getElementById('status').innerText = "> INITIALIZING BIOMETRIC SCAN...";
            
            setTimeout(() => { document.getElementById('status').innerText = "> UPLOADING TO SECURE VAULT..."; }, 1200);
            setTimeout(() => { document.getElementById('profileForm').submit(); }, 2800);
        }, 'image/jpeg');
    }
</script>

<?php include 'footer.php'; ?>
</body>
</html>