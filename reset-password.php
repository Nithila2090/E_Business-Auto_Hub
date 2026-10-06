<?php
/**
 * AUTO HUB - Reset Password Page
 * Validates 32-byte token hash, checks expiration, and updates customer password in MySQL
 */

require_once __DIR__ . '/config/helpers.php';

$pdo = get_db();
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$isResetSuccess = false;
$isValidToken = false;
$user = null;
$resetRecord = null;

if (empty($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
    $error = 'Invalid or missing password reset token. Please check the link in your email or request a new one.';
} else {
    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare("
        SELECT pr.*, u.id AS user_id, u.full_name, u.email
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token_hash = ? AND pr.used = 0
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $resetRecord = $stmt->fetch();

    if (!$resetRecord) {
        $error = 'This password reset link is invalid or has already been used. Please request a new reset link.';
    } elseif (strtotime($resetRecord['expires_at']) < time()) {
        $error = 'This password reset link has expired (valid for 30 minutes). Please request a new reset link.';
    } else {
        $isValidToken = true;
        $user = $resetRecord;

        // Process new password submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($newPassword) || empty($confirmPassword)) {
                $error = 'Please fill out both password fields.';
            } elseif (strlen($newPassword) < 6) {
                $error = 'Password must be at least 6 characters long.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'Passwords do not match. Please verify and try again.';
            } else {
                // Update password in database
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmtUpd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmtUpd->execute([$hashedPassword, $user['user_id']]);

                // Mark token as used
                $stmtUsed = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
                $stmtUsed->execute([$resetRecord['id']]);

                $isResetSuccess = true;
            }
        }
    }
}

$pageTitle = "Reset Password | AUTO HUB – Spare Parts & Accessories";
$pageDescription = "Create a new secure password for your AUTO HUB customer account.";

require_once __DIR__ . '/includes/header.php';
?>

  <!-- Reset Password Form Main -->
  <main class="auth-page-wrap">
    <div class="auth-card">
      
      <div class="auth-header">
        <div class="auth-icon-wrap">
          <i class="fas fa-lock-open"></i>
        </div>
        <h1 class="auth-title">Reset Password</h1>
        <p class="auth-subtitle">Create a new secure password for your account</p>
      </div>

      <?php if ($isResetSuccess): ?>
        <div class="alert-banner alert-success" style="margin-bottom: 1.5rem; text-align: center; flex-direction: column; padding: 1.5rem;">
          <i class="fas fa-check-circle text-red" style="font-size: 2.2rem; margin-bottom: 0.5rem;"></i>
          <strong style="font-size: 1.1rem; color: #15803d;">Password Reset Successfully!</strong>
          <p style="margin: 0.5rem 0 0; color: #334155; font-size: 0.9rem;">
            Your password has been updated. You can now sign in to your account with your new password.
          </p>
        </div>

        <a href="login.php" class="btn btn-primary btn-block btn-lg">
          <i class="fas fa-sign-in-alt"></i> Proceed to Sign In
        </a>
      <?php elseif (!$isValidToken): ?>
        <div class="alert-banner alert-danger" style="margin-bottom: 1.5rem; text-align: center; flex-direction: column; padding: 1.5rem;">
          <i class="fas fa-exclamation-triangle text-red" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
          <p style="margin: 0;"><?php echo e($error); ?></p>
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: center;">
          <a href="forgot-password.php" class="btn btn-primary btn-block">
            <i class="fas fa-paper-plane"></i> Request New Reset Link
          </a>
        </div>
      <?php else: ?>
        <?php if (!empty($error)): ?>
          <div class="alert-banner alert-danger" style="margin-bottom: 1.5rem;">
            <div class="alert-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="alert-content"><?php echo e($error); ?></div>
          </div>
        <?php endif; ?>

        <div style="background: #f8fafc; padding: 0.85rem 1rem; border-radius: 10px; border: 1px solid var(--border-color); margin-bottom: 1.5rem; font-size: 0.85rem; color: #475569;">
          Resetting password for: <strong style="color: var(--text-main);"><?php echo e($user['email']); ?></strong>
        </div>

        <form action="reset-password.php?token=<?php echo urlencode($token); ?>" method="POST" class="auth-form">
          <input type="hidden" name="token" value="<?php echo e($token); ?>">

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
            <i class="fas fa-check-circle"></i> Save New Password
          </button>
        </form>

        <div class="auth-footer">
          Remember your password? <a href="login.php">Sign In</a>
        </div>
      <?php endif; ?>

    </div>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
