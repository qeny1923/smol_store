<?php
// ═══════════════════════════════════════════════════════════
//  index.php — Direct login (no OTP on login)
//  OTP verification only happens during Sign Up
// ═══════════════════════════════════════════════════════════
session_start();
require_once 'auth_helpers.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $conn       = db();
    $email      = trim($_POST['email']);
    $password   = $_POST['password'];
    $safe_email = $conn->real_escape_string($email);

    $result = $conn->query("SELECT * FROM Users WHERE email = '$safe_email' AND is_verified = 1 LIMIT 1");

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']  = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email']    = $user['email'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Incorrect password. Please try again.";
        }
    } else {
        // Check if account exists but isn't verified yet
        $check = $conn->query("SELECT is_verified FROM Users WHERE email = '$safe_email' LIMIT 1");
        if ($check && $check->num_rows > 0 && $check->fetch_assoc()['is_verified'] == 0) {
            $error = "Email not verified. Please complete sign up first.";
        } else {
            $error = "No account found with that email.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — CornerStop</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; }

        .auth-card {
            width: 100%;
            max-width: 420px;
            background: rgba(255,255,255,0.72);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(255,255,255,0.5);
            border-radius: 20px;
            padding: 44px 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.22);
        }

        .auth-brand { text-align:center; margin-bottom:32px; }

        .logo-icon {
            width:54px; height:54px;
            background: var(--primary);
            border-radius:14px;
            display:inline-flex; align-items:center; justify-content:center;
            margin-bottom:14px;
            box-shadow: 0 4px 16px rgba(44,62,80,0.25);
        }

        .auth-brand h1 { font-size:1.5rem; font-weight:700; color:var(--primary); margin:0 0 4px; text-shadow:0 1px 3px rgba(255,255,255,0.6); }
        .auth-brand p  { font-size:0.82rem; color:#636e72; font-weight:600; margin:0; }
        .auth-title    { font-size:1.1rem; font-weight:700; color:var(--primary); margin:0 0 6px; text-shadow:0 1px 2px rgba(255,255,255,0.6); }
        .auth-subtitle { font-size:0.8rem; color:#636e72; font-weight:600; margin:0 0 28px; }

        .form-group  { margin-bottom:18px; }
        .btn-full    { width:100%; justify-content:center; padding:13px; font-size:0.85rem; margin-top:6px; }

        .divider {
            display:flex; align-items:center; gap:12px;
            margin:22px 0; color:#b2bec3;
            font-size:0.7rem; font-weight:700;
            text-transform:uppercase; letter-spacing:1px;
        }
        .divider::before, .divider::after { content:''; flex:1; height:1px; background:rgba(0,0,0,0.1); }

        .auth-footer { text-align:center; font-size:0.8rem; color:#636e72; font-weight:600; }
        .auth-footer a { color:var(--accent); text-decoration:none; font-weight:700; }
        .auth-footer a:hover { text-decoration:underline; }
    </style>
</head>
<body>
<div class="auth-card">

    <div class="auth-brand">
        <div class="logo-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
        </div>
        <h1>CornerStop</h1>
        <p>Operations Management System</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <p class="auth-title">Welcome back</p>
    <p class="auth-subtitle">Sign in to access your console.</p>

    <form method="POST">
        <div class="form-group">
            <label class="label-small">Email Address</label>
            <input type="email" name="email" class="form-control"
                   placeholder="your@email.com"
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                   required autofocus>
        </div>
        <div class="form-group">
            <label class="label-small">Password</label>
            <input type="password" name="password" class="form-control"
                   placeholder="••••••••" required>
        </div>
        <button type="submit" name="login_submit" class="btn btn-primary btn-full">
            Sign In &rarr;
        </button>
    </form>

    <div class="divider">or</div>

    <div class="auth-footer">
        Don't have an account? <a href="signup.php">Create one</a>
    </div>

</div>
</body>
</html>