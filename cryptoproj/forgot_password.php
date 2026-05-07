<?php
session_start();

// MANUAL LOADING: Pointing to your specific PHPMailer folder structure
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$conn = new mysqli("localhost", "root", "", "whatsapp_clone");

$error = "";
$success = "";

if (isset($_POST['send_otp'])) {
    $email = $_POST['email'];
    
    // Check if the email exists in the vault
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $otp = rand(100000, 999999);
        $_SESSION['temp_otp'] = $otp;
        $_SESSION['temp_email'] = $email;
        
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'ENTER YOUR EMAIL';
            $mail->Password   = 'ENTER APP PASSWORD'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('ENTER YOUR EMAIL', 'SecureVault Elite');
            $mail->addAddress($email);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Access Key Recovery Protocol';
            $mail->Body    = "
                <div style='font-family: sans-serif; padding: 20px; background-color: #0f172a; color: #f8fafc; border-radius: 10px;'>
                    <h2 style='color: #c2a35d;'>Identity Verification</h2>
                    <p>An access key reset has been requested for your operative profile.</p>
                    <p>Your one-time authorization code is:</p>
                    <h1 style='letter-spacing: 5px; color: #c2a35d;'>$otp</h1>
                    <p>If you did not request this, secure your terminal immediately.</p>
                </div>";

            if($mail->send()) {
                header("Location: verify_otp.php");
                exit();
            }
        } catch (Exception $e) {
            $error = "Mail dispatch failed. System Error: {$mail->ErrorInfo}";
        }
    } else {
        $error = "Identity not recognized in the Vault.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SecureVault Elite | Recovery</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary-dark: #0f172a; 
            --accent-gold: #c2a35d; 
            --bg-canvas: #f8fafc;
            --sidebar-bg: #ffffff;
            --text-main: #334155;
            --danger: #ef4444;
        }

        [data-theme="dark"] {
            --primary-dark: #f8fafc; 
            --accent-gold: #e2b85e; 
            --bg-canvas: #0a0f1d;
            --sidebar-bg: #111827;
            --text-main: #cbd5e1;
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--bg-canvas); 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
            color: var(--text-main);
            transition: background 0.3s ease;
        }

        .login-card { 
            background: var(--sidebar-bg); 
            padding: 50px; 
            border-radius: 32px; 
            box-shadow: 0 30px 60px rgba(0,0,0,0.1); 
            width: 400px; 
            text-align: center; 
            border: 1px solid rgba(0,0,0,0.05);
        }

        [data-theme="dark"] .login-card {
            border: 1px solid rgba(255,255,255,0.05);
            box-shadow: 0 50px 100px rgba(0,0,0,0.4);
        }

        .logo { color: var(--accent-gold); font-size: 50px; margin-bottom: 20px; }
        h2 { margin-bottom: 10px; color: var(--primary-dark); font-weight: 800; }
        p.subtitle { margin-bottom: 30px; font-size: 14px; opacity: 0.7; }

        input { 
            width: 100%; padding: 16px; margin-bottom: 15px; 
            border: 1.5px solid rgba(0,0,0,0.05); border-radius: 14px; 
            box-sizing: border-box; background: var(--bg-canvas);
            color: var(--primary-dark); font-family: inherit; outline: none; transition: 0.3s;
        }

        input:focus { border-color: var(--accent-gold); box-shadow: 0 0 0 4px rgba(194, 163, 93, 0.1); }

        button { 
            width: 100%; padding: 16px; background: var(--primary-dark); 
            color: var(--bg-canvas); border: none; border-radius: 14px; 
            cursor: pointer; font-weight: 800; text-transform: uppercase;
            letter-spacing: 1px; transition: 0.3s; margin-top: 10px;
        }

        button:hover { background: var(--accent-gold); color: #000; transform: translateY(-2px); }

        .error { background: rgba(239, 68, 68, 0.1); color: var(--danger); padding: 12px; border-radius: 10px; font-size: 14px; margin-bottom: 20px; font-weight: 600; }

        .link { margin-top: 25px; font-size: 14px; color: #94a3b8; }
        a { color: var(--accent-gold); text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>

<div class="login-card">
    <i class="fas fa-satellite-dish logo"></i>
    <h2>Recovery Link</h2>
    <p class="subtitle">Enter your email to dispatch a verification code.</p>
    
    <?php if($error): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="email" name="email" placeholder="Recovery Email Address" required autofocus>
        <button type="submit" name="send_otp">Dispatch Code</button>
    </form>

    <div class="link">
        Abort protocol? <a href="login.php">Back to Login</a>
    </div>
</div>

<script>
    // Theme sync logic
    const currentTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', currentTheme);
</script>
<?php include 'footer.php'; ?>
</body>
</html>