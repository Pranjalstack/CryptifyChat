<?php
session_start();
$conn = new mysqli("localhost", "root", "", "whatsapp_clone");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- ENHANCED DATABASE REPAIR BLOCK ---
$checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'email'");
if ($checkColumn->num_rows == 0) {
    // 1. Drop ghost index if it exists to prevent 'Duplicate entry' on the key itself
    $conn->query("ALTER TABLE users DROP INDEX IF EXISTS email");
    
    // 2. Add the column temporarily without the UNIQUE constraint to avoid the '' error
    $conn->query("ALTER TABLE users ADD COLUMN email VARCHAR(255) AFTER username");
    
    // 3. Update existing rows to have a dummy email based on their username 
    // This prevents multiple '' (empty strings) from blocking the UNIQUE constraint
    $conn->query("UPDATE users SET email = CONCAT(username, '@vault.local') WHERE email IS NULL OR email = ''");
    
    // 4. Now safely apply the UNIQUE and NOT NULL constraints
    $conn->query("ALTER TABLE users MODIFY COLUMN email VARCHAR(255) NOT NULL");
    $conn->query("ALTER TABLE users ADD UNIQUE (email)");
}
// --------------------------------------

$message = "";
$error = "";

if (isset($_POST['register'])) {
    $username = $_POST['username'];
    $email = $_POST['email']; 
    $password = $_POST['password'];

    // Check if user or email already exists
    $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $check->bind_param("ss", $username, $email);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        $error = "Identifier or Email already registered in the Vault.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $email, $hashed_password);
        
        if ($stmt->execute()) {
            $message = "Onboarding complete. <a href='login.php'>Access Vault</a>";
        } else {
            $error = "Transmission failed. Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SecureVault Elite | Register</title>
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
            --success: #1bd1a5;
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
            transition: all 0.3s ease;
        }

        [data-theme="dark"] .login-card {
            border: 1px solid rgba(255,255,255,0.05);
            box-shadow: 0 50px 100px rgba(0,0,0,0.4);
        }

        .logo { color: var(--accent-gold); font-size: 50px; margin-bottom: 20px; }
        h2 { margin-bottom: 30px; color: var(--primary-dark); font-weight: 800; letter-spacing: -0.5px; }

        input { 
            width: 100%; 
            padding: 16px; 
            margin-bottom: 15px; 
            border: 1.5px solid rgba(0,0,0,0.1); 
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
            width: 100%; padding: 16px; 
            background: var(--primary-dark); 
            color: var(--bg-canvas); 
            border: none; border-radius: 14px; 
            cursor: pointer; font-weight: 800; 
            text-transform: uppercase; letter-spacing: 1px;
            transition: 0.3s; margin-top: 10px;
        }

        button:hover { 
            background: var(--accent-gold); 
            color: #000; transform: translateY(-2px);
        }

        .error { background: rgba(239, 68, 68, 0.1); color: var(--danger); padding: 12px; border-radius: 10px; font-size: 14px; margin-bottom: 20px; font-weight: 600; }
        .success { background: rgba(27, 209, 165, 0.1); color: var(--success); padding: 12px; border-radius: 10px; font-size: 14px; margin-bottom: 20px; font-weight: 600; }
        .link { margin-top: 25px; font-size: 14px; color: #94a3b8; }
        a { color: var(--accent-gold); text-decoration: none; font-weight: 700; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="login-card">
    <i class="fas fa-user-plus logo"></i>
    <h2>Secure Onboarding</h2>
    
    <?php if($error): ?> <div class="error"><?php echo $error; ?></div> <?php endif; ?>
    <?php if($message): ?> <div class="success"><?php echo $message; ?></div> <?php endif; ?>

    <form method="POST">
        <input type="text" name="username" placeholder="Choose Identifier" required>
        <input type="email" name="email" placeholder="Recovery Email Address" required>
        <input type="password" name="password" placeholder="Choose Access Key" required>
        <button type="submit" name="register">Initialize Profile</button>
    </form>

    <div class="link">
        Already registered? <a href="login.php">Access Vault</a>
    </div>
</div>

<script>
    function applyTheme() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
    }
    applyTheme();
</script>

<?php include 'footer.php'; ?>
</body>
</html>