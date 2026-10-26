<?php
/**
 * AUTO HUB - Forgot Password & Secure OTP Recovery
 * Complete 3-Step Password Recovery Workflow:
 * 1. Email Submission & 6-Digit OTP Generation
 * 2. Server-side OTP Verification with 5-Min Expiry, Attempt Limiting, and Resend Cooldown
 * 3. New Password Reset with password_hash() and Invalidation
 */

require_once __DIR__ . '/config/helpers.php';

$pdo = get_db();
$error = '';
$success = '';

// Helper to mask email for display
function mask_reset_email(string $email): string {
    $parts = explode('@', $email, 2);
    if (count($parts) < 2) return $email;
    $name = $parts[0];
    $domain = $parts[1];
    $len = strlen($name);
    if ($len <= 2) {
        $masked = substr($name, 0, 1) . '*';
    } else {
        $masked = substr($name, 0, 1) . str_repeat('*', min(5, $len - 2)) . substr($name, -1);
    }
    return $masked . '@' . $domain;
}

// Action: Restart / Change Email
if (isset($_GET['action']) && $_GET['action'] === 'restart') {
    unset(
        $_SESSION['pwd_reset_step'],
        $_SESSION['pwd_reset_user_id'],
        $_SESSION['pwd_reset_email'],
        $_SESSION['pwd_reset_name'],
        $_SESSION['pwd_reset_otp_time'],
        $_SESSION['pwd_reset_verified']
    );
    header('Location: forgot-password.php');
    exit;
}

$currentStep = $_SESSION['pwd_reset_step'] ?? 'email';

// If on verify or password step but missing user ID, fallback to email step
if (in_array($currentStep, ['verify_otp', 'new_password']) && empty($_SESSION['pwd_reset_user_id'])) {
    $currentStep = 'email';
    $_SESSION['pwd_reset_step'] = 'email';
}

// If on new_password step but not verified, fallback to verify_otp
if ($currentStep === 'new_password' && empty($_SESSION['pwd_reset_verified'])) {
    $currentStep = 'verify_otp';
    $_SESSION['pwd_reset_step'] = 'verify_otp';
}

