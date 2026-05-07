<?php
session_start();

// PROTECTION BLOCK: Redirect to login if not logged in
if (!isset($_SESSION['my_name'])) {
    header("Location: login.php");
    exit();
}

// LOGOUT HANDLER
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "whatsapp_clone");

// --- NEW DATA FETCH FOR PROFILE ---
$stmt_me = $conn->prepare("SELECT profile_pic FROM users WHERE username = ?");
$stmt_me->bind_param("s", $_SESSION['my_name']);
$stmt_me->execute();
$my_data = $stmt_me->get_result()->fetch_assoc();
$my_avatar = (!empty($my_data['profile_pic'])) ? $my_data['profile_pic'] : "";
// ----------------------------------

// Handle Lock Request (Clear the Vault Key)
if (isset($_POST['lock_chat'])) {
    unset($_SESSION['vault_key']);
    header("Location: index.php?user=" . urlencode($_GET['user'] ?? ''));
    exit();
}

// 1. Handle Sending Logic (Updated to handle actual File Uploads)
if (isset($_POST['send_msg']) || isset($_FILES['chat_file'])) {
    $user = $_SESSION['my_name'];
    $vault_key = $_SESSION['vault_key'] ?? '';
    $receiver_name = $_GET['user'] ?? '';
    
    if ($vault_key && $receiver_name) {
        $content = "";

        // Handle Physical File Upload
        if (!empty($_FILES['chat_file']['name'])) {
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $file_name = time() . "_" . basename($_FILES["chat_file"]["name"]);
            $target_file = $target_dir . $file_name;
            
            if (move_uploaded_file($_FILES["chat_file"]["tmp_name"], $target_file)) {
                $content = "[FILE_TRANSFER] " . $target_file;
            }
        } else {
            $content = $_POST['message'];
        }

        if ($content != "") {
            $key = hash('sha256', $vault_key, true);
            $iv = substr(hash('sha256', "static_iv_123"), 0, 16);
            $ciphertext = openssl_encrypt($content, "AES-256-CBC", $key, 0, $iv);
            
            $stmt = $conn->prepare("INSERT INTO messages (sender, ciphertext, receiver) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $user, $ciphertext, $receiver_name);
            $stmt->execute();
        }
    }
    header("Location: index.php?user=" . urlencode($_GET['user']));
    exit();
}

// Handle New Chat Modal
if (isset($_POST['create_thread'])) {
    $user = $_POST['username'];
    $_SESSION['my_name'] = $user;
    $pass = $_POST['chat_password'];
    $_SESSION['vault_key'] = $pass;

    $key = hash('sha256', $pass, true);
    $iv = substr(hash('sha256', "static_iv_123"), 0, 16);
    $ciphertext = openssl_encrypt($_POST['message'], "AES-256-CBC", $key, 0, $iv);
    
    $receiver_name = $_POST['new_contact'];

    $stmt = $conn->prepare("INSERT INTO messages (sender, ciphertext, receiver) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $user, $ciphertext, $receiver_name);
    $stmt->execute();
    
    header("Location: index.php?user=" . urlencode($receiver_name));
    exit();
}

// 2. Identify Active Chat and Current User
$active_user = $_GET['user'] ?? ''; 
$my_name = $_SESSION['my_name'] ?? '';

// --- FIX: Fetch Active Chat User Profile Pic ---
$active_pfp = "";
if ($active_user) {
    $stmt_active = $conn->prepare("SELECT profile_pic FROM users WHERE username = ?");
    $stmt_active->bind_param("s", $active_user);
    $stmt_active->execute();
    $active_data = $stmt_active->get_result()->fetch_assoc();
    $active_pfp = (!empty($active_data['profile_pic'])) ? $active_data['profile_pic'] : "";
}
// ------------------------------------------------

// FEATURE: SELECTIVE MASS DELETION
if (isset($_POST['mass_delete']) && isset($_POST['selected_msgs']) && is_array($_POST['selected_msgs'])) {
    $ids = $_POST['selected_msgs'];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids)) . 's';
    
    $stmt = $conn->prepare("DELETE FROM messages WHERE id IN ($placeholders) AND sender = ?");
    $params = array_merge($ids, [$my_name]);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    
    header("Location: index.php?user=" . urlencode($active_user));
    exit();
}

