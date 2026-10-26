<?php
/**
 * AUTO HUB - Mail Configuration & PHPMailer Integration
 */

// Simple lightweight .env loader
if (!function_exists('auto_hub_load_env')) {
    function auto_hub_load_env($filePath) {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return;
        }
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                list($key, $val) = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val);
                // Strip surrounding quotes
                if (
                    (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                    (str_starts_with($val, "'") && str_ends_with($val, "'"))
                ) {
                    $val = substr($val, 1, -1);
                }
                if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                    putenv("{$key}={$val}");
                    $_ENV[$key] = $val;
                    $_SERVER[$key] = $val;
                }
            }
        }
    }
}
auto_hub_load_env(__DIR__ . '/../.env');

require_once __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/SMTP.php';
require_once __DIR__ . '/../libs/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!defined('MAIL_HOST')) {
    define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.gmail.com');
}
if (!defined('MAIL_PORT')) {
    define('MAIL_PORT', (int)(getenv('MAIL_PORT') ?: 587));
}
if (!defined('MAIL_USERNAME')) {
    define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'nithilarodrigo@gmail.com');
}
if (!defined('MAIL_PASSWORD')) {
    define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: ''); // Configured in .env or via Gmail App Password
}
if (!defined('MAIL_ENCRYPTION')) {
    define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'tls');
}
if (!defined('MAIL_FROM_ADDRESS')) {
    define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'nithilarodrigo@gmail.com');
}
if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'AUTO HUB Spare Parts & Accessories');
}

/**
 * Send an email using PHPMailer with SMTP
 *
 * @param string $toEmail
 * @param string $toName
 * @param string $subject
 * @param string $htmlBody
 * @param string $altBody
 * @return array ['success' => bool, 'error' => string|null, 'technical' => string|null]
 */
function send_mail($toEmail, $toName, $subject, $htmlBody, $altBody = '')
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    // Fail immediately if no password configured
    if (empty(MAIL_PASSWORD)) {
        $errMsg = "SMTP password is empty. Please set MAIL_PASSWORD (e.g. Gmail App Password) in your .env file.";
        $logEntry = date('[Y-m-d H:i:s]') . " Mail Error to {$toEmail}: {$errMsg}\n";
        @file_put_contents($logDir . '/mail_errors.log', $logEntry, FILE_APPEND);

        return [
            'success' => false,
            'error' => 'Email sending is not configured yet. Please configure the SMTP password in the server .env file.',
            'technical' => $errMsg
        ];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->Port       = MAIL_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->Timeout    = 15;

        // Encryption mode
        if (strtolower(MAIL_ENCRYPTION) === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif (strtolower(MAIL_ENCRYPTION) === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;
        }

        // Windows localhost OpenSSL stream options fix
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];

        // Sender & Recipient
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);

        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = !empty($altBody) ? $altBody : strip_tags($htmlBody);

        $mail->send();

        // Audit log of successful email send
        $logEntry = date('[Y-m-d H:i:s]') . " Sent to: {$toEmail} | Subject: {$subject}\n";
        @file_put_contents($logDir . '/mail_sent.log', $logEntry, FILE_APPEND);

        return ['success' => true, 'error' => null, 'technical' => null];
    } catch (Exception $e) {
        $errMsg = $mail->getErrorInfo() ?: $e->getMessage();
        $logEntry = date('[Y-m-d H:i:s]') . " Mail Error to {$toEmail}: {$errMsg}\n";
        @file_put_contents($logDir . '/mail_errors.log', $logEntry, FILE_APPEND);

        return [
            'success' => false,
            'error' => 'Failed to deliver the email. Please check SMTP connection settings and try again.',
            'technical' => $errMsg
        ];
    }
}

/**
 * Send 6-Digit OTP Password Recovery Email with AUTO HUB Branding
 *
 * @param string $toEmail
 * @param string $toName
 * @param string $otpCode
 * @param int $expiryMinutes
 * @return array ['success' => bool, 'error' => string|null, 'technical' => string|null]
 */
