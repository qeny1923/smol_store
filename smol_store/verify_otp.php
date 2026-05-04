<?php
// ═══════════════════════════════════════════════════════════
//  verify_otp.php — Email verification (Sign Up only)
//  Shows a success screen before redirecting to dashboard
// ═══════════════════════════════════════════════════════════
session_start();
require_once 'auth_helpers.php';

// Must come from the signup flow
if (!isset($_SESSION['pending_user_id']) || !isset($_SESSION['pending_email'])) {
    header("Location: index.php");
    exit();
}

$user_id = (int) $_SESSION['pending_user_id'];
$email   = $_SESSION['pending_email'];
$masked  = maskEmail($email);

$error     = "";
$resend_ok = "";
$verified  = false;   // flips to true after correct OTP → shows success screen

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = db();

    // ── Resend OTP ────────────────────────────────────────
    if (isset($_POST['resend_otp'])) {
        $user = $conn->query("SELECT username FROM Users WHERE user_id = $user_id")->fetch_assoc();
        $otp  = generateOTP();
        saveOTP($user_id, $otp);
        $sent = sendOTPEmail($email, $user['username'], $otp);
        $resend_ok = $sent
            ? "A new code was sent to $masked."
            : "Failed to resend. Please try again.";
    }

    // ── Verify OTP ────────────────────────────────────────
    if (isset($_POST['verify_submit'])) {
        $digits = '';
        for ($i = 1; $i <= 6; $i++) {
            $digits .= preg_replace('/\D/', '', $_POST["d$i"] ?? '');
        }

        if (strlen($digits) < 6) {
            $error = "Please enter all 6 digits.";
        } elseif (verifyOTP($user_id, $digits)) {
            // ── Correct — mark account verified & log in ──
            $conn->query("UPDATE Users SET is_verified = 1 WHERE user_id = $user_id");
            $user = $conn->query("SELECT * FROM Users WHERE user_id = $user_id")->fetch_assoc();

            // Clear pending keys, set full session
            unset($_SESSION['pending_user_id'], $_SESSION['pending_email']);
            $_SESSION['user_id']  = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email']    = $user['email'];

            $verified = true;   // show success screen
        } else {
            $error = "Invalid or expired code. Check your inbox and try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email — CornerStop</title>
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
            text-align: center;
        }

        /* ── OTP screen ──────────────────────────── */
        .otp-icon {
            width: 62px; height: 62px;
            background: rgba(26,188,156,0.1);
            border: 2px solid rgba(26,188,156,0.25);
            border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            margin-bottom: 18px;
            color: var(--accent);
        }

        .otp-title    { font-size:1.2rem; font-weight:700; color:var(--primary); margin:0 0 8px; text-shadow:0 1px 2px rgba(255,255,255,0.6); }
        .otp-subtitle { font-size:0.82rem; color:#636e72; font-weight:600; margin:0 0 30px; line-height:1.7; }
        .otp-subtitle strong { color:var(--primary); }

        .otp-inputs { display:flex; gap:10px; justify-content:center; margin-bottom:24px; }

        .otp-inputs input {
            width: 50px; height: 58px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            background: rgba(255,255,255,0.85);
            border: 2px solid rgba(255,255,255,0.6);
            border-radius: 12px;
            padding: 0;
            transition: border-color 0.2s, box-shadow 0.2s, transform 0.15s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            caret-color: var(--accent);
        }

        .otp-inputs input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(26,188,156,0.2);
            transform: scale(1.06);
            outline: none;
        }

        .otp-inputs input.filled {
            border-color: var(--accent);
            background: rgba(26,188,156,0.05);
        }

        .btn-full    { width:100%; justify-content:center; padding:13px; font-size:0.85rem; margin-bottom:16px; }

        .resend-row  { font-size:0.8rem; color:#636e72; font-weight:600; }
        .resend-row button {
            background:none; border:none; color:var(--accent);
            font-weight:700; cursor:pointer; font-size:0.8rem;
            padding:0; font-family:'Segoe UI',sans-serif;
        }
        .resend-row button:disabled { color:#b2bec3; cursor:default; }
        .resend-row button:not(:disabled):hover { text-decoration:underline; }

        #countdown { font-size:0.72rem; color:#a0aec0; margin-top:6px; font-weight:600; }
        #countdown.expiring { color:var(--danger); }

        .back-link { display:inline-block; margin-top:20px; font-size:0.78rem; color:#a0aec0; text-decoration:none; font-weight:600; }
        .back-link:hover { color:var(--primary); }

        /* ── Success screen ──────────────────────── */
        .success-screen { display:none; }
        .success-screen.visible { display:block; }
        .otp-screen { display:block; }
        .otp-screen.hidden { display:none; }

        .success-icon {
            width: 72px; height: 72px;
            background: rgba(26,188,156,0.12);
            border: 3px solid var(--accent);
            border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            margin-bottom: 20px;
            color: var(--accent);
            animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
        }

        @keyframes popIn {
            from { transform: scale(0.5); opacity: 0; }
            to   { transform: scale(1);   opacity: 1; }
        }

        .success-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0 0 10px;
            text-shadow: 0 1px 2px rgba(255,255,255,0.6);
            animation: fadeUp 0.4s 0.2s ease both;
        }

        .success-msg {
            font-size: 0.85rem;
            color: #636e72;
            font-weight: 600;
            line-height: 1.7;
            margin: 0 0 28px;
            animation: fadeUp 0.4s 0.3s ease both;
        }

        .success-msg strong { color: var(--primary); }

        @keyframes fadeUp {
            from { opacity:0; transform:translateY(10px); }
            to   { opacity:1; transform:translateY(0); }
        }

        .progress-wrap {
            background: rgba(0,0,0,0.08);
            border-radius: 4px;
            height: 4px;
            overflow: hidden;
            margin-bottom: 10px;
            animation: fadeUp 0.4s 0.4s ease both;
        }

        .progress-fill {
            height: 100%;
            background: var(--accent);
            border-radius: 4px;
            width: 100%;
            transform-origin: left;
            animation: drainRight 3s linear forwards;
        }

        @keyframes drainRight {
            from { transform: scaleX(1); }
            to   { transform: scaleX(0); }
        }

        .redirect-note {
            font-size: 0.75rem;
            color: #a0aec0;
            font-weight: 600;
            animation: fadeUp 0.4s 0.4s ease both;
        }
    </style>
</head>
<body>

<div class="auth-card">

    <!-- ══ OTP INPUT SCREEN ══════════════════════════════ -->
    <div class="otp-screen <?php echo $verified ? 'hidden' : ''; ?>">

        <div class="otp-icon">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                <polyline points="22,6 12,13 2,6"/>
            </svg>
        </div>

        <h2 class="otp-title">Check your inbox</h2>
        <p class="otp-subtitle">
            We sent a 6-digit code to<br>
            <strong><?php echo htmlspecialchars($masked); ?></strong><br>
            Enter it below to verify your email.
        </p>

        <?php if ($error): ?>
            <div class="alert alert-error" style="text-align:left;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($resend_ok): ?>
            <div class="alert alert-success" style="text-align:left;"><?php echo htmlspecialchars($resend_ok); ?></div>
        <?php endif; ?>

        <form method="POST" id="otpForm">
            <div class="otp-inputs">
                <?php for ($i = 1; $i <= 6; $i++): ?>
                    <input type="text" name="d<?php echo $i; ?>" id="d<?php echo $i; ?>"
                           maxlength="1" inputmode="numeric" pattern="[0-9]"
                           autocomplete="off">
                <?php endfor; ?>
            </div>
            <button type="submit" name="verify_submit" class="btn btn-accent btn-full">
                Verify &amp; Create Account
            </button>
        </form>

        <div class="resend-row">
            Didn't get it?
            <form method="POST" style="display:inline;">
                <button type="submit" name="resend_otp" id="resendBtn" disabled>Resend code</button>
            </form>
        </div>
        <div id="countdown">Resend available in <span id="timer">60</span>s</div>

        <a href="signup.php" class="back-link">← Back to sign up</a>
    </div>

    <!-- ══ SUCCESS SCREEN ════════════════════════════════ -->
    <div class="success-screen <?php echo $verified ? 'visible' : ''; ?>">

        <div class="success-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>

        <h2 class="success-title">You're verified!</h2>
        <p class="success-msg">
            Welcome to CornerStop, <strong><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></strong>.<br>
            Your email has been confirmed and your account is ready.
        </p>

        <div class="progress-wrap">
            <div class="progress-fill"></div>
        </div>
        <p class="redirect-note">Redirecting you to the dashboard…</p>

        <?php if ($verified): ?>
        <script>
            // Auto-redirect after 3 seconds (matches progress bar animation)
            setTimeout(function() {
                window.location.href = 'dashboard.php';
            }, 3000);
        </script>
        <?php endif; ?>
    </div>

</div><!-- /.auth-card -->

<script>
// ── Auto-advance OTP inputs ───────────────────────────────
const inputs = document.querySelectorAll('.otp-inputs input');

inputs.forEach((input, idx) => {
    input.addEventListener('input', () => {
        input.value = input.value.replace(/\D/g, '');
        if (input.value) {
            input.classList.add('filled');
            if (idx < inputs.length - 1) inputs[idx + 1].focus();
        } else {
            input.classList.remove('filled');
        }
        // Auto-submit when all 6 filled
        if ([...inputs].every(i => i.value.length === 1)) {
            setTimeout(() => document.getElementById('otpForm').submit(), 180);
        }
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !input.value && idx > 0) {
            inputs[idx - 1].focus();
            inputs[idx - 1].classList.remove('filled');
        }
    });

    // Support pasting full 6-digit code
    input.addEventListener('paste', (e) => {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData)
                       .getData('text').replace(/\D/g, '').slice(0, 6);
        pasted.split('').forEach((char, i) => {
            if (inputs[i]) { inputs[i].value = char; inputs[i].classList.add('filled'); }
        });
        if (pasted.length === 6) {
            setTimeout(() => document.getElementById('otpForm').submit(), 180);
        }
    });
});

if (inputs[0]) inputs[0].focus();

// ── Resend cooldown (60s) ─────────────────────────────────
let secs     = 60;
const timerEl  = document.getElementById('timer');
const countEl  = document.getElementById('countdown');
const resendBtn= document.getElementById('resendBtn');

if (resendBtn) {
    const tick = setInterval(() => {
        secs--;
        if (timerEl) timerEl.textContent = secs;
        if (secs <= 10 && countEl) countEl.classList.add('expiring');
        if (secs <= 0) {
            clearInterval(tick);
            if (countEl) countEl.style.display = 'none';
            if (resendBtn) resendBtn.disabled = false;
        }
    }, 1000);
}
</script>

</body>
</html>
