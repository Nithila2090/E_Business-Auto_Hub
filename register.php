<?php
/**
 * AUTO HUB - Customer Registration Page
 */

require_once __DIR__ . '/config/helpers.php';

$pdo = get_db();
$error = '';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($fullName) || empty($email) || empty($phone) || empty($password)) {
        $error = 'All required fields marked with * must be filled.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match. Please verify your password entry.';
    } else {
        // Check duplicate email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email already exists. Please sign in instead.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, 'customer')");
            $stmt->execute([$fullName, $email, $phone, $hashedPassword]);
            $newId = (int)$pdo->lastInsertId();

            $_SESSION['user_id'] = $newId;
            $_SESSION['user_name'] = $fullName;
            $_SESSION['user_email'] = $email;
            $_SESSION['role'] = 'customer';

            sync_guest_cart_to_user($newId);

            header('Location: index.php');
            exit;
        }
    }
}

$pageTitle = "Register Account | AUTO HUB – Spare Parts & Accessories";
$pageDescription = "Create an account at AUTO HUB to enjoy fast checkout, special Sri Lankan automotive spare parts discounts and order tracking.";

require_once __DIR__ . '/includes/header.php';
?>

  <!-- Register Form Main -->
  <main class="auth-page-wrap">
    <div class="auth-card auth-card-wide">
      <div class="auth-header">
        <div class="auth-icon-wrap">
          <i class="fas fa-user-plus"></i>
        </div>
        <h1 class="auth-title">Create Account</h1>
        <p class="auth-subtitle">Join Sri Lanka's leading automotive spare parts network</p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert-banner alert-danger" style="margin-bottom: 1.5rem;">
          <div class="alert-icon"><i class="fas fa-exclamation-triangle"></i></div>
          <div class="alert-content"><?php echo e($error); ?></div>
        </div>
      <?php endif; ?>

      <form id="register-form" action="register.php" method="POST" class="auth-form">
        
        <div class="form-group">
          <label class="form-label" for="reg-name">Full Name <span class="text-red">*</span></label>
          <div class="auth-input-wrap">
            <input type="text" name="full_name" id="reg-name" class="form-control" required placeholder="e.g. Kasun Jayasuriya" value="<?php echo e($_POST['full_name'] ?? ''); ?>" autocomplete="name">
            <i class="fas fa-user input-icon"></i>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="reg-email">Email Address <span class="text-red">*</span></label>
          <div class="auth-input-wrap">
            <input type="email" name="email" id="reg-email" class="form-control" required placeholder="name@example.com" value="<?php echo e($_POST['email'] ?? ''); ?>" autocomplete="email">
            <i class="fas fa-envelope input-icon"></i>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="reg-phone">Mobile / Phone Number <span class="text-red">*</span></label>
          <div class="auth-input-wrap">
            <input type="tel" name="phone" id="reg-phone" class="form-control" required placeholder="070 727 5599" value="<?php echo e($_POST['phone'] ?? ''); ?>" autocomplete="tel">
            <i class="fas fa-phone-alt input-icon"></i>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label" for="reg-password">Password <span class="text-red">*</span></label>
            <div class="auth-input-wrap">
              <input type="password" name="password" id="reg-password" class="form-control" required minlength="6" placeholder="Min 6 characters" autocomplete="new-password">
              <i class="fas fa-lock input-icon"></i>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label" for="reg-confirm-password">Confirm Password <span class="text-red">*</span></label>
            <div class="auth-input-wrap">
              <input type="password" name="confirm_password" id="reg-confirm-password" class="form-control" required minlength="6" placeholder="Re-enter password" autocomplete="new-password">
              <i class="fas fa-lock input-icon"></i>
            </div>
          </div>
        </div>

        <div style="margin-bottom: 1.5rem;">
          <label class="filter-checkbox-label" style="font-size: 0.85rem; color: var(--text-body); cursor: pointer;">
            <input type="checkbox" id="terms-check" required checked>
            <span>I agree to AUTO HUB's <a href="contact.php" class="text-red fw-bold">Terms of Service</a> &bull; <a href="contact.php" class="text-red fw-bold">Privacy Policy</a></span>
          </label>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">
          <i class="fas fa-check-circle"></i> Create Account
        </button>
      </form>

      <div class="auth-footer">
        Already have an account? <a href="login.php">Sign In</a>
      </div>
    </div>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