function send_otp_mail($toEmail, $toName, $otpCode, $expiryMinutes = 5)
{
    $displayName = htmlspecialchars($toName ?: 'Valued Customer', ENT_QUOTES, 'UTF-8');
    $subject = "AUTO HUB - Your Verification Code: {$otpCode}";

    $htmlBody = "
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset='utf-8'>
      <style>
        body { margin: 0; padding: 0; background-color: #070a12; font-family: 'Helvetica Neue', Arial, sans-serif; color: #334155; }
        .wrapper { width: 100%; background-color: #070a12; padding: 40px 0; }
        .container { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #1e293b; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        .header { background: #0f172a; padding: 25px; text-align: center; border-bottom: 3px solid #e11d48; }
        .header h1 { margin: 0; font-size: 26px; font-weight: 900; color: #ffffff; letter-spacing: 1px; }
        .header h1 span { color: #e11d48; }
        .header p { margin: 4px 0 0; color: #94a3b8; font-size: 11px; text-transform: uppercase; letter-spacing: 2px; }
        .content { padding: 35px 30px; line-height: 1.6; color: #1e293b; }
        .content h2 { font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 0; }
        .otp-box { background: #f8fafc; border: 2px dashed #e11d48; border-radius: 10px; padding: 20px; text-align: center; margin: 25px 0; }
        .otp-label { font-size: 12px; text-transform: uppercase; letter-spacing: 1.5px; color: #64748b; font-weight: 700; margin-bottom: 8px; }
        .otp-code { font-size: 36px; font-weight: 900; letter-spacing: 10px; color: #e11d48; font-family: 'Courier New', Courier, monospace; margin: 0; }
        .note { background: #f1f5f9; border-left: 4px solid #e11d48; border-radius: 4px; padding: 12px 16px; font-size: 13px; color: #475569; margin-top: 20px; }
        .footer { background: #0f172a; padding: 22px; text-align: center; color: #94a3b8; font-size: 12px; line-height: 1.5; border-top: 1px solid #1e293b; }
        .footer a { color: #e11d48; text-decoration: none; font-weight: 600; }
      </style>
    </head>
    <body>
      <div class='wrapper'>
        <div class='container'>
          <div class='header'>
            <h1>AUTO <span>HUB</span></h1>
            <p>Spare Parts &amp; Accessories</p>
          </div>
          <div class='content'>
            <h2>Password Recovery Verification</h2>
            <p>Hello <strong>{$displayName}</strong>,</p>
            <p>We received a request to reset the password for your AUTO HUB account. Use the 6-digit One-Time Password (OTP) below to complete your verification:</p>
            
            <div class='otp-box'>
              <div class='otp-label'>Your One-Time Password (OTP)</div>
              <div class='otp-code'>{$otpCode}</div>
            </div>

            <div class='note'>
              <strong>Security Notice:</strong> This OTP is valid for <strong>{$expiryMinutes} minutes</strong> and can only be used once.<br>
              Never share this code with anyone. AUTO HUB support staff will never ask for your verification code.
            </div>

            <p style='margin-top: 20px; font-size: 13px; color: #64748b;'>
              If you did not request a password reset, you can safely disregard this email. Your existing password remains secure.
            </p>
          </div>
          <div class='footer'>
            <strong>AUTO HUB &bull; Genuine Automotive Spare Parts &amp; Accessories</strong><br>
            Hotline: 070 727 5599 &bull; Email: <a href='mailto:nithilarodrigo@gmail.com'>nithilarodrigo@gmail.com</a><br>
            Colombo, Sri Lanka
          </div>
        </div>
      </div>
    </body>
    </html>
    ";

    $altBody = "AUTO HUB - Password Recovery\n\nHello {$displayName},\n\nYour 6-digit verification code is: {$otpCode}\n\nThis code expires in {$expiryMinutes} minutes.\nNever share this code with anyone.\n\nAUTO HUB Spare Parts & Accessories";

    return send_mail($toEmail, $toName, $subject, $htmlBody, $altBody);
}

