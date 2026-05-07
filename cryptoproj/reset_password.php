<?php
session_start();
$conn = new mysqli("localhost", "root", "", "whatsapp_clone");

// Security check: If OTP wasn't verified, kick them back
if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
    header("Location: forgot_password.php");
    exit();
}

$error = "";

if (isset($_POST['reset_password'])) {
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $error = "Access keys do not match.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $email = $_SESSION['temp_email'];

        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashed_password, $email);

        if ($stmt->execute()) {
            // Success! Clear the session and redirect
            session_destroy();
            header("Location: login.php?reset=success");
            exit();
        } else {
            $error = "Update failed. Protocol error.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SecureVault Elite | Reset Access Key</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        /* Reusing the same Elite UI styles */
        :root { --primary-dark: #0f172a; --accent-gold: #c2a35d; --bg-canvas: #f8fafc; --sidebar-bg: #ffffff; --text-main: #334155; --danger: #ef4444; }
        [data-theme="dark"] { --primary-dark: #f8fafc; --accent-gold: #e2b85e; --bg-canvas: #0a0f1d; --sidebar-bg: #111827; --text-main: #cbd5e1; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg-canvas); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; color: var(--text-main); }
        .login-card { background: var(--sidebar-bg); padding: 50px; border-radius: 32px; box-shadow: 0 30px 60px rgba(0,0,0,0.1); width: 400px; text-align: center; border: 1px solid rgba(0,0,0,0.05); }
        [data-theme="dark"] .login-card { border: 1px solid rgba(255,255,255,0.05); }
        .logo { color: var(--accent-gold); font-size: 50px; margin-bottom: 20px; }
        h2 { margin-bottom: 30px; color: var(--primary-dark); font-weight: 800; }
        input { width: 100%; padding: 16px; margin-bottom: 15px; border: 1.5px solid rgba(0,0,0,0.05); border-radius: 14px; box-sizing: border-box; background: var(--bg-canvas); color: var(--primary-dark); outline: none; }
        button { width: 100%; padding: 16px; background: var(--primary-dark); color: var(--bg-canvas); border: none; border-radius: 14px; cursor: pointer; font-weight: 800; text-transform: uppercase; }
        button:hover { background: var(--accent-gold); color: #000; }
        .error { background: rgba(239, 68, 68, 0.1); color: var(--danger); padding: 12px; border-radius: 10px; font-size: 14px; margin-bottom: 20px; font-weight: 600; }
    </style>
</head>
<body>
<div class="login-card">
    <i class="fas fa-lock-open logo"></i>
    <h2>Update Access Key</h2>
    
    <?php if($error): ?> <div class="error"><?php echo $error; ?></div> <?php endif; ?>

    <form method="POST">
        <input type="password" name="password" placeholder="New Access Key" required>
        <input type="password" name="confirm_password" placeholder="Confirm Access Key" required>
        <button type="submit" name="reset_password">Finalize Update</button>
    </form>
</div>
<script>
    const theme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', theme);
</script>
<?php include 'footer.php'; ?>
</body>
</html>