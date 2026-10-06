<?php
/**
 * AUTO HUB - Customer & Admin Login Page
 * Secure Authentication System
 */

require_once __DIR__ . '/config/helpers.php';

$pdo = get_db();
$error = '';

// If already authenticated, redirect to appropriate destination
if (is_logged_in()) {
    if (is_admin()) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your email address and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            // Sync guest cart to authenticated user
            sync_guest_cart_to_user($user['id']);

            if ($user['role'] === 'admin') {
                header('Location: admin/dashboard.php');
            } else {
                $redir = $_SESSION['redirect_after_login'] ?? 'index.php';
                unset($_SESSION['redirect_after_login']);
                header("Location: $redir");
            }
            exit;
        } else {
            $error = 'Invalid email address or password. Please try again.';
        }
    }
}

$pageTitle = "Customer Login | AUTO HUB – Spare Parts & Accessories";
$pageDescription = "Sign in to your AUTO HUB account to track orders, save vehicle garage profiles and access exclusive automotive spare parts discounts.";

require_once __DIR__ . '/includes/header.php';
?>

  <!-- Main Login Section -->
  <main class="auth-page-wrap">
    <div class="auth-card">
      
      <!-- Card Header -->
      <div class="auth-header">
        <div class="auth-icon-wrap">
          <i class="fas fa-user-circle"></i>
        </div>
        <h1 class="auth-title">Welcome Back</h1>
        <p class="auth-subtitle">Sign in to manage your orders, vehicle fitment, and account</p>
      </div>

      <!-- Feedback Alerts -->
      <?php if (!empty($error)): ?>
        <div class="alert-banner alert-danger" style="margin-bottom: 1.5rem;">
          <div class="alert-icon"><i class="fas fa-exclamation-triangle"></i></div>
          <div class="alert-content"><?php echo e($error); ?></div>
        </div>
      <?php endif; ?>

      <?php if (!empty($_GET['registered'])): ?>
        <div class="alert-banner alert-success" style="margin-bottom: 1.5rem;">
          <div class="alert-icon"><i class="fas fa-check-circle"></i></div>
          <div class="alert-content">Registration successful! You can now log in to your account.</div>
        </div>
      <?php endif; ?>

      <?php if (!empty($_GET['logged_out'])): ?>
        <div class="alert-banner alert-success" style="margin-bottom: 1.5rem;">
          <div class="alert-icon"><i class="fas fa-info-circle"></i></div>
          <div class="alert-content">You have signed out successfully.</div>
        </div>
      <?php endif; ?>

      <!-- Login Form -->
      <form id="login-form" action="login.php" method="POST" class="auth-form">
        
        <!-- Email Input -->
        <div class="form-group">
          <label class="form-label" for="login-email">Email Address</label>
          <div class="auth-input-wrap">
            <input type="email" name="email" id="login-email" class="form-control" required placeholder="name@example.com" value="<?php echo e($_POST['email'] ?? ''); ?>" autocomplete="email">
            <i class="fas fa-envelope input-icon"></i>
          </div>
        </div>

        <!-- Password Input -->
        <div class="form-group">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
            <label class="form-label" for="login-password" style="margin-bottom: 0;">Password</label>
            <a href="forgot-password.php" class="text-red" style="font-size: 0.82rem; font-weight: 600;">Forgot Password?</a>
          </div>
          <div class="auth-input-wrap">
            <input type="password" name="password" id="login-password" class="form-control" required placeholder="Enter your password" autocomplete="current-password" style="padding-right: 2.75rem;">
            <i class="fas fa-lock input-icon"></i>
            <button type="button" class="auth-toggle-pw" id="btn-toggle-pw" aria-label="Toggle password visibility">
              <i class="far fa-eye"></i>
            </button>
          </div>
        </div>

        <!-- Options Row -->
        <div class="auth-options-row">
          <label class="filter-checkbox-label" style="font-size: 0.85rem; color: var(--text-body); cursor: pointer;">
            <input type="checkbox" id="remember-me" name="remember_me" checked>
            <span>Remember my session</span>
          </label>
        </div>

        <!-- Submit Button -->
        <button type="submit" class="btn btn-primary btn-block btn-lg">
          <i class="fas fa-sign-in-alt"></i> Sign In to Account
        </button>

      </form>

      <!-- Footer Callout -->
      <div class="auth-footer">
        Don't have an AUTO HUB account? <a href="register.php">Create Free Account</a>
      </div>

    </div>
  </main>

  <!-- Script for Show/Hide Password -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const pwToggle = document.getElementById('btn-toggle-pw');
      const pwInput = document.getElementById('login-password');
      if (pwToggle && pwInput) {
        pwToggle.addEventListener('click', () => {
          const isPassword = pwInput.type === 'password';
          pwInput.type = isPassword ? 'text' : 'password';
          const icon = pwToggle.querySelector('i');
          if (icon) {
            icon.className = isPassword ? 'far fa-eye-slash' : 'far fa-eye';
          }
        });
      }
    });
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
