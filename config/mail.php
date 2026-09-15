<?php
/**
 * AUTO HUB - Mail Configuration & PHPMailer Integration
 */

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
    define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: ''); // Configurable via environment or App Password
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
 * @return array ['success' => bool, 'error' => string|null]
 */
function send_mail($toEmail, $toName, $subject, $htmlBody, $altBody = '')
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->Port       = MAIL_PORT;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->SMTPAuth   = !empty(MAIL_PASSWORD);
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->Timeout    = 15;

        // Sender & Recipient
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = !empty($altBody) ? $altBody : strip_tags($htmlBody);

        // If password is provided, perform live SMTP send
        if (!empty(MAIL_PASSWORD)) {
            $mail->send();
        }

        // Log sent mail for audit / offline development testing
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logEntry = date('[Y-m-d H:i:s]') . " Sent to: {$toEmail} | Subject: {$subject}\n";
        @file_put_contents($logDir . '/mail_sent.log', $logEntry, FILE_APPEND);

        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $errMsg = $mail->getErrorInfo() ?: $e->getMessage();
        $logEntry = date('[Y-m-d H:i:s]') . " Mail Error to {$toEmail}: {$errMsg}\n";
        @file_put_contents($logDir . '/mail_errors.log', $logEntry, FILE_APPEND);

        return [
            'success' => false,
            'error' => 'We could not send the reset email right now. Please try again later.'
        ];
    }
}
