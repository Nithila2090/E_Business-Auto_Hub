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
            // Process optional profile photo upload
            $profilePhotoPath = null;
            if (!empty($_FILES['profile_photo']['name'])) {
                $uploadRes = handle_profile_photo_upload($_FILES['profile_photo']);
                if (!$uploadRes['success']) {
                    $error = $uploadRes['error'];
                } else {
                    $profilePhotoPath = $uploadRes['file_path'];
                }
            }

            if (empty($error)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password, role, profile_image) VALUES (?, ?, ?, ?, 'customer', ?)");
                $stmt->execute([$fullName, $email, $phone, $hashedPassword, $profilePhotoPath]);
                $newId = (int)$pdo->lastInsertId();

                $_SESSION['user_id'] = $newId;
                $_SESSION['user_name'] = $fullName;
                $_SESSION['user_email'] = $email;
                $_SESSION['role'] = 'customer';
                $_SESSION['profile_image'] = $profilePhotoPath;

                sync_guest_cart_to_user($newId);

                header('Location: index.php');
                exit;
            }
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

      <form id="register-form" action="register.php" method="POST" enctype="multipart/form-data" class="auth-form">
        
        <!-- Profile Photo Upload Field -->
        <div class="form-group profile-upload-group" style="margin-bottom: 1.5rem;">
          <label class="form-label" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
            <span style="font-weight: 600;">Profile Photo</span>
            <span style="font-size: 0.78rem; font-weight: 500; color: #94a3b8;">Optional</span>
          </label>
          <div class="photo-upload-container" style="background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 12px; padding: 1rem 1.25rem; transition: border-color var(--transition-fast);">
            <!-- Circular Avatar Preview Box -->
            <div class="photo-preview-box" id="reg-avatar-preview-box" role="button" tabindex="0" title="Click to choose profile photo" style="width: 76px; height: 76px; border-radius: 50%; border: 2px solid var(--border-color); overflow: hidden; background: #e2e8f0; display: flex; align-items: center; justify-content: center; flex-shrink: 0; cursor: pointer; position: relative;">
              <div id="reg-avatar-fallback" class="photo-preview-fallback" style="font-size: 2.4rem; color: #94a3b8; display: flex; align-items: center; justify-content: center; width: 100%; height: 100%;">
                <i class="fas fa-user"></i>
              </div>
              <img src="" alt="Profile Preview" id="reg-avatar-img" class="photo-preview-img" style="display: none; width: 100%; height: 100%; object-fit: cover;">
            </div>

            <!-- Controls & Instructions -->
            <div class="photo-upload-controls" style="flex: 1; min-width: 0;">
              <input type="file" name="profile_photo" id="reg-profile-photo" accept="image/jpeg,image/png,image/webp" style="display: none;">
              <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                <button type="button" class="btn btn-outline-dark btn-sm" id="btn-browse-photo" style="font-size: 0.82rem; padding: 0.4rem 0.85rem; border-radius: 6px;">
                  <i class="fas fa-camera"></i> <span id="btn-browse-text">Choose Photo</span>
                </button>
                <button type="button" class="btn btn-sm" id="btn-remove-photo" style="display: none; font-size: 0.82rem; padding: 0.4rem 0.85rem; border-radius: 6px; color: #e11d48; border: 1px solid #fecdd3; background: #fff5f5;">
                  <i class="fas fa-trash-alt"></i> Remove
                </button>
              </div>
              <p class="upload-help-text" style="font-size: 0.75rem; color: #64748b; margin: 0.4rem 0 0; line-height: 1.4;">
                <i class="fas fa-info-circle"></i> Supported: <strong>JPG, PNG, WEBP</strong> (Max 2MB).
              </p>
            </div>
          </div>
        </div>

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

  <!-- Interactive Profile Photo Preview & Reset Script -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const fileInput = document.getElementById('reg-profile-photo');
      const btnBrowse = document.getElementById('btn-browse-photo');
      const btnBrowseText = document.getElementById('btn-browse-text');
      const btnRemove = document.getElementById('btn-remove-photo');
      const previewImg = document.getElementById('reg-avatar-img');
      const previewFallback = document.getElementById('reg-avatar-fallback');
      const previewBox = document.getElementById('reg-avatar-preview-box');

      if (!fileInput || !btnBrowse) return;

      // Click button or preview circle to open file dialog
      btnBrowse.addEventListener('click', () => fileInput.click());
      previewBox.addEventListener('click', () => fileInput.click());
      previewBox.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          fileInput.click();
        }
      });

      // Handle file selection
      fileInput.addEventListener('change', () => {
        const file = fileInput.files && fileInput.files[0];
        if (!file) return;

        // Client-side validations
        const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!validTypes.includes(file.type)) {
          alert('Please select a valid image file (JPG, PNG, or WEBP).');
          resetProfilePhoto();
          return;
        }

        const maxSizeBytes = 2 * 1024 * 1024; // 2MB
        if (file.size > maxSizeBytes) {
          alert('The selected image is larger than 2MB. Please choose a smaller image.');
          resetProfilePhoto();
          return;
        }

        // Live image preview
        const reader = new FileReader();
        reader.onload = (e) => {
          previewImg.src = e.target.result;
          previewImg.style.display = 'block';
          previewFallback.style.display = 'none';
          btnBrowseText.textContent = 'Change Photo';
          btnRemove.style.display = 'inline-flex';
        };
        reader.readAsDataURL(file);
      });

      // Remove photo button
      btnRemove.addEventListener('click', () => {
        resetProfilePhoto();
      });

      function resetProfilePhoto() {
        fileInput.value = '';
        previewImg.src = '';
        previewImg.style.display = 'none';
        previewFallback.style.display = 'flex';
        btnBrowseText.textContent = 'Choose Photo';
        btnRemove.style.display = 'none';
      }
    });
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
