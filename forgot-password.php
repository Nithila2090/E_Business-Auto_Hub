<?php
/**
 * AUTO HUB - Forgot Password Page
 * Generates secure token hash and delivers reset link via PHPMailer SMTP
 */

require_once __DIR__ . '/config/helpers.php';

$pdo = get_db();
$isSubmitted = false;
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    // Rate Limiting (1 request per 20 seconds per session)
    $lastRequest = $_SESSION['last_pwd_reset_time'] ?? 0;
    if (time() - $lastRequest < 20) {
        $msg = 'A password reset request was recently submitted. If an account matches, an email is on its way. Please wait before requesting again.';
        $isSubmitted = true;
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $_SESSION['last_pwd_reset_time'] = time();

        // Check if user exists in database
        $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate 32 bytes cryptographically secure random token (64 hex characters)
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $expiresAt = date('Y-m-d H:i:s', time() + (30 * 60)); // 30 minutes

            // Invalidate any older unused tokens for this user
            $pdo->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0")->execute([$user['id']]);

            // Save hashed token
            $stmtInsert = $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
            $stmtInsert->execute([$user['id'], $tokenHash, $expiresAt]);

            $resetUrl = BASE_URL . '/reset-password.php?token=' . $rawToken;

            // HTML Email Template matching AUTO HUB Automotive branding
            $userName = e($user['full_name']);
            $emailSubject = "AUTO HUB - Password Reset Request";
            
            $emailBody = "
            <!DOCTYPE html>
            <html>
            <head>
              <meta charset='utf-8'>
              <style>
                body { margin: 0; padding: 0; background-color: #070a12; font-family: 'Helvetica Neue', Arial, sans-serif; color: #334155; }
                .wrapper { width: 100%; background-color: #070a12; padding: 40px 0; }
                .container { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #1e293b; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
                .header { background: #0f172a; padding: 25px; text-align: center; border-bottom: 3px solid #e11d48; }
                .header h1 { margin: 0; font-size: 26px; font-weight: 900; color: #ffffff; letter-spacing: 1px; }
                .header h1 span { color: #e11d48; }
                .header p { margin: 4px 0 0; color: #94a3b8; font-size: 11px; text-transform: uppercase; letter-spacing: 2px; }
                .content { padding: 35px 30px; line-height: 1.6; color: #1e293b; }
                .content h2 { font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 0; }
                .btn-box { text-align: center; margin: 30px 0; }
                .btn { display: inline-block; background-color: #e11d48; color: #ffffff !important; text-decoration: none; padding: 14px 34px; font-size: 15px; font-weight: 700; border-radius: 8px; box-shadow: 0 4px 12px rgba(225,29,72,0.35); text-transform: uppercase; letter-spacing: 0.5px; }
                .note { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; font-size: 13px; color: #64748b; margin-top: 25px; }
                .footer { background: #0f172a; padding: 25px; text-align: center; color: #94a3b8; font-size: 12px; line-height: 1.5; border-top: 1px solid #1e293b; }
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
                    <h2>Hello {$userName},</h2>
                    <p>We received a request to reset your <strong>AUTO HUB</strong> account password.</p>
                    <p>Click the button below to securely create a new password for your account:</p>
                    
                    <div class='btn-box'>
                      <a href='{$resetUrl}' class='btn' target='_blank'>RESET PASSWORD</a>
                    </div>

                    <div class='note'>
                      <strong style='color: #0f172a;'>Security Notice:</strong> This password reset link is valid for <strong>30 minutes</strong> and can only be used once.<br><br>
                      If you did not request a password reset, please ignore this email. Your password will remain unchanged.
                    </div>
                  </div>
                  <div class='footer'>
                    <strong>AUTO HUB &bull; Genuine Automotive Spare Parts &amp; Accessories</strong><br>
                    Hotline: <a href='tel:0707275599'>070 727 5599</a> &bull; Email: <a href='mailto:nithilarodrigo@gmail.com'>nithilarodrigo@gmail.com</a><br>
                    Colombo, Sri Lanka
                  </div>
                </div>
              </div>
            </body>
            </html>
            ";

            $altText = "Hello {$userName},\n\nWe received a request to reset your AUTO HUB password.\n\nPlease open this link in your browser to reset your password:\n{$resetUrl}\n\nThis link will expire in 30 minutes.\n\nRegards,\nAUTO HUB Spare Parts & Accessories\n070 727 5599\nnithilarodrigo@gmail.com";

            // Send via PHPMailer
            send_mail($user['email'], $user['full_name'], $emailSubject, $emailBody, $altText);
        }

        // Generic response to prevent account enumeration
        $isSubmitted = true;
        $msg = 'If an account exists for this email address, a password reset link has been sent. Please check your email inbox and spam folder.';
    }
}

$pageTitle = "Forgot Password | AUTO HUB – Spare Parts & Accessories";
$pageDescription = "Reset your AUTO HUB account password safely using our verified email token verification system.";

require_once __DIR__ . '/includes/header.php';
?>

  <!-- Forgot Password Form Main -->
  <main class="auth-page-wrap">
    <div class="auth-card">
      
      <div class="auth-header">
        <div class="auth-icon-wrap">
          <i class="fas fa-key"></i>
        </div>
        <h1 class="auth-title">Forgot Password?</h1>
        <p class="auth-subtitle">Enter your registered email address to receive a secure password reset link</p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert-banner alert-danger" style="margin-bottom: 1.5rem;">
          <div class="alert-icon"><i class="fas fa-exclamation-triangle"></i></div>
          <div class="alert-content"><?php echo e($error); ?></div>
        </div>
      <?php endif; ?>

      <?php if ($isSubmitted): ?>
        <div class="alert-banner alert-success" style="margin-bottom: 1.5rem; text-align: center; flex-direction: column; padding: 1.5rem;">
          <i class="fas fa-paper-plane text-red" style="font-size: 2.2rem; margin-bottom: 0.5rem;"></i>
          <strong style="font-size: 1.05rem;">Check Your Email Inbox</strong>
          <p style="margin: 0.5rem 0 0; color: #334155; font-size: 0.9rem;"><?php echo e($msg); ?></p>
        </div>

        <div style="text-align: center; margin-top: 1.5rem;">
          <a href="login.php" class="btn btn-primary btn-block btn-lg">
            <i class="fas fa-sign-in-alt"></i> Return to Sign In
          </a>
        </div>
      <?php else: ?>
        <form action="forgot-password.php" method="POST" class="auth-form">
          <div class="form-group" style="margin-bottom: 1.5rem;">
            <label class="form-label" for="forgot-email">Registered Email Address</label>
            <div class="auth-input-wrap">
              <input type="email" name="email" id="forgot-email" class="form-control" required placeholder="name@example.com" value="<?php echo e($_POST['email'] ?? ''); ?>" autocomplete="email">
              <i class="fas fa-envelope input-icon"></i>
            </div>
            <small style="color: #64748b; font-size: 0.78rem; margin-top: 0.35rem; display: block;">
              We will deliver a 30-minute password reset link to this address.
            </small>
          </div>

          <button type="submit" class="btn btn-primary btn-block btn-lg">
            <i class="fas fa-paper-plane"></i> Send Password Reset Link
          </button>
        </form>

        <div class="auth-footer">
          Remember your password? <a href="login.php">Sign In</a>
        </div>
      <?php endif; ?>

    </div>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
