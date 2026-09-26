<?php
/**
 * AUTO HUB - Admin Login Screen
 */

require_once __DIR__ . '/../config/helpers.php';

$pdo = get_db();
$error = '';

if (is_admin()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your administrator email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['role'] !== 'admin') {
                $error = 'Access denied. Administrator privileges required.';
            } else {
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['role'] = 'admin';

                header('Location: dashboard.php');
                exit;
            }
        } else {
            $error = 'Invalid admin credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login | AUTO HUB</title>
  
  <link rel="icon" type="image/x-icon" href="../images/logo/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body style="background: #070a12; display: flex; align-items: center; justify-content: center; min-height: 100vh;">

  <div style="width: 100%; max-width: 420px; padding: 2.5rem; background: #0f172a; border: 1px solid #334155; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.6);">
    
    <div style="text-align: center; margin-bottom: 2rem;">
      <div style="width: 56px; height: 56px; border-radius: 12px; background: var(--primary-red); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 1rem;">
        <i class="fas fa-shield-alt"></i>
      </div>
      <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.6rem; font-weight: 800; color: white; margin: 0;">AUTO <span style="color: var(--primary-red);">HUB</span></h1>
      <p style="font-size: 0.85rem; color: #94a3b8; margin-top: 0.25rem;">Administrator Control Center</p>
    </div>

    <?php if (!empty($error)): ?>
      <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.85rem; margin-bottom: 1.25rem;">
        <i class="fas fa-exclamation-triangle"></i> <?php echo e($error); ?>
      </div>
    <?php endif; ?>

    <form action="login.php" method="POST">
      <div class="admin-form-group">
        <label class="admin-label">Admin Email Address</label>
        <input type="email" name="email" class="admin-input" required placeholder="admin@autohub.lk" value="<?php echo e($_POST['email'] ?? ''); ?>">
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Password</label>
        <input type="password" name="password" class="admin-input" required placeholder="Enter password">
      </div>

      <button type="submit" class="admin-btn admin-btn-primary" style="width: 100%; justify-content: center; padding: 0.75rem; margin-top: 1rem;">
        <i class="fas fa-lock"></i> Secure Sign In
      </button>
    </form>

    <div style="margin-top: 2rem; text-align: center; border-top: 1px solid #1e293b; padding-top: 1.25rem;">
      <a href="../index.php" style="color: #94a3b8; font-size: 0.85rem; text-decoration: none;">
        <i class="fas fa-arrow-left"></i> Return to Main Website
      </a>
    </div>

  </div>

</body>
</html>
