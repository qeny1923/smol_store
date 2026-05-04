<?php
/**
 * CornerStop — PHPMailer Integration
 * ------------------------------------
 * Manual installation method: PHPMailer files live in /phpmailer/
 * and are loaded via require_once (no Composer autoloader needed).
 *
 * SMTP Target  : Gmail
 * Port         : 587  (STARTTLS / TLS encryption)
 * Error lang   : English (PHPMailer internal default — no language file required)
 * Body format  : HTML with plain-text alternative (multipart/alternative)
 * Error model  : Exception-based (exceptions = true)
 */

// ──────────────────────────────────────────────
// 1. LOAD PHPMAILER — MANUAL REQUIRE (no Composer)
//    Adjust path if your folder is nested differently.
// ──────────────────────────────────────────────
require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

// ──────────────────────────────────────────────
// 2. IMPORT NAMESPACES
//    All three classes live under PHPMailer\PHPMailer
// ──────────────────────────────────────────────
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ──────────────────────────────────────────────
// 3. GMAIL APP PASSWORD — CONFIGURATION
//    Never hard-code real credentials in production.
//    Use environment variables or a config file
//    excluded from version control (.gitignore).
//
//    HOW TO GET AN APP PASSWORD:
//      Google Account → Security → 2-Step Verification
//      → App passwords → Generate (select "Mail" + "Other")
//      Paste the 16-char code below (no spaces).
// ──────────────────────────────────────────────
define('SMTP_HOST',     'smtp.gmail.com');
define('SMTP_PORT',     587);                        // Port 587 = STARTTLS
define('SMTP_USERNAME', 'qaiserth2005@gmail.com');
define('SMTP_PASSWORD', 'nepg geib nazf vmgk'); // paste your 16-char App Password here (no spaces)
define('MAIL_FROM',     'qaiserth2005@gmail.com');
define('MAIL_FROM_NAME','CornerStop System');         // sender display name

// ──────────────────────────────────────────────
// 4. HELPER FUNCTION — sendCornerStopMail()
//    Centralises all PHPMailer logic so the rest
//    of your project (dashboard.php, etc.) only
//    needs one clean function call.
//
//    @param string       $toEmail    Recipient e-mail
//    @param string       $toName     Recipient display name
//    @param string       $subject    E-mail subject line
//    @param string       $htmlBody   Full HTML message body
//    @param string|null  $plainBody  Plain-text fallback (auto-generated if null)
//    @return array ['success' => bool, 'message' => string]
// ──────────────────────────────────────────────
function sendCornerStopMail(
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody,
    ?string $plainBody = null
): array {

    // Pass `true` to the constructor to enable exceptions.
    // Without this, PHPMailer returns false on failure instead of throwing.
    $mail = new PHPMailer(true);

    try {

        // ── SMTP TRANSPORT ──────────────────────────────
        $mail->isSMTP();                                 // Use SMTP (not PHP mail())
        $mail->Host       = SMTP_HOST;                   // smtp.gmail.com
        $mail->SMTPAuth   = true;                        // Require authentication
        $mail->Username   = SMTP_USERNAME;               // Gmail address
        $mail->Password   = SMTP_PASSWORD;               // App Password (not account PW)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS via STARTTLS (port 587)
        $mail->Port       = SMTP_PORT;                   // 587

        // ── LANGUAGE / DEBUG ────────────────────────────
        // setLanguage() with no arguments defaults to English internally.
        // PHPMailer::DEBUG_OFF  = 0 — silent in production (recommended)
        // PHPMailer::DEBUG_SERVER = 2 — verbose (use only while testing)
        $mail->setLanguage('en');                        // English error messages (default)
        $mail->SMTPDebug  = SMTP::DEBUG_OFF;             // Change to DEBUG_SERVER to debug

        // ── SENDER & RECIPIENT ──────────────────────────
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);            // Primary recipient
        // $mail->addCC('manager@example.com', 'Manager'); // Optional CC
        // $mail->addBCC('log@example.com');               // Optional BCC

        // ── REPLY-TO (optional) ─────────────────────────
        $mail->addReplyTo(MAIL_FROM, MAIL_FROM_NAME);

        // ── MESSAGE CONTENT ─────────────────────────────
        $mail->isHTML(true);                             // Enable HTML mode
        $mail->CharSet  = PHPMailer::CHARSET_UTF8;       // UTF-8 for Filipino characters (₱, etc.)
        $mail->Subject  = $subject;
        $mail->Body     = $htmlBody;                     // HTML version shown by modern clients

        // Plain-text alternative: shown by clients that cannot render HTML.
        // If caller didn't supply one, strip HTML tags as a basic fallback.
        $mail->AltBody  = $plainBody ?? strip_tags(
            str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], "\n", $htmlBody)
        );

        // ── SEND ────────────────────────────────────────
        $mail->send();

        return [
            'success' => true,
            'message' => 'Email sent successfully to ' . $toEmail
        ];

    } catch (Exception $e) {
        // $mail->ErrorInfo contains the human-readable SMTP error string.
        return [
            'success' => false,
            'message' => 'Mailer Error: ' . $mail->ErrorInfo
        ];
    }
}


