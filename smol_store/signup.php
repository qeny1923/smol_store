<?php
// ═══════════════════════════════════════════════════════════
//  signup.php — Registration page with OTP email verification
// ═══════════════════════════════════════════════════════════
session_start();
require_once 'auth_helpers.php';

// FIX: was checking $_SESSION['verified'] which is never set anywhere.
// The correct check is whether the user is already fully logged in.
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error   = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup_submit'])) {
    $conn     = db();
    $username = trim($_POST['username']);
    $email    = strtolower(trim($_POST['email']));
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    // ── Validation ────────────────────────────────────────
    if (empty($username) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // Check if email already registered
        $safe_email = $conn->real_escape_string($email);
        $check = $conn->query("SELECT user_id, is_verified FROM Users WHERE email = '$safe_email' LIMIT 1");

        if ($check && $check->num_rows > 0) {
            $existing = $check->fetch_assoc();
            if ($existing['is_verified'] == 1) {
                // Fully registered account — tell them to sign in
                $error = "An account with that email already exists. <a href='index.php'>Sign in instead?</a>";
            } else {
                // Unverified account left over — resend OTP instead of duplicating the row
                $existing_id = (int) $existing['user_id'];
                $user_row = $conn->query("SELECT username FROM Users WHERE user_id = $existing_id")->fetch_assoc();
                $otp  = generateOTP();
                saveOTP($existing_id, $otp);
                // FIX: sendOTPEmail only accepts 3 params — removed bogus 4th arg
                $sent = sendOTPEmail($email, $user_row['username'], $otp);
                if ($sent) {
                    $_SESSION['pending_user_id'] = $existing_id;
                    $_SESSION['pending_email']   = $email;
                    header("Location: verify_otp.php");
                    exit();
                } else {
                    $error = "Failed to send verification email. Please try again.";
                }
            }
        } else {
            // Create the account (unverified)
            $safe_username = $conn->real_escape_string($username);
            $hash          = password_hash($password, PASSWORD_DEFAULT);

            $conn->query("INSERT INTO Users (username, email, password_hash, is_verified)
                          VALUES ('$safe_username', '$safe_email', '$hash', 0)");

            $new_user_id = $conn->insert_id;

            // FIX: saveOTP only accepts 2 params — removed bogus 3rd arg
            $otp  = generateOTP();
            saveOTP($new_user_id, $otp);

            // FIX: sendOTPEmail only accepts 3 params — removed bogus 4th arg
            $sent = sendOTPEmail($email, $username, $otp);

            if ($sent) {
                $_SESSION['pending_user_id'] = $new_user_id;
                $_SESSION['pending_email']   = $email;
                header("Location: verify_otp.php");
                exit();
            } else {
                // Email failed — delete the incomplete account
                $conn->query("DELETE FROM Users WHERE user_id = $new_user_id");
                $error = "Failed to send verification email. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up — CornerStop</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 30px 20px;
        }

        .auth-card {
            width: 100%;
            max-width: 440px;
            background: rgba(255, 255, 255, 0.72);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(255,255,255,0.5);
            border-radius: 20px;
            padding: 44px 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.22);
        }

        .auth-brand {
            text-align: center;
            margin-bottom: 32px;
        }

        .auth-brand .logo-icon {
            width: 54px; height: 54px;
            background: var(--primary);
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
            box-shadow: 0 4px 16px rgba(44,62,80,0.25);
        }

        .auth-brand h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0 0 4px;
            text-shadow: 0 1px 3px rgba(255,255,255,0.6);
        }

        .auth-brand p {
            font-size: 0.82rem;
            color: #636e72;
            margin: 0;
            font-weight: 600;
        }

        .auth-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0 0 6px;
            text-shadow: 0 1px 2px rgba(255,255,255,0.6);
        }

        .auth-subtitle {
            font-size: 0.8rem;
            color: #636e72;
            font-weight: 600;
            margin: 0 0 24px;
        }

        .auth-form .form-group { margin-bottom: 16px; }

        .password-hint {
            font-size: 0.7rem;
            color: #a0aec0;
            font-weight: 600;
            margin-top: 4px;
        }

        .strength-bar {
            height: 3px;
            border-radius: 3px;
            background: #e2e8f0;
            margin-top: 6px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            border-radius: 3px;
            width: 0%;
            transition: width 0.3s, background 0.3s;
        }

        .btn-full {
            width: 100%;
            justify-content: center;
            padding: 13px;
            font-size: 0.82rem;
            margin-top: 6px;
        }

        .auth-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 0.8rem;
            color: #636e72;
            font-weight: 600;
        }

        .auth-footer a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 700;
        }

        .auth-footer a:hover { text-decoration: underline; }

        .terms-note {
            font-size: 0.72rem;
            color: #a0aec0;
            text-align: center;
            margin-top: 14px;
            font-weight: 600;
            line-height: 1.5;
        }
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
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <p class="auth-title">Create your account</p>
    <p class="auth-subtitle">A verification code will be sent to your email to confirm your address.</p>

    <form method="POST" class="auth-form" id="signupForm">

        <div class="form-group">
            <label class="label-small">Username</label>
            <input type="text" name="username" class="form-control"
                   placeholder="e.g. Bossing"
                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                   required autofocus>
        </div>

        <div class="form-group">
            <label class="label-small">Email Address</label>
            <input type="email" name="email" class="form-control"
                   placeholder="your@email.com"
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                   required>
        </div>

        <div class="form-group">
            <label class="label-small">Password</label>
            <input type="password" name="password" id="passwordInput"
                   class="form-control" placeholder="Min. 8 characters"
                   required oninput="checkStrength(this.value)">
            <div class="strength-bar">
                <div class="strength-fill" id="strengthFill"></div>
            </div>
            <div class="password-hint" id="strengthLabel">Enter a password</div>
        </div>

        <div class="form-group">
            <label class="label-small">Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control"
                   placeholder="Repeat your password" required>
        </div>

        <button type="submit" name="signup_submit" class="btn btn-accent btn-full">
            Create Account &rarr;
        </button>
    </form>

    <div class="terms-note">
        By signing up, your email will be verified via a one-time code.
    </div>

    <div class="auth-footer" style="margin-top: 16px;">
        Already have an account? <a href="index.php">Sign in</a>
    </div>

</div>

<script>
function checkStrength(password) {
    const fill  = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');

    let score = 0;
    if (password.length >= 8)               score++;
    if (/[A-Z]/.test(password))             score++;
    if (/[0-9]/.test(password))             score++;
    if (/[^A-Za-z0-9]/.test(password))     score++;

    const levels = [
        { pct: '0%',   color: '#e2e8f0', text: 'Enter a password' },
        { pct: '25%',  color: '#e74c3c', text: 'Weak' },
        { pct: '50%',  color: '#f39c12', text: 'Fair' },
        { pct: '75%',  color: '#3498db', text: 'Good' },
        { pct: '100%', color: '#1abc9c', text: 'Strong ✓' },
    ];

    const level = password.length === 0 ? levels[0] : levels[score];
    fill.style.width      = level.pct;
    fill.style.background = level.color;
    label.textContent     = level.text;
    label.style.color     = level.color;
}
</script>

</body>
</html>
