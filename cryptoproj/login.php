<?php
session_start();
$conn = new mysqli("localhost", "root", "", "whatsapp_clone");

$error = "";

if (isset($_POST['login'])) {
    $email = $_POST['email']; // Changed from username to email
    $password = $_POST['password'];

    // Updated query to select by email instead of username
    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        // Verify the hashed password
        if (password_verify($password, $user['password'])) {
            $_SESSION['my_name'] = $user['username']; // Still store username for the UI
            $_SESSION['user_id'] = $user['id'];
            header("Location: index.php");
            exit();
        } else {
            $error = "Invalid access key.";
        }
    } else {
        $error = "Identity not recognized.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SecureVault Elite | Login</title>
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
            transition: background 0.3s ease, color 0.3s ease;
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

        .logo { 
            color: var(--accent-gold); 
            font-size: 50px; 
            margin-bottom: 20px; 
        }

        h2 { 
            margin-bottom: 30px; 
            color: var(--primary-dark); 
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        input { 
            width: 100%; 
            padding: 16px; 
            margin-bottom: 15px; 
            border: 1.5px solid rgba(0,0,0,0.05); 
            border-radius: 14px; 
            box-sizing: border-box; 
            background: var(--bg-canvas);
            color: var(--primary-dark);
            font-family: inherit;
            outline: none;
            transition: 0.3s;
        }

        input:focus {
            border-color: var(--accent-gold);
            box-shadow: 0 0 0 4px rgba(194, 163, 93, 0.1);
        }

        button { 
            width: 100%; 
            padding: 16px; 
            background: var(--primary-dark); 
            color: var(--bg-canvas); 
            border: none; 
            border-radius: 14px; 
            cursor: pointer; 
            font-weight: 800; 
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: 0.3s;
            margin-top: 10px;
        }

        button:hover { 
            background: var(--accent-gold); 
            color: #000;
            transform: translateY(-2px);
        }

        .error { 
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger); 
            padding: 12px;
            border-radius: 10px;
            font-size: 14px; 
            margin-bottom: 20px; 
            font-weight: 600;
        }

        .link { 
            margin-top: 25px; 
            font-size: 14px; 
            color: #94a3b8; 
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        a { 
            color: var(--accent-gold); 
            text-decoration: none; 
            font-weight: 700; 
        }
        
        a:hover {
            text-decoration: underline;
        }

        .forgot-link {
            font-size: 13px;
            opacity: 0.8;
        }
    </style>
</head>
<body>

<div class="login-card">
    <i class="fas fa-shield-halved logo"></i>
    <h2>Vault Access</h2>
    
    <?php if($error): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="email" name="email" placeholder="Registered Email" required autofocus>
        <input type="password" name="password" placeholder="Access Key" required>
        <button type="submit" name="login">Authorize Session</button>
    </form>

    <div class="link">
        <a href="forgot_password.php" class="forgot-link">Lost your access key?</a>
        <div>New operative? <a href="register.php">Initialize Account</a></div>
    </div>
</div>

<script>
    function syncTheme() {
        const currentTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', currentTheme);
    }
    syncTheme();
    window.addEventListener('storage', (e) => {
        if (e.key === 'theme') syncTheme();
    });
</script>

<?php include 'footer.php'; ?>
</body>
</html>