// ══════════════════════════════════════════════
// USAGE EXAMPLES
// Uncomment the block that matches your use case
// ══════════════════════════════════════════════

// ──────────────────────────────────────────────
// EXAMPLE A — Standalone test (run this file directly)
// ──────────────────────────────────────────────

$htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body      { font-family: 'Segoe UI', sans-serif; background: #f8fafc; margin: 0; padding: 0; }
    .container{ max-width: 580px; margin: 40px auto; background: #ffffff;
                border-radius: 12px; overflow: hidden;
                box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
    .header   { background: #0f172a; padding: 30px 40px; color: #ffffff; }
    .header h1{ margin: 0; font-size: 1.4rem; letter-spacing: -0.5px; }
    .header p { margin: 4px 0 0; color: #94a3b8; font-size: 0.85rem; }
    .body     { padding: 35px 40px; color: #334155; line-height: 1.7; }
    .body h2  { color: #0f172a; margin-top: 0; }
    .badge    { display: inline-block; background: #0d9488; color: #fff;
                padding: 4px 12px; border-radius: 20px; font-size: 0.75rem;
                font-weight: 600; margin-bottom: 20px; }
    .footer   { background: #f1f5f9; padding: 20px 40px;
                font-size: 0.75rem; color: #94a3b8; text-align: center; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>CornerStop</h1>
      <p>Database Management System</p>
    </div>
    <div class="body">
      <span class="badge">System Notification</span>
      <h2>Test Email — PHPMailer Integration</h2>
      <p>Hello! This email confirms that PHPMailer is correctly configured
         on the <strong>CornerStop</strong> system using Gmail SMTP on
         <strong>Port 587 with STARTTLS</strong> encryption.</p>
      <p>If you received this, your credentials and SMTP settings are working.
         You can now integrate <code>sendCornerStopMail()</code> anywhere in
         your project.</p>
      <hr style="border:none; border-top:1px solid #e2e8f0; margin: 24px 0;">
      <p style="font-size:0.85rem; color:#64748b;">
        Sent from: <?php echo MAIL_FROM; ?><br>
        Timestamp: <?php echo date('F j, Y  g:i A'); ?>
      </p>
    </div>
    <div class="footer">
      CornerStop Operations &copy; <?php echo date('Y'); ?> &nbsp;|&nbsp; Automated System Mail
    </div>
  </div>
</body>
</html>
HTML;

$result = sendCornerStopMail(
    toEmail: 'qaiserth2005@gmail.com',  // send to yourself
    toName:  'Qaiser',
    subject: 'CornerStop — PHPMailer Test',
    htmlBody: $htmlBody
);

echo $result['message'];



// ──────────────────────────────────────────────
// EXAMPLE B — Low-stock alert (call from dashboard.php)
//
// After a sale is posted, check if stock fell below
// threshold and fire an alert email to the store owner.
//
// In dashboard.php, after the 'add_order' block:
//   require_once 'send_mail.php';
//   if ($p_check['stock_quantity'] - $qty < 10) {
//       sendCornerStopMail(
//           'owner@example.com',
//           'Aling Owner',
//           'Low Stock Alert — CornerStop',
//           buildLowStockEmail($product_name, $remaining_qty)
//       );
//   }
// ──────────────────────────────────────────────
function buildLowStockEmail(string $productName, int $remaining): string
{
    return <<<HTML
    <!DOCTYPE html><html><body style="font-family:Segoe UI,sans-serif;background:#f8fafc;">
    <div style="max-width:520px;margin:40px auto;background:#fff;border-radius:12px;
                box-shadow:0 4px 20px rgba(0,0,0,0.08);overflow:hidden;">
      <div style="background:#7f1d1d;padding:28px 36px;color:#fff;">
        <h2 style="margin:0;">⚠ Low Stock Alert</h2>
        <p style="margin:4px 0 0;color:#fca5a5;font-size:0.85rem;">CornerStop Inventory System</p>
      </div>
      <div style="padding:30px 36px;color:#334155;line-height:1.7;">
        <p>The following item has dropped below the minimum stock threshold:</p>
        <table style="width:100%;border-collapse:collapse;margin:16px 0;">
          <tr style="background:#fef2f2;">
            <td style="padding:12px 16px;font-weight:600;">Product</td>
            <td style="padding:12px 16px;">{$productName}</td>
          </tr>
          <tr>
            <td style="padding:12px 16px;font-weight:600;">Remaining Stock</td>
            <td style="padding:12px 16px;color:#dc2626;font-weight:700;">{$remaining} units</td>
          </tr>
        </table>
        <p>Please restock this item as soon as possible to avoid stockouts.</p>
      </div>
      <div style="background:#f1f5f9;padding:18px 36px;font-size:0.75rem;color:#94a3b8;text-align:center;">
        CornerStop Operations &copy; <?php echo date('Y'); ?> &nbsp;|&nbsp; Automated Alert
      </div>
    </div>
    </body></html>
    HTML;
}