// FEATURE: DELETE SINGLE MESSAGE
if (isset($_GET['delete_id'])) {
    $msg_id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM messages WHERE id = ? AND sender = ?");
    $stmt->bind_param("is", $msg_id, $my_name);
    $stmt->execute();
    header("Location: index.php?user=" . urlencode($active_user));
    exit();
}

// FEATURE 1: DELETE CHAT
if (isset($_POST['delete_chat']) && !empty($active_user)) {
    $stmt = $conn->prepare("DELETE FROM messages WHERE (sender = ? AND receiver = ?) OR (sender = ? AND receiver = ?)");
    $stmt->bind_param("ssss", $my_name, $active_user, $active_user, $my_name);
    if ($stmt->execute()) {
        header("Location: index.php");
        exit();
    }
}

// FEATURE 2: FILE SHARING HANDSHAKE (URL based)
if (isset($_POST['share_file']) && !empty($active_user) && isset($_SESSION['vault_key'])) {
    $file_ref = "[FILE_TRANSFER] " . $_POST['file_url']; 
    $key = hash('sha256', $_SESSION['vault_key'], true);
    $iv = substr(hash('sha256', "static_iv_123"), 0, 16);
    $ciphertext = openssl_encrypt($file_ref, "AES-256-CBC", $key, 0, $iv);

    $stmt = $conn->prepare("INSERT INTO messages (sender, ciphertext, receiver) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $my_name, $ciphertext, $active_user);
    $stmt->execute();
    header("Location: index.php?user=" . urlencode($active_user));
    exit();
}

// FEATURE 3: UNLOCK VAULT LOGIC
if (isset($_POST['view_password']) && !empty($active_user)) {
    $input_key = $_POST['view_password'];
    $test_stmt = $conn->prepare("SELECT ciphertext FROM messages WHERE (sender = ? AND receiver = ?) OR (sender = ? AND receiver = ?) ORDER BY created_at ASC LIMIT 1");
    $test_stmt->bind_param("ssss", $my_name, $active_user, $active_user, $my_name);
    $test_stmt->execute();
    $test_res = $test_stmt->get_result();
    
    if ($row = $test_res->fetch_assoc()) {
        $test_k = hash('sha256', $input_key, true);
        $test_i = substr(hash('sha256', "static_iv_123"), 0, 16);
        $decrypted_test = openssl_decrypt($row['ciphertext'], "AES-256-CBC", $test_k, 0, $test_i);
        
        if ($decrypted_test !== false) {
            $_SESSION['vault_key'] = $input_key;
        } else {
            echo "<script>alert('Incorrect Vault Key! Access Denied.');</script>";
        }
    } else {
        $_SESSION['vault_key'] = $input_key;
    }
}

$vault_key = $_SESSION['vault_key'] ?? '';

// 3. Fetch Contacts
$contact_result = $conn->query("SELECT DISTINCT CASE WHEN sender = '$my_name' THEN receiver ELSE sender END as contact FROM messages WHERE (sender = '$my_name' OR receiver = '$my_name') AND sender != '' AND receiver != '' ORDER BY contact ASC");

// 4. Fetch Messages
$messages = null;
if ($active_user) {
    $stmt = $conn->prepare("SELECT * FROM messages WHERE (sender = ? AND receiver = ?) OR (sender = ? AND receiver = ?) ORDER BY created_at ASC");
    $stmt->bind_param("ssss", $my_name, $active_user, $active_user, $my_name);
    $stmt->execute();
    $messages = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SecureVault Elite | Encrypted Communications</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary-dark: #0f172a; 
            --accent-gold: #c2a35d; 
            --accent-glow: #1bd1a5;
            --bg-canvas: #f8fafc;
            --sidebar-bg: #ffffff;
            --bubble-sent: #1e293b;
            --text-main: #334155;
            --text-muted: #64748b;
            --danger: #ef4444;
            --glass: rgba(255, 255, 255, 0.7);
        }

        [data-theme="dark"] {
            --primary-dark: #f8fafc; 
            --accent-gold: #e2b85e; 
            --accent-glow: #00ffcc;
            --bg-canvas: #0a0f1d;
            --sidebar-bg: #111827;
            --bubble-sent: #1e293b;
            --text-main: #f1f5f9; 
            --text-muted: #94a3b8;
            --glass: rgba(17, 24, 39, 0.8);
        }
        
        body, html { 
            margin: 0; padding: 0; height: 100%; 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--bg-canvas); color: var(--text-main);
            -webkit-font-smoothing: antialiased;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .app-container { display: flex; height: 100vh; overflow: hidden; }
        
        .sidebar { 
            width: 380px; background: var(--sidebar-bg); 
            border-right: 1px solid rgba(0,0,0,0.06); 
            display: flex; flex-direction: column; z-index: 10;
            transition: background 0.3s ease;
        }
        .sidebar-header { 
            padding: 40px 30px 20px; display: flex; 
            justify-content: space-between; align-items: center; 
        }
        .sidebar-header h2 { 
            margin: 0; font-size: 18px; font-weight: 800; 
            letter-spacing: 2px; color: var(--primary-dark);
            text-transform: uppercase;
        }
        
        .search-box { padding: 10px 30px 25px; }
        .search-wrapper { 
            background: #f1f5f9; border-radius: 12px; 
            display: flex; align-items: center; padding: 5px 15px; 
            transition: 0.3s; border: 1.5px solid transparent; 
        }
        [data-theme="dark"] .search-wrapper { background: #1f2937; }
        .search-wrapper:focus-within { 
            background: #fff; border-color: var(--accent-gold); 
            box-shadow: 0 10px 25px rgba(194, 163, 93, 0.1); 
        }
        .search-wrapper input { 
            border: none; background: transparent; padding: 12px; 
            width: 100%; outline: none; font-size: 14px; color: var(--text-main);
        }

        .contact-list { overflow-y: auto; flex-grow: 1; padding: 0 20px; }
        .contact-item { 
            display: flex; align-items: center; padding: 16px; 
            border-radius: 16px; margin-bottom: 5px; transition: 0.3s; 
            text-decoration: none; color: var(--text-muted); position: relative;
        }
        
        .contact-item .contact-name {
            color: var(--text-main);
            font-weight: 700;
            margin-bottom: 4px;
            font-size: 14px;
        }

        .contact-item:hover { background: #f1f5f9; }
        [data-theme="dark"] .contact-item:hover { background: #1f2937; }
        
        .contact-item.active { 
            background: var(--accent-gold); 
            box-shadow: 0 15px 30px rgba(194, 163, 93, 0.15);
        }
        .contact-item.active .contact-name { color: #000 !important; }
        .contact-item.active .status-label { color: #000 !important; opacity: 0.7; }
        .contact-item.active .avatar { background: #000; color: var(--accent-gold); }

        .avatar { 
            width: 48px; height: 48px; border-radius: 14px; 
            background: #e2e8f0; margin-right: 15px; 
            display: flex; align-items: center; justify-content: center; 
            font-weight: 800; font-size: 16px; color: var(--primary-dark);
            transition: 0.3s;
            overflow: hidden;
        }
        .avatar img { width: 100%; height: 100%; object-fit: cover; }
        [data-theme="dark"] .avatar { background: #374151; color: #fff; }

        .profile-trigger {
            width: 40px; height: 40px; border-radius: 12px;
            background: var(--primary-dark); color: var(--accent-gold);
            display: flex; align-items: center; justify-content: center;
            text-decoration: none; transition: 0.3s; position: relative;
            overflow: hidden; border: 1.5px solid var(--accent-gold);
        }
        .profile-trigger:hover { transform: scale(1.1); box-shadow: 0 0 15px rgba(194,163,93,0.3); }

        .chat-window { flex-grow: 1; background: var(--bg-canvas); position: relative; display: flex; flex-direction: column; }
        .chat-header-bar { 
            padding: 25px 40px; background: var(--glass); 
            backdrop-filter: blur(20px); display: flex; 
            justify-content: space-between; align-items: center; 
            border-bottom: 1px solid rgba(0,0,0,0.03);
        }
        .chat-header-bar h3 { margin: 0; font-weight: 800; color: var(--primary-dark); font-size: 20px; }

        .message-viewport { 
            flex-grow: 1; padding: 40px; overflow-y: auto; 
            display: flex; flex-direction: column; 
            background: transparent;
        }
        
        .p-bubble { 
            padding: 18px 24px; border-radius: 24px; margin-bottom: 20px; 
            max-width: 60%; font-size: 14.5px; line-height: 1.7; 
            position: relative; box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        }
        .msg-left { 
            align-self: flex-start; background: var(--sidebar-bg); color: var(--text-main); 
            border-bottom-left-radius: 4px; border: 1px solid rgba(0,0,0,0.05);
        }
        .msg-right { 
            align-self: flex-end; background: var(--bubble-sent); color: #f8fafc; 
            border-bottom-right-radius: 4px; 
        }
        
        .cipher-text { 
            font-family: 'JetBrains Mono', monospace; font-size: 11px; 
            opacity: 0.7; background: rgba(0,0,0,0.04); 
            padding: 12px; border-radius: 12px; display: block; 
            margin-top: 10px; border: 1px solid rgba(0,0,0,0.05);
            word-break: break-all;
        }
        .msg-right .cipher-text { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); }

        .chat-footer { padding: 30px 40px; background: var(--sidebar-bg); transition: 0.3s; }
        .footer-form { display: flex; align-items: center; gap: 15px; }
        .input-wrapper { 
            flex-grow: 1; background: var(--bg-canvas); border-radius: 18px; 
            padding: 4px 20px; display: flex; align-items: center; 
            border: 1.5px solid rgba(0,0,0,0.05); transition: 0.3s;
        }
        .input-wrapper:focus-within { 
            background: var(--sidebar-bg); border-color: var(--accent-gold);
            box-shadow: 0 10px 30px rgba(194, 163, 93, 0.08);
        }
        .message-input { 
            width: 100%; border: none; outline: none; padding: 15px 0; 
            font-size: 15px; background: transparent; color: var(--primary-dark);
        }
        
        .icon-btn { 
            background: none; border: none; color: #94a3b8; 
            font-size: 20px; cursor: pointer; transition: 0.3s; 
        }
        .icon-btn:hover { color: var(--accent-gold); transform: translateY(-2px); }
        
        .send-btn { 
            background: var(--primary-dark); color: var(--bg-canvas); border: none; 
            width: 56px; height: 56px; border-radius: 18px; 
            cursor: pointer; display: flex; align-items: center; 
            justify-content: center; font-size: 18px; transition: 0.3s; 
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.2);
        }
        .send-btn:hover { background: var(--accent-gold); color: #000; transform: scale(1.05); }

        .lock-btn { 
            background: #f1f5f9; color: var(--primary-dark); border: none; 
            padding: 12px 20px; border-radius: 12px; cursor: pointer; 
            font-size: 11px; font-weight: 800; text-transform: uppercase; 
            letter-spacing: 1.5px; transition: 0.3s;
        }
        [data-theme="dark"] .lock-btn { background: #1f2937; color: white; }
        .lock-btn:hover { background: var(--danger); color: white; }

        .single-delete {
            position: absolute; right: -25px; top: 50%; transform: translateY(-50%);
            color: #cbd5e1; font-size: 12px; opacity: 0; transition: 0.2s;
        }
        .p-bubble:hover .single-delete { opacity: 1; right: -35px; }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        [data-theme="dark"] ::-webkit-scrollbar-thumb { background: #374151; }

        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .p-bubble { animation: slideUp 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; }
    </style>
</head>
<body>

<div class="app-container">
    <div class="sidebar">
        <div class="sidebar-header">
            <div style="display:flex; align-items:center; gap:15px;">
                <a href="profile.php" class="profile-trigger" title="Operative Profile">
                    <?php if($my_avatar): ?>
                        <img src="<?php echo $my_avatar; ?>" alt="Me">
                    <?php else: ?>
                        <i class="fa-solid fa-user-secret"></i>
                    <?php endif; ?>
                </a>
                <h2>Secure<span style="color:var(--accent-gold)">Vault</span></h2>
            </div>
            
            <div style="display:flex; gap: 15px; align-items: center;">
                <button onclick="document.getElementById('contactModal').style.display='block'" class="icon-btn" style="color: var(--primary-dark);">
                    <i class="fa-solid fa-circle-plus"></i>
                </button>
                <a href="index.php?logout=1" class="icon-btn" title="Exit System"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
            </div>
        </div>

        <div class="search-box">
            <div class="search-wrapper">
                <i class="fa-solid fa-magnifying-glass" style="color: #cbd5e1; font-size: 12px;"></i>
                <input type="text" id="contactSearch" placeholder="Search Encrypted Channels..." onkeyup="filterContacts()">
            </div>
        </div>

        <div class="contact-list" id="contactList">
            <?php while($c = $contact_result->fetch_assoc()): ?>
                <a href="index.php?user=<?php echo urlencode($c['contact']); ?>" class="contact-item <?php echo ($active_user == $c['contact']) ? 'active' : ''; ?>">
                    <div class="avatar">
                        <?php 
                        $c_name = $c['contact'];
                        $c_res = $conn->query("SELECT profile_pic FROM users WHERE username = '$c_name'");
                        $c_data = $c_res->fetch_assoc();
                        if(!empty($c_data['profile_pic'])): ?>
                            <img src="<?php echo $c_data['profile_pic']; ?>">
                        <?php else: ?>
                            <?php echo strtoupper(substr($c['contact'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div style="flex-grow: 1;">
                        <div class="contact-name"><?php echo htmlspecialchars($c['contact']); ?></div>
                        <div style="font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;" class="status-label">
                            <i class="fa-solid fa-shield-check" style="margin-right:4px;"></i>Verified Node
                        </div>
                    </div>
                </a>
            <?php endwhile; ?>
        </div>
    </div>

    <div class="chat-window">
        <?php if ($active_user): ?>
            <div class="chat-header-bar">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <div class="avatar" style="width: 44px; height: 44px; background: var(--primary-dark); color: var(--accent-gold);">
                         <?php if(!empty($active_pfp)): ?>
                            <img src="<?php echo $active_pfp; ?>">
                        <?php else: ?>
                            <?php echo strtoupper(substr($active_user, 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3><?php echo htmlspecialchars($active_user); ?></h3>
                        <div style="font-size: 10px; color: var(--accent-glow); font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">
                            <i class="fa-solid fa-lock" style="font-size: 9px;"></i> E2E Protocol Active
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; align-items: center; gap: 15px;">
                    <button type="submit" form="massDeleteForm" name="mass_delete" id="topDeleteBtn" class="lock-btn" style="display: none; background: var(--danger); color: white;" onclick="return confirm('Purge selected records?')">
                        <i class="fa-solid fa-trash-can"></i> PURGE
                    </button>

                    <?php if ($vault_key): ?>
                        <form method="POST"><button type="submit" name="lock_chat" class="lock-btn"><i class="fa-solid fa-vault"></i> LOCK VAULT</button></form>
                    <?php else: ?>
                        <form method="POST" style="display:flex; gap:8px; background: var(--sidebar-bg); padding: 6px; border-radius: 12px; border: 1.5px solid rgba(0,0,0,0.05);">
                            <input type="password" name="view_password" style="border:none; background:transparent; padding:4px 12px; outline:none; font-size:12px; width:160px; color: var(--primary-dark);" placeholder="Vault Access Key..." required>
                            <button type="submit" class="send-btn" style="width:32px; height:32px; border-radius: 8px; font-size:12px; box-shadow: none;"><i class="fa-solid fa-key"></i></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <form id="massDeleteForm" method="POST" class="message-viewport">
                <?php 
                if($messages) {
                    while($row = $messages->fetch_assoc()): 
                        $side = ($row['sender'] === $my_name) ? 'msg-right' : 'msg-left';
                        $decrypted = false;
                        if ($vault_key) {
                            $k = hash('sha256', $vault_key, true);
                            $i = substr(hash('sha256', "static_iv_123"), 0, 16);
                            $decrypted = openssl_decrypt($row['ciphertext'], "AES-256-CBC", $k, 0, $i);
                        }
                    ?>
                        <div class="p-bubble <?php echo $side; ?>">
                            <?php if($row['sender'] === $my_name): ?>
                                <input type="checkbox" name="selected_msgs[]" value="<?php echo $row['id']; ?>" class="msg-checkbox" style="position:absolute; left:-30px; top:22px; cursor:pointer;" onchange="toggleDeleteBtn()">
                                <a href="index.php?user=<?php echo urlencode($active_user); ?>&delete_id=<?php echo $row['id']; ?>" class="single-delete" title="Delete"><i class="fa-solid fa-circle-xmark"></i></a>
                            <?php endif; ?>

                            <?php if ($decrypted): ?>
                                <?php if (strpos($decrypted, '[FILE_TRANSFER]') === 0): 
                                    $path = str_replace('[FILE_TRANSFER] ', '', $decrypted); ?>
                                    <div style="display:flex; align-items:center; gap:15px; background: rgba(0,0,0,0.05); padding: 15px; border-radius: 15px;">
                                        <i class="fa-solid fa-file-shield" style="font-size: 20px; color: var(--accent-gold);"></i>
                                        <a href="<?php echo $path; ?>" target="_blank" style="color: inherit; font-weight: 700; text-decoration: none; font-size:13px;"><?php echo basename($path); ?></a>
                                    </div>
                                <?php else: ?>
                                    <span style="font-weight: 500;"><?php echo htmlspecialchars($decrypted); ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="display:flex; align-items:center; gap:8px; color: #f87171; font-weight: 800; font-size: 9px; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px;">
                                    <i class="fa-solid fa-microchip"></i> Encrypted Block
                                </div>
                                <span class="cipher-text"><?php echo htmlspecialchars($row['ciphertext']); ?></span>
                            <?php endif; ?>
                            
                            <div style="font-size: 9px; opacity: 0.4; margin-top: 10px; text-align: right; font-weight: 800; font-family: 'JetBrains Mono';">
                                <?php echo date('H:i', strtotime($row['created_at'])); ?>
                            </div>
                        </div>
                    <?php endwhile; 
                } ?>
            </form>

            <footer class="chat-footer">
                <form method="POST" enctype="multipart/form-data" class="footer-form" id="chatForm">
                    <button type="button" class="icon-btn" onclick="document.getElementById('fileInput').click()" title="Secure Data Upload">
                        <i class="fa-solid fa-paperclip"></i>
                    </button>
                    <input type="file" name="chat_file" id="fileInput" style="display:none;" onchange="document.getElementById('msgText').required=false; document.getElementById('chatForm').submit();">

                    <div class="input-wrapper">
                        <input type="text" name="message" id="msgText" class="message-input" placeholder="Type a secure transmission..." required <?php echo !$vault_key ? 'disabled' : ''; ?>>
                        <i class="fa-solid fa-fingerprint" style="color: #cbd5e1; font-size: 14px;"></i>
                    </div>

                    <button type="submit" name="send_msg" class="send-btn" <?php echo !$vault_key ? 'disabled' : ''; ?>>
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>
            </footer>

        <?php else: ?>
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; text-align: center;">
                <div style="width: 120px; height: 120px; background: var(--sidebar-bg); border-radius: 40px; display: flex; align-items: center; justify-content: center; margin-bottom: 30px; box-shadow: 0 30px 60px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.05);">
                    <i class="fa-solid fa-shield-halved" style="font-size: 50px; color: var(--accent-gold);"></i>
                </div>
                <h2 style="color: var(--primary-dark); font-weight: 800; margin-bottom: 12px; letter-spacing: -0.5px;">Executive Comms Portal</h2>
                <p style="max-width: 340px; line-height: 1.8; font-size: 14px; color: #94a3b8;">Welcome to the Elite Secure Suite. Please select an authorized contact from the sidebar to establish a secure handshake.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="contactModal" style="display:none; position:fixed; z-index:3000; left:0; top:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.8); backdrop-filter: blur(12px);">
    <div style="background:var(--sidebar-bg); margin:6% auto; padding:50px; width:440px; border-radius:32px; box-shadow: 0 50px 100px rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.1);">
        <h3 style="font-weight: 800; font-size: 22px; color: var(--primary-dark); margin-top: 0; margin-bottom: 30px; letter-spacing: -0.5px;">Initialize New Secure Link</h3>
        <form method="POST">
            <div style="margin-bottom: 20px;">
                <label style="font-size: 10px; font-weight: 800; color: #94a3b8; display: block; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px;">Target Identifier</label>
                <input type="text" name="new_contact" placeholder="Recipient Identity" required style="width:100%; padding:16px; border-radius:14px; border:1.5px solid rgba(0,0,0,0.05); background:var(--bg-canvas); box-sizing: border-box; font-family: inherit; color: var(--primary-dark);">
            </div>
            <div style="margin-bottom: 20px;">
                <label style="font-size: 10px; font-weight: 800; color: #94a3b8; display: block; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px;">Your Alias</label>
                <input type="text" name="username" placeholder="Sender Identity" required style="width:100%; padding:16px; border-radius:14px; border:1.5px solid rgba(0,0,0,0.05); background:var(--bg-canvas); box-sizing: border-box; font-family: inherit; color: var(--primary-dark);">
            </div>
            <div style="margin-bottom: 20px;">
                <label style="font-size: 10px; font-weight: 800; color: #94a3b8; display: block; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px;">Session Vault Key</label>
                <input type="password" name="chat_password" placeholder="Unique Passphrase" required style="width:100%; padding:16px; border-radius:14px; border:1.5px solid rgba(0,0,0,0.05); background:var(--bg-canvas); box-sizing: border-box; font-family: inherit; color: var(--primary-dark);">
            </div>
            <div style="margin-bottom: 35px;">
                <label style="font-size: 10px; font-weight: 800; color: #94a3b8; display: block; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px;">Initial Handshake</label>
                <textarea name="message" placeholder="Begin secure message..." required style="width:100%; padding:16px; border-radius:14px; border:1.5px solid rgba(0,0,0,0.05); background:var(--bg-canvas); height:100px; resize: none; box-sizing: border-box; font-family: inherit; color: var(--primary-dark);"></textarea>
            </div>
            <button type="submit" name="create_thread" class="send-btn" style="width:100%; border-radius: 16px; font-weight: 800; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Establish Link</button>
            <button type="button" onclick="document.getElementById('contactModal').style.display='none'" style="width:100%; margin-top:20px; border:none; background:none; color:#94a3b8; cursor:pointer; font-weight: 700; font-size: 12px;">Abort Protocol</button>
        </form>
    </div>
</div>

<script>
    function toggleDeleteBtn() {
        const checkboxes = document.querySelectorAll('.msg-checkbox:checked');
        const btn = document.getElementById('topDeleteBtn');
        if(checkboxes.length > 0) {
            btn.style.display = 'flex';
            btn.style.alignItems = 'center';
            btn.style.gap = '8px';
        } else {
            btn.style.display = 'none';
        }
    }
    
    function filterContacts() {
        let input = document.getElementById('contactSearch').value.toLowerCase();
        let items = document.getElementsByClassName('contact-item');
        for (let i = 0; i < items.length; i++) {
            let name = items[i].querySelector('.contact-name').innerText.toLowerCase();
            items[i].style.display = name.includes(input) ? "flex" : "none";
        }
    }

    const viewport = document.querySelector('.message-viewport');
    if(viewport) viewport.scrollTop = viewport.scrollHeight;
</script>

<?php $conn->close(); ?>
<?php include 'footer.php'; ?>
</body>
</html>