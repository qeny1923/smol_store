<?php
// ═══════════════════════════════════════════════════════════
//  auth_helpers.php — Shared utilities for Sign Up + OTP
// ═══════════════════════════════════════════════════════════
require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ── Database connection (singleton) ──────────────────────
function db(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli("localhost", "root", "", "store_tracker");
        if ($conn->connect_error) die("DB connection failed.");
        $conn->set_charset("utf8mb4");
    }
    return $conn;
}

// ── Generate a secure 6-digit OTP ────────────────────────
function generateOTP(): string {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

// ── Save OTP (invalidates any previous unused ones) ──────
function saveOTP(int $userId, string $code): void {
    $conn = db();
    $conn->query("UPDATE OTP_Tokens SET used = 1
                  WHERE user_id = $userId AND purpose = 'signup' AND used = 0");
    $conn->query("INSERT INTO OTP_Tokens (user_id, otp_code, purpose, expires_at)
                  VALUES ($userId, '$code', 'signup', DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
}

// ── Verify OTP ────────────────────────────────────────────
function verifyOTP(int $userId, string $inputCode): bool {
    $conn = db();
    $code = $conn->real_escape_string(trim($inputCode));

    $result = $conn->query("
        SELECT token_id FROM OTP_Tokens
        WHERE user_id  = $userId
          AND otp_code = '$code'
          AND purpose  = 'signup'
          AND used     = 0
          AND expires_at > NOW()
        LIMIT 1
    ");

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $conn->query("UPDATE OTP_Tokens SET used = 1 WHERE token_id = {$row['token_id']}");
        return true;
    }
    return false;
}

// ── Send OTP email via Gmail ──────────────────────────────
function sendOTPEmail(string $toEmail, string $toName, string $otp): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'qaiserth2005@gmail.com';      // ← your Gmail
        $mail->Password   = 'xxxx xxxx xxxx xxxx';         // ← your App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->SMTPDebug  = SMTP::DEBUG_OFF;

        $mail->setFrom('qaiserth2005@gmail.com', 'CornerStop System');
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->CharSet = PHPMailer::CHARSET_UTF8;

        $mail->Subject = "CornerStop — Your Verification Code: $otp";
        $mail->Body    = buildOTPEmail($toName, $otp);
        $mail->AltBody = "Your CornerStop email verification code is: $otp\nExpires in 10 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("OTP mail error: " . $mail->ErrorInfo);
        return false;
    }
}

// ── HTML email template ───────────────────────────────────
function buildOTPEmail(string $name, string $otp): string {
    $digits = '';
    foreach (str_split($otp) as $d) {
        $digits .= "<span style='display:inline-block;width:46px;height:56px;line-height:56px;
            text-align:center;background:#f8fafc;border:2px solid #e2e8f0;border-radius:10px;
            font-size:1.7rem;font-weight:800;color:#0f172a;margin:0 3px;
            font-family:monospace;'>$d</span>";
    }

    return <<<HTML
    <!DOCTYPE html><html><head><meta charset="UTF-8"></head>
    <body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',sans-serif;">
      <div style="max-width:520px;margin:40px auto;background:#fff;border-radius:16px;
                  overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
        <div style="background:#0f172a;padding:32px 40px;">
          <div style="color:#0d9488;font-size:0.7rem;font-weight:800;letter-spacing:2px;
                      text-transform:uppercase;margin-bottom:6px;">CornerStop System</div>
          <h1 style="color:#fff;margin:0;font-size:1.4rem;font-weight:700;">Email Verification</h1>
        </div>
        <div style="padding:36px 40px;">
          <p style="color:#334155;font-size:0.95rem;line-height:1.7;margin:0 0 8px;">
            Hi <strong>$name</strong>,
          </p>
          <p style="color:#64748b;font-size:0.88rem;line-height:1.7;margin:0 0 28px;">
            Enter this code to verify your email and complete registration.
            It expires in <strong>10 minutes</strong> and can only be used once.
          </p>
          <div style="text-align:center;margin:0 0 28px;">$digits</div>
          <div style="background:#fffbeb;border-left:4px solid #f59e0b;border-radius:6px;
                      padding:14px 18px;margin-bottom:24px;">
            <p style="margin:0;font-size:0.8rem;color:#92400e;font-weight:600;">
              ⚠ Never share this code. CornerStop will never ask for it.
            </p>
          </div>
          <p style="color:#94a3b8;font-size:0.78rem;margin:0;">
            If you didn't create a CornerStop account, ignore this email.
          </p>
        </div>
        <div style="background:#f8fafc;padding:20px 40px;border-top:1px solid #e2e8f0;text-align:center;">
          <p style="margin:0;color:#94a3b8;font-size:0.72rem;">
            CornerStop Operations &copy; 2026 &nbsp;|&nbsp; Automated Security Mail
          </p>
        </div>
      </div>
    </body></html>
    HTML;
}

// ── Mask email for display: qa****@gmail.com ──────────────
function maskEmail(string $email): string {
    [$local, $domain] = explode('@', $email);
    $visible = substr($local, 0, 2);
    return $visible . str_repeat('*', max(1, strlen($local) - 2)) . '@' . $domain;
}
?>