// -----------------------------------------------------------------------------
// POST HANDLERS
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // -------------------------------------------------------------------------
    // STEP 1: REQUEST OTP VIA EMAIL
    // -------------------------------------------------------------------------
    if ($action === 'request_otp') {
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid registered email address.';
        } else {
            // Check if user exists in database
            $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                // Secure message: do not expose system internals, but clearly guide user
                $error = 'No registered account found matching that email address. Please check your spelling or register a new account.';
            } else {
                // Rate Limiting: 20 seconds between new requests from the same session
                $lastSent = $_SESSION['pwd_reset_otp_time'] ?? 0;
                if (time() - $lastSent < 20) {
                    $waitSec = 20 - (time() - $lastSent);
                    $error = "A verification code was requested recently. Please wait {$waitSec} seconds before trying again.";
                } else {
                    // Generate cryptographically secure 6-digit numeric OTP
                    $otp = (string)random_int(100000, 999999);
                    $otpHash = hash('sha256', $otp);
                    $expiresAt = date('Y-m-d H:i:s', time() + (5 * 60)); // 5 minutes

                    // Invalidate any older unused reset tokens for this user
                    $pdo->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0")->execute([$user['id']]);

                    // Insert fresh OTP record with 0 attempts
                    $stmtInsert = $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at, attempts, used) VALUES (?, ?, ?, 0, 0)");
                    $stmtInsert->execute([$user['id'], $otpHash, $expiresAt]);

                    // Deliver OTP via PHPMailer SMTP
                    $mailResult = send_otp_mail($user['email'], $user['full_name'], $otp, 5);

                    if ($mailResult['success']) {
                        $_SESSION['pwd_reset_user_id'] = (int)$user['id'];
                        $_SESSION['pwd_reset_email'] = $user['email'];
                        $_SESSION['pwd_reset_name'] = $user['full_name'];
                        $_SESSION['pwd_reset_otp_time'] = time();
                        $_SESSION['pwd_reset_step'] = 'verify_otp';
                        $currentStep = 'verify_otp';
                        $success = 'A 6-digit verification code has been sent to ' . mask_reset_email($user['email']) . '. Please check your inbox.';
                    } else {
                        // Display actual user-friendly error and log technical details
                        $error = $mailResult['error'];
                        if (!empty($mailResult['technical'])) {
                            // Friendly reminder if SMTP password not configured
                            if (str_contains($mailResult['technical'], 'SMTP password')) {
                                $error .= ' (Localhost Notice: Set your Gmail App Password in .env)';
                            }
                        }
                    }
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // STEP 2: VERIFY OTP CODE OR RESEND
    // -------------------------------------------------------------------------
    elseif ($action === 'verify_otp') {
        $userId = $_SESSION['pwd_reset_user_id'] ?? 0;
        $enteredOtp = trim($_POST['otp'] ?? '');

        if (empty($enteredOtp) || !preg_match('/^[0-9]{6}$/', $enteredOtp)) {
            $error = 'Please enter the 6-digit numeric verification code.';
        } else {
            // Find active unused OTP record for this user
            $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE user_id = ? AND used = 0 ORDER BY id DESC LIMIT 1");
            $stmt->execute([$userId]);
            $resetRecord = $stmt->fetch();

            if (!$resetRecord) {
                $error = 'No active verification code found. Please request a new code.';
                $currentStep = 'email';
                $_SESSION['pwd_reset_step'] = 'email';
            } elseif ($resetRecord['attempts'] >= 5) {
                // Brute force protection: maximum 5 failed attempts
                $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")->execute([$resetRecord['id']]);
                $error = 'Too many failed verification attempts (maximum 5). For your security, this code has expired. Please request a new code.';
            } elseif (strtotime($resetRecord['expires_at']) < time()) {
                // Expired OTP (5 minutes)
                $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")->execute([$resetRecord['id']]);
                $error = 'Your verification code has expired (valid for 5 minutes). Please click "Resend Code" to get a fresh OTP.';
            } else {
                // Verify submitted OTP hash against database hash
                $submittedHash = hash('sha256', $enteredOtp);
                if (hash_equals($resetRecord['token_hash'], $submittedHash)) {
                    // Correct OTP - Invalidate record to prevent reuse
                    $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")->execute([$resetRecord['id']]);
                    $_SESSION['pwd_reset_verified'] = true;
                    $_SESSION['pwd_reset_step'] = 'new_password';
                    $currentStep = 'new_password';
                    $success = 'Verification successful! You can now create your new password.';
                } else {
                    // Increment failed attempt counter
                    $newAttempts = (int)$resetRecord['attempts'] + 1;
                    $pdo->prepare("UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?")->execute([$resetRecord['id']]);
                    $remaining = max(0, 5 - $newAttempts);

                    if ($remaining > 0) {
                        $error = "Incorrect verification code. Please check your email and try again ({$remaining} attempt" . ($remaining === 1 ? '' : 's') . " remaining).";
                    } else {
                        $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")->execute([$resetRecord['id']]);
                        $error = 'Too many failed verification attempts. This code has been invalidated. Please request a new code.';
                    }
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // STEP 2B: RESEND OTP WITH COOLDOWN
    // -------------------------------------------------------------------------
    elseif ($action === 'resend_otp') {
        $userId = $_SESSION['pwd_reset_user_id'] ?? 0;
        $userEmail = $_SESSION['pwd_reset_email'] ?? '';
        $userName = $_SESSION['pwd_reset_name'] ?? 'Valued Customer';
        $lastSent = $_SESSION['pwd_reset_otp_time'] ?? 0;
        $cooldown = 60; // 60 seconds cooldown

        if (empty($userId) || empty($userEmail)) {
            $currentStep = 'email';
            $_SESSION['pwd_reset_step'] = 'email';
            $error = 'Session expired. Please enter your email address to continue.';
        } elseif ((time() - $lastSent) < $cooldown) {
            $waitSec = $cooldown - (time() - $lastSent);
            $error = "Please wait {$waitSec} seconds before requesting a new verification code.";
            $currentStep = 'verify_otp';
        } else {
            // Generate new OTP
            $otp = (string)random_int(100000, 999999);
            $otpHash = hash('sha256', $otp);
            $expiresAt = date('Y-m-d H:i:s', time() + (5 * 60));

            // Invalidate old tokens
            $pdo->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0")->execute([$userId]);

            // Save new OTP
            $stmtInsert = $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at, attempts, used) VALUES (?, ?, ?, 0, 0)");
            $stmtInsert->execute([$userId, $otpHash, $expiresAt]);

            // Send email
            $mailResult = send_otp_mail($userEmail, $userName, $otp, 5);

            if ($mailResult['success']) {
                $_SESSION['pwd_reset_otp_time'] = time();
                $success = 'A fresh 6-digit verification code has been sent to your email inbox.';
            } else {
                $error = $mailResult['error'];
            }
            $currentStep = 'verify_otp';
        }
    }

    // -------------------------------------------------------------------------
    // STEP 3: SAVE NEW PASSWORD
    // -------------------------------------------------------------------------
    elseif ($action === 'save_password') {
        $userId = $_SESSION['pwd_reset_user_id'] ?? 0;

        if (empty($userId) || empty($_SESSION['pwd_reset_verified'])) {
            $error = 'Unauthorized password reset attempt. Please complete the verification process.';
            $currentStep = 'email';
            $_SESSION['pwd_reset_step'] = 'email';
        } else {
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($newPassword) || empty($confirmPassword)) {
                $error = 'Please fill out both password fields.';
                $currentStep = 'new_password';
            } elseif (strlen($newPassword) < 6) {
                $error = 'Password must be at least 6 characters long.';
                $currentStep = 'new_password';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'Passwords do not match. Please verify and re-enter your password.';
                $currentStep = 'new_password';
            } else {
                // Securely hash password using standard PHP bcrypt
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                // Update user's password in database
                $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmtUpdate->execute([$hashedPassword, $userId]);

                // Invalidate all pending reset tokens for this user
                $pdo->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ?")->execute([$userId]);

                // Clear all password recovery session variables
                unset(
                    $_SESSION['pwd_reset_step'],
                    $_SESSION['pwd_reset_user_id'],
                    $_SESSION['pwd_reset_email'],
                    $_SESSION['pwd_reset_name'],
                    $_SESSION['pwd_reset_otp_time'],
                    $_SESSION['pwd_reset_verified']
                );

                $currentStep = 'complete';
            }
        }
    }
}

$pageTitle = "Forgot Password | AUTO HUB – Spare Parts & Accessories";
$pageDescription = "Reset your AUTO HUB account password securely with our email OTP verification system.";

require_once __DIR__ . '/includes/header.php';
?>

  <!-- Forgot Password Form Main Container -->
  <main class="auth-page-wrap">
    <div class="auth-card">

      <!-- ================================================================= -->
      <!-- STEP 1: ENTER REGISTERED EMAIL -->
      <!-- ================================================================= -->
      <?php if ($currentStep === 'email'): ?>
        
        <div class="auth-header">
          <div class="auth-icon-wrap">
            <i class="fas fa-key"></i>
          </div>
          <h1 class="auth-title">Forgot Password?</h1>
          <p class="auth-subtitle">Enter your registered email address to receive a 6-digit OTP code</p>
        </div>

        <?php if (!empty($error)): ?>
          <div class="alert-banner alert-danger" style="margin-bottom: 1.5rem;">
            <div class="alert-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="alert-content"><?php echo e($error); ?></div>
          </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
          <div class="alert-banner alert-success" style="margin-bottom: 1.5rem;">
            <div class="alert-icon"><i class="fas fa-check-circle"></i></div>
            <div class="alert-content"><?php echo e($success); ?></div>
          </div>
        <?php endif; ?>

        <form action="forgot-password.php" method="POST" class="auth-form">
          <input type="hidden" name="action" value="request_otp">

          <div class="form-group" style="margin-bottom: 1.5rem;">
            <label class="form-label" for="forgot-email">Registered Email Address</label>
            <div class="auth-input-wrap">
              <input type="email" name="email" id="forgot-email" class="form-control" required placeholder="name@example.com" value="<?php echo e($_POST['email'] ?? ''); ?>" autocomplete="email">
              <i class="fas fa-envelope input-icon"></i>
            </div>
            <small style="color: #64748b; font-size: 0.78rem; margin-top: 0.35rem; display: block;">
              We will send a 6-digit verification code (valid for 5 minutes) to this email address.
            </small>
          </div>

          <button type="submit" class="btn btn-primary btn-block btn-lg">
            <i class="fas fa-paper-plane"></i> Send Verification Code
          </button>
        </form>

        <div class="auth-footer">
          Remember your password? <a href="login.php">Sign In</a>
        </div>

      <!-- ================================================================= -->
      <!-- STEP 2: VERIFY 6-DIGIT OTP -->
      <!-- ================================================================= -->
      <?php elseif ($currentStep === 'verify_otp'): ?>
        
        <div class="auth-header">
          <div class="auth-icon-wrap">
            <i class="fas fa-shield-alt"></i>
          </div>
          <h1 class="auth-title">Verify Code</h1>
          <p class="auth-subtitle">
            Enter the 6-digit code sent to<br>
            <strong style="color: var(--text-main); font-size: 0.95rem;">
              <?php echo e(mask_reset_email($_SESSION['pwd_reset_email'] ?? 'your email')); ?>
            </strong>
          </p>
        </div>

        <?php if (!empty($error)): ?>
          <div class="alert-banner alert-danger" style="margin-bottom: 1.5rem;">
            <div class="alert-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="alert-content"><?php echo e($error); ?></div>
          </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
          <div class="alert-banner alert-success" style="margin-bottom: 1.5rem;">
            <div class="alert-icon"><i class="fas fa-check-circle"></i></div>
            <div class="alert-content"><?php echo e($success); ?></div>
          </div>
        <?php endif; ?>

        <form action="forgot-password.php" method="POST" class="auth-form">
          <input type="hidden" name="action" value="verify_otp">

          <div class="form-group" style="margin-bottom: 1.5rem;">
            <label class="form-label" for="otp-input" style="text-align: center; display: block;">6-Digit OTP Code</label>
            <div class="auth-input-wrap">
              <input type="text" name="otp" id="otp-input" class="form-control" required maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="------" autocomplete="one-time-code" autofocus style="text-align: center; font-size: 1.8rem; letter-spacing: 12px; font-weight: 800; font-family: 'Courier New', Courier, monospace; height: 56px;">
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem; font-size: 0.8rem; color: #64748b;">
              <span><i class="far fa-clock"></i> Valid for 5 minutes</span>
              <span>Max 5 attempts</span>
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-block btn-lg">
            <i class="fas fa-check-circle"></i> Verify Code &amp; Continue
          </button>
        </form>

        <!-- Resend OTP & Change Email options -->
        <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem; text-align: center;">
          <form action="forgot-password.php" method="POST" style="margin: 0;">
            <input type="hidden" name="action" value="resend_otp">
            <?php 
              $timeSinceLast = time() - ($_SESSION['pwd_reset_otp_time'] ?? 0);
              $cooldownLeft = max(0, 60 - $timeSinceLast);
            ?>
            <button type="submit" id="btn-resend-otp" class="btn btn-outline" style="width: 100%; border: 1px solid var(--border-color); background: transparent; color: var(--text-main); font-size: 0.88rem; padding: 0.65rem 1rem;" <?php echo ($cooldownLeft > 0) ? 'disabled' : ''; ?>>
              <i class="fas fa-redo-alt"></i> 
              <span id="resend-text"><?php echo ($cooldownLeft > 0) ? "Resend Code ({$cooldownLeft}s)" : 'Resend Code'; ?></span>
            </button>
          </form>

          <a href="forgot-password.php?action=restart" style="font-size: 0.82rem; color: #64748b; text-decoration: underline;">
            <i class="fas fa-arrow-left"></i> Change email address
          </a>
        </div>

        <?php if ($cooldownLeft > 0): ?>
          <script>
            (function() {
              var timeLeft = <?php echo (int)$cooldownLeft; ?>;
              var btn = document.getElementById('btn-resend-otp');
              var txt = document.getElementById('resend-text');
              var timer = setInterval(function() {
                timeLeft--;
                if (timeLeft <= 0) {
                  clearInterval(timer);
                  btn.disabled = false;
                  txt.textContent = 'Resend Code';
                } else {
                  txt.textContent = 'Resend Code (' + timeLeft + 's)';
                }
              }, 1000);
            })();
          </script>
        <?php endif; ?>

      <!-- ================================================================= -->
      <!-- STEP 3: ENTER NEW PASSWORD -->
      <!-- ================================================================= -->
      <?php elseif ($currentStep === 'new_password'): ?>
        
        <div class="auth-header">
          <div class="auth-icon-wrap">
            <i class="fas fa-lock-open"></i>
          </div>
          <h1 class="auth-title">Create New Password</h1>
          <p class="auth-subtitle">Enter a new secure password for your account</p>
        </div>

        <?php if (!empty($error)): ?>
          <div class="alert-banner alert-danger" style="margin-bottom: 1.5rem;">
            <div class="alert-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="alert-content"><?php echo e($error); ?></div>
          </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
          <div class="alert-banner alert-success" style="margin-bottom: 1.5rem;">
            <div class="alert-icon"><i class="fas fa-check-circle"></i></div>
            <div class="alert-content"><?php echo e($success); ?></div>
          </div>
        <?php endif; ?>

        <form action="forgot-password.php" method="POST" class="auth-form">
          <input type="hidden" name="action" value="save_password">

          <div class="form-group">
            <label class="form-label" for="reset-new-pw">New Password</label>
            <div class="auth-input-wrap">
              <input type="password" name="new_password" id="reset-new-pw" class="form-control" required minlength="6" placeholder="Minimum 6 characters" autocomplete="new-password">
              <i class="fas fa-lock input-icon"></i>
            </div>
          </div>

          <div class="form-group" style="margin-bottom: 1.5rem;">
            <label class="form-label" for="reset-confirm-pw">Confirm New Password</label>
            <div class="auth-input-wrap">
              <input type="password" name="confirm_password" id="reset-confirm-pw" class="form-control" required minlength="6" placeholder="Re-enter your new password" autocomplete="new-password">
              <i class="fas fa-shield-alt input-icon"></i>
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-block btn-lg">
            <i class="fas fa-save"></i> Save New Password
          </button>
        </form>

      <!-- ================================================================= -->
      <!-- STEP 4: SUCCESS CONFIRMATION -->
      <!-- ================================================================= -->
      <?php elseif ($currentStep === 'complete'): ?>
        
        <div class="alert-banner alert-success" style="margin-bottom: 1.5rem; text-align: center; flex-direction: column; padding: 2rem 1.5rem;">
          <i class="fas fa-check-circle text-red" style="font-size: 3rem; margin-bottom: 1rem;"></i>
          <h2 style="font-size: 1.3rem; margin: 0 0 0.5rem; color: #15803d; font-weight: 800;">Password Reset Successfully!</h2>
          <p style="margin: 0; color: #334155; font-size: 0.95rem; line-height: 1.5;">
            Your account password has been updated securely. You can now sign in with your new credentials.
          </p>
        </div>

        <div style="text-align: center; margin-top: 1.5rem;">
          <a href="login.php" class="btn btn-primary btn-block btn-lg">
            <i class="fas fa-sign-in-alt"></i> Proceed to Sign In
          </a>
        </div>

      <?php endif; ?>

    </div>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
