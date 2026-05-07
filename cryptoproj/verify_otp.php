<?php
session_start();

// Redirect if they haven't even sent an email yet
if (!isset($_SESSION['temp_otp'])) {
    header("Location: forgot_password.php");
    exit();
}

$error = "";

if (isset($_POST['verify_otp'])) {
    $entered_otp = $_POST['otp'];
    
    // Check if entered OTP matches the one sent via PHPMailer
    if ($entered_otp == $_SESSION['temp_otp']) {
        $_SESSION['otp_verified'] = true; // This UNLOCKS reset_password.php
        header("Location: reset_password.php");
        exit();
    } else {
        $error = "Invalid authorization code. Access denied.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SecureVault Elite | Verify OTP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary-dark: #0f172a; --accent-gold: #c2a35d; --bg-canvas: #f8fafc; --sidebar-bg: #ffffff; --text-main: #334155; --danger: #ef4444; }
        [data-theme="dark"] { --primary-dark: #f8fafc; --accent-gold: #e2b85e; --bg-canvas: #0a0f1d; --sidebar-bg: #111827; --text-main: #cbd5e1; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg-canvas); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; color: var(--text-main); }
        .login-card { background: var(--sidebar-bg); padding: 50px; border-radius: 32px; width: 400px; text-align: center; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 30px 60px rgba(0,0,0,0.1); }
        [data-theme="dark"] .login-card { border: 1px solid rgba(255,255,255,0.05); }
        .logo { color: var(--accent-gold); font-size: 50px; margin-bottom: 20px; }
        h2 { margin-bottom: 10px; color: var(--primary-dark); font-weight: 800; }
        input { width: 100%; padding: 16px; margin-bottom: 15px; border: 1.5px solid rgba(0,0,0,0.05); border-radius: 14px; background: var(--bg-canvas); color: var(--primary-dark); text-align: center; font-size: 24px; letter-spacing: 8px; outline: none; }
        button { width: 100%; padding: 16px; background: var(--primary-dark); color: var(--bg-canvas); border: none; border-radius: 14px; cursor: pointer; font-weight: 800; text-transform: uppercase; transition: 0.3s; }
        button:hover { background: var(--accent-gold); color: #000; transform: translateY(-2px); }
        .error { background: rgba(239, 68, 68, 0.1); color: var(--danger); padding: 12px; border-radius: 10px; font-size: 14px; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="login-card">
    <i class="fas fa-user-shield logo"></i>
    <h2>Verify Identity</h2>
    <p style="font-size: 14px; opacity: 0.7; margin-bottom: 25px;">Enter the 6-digit code sent to your inbox.</p>
    
    <?php if($error): ?> <div class="error"><?php echo $error; ?></div> <?php endif; ?>

    <form method="POST">
        <input type="text" name="otp" placeholder="000000" maxlength="6" required autofocus>
        <button type="submit" name="verify_otp">Authorize Access</button>
    </form>
</div>
<script>
    document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'light');
</script>
<?php include 'footer.php'; ?>
</body>
</html>