<?php
/**
 * AUTO HUB - Customer Profile & Account Management
 * View and Edit Customer Information, Profile Photo Upload, Password Change, and Order History
 */

require_once __DIR__ . '/config/helpers.php';

// Enforce login - redirect guests to login page
require_login('login.php');

$pdo = get_db();
$userId = current_user_id();
$msg = '';
$error = '';

// Determine active tab from request parameter or default to 'view'
$activeTab = $_GET['tab'] ?? ($_GET['action'] ?? 'view');
if ($activeTab === 'edit' || isset($_GET['edit'])) {
    $activeTab = 'edit';
} elseif ($activeTab === 'orders') {
    $activeTab = 'orders';
} elseif ($activeTab === 'security') {
    $activeTab = 'security';
} else {
    $activeTab = 'view';
}

// Fetch current authenticated user record
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmtUser->execute([$userId]);
$user = $stmtUser->fetch();

if (!$user) {
    // If user record is missing, clear session and redirect
    header('Location: logout.php');
    exit;
}

// Handle POST submissions (Profile Update & Password Change)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Verify CSRF token for security
    if (!verify_csrf_token()) {
        $error = 'Security validation failed (CSRF token expired). Please reload the page and try again.';
    } else {

        // 1. UPDATE PROFILE
        if ($action === 'update_profile') {
            $fullName = trim($_POST['full_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $postalCode = trim($_POST['postal_code'] ?? '');
            $province = trim($_POST['province'] ?? '');

            if (empty($fullName)) {
                $error = 'Full Name is required and cannot be empty.';
                $activeTab = 'edit';
            } elseif (strlen($fullName) < 2 || strlen($fullName) > 100) {
                $error = 'Full Name must be between 2 and 100 characters.';
                $activeTab = 'edit';
            } else {
                $profileImage = $user['profile_image'];

                // Handle file upload if a new photo was selected
                if (!empty($_FILES['profile_image']['name'])) {
                    $uploadRes = handle_image_upload($_FILES['profile_image'], 'profiles');
                    if ($uploadRes['success']) {
                        $profileImage = $uploadRes['file_path'];
                    } else {
                        $error = $uploadRes['error'];
                        $activeTab = 'edit';
                    }
                }

                if (empty($error)) {
                    $stmtUpd = $pdo->prepare("
                        UPDATE users 
                        SET full_name = ?, phone = ?, address = ?, city = ?, postal_code = ?, province = ?, profile_image = ? 
                        WHERE id = ?
                    ");
                    $stmtUpd->execute([$fullName, $phone, $address, $city, $postalCode, $province, $profileImage, $userId]);

                    // Refresh session user name so navbar reflects change immediately
                    $_SESSION['user_name'] = $fullName;
                    $msg = 'Your profile details have been successfully updated!';
                    $activeTab = 'view';

                    // Re-fetch user record to display updated values
                    $stmtUser->execute([$userId]);
                    $user = $stmtUser->fetch();
                }
            }
        }

        // 2. CHANGE PASSWORD
        elseif ($action === 'change_password') {
            $currentPw = $_POST['current_password'] ?? '';
            $newPw = $_POST['new_password'] ?? '';
            $confirmPw = $_POST['confirm_password'] ?? '';

            if (empty($currentPw) || empty($newPw) || empty($confirmPw)) {
                $error = 'Please fill in all password fields.';
                $activeTab = 'security';
            } elseif (!password_verify($currentPw, $user['password'])) {
                $error = 'Current password entered is incorrect.';
                $activeTab = 'security';
            } elseif (strlen($newPw) < 6) {
                $error = 'New password must be at least 6 characters long.';
                $activeTab = 'security';
            } elseif ($newPw !== $confirmPw) {
                $error = 'New passwords do not match. Please verify your entries.';
                $activeTab = 'security';
            } elseif ($currentPw === $newPw) {
                $error = 'New password cannot be identical to your current password.';
                $activeTab = 'security';
            } else {
                $hashed = password_hash($newPw, PASSWORD_DEFAULT);
                $stmtPw = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmtPw->execute([$hashed, $userId]);
                $msg = 'Your password has been changed successfully!';
                $activeTab = 'security';

                // Re-fetch user record
                $stmtUser->execute([$userId]);
                $user = $stmtUser->fetch();
            }
        }
    }
}

// Fetch Customer's Order History (Strictly for current user ID)
$stmtOrders = $pdo->prepare("
    SELECT o.*, 
           COUNT(DISTINCT oi.id) AS item_count,
           p.payment_status,
           p.transaction_id
    FROM orders o 
    LEFT JOIN order_items oi ON oi.order_id = o.id 
    LEFT JOIN (
        SELECT p1.* FROM payments p1
        INNER JOIN (SELECT order_id, MAX(id) as max_id FROM payments GROUP BY order_id) p2
        ON p1.id = p2.max_id
    ) p ON p.order_id = o.id
    WHERE o.user_id = ? 
    GROUP BY o.id 
    ORDER BY o.id DESC
");
$stmtOrders->execute([$userId]);
$orders = $stmtOrders->fetchAll();

// Calculate User Summary Stats
$totalOrdersCount = count($orders);
$wishlistCount = get_wishlist_count();
$cartCount = get_cart_count();
$memberSince = !empty($user['created_at']) ? date('F j, Y', strtotime($user['created_at'])) : 'Recent Member';
$memberDays = !empty($user['created_at']) ? max(1, (int)((time() - strtotime($user['created_at'])) / 86400)) : 1;

// Sri Lankan Provinces List for Dropdown
$provinces = [
    'Western Province',
    'Central Province',
    'Southern Province',
    'North Western Province',
    'Sabaragamuwa Province',
    'Eastern Province',
    'Northern Province',
    'North Central Province',
    'Uva Province'
];

$pageTitle = "My Profile & Account | AUTO HUB – Spare Parts & Accessories";
$pageDescription = "View and manage your AUTO HUB customer profile, shipping address, security settings, and order history.";

require_once __DIR__ . '/includes/header.php';
?>

  <!-- Profile Page Wrapper -->
  <main class="profile-page-wrap">
    <div class="container">

      <!-- Breadcrumbs -->
      <nav class="breadcrumb-nav" aria-label="Breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i> Home</a>
        <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
        <span>My Account</span>
        <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
        <span class="breadcrumb-active">Customer Profile</span>
      </nav>

      <!-- Feedback Alerts -->
      <?php if (!empty($msg)): ?>
        <div class="alert-banner alert-success" role="alert">
          <div class="alert-icon"><i class="fas fa-check-circle"></i></div>
          <div class="alert-content">
            <strong>Success!</strong> <?php echo e($msg); ?>
          </div>
          <button type="button" class="alert-close" onclick="this.parentElement.remove();">&times;</button>
        </div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
        <div class="alert-banner alert-danger" role="alert">
          <div class="alert-icon"><i class="fas fa-exclamation-triangle"></i></div>
          <div class="alert-content">
            <strong>Error:</strong> <?php echo e($error); ?>
          </div>
          <button type="button" class="alert-close" onclick="this.parentElement.remove();">&times;</button>
        </div>
      <?php endif; ?>

      <!-- 1. Customer Hero Banner & Overview -->
      <div class="profile-hero-card">
        <div class="profile-hero-main">
          
          <!-- Avatar Frame -->
          <div class="profile-avatar-wrapper">
            <div class="profile-avatar-circle" id="profile-avatar-display">
              <?php if (!empty($user['profile_image']) && file_exists(__DIR__ . '/' . $user['profile_image'])): ?>
                <img src="<?php echo e($user['profile_image']); ?>" alt="<?php echo e($user['full_name']); ?>" class="profile-avatar-img">
              <?php else: ?>
                <div class="profile-avatar-fallback">
                  <i class="fas fa-user-circle"></i>
                </div>
              <?php endif; ?>
            </div>
            <button type="button" class="profile-avatar-edit-btn" title="Edit Profile Photo" onclick="activateTab('edit', true)">
              <i class="fas fa-camera"></i>
            </button>
          </div>

          <!-- Customer Identity Info -->
          <div class="profile-identity">
            <div class="profile-name-row">
              <h1 class="profile-user-name"><?php echo e($user['full_name']); ?></h1>
              <span class="badge badge-role <?php echo ($user['role'] === 'admin') ? 'badge-admin' : 'badge-customer'; ?>">
                <i class="<?php echo ($user['role'] === 'admin') ? 'fas fa-shield-halved' : 'fas fa-shield-check'; ?>"></i>
                <?php echo ($user['role'] === 'admin') ? 'Administrator' : 'Verified Customer'; ?>
              </span>
            </div>
            <div class="profile-meta-row">
              <span class="profile-meta-item"><i class="fas fa-envelope text-red"></i> <?php echo e($user['email']); ?></span>
              <?php if (!empty($user['phone'])): ?>
                <span class="profile-meta-item"><i class="fas fa-phone-alt text-red"></i> <?php echo e($user['phone']); ?></span>
              <?php endif; ?>
              <span class="profile-meta-item"><i class="fas fa-calendar-alt text-red"></i> Member since <?php echo e($memberSince); ?></span>
            </div>
          </div>
        </div>

        <!-- Quick Summary Stats Grid -->
        <div class="profile-stats-bar">
          <div class="profile-stat-box" onclick="activateTab('orders')">
            <div class="stat-icon-wrap stat-icon-red">
              <i class="fas fa-box-open"></i>
            </div>
            <div class="stat-info">
              <span class="stat-number"><?php echo $totalOrdersCount; ?></span>
              <span class="stat-label">Orders Placed</span>
            </div>
          </div>

          <a href="wishlist.php" class="profile-stat-box">
            <div class="stat-icon-wrap stat-icon-pink">
              <i class="fas fa-heart"></i>
            </div>
            <div class="stat-info">
              <span class="stat-number"><?php echo $wishlistCount; ?></span>
              <span class="stat-label">Wishlist Items</span>
            </div>
          </a>

          <a href="cart.php" class="profile-stat-box">
            <div class="stat-icon-wrap stat-icon-blue">
              <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stat-info">
              <span class="stat-number"><?php echo $cartCount; ?></span>
              <span class="stat-label">Cart Items</span>
            </div>
          </a>

          <div class="profile-stat-box">
            <div class="stat-icon-wrap stat-icon-gold">
              <i class="fas fa-award"></i>
            </div>
            <div class="stat-info">
              <span class="stat-number"><?php echo $memberDays; ?></span>
              <span class="stat-label">Days with Us</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 2. Profile Dashboard Navigation Tabs -->
      <div class="profile-dashboard-layout">
        
        <!-- Sidebar Navigation -->
        <aside class="profile-sidebar">
          <div class="profile-nav-card">
            <div class="profile-nav-header">
              <i class="fas fa-user-gear text-red"></i> Account Navigation
            </div>
            <nav class="profile-tabs-nav" role="tablist">
              <button type="button" class="profile-tab-btn <?php echo ($activeTab === 'view') ? 'active' : ''; ?>" data-tab="view" onclick="activateTab('view')">
                <i class="fas fa-id-card"></i> <span>Profile Details</span>
              </button>
              <button type="button" class="profile-tab-btn <?php echo ($activeTab === 'edit') ? 'active' : ''; ?>" data-tab="edit" onclick="activateTab('edit')">
                <i class="fas fa-user-pen"></i> <span>Edit Profile</span>
              </button>
              <button type="button" class="profile-tab-btn <?php echo ($activeTab === 'orders') ? 'active' : ''; ?>" data-tab="orders" onclick="activateTab('orders')">
                <i class="fas fa-clock-rotate-left"></i> <span>Order History</span>
                <span class="tab-badge"><?php echo $totalOrdersCount; ?></span>
              </button>
              <button type="button" class="profile-tab-btn <?php echo ($activeTab === 'security') ? 'active' : ''; ?>" data-tab="security" onclick="activateTab('security')">
                <i class="fas fa-shield-halved"></i> <span>Change Password</span>
              </button>
              <a href="wishlist.php" class="profile-tab-btn">
                <i class="fas fa-heart"></i> <span>My Wishlist</span>
                <span class="tab-badge"><?php echo $wishlistCount; ?></span>
              </a>
              <a href="cart.php" class="profile-tab-btn">
                <i class="fas fa-shopping-cart"></i> <span>Shopping Cart</span>
                <span class="tab-badge"><?php echo $cartCount; ?></span>
              </a>
              <div class="profile-nav-divider"></div>
              <a href="logout.php" class="profile-tab-btn profile-tab-logout">
                <i class="fas fa-sign-out-alt"></i> <span>Sign Out</span>
              </a>
            </nav>
          </div>

          <!-- Need Help Widget -->
          <div class="profile-help-card">
            <div class="help-card-icon"><i class="fas fa-headset"></i></div>
            <h4>Need Assistance?</h4>
            <p>Our automotive parts specialists are available to assist you with order inquiries and part fitment verification.</p>
            <a href="tel:0707275599" class="btn btn-sm btn-outline-dark btn-block"><i class="fas fa-phone-alt"></i> 070 727 5599</a>
          </div>
        </aside>

        <!-- Main Content Area with Tab Panes -->
        <div class="profile-content-area">

          <!-- TAB PANE 1: View Profile Details -->
          <div class="profile-tab-pane <?php echo ($activeTab === 'view') ? 'active' : ''; ?>" id="tab-pane-view">
            <div class="profile-card">
              <div class="profile-card-header">
                <div class="card-header-left">
                  <h2 class="card-title"><i class="fas fa-user-check text-red"></i> Customer Profile Information</h2>
                  <p class="card-subtitle">Review your verified account details, contact numbers, and delivery address</p>
                </div>
                <div class="card-header-right">
                  <button type="button" class="btn btn-primary btn-sm" onclick="activateTab('edit')">
                    <i class="fas fa-edit"></i> Edit Profile
                  </button>
                </div>
              </div>

              <div class="profile-card-body">
                <div class="profile-info-grid">
                  
                  <!-- Personal Details Group -->
                  <div class="info-section">
                    <h3 class="info-section-title"><i class="fas fa-id-badge text-red"></i> Personal &amp; Contact Info</h3>
                    
                    <div class="info-row">
                      <span class="info-label">Full Name:</span>
                      <strong class="info-value"><?php echo e($user['full_name']); ?></strong>
                    </div>

                    <div class="info-row">
                      <span class="info-label">Email Address:</span>
                      <div class="info-value">
                        <span><?php echo e($user['email']); ?></span>
                        <span class="badge-tag badge-verified"><i class="fas fa-lock"></i> Primary ID</span>
                      </div>
                    </div>

                    <div class="info-row">
                      <span class="info-label">Phone Number:</span>
                      <div class="info-value">
                        <?php if (!empty($user['phone'])): ?>
                          <strong><?php echo e($user['phone']); ?></strong>
                        <?php else: ?>
                          <span class="text-muted">Not provided</span>
                          <a href="javascript:void(0)" onclick="activateTab('edit')" class="link-action"><i class="fas fa-plus"></i> Add</a>
                        <?php endif; ?>
                      </div>
                    </div>

                    <div class="info-row">
                      <span class="info-label">Account Role:</span>
                      <span class="info-value text-capitalize"><?php echo e($user['role']); ?></span>
                    </div>

                    <div class="info-row">
                      <span class="info-label">Registered On:</span>
                      <span class="info-value"><?php echo !empty($user['created_at']) ? date('d M Y, h:i A', strtotime($user['created_at'])) : 'N/A'; ?></span>
                    </div>
                  </div>

                  <!-- Shipping Address Group -->
                  <div class="info-section">
                    <h3 class="info-section-title"><i class="fas fa-map-location-dot text-red"></i> Default Delivery Address</h3>

                    <div class="info-row">
                      <span class="info-label">Street Address:</span>
                      <div class="info-value">
                        <?php if (!empty($user['address'])): ?>
                          <strong><?php echo e($user['address']); ?></strong>
                        <?php else: ?>
                          <span class="text-muted">No street address saved</span>
                        <?php endif; ?>
                      </div>
                    </div>

                    <div class="info-row">
                      <span class="info-label">City / Town:</span>
                      <div class="info-value">
                        <?php echo !empty($user['city']) ? e($user['city']) : '<span class="text-muted">Not specified</span>'; ?>
                      </div>
                    </div>

                    <div class="info-row">
                      <span class="info-label">Postal Code:</span>
                      <div class="info-value">
                        <?php echo !empty($user['postal_code']) ? e($user['postal_code']) : '<span class="text-muted">Not specified</span>'; ?>
                      </div>
                    </div>

                    <div class="info-row">
                      <span class="info-label">Province:</span>
                      <div class="info-value">
                        <?php echo !empty($user['province']) ? e($user['province']) : '<span class="text-muted">Not specified</span>'; ?>
                      </div>
                    </div>

                    <div class="info-row">
                      <span class="info-label">Country:</span>
                      <strong class="info-value">Sri Lanka</strong>
                    </div>
                  </div>

                </div>

                <!-- Action Bar -->
                <div class="profile-card-footer">
                  <div class="footer-note">
                    <i class="fas fa-shield-alt text-red"></i> Your customer data is strictly encrypted and protected under AUTO HUB Privacy Standards.
                  </div>
                  <button type="button" class="btn btn-primary" onclick="activateTab('edit')">
                    <i class="fas fa-user-pen"></i> Edit Profile Information
                  </button>
                </div>

              </div>
            </div>
          </div>

          <!-- TAB PANE 2: Edit Profile Form -->
          <div class="profile-tab-pane <?php echo ($activeTab === 'edit') ? 'active' : ''; ?>" id="tab-pane-edit">
            <div class="profile-card">
              <div class="profile-card-header">
                <div class="card-header-left">
                  <h2 class="card-title"><i class="fas fa-user-pen text-red"></i> Edit Profile Details</h2>
                  <p class="card-subtitle">Update your personal information, contact numbers, and delivery address</p>
                </div>
                <div class="card-header-right">
                  <button type="button" class="btn btn-outline-dark btn-sm" onclick="activateTab('view')">
                    <i class="fas fa-times"></i> Cancel
                  </button>
                </div>
              </div>

              <div class="profile-card-body">
                <form action="profile.php" method="POST" enctype="multipart/form-data" id="profile-edit-form" class="profile-form">
                  <input type="hidden" name="action" value="update_profile">
                  <?php echo csrf_field(); ?>

                  <!-- Photo Upload Box -->
                  <div class="profile-photo-upload-section">
                    <label class="form-label" style="font-weight: 700;">Profile Photo</label>
                    <div class="photo-upload-container">
                      <div class="photo-preview-box" id="avatar-preview-container">
                        <?php if (!empty($user['profile_image']) && file_exists(__DIR__ . '/' . $user['profile_image'])): ?>
                          <img src="<?php echo e($user['profile_image']); ?>" alt="Profile Preview" id="avatar-preview-img" class="photo-preview-img">
                        <?php else: ?>
                          <div id="avatar-preview-fallback" class="photo-preview-fallback">
                            <i class="fas fa-user"></i>
                          </div>
                          <img src="" alt="Profile Preview" id="avatar-preview-img" class="photo-preview-img" style="display: none;">
                        <?php endif; ?>
                      </div>
                      
                      <div class="photo-upload-controls">
                        <div class="file-input-wrapper">
                          <input type="file" name="profile_image" id="profile-image-input" accept="image/jpeg,image/png,image/webp" class="file-input" onchange="previewSelectedAvatar(this)">
                          <label for="profile-image-input" class="btn btn-outline-dark btn-sm">
                            <i class="fas fa-upload"></i> Choose New Photo
                          </label>
                        </div>
                        <p class="upload-help-text">
                          <i class="fas fa-info-circle"></i> Supported formats: <strong>JPG, JPEG, PNG, WEBP</strong>. Maximum size: <strong>5MB</strong>.
                        </p>
                      </div>
                    </div>
                  </div>

                  <div class="form-divider"></div>

                  <!-- Form Fields Grid -->
                  <div class="form-grid-2">
                    
                    <div class="form-group">
                      <label class="form-label" for="input-full-name">Full Name <span class="required-star">*</span></label>
                      <div class="input-icon-wrap">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="full_name" id="input-full-name" class="form-control with-icon" required value="<?php echo e($user['full_name']); ?>" placeholder="Enter your full name">
                      </div>
                    </div>

                    <div class="form-group">
                      <label class="form-label" for="input-email">Email Address <span class="badge-tag badge-locked"><i class="fas fa-lock"></i> Read-Only</span></label>
                      <div class="input-icon-wrap">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" id="input-email" class="form-control with-icon input-disabled" disabled value="<?php echo e($user['email']); ?>" style="background: #f1f5f9; cursor: not-allowed;">
                      </div>
                      <small class="field-hint">Email address is your primary login ID and cannot be modified.</small>
                    </div>

                    <div class="form-group">
                      <label class="form-label" for="input-phone">Phone / Mobile Number</label>
                      <div class="input-icon-wrap">
                        <i class="fas fa-phone input-icon"></i>
                        <input type="tel" name="phone" id="input-phone" class="form-control with-icon" value="<?php echo e($user['phone'] ?? ''); ?>" placeholder="e.g. 070 727 5599">
                      </div>
                    </div>

                    <div class="form-group">
                      <label class="form-label" for="input-address">Street Address</label>
                      <div class="input-icon-wrap">
                        <i class="fas fa-location-dot input-icon"></i>
                        <input type="text" name="address" id="input-address" class="form-control with-icon" value="<?php echo e($user['address'] ?? ''); ?>" placeholder="House / Building No, Street Name">
                      </div>
                    </div>

                    <div class="form-group">
                      <label class="form-label" for="input-city">City / Town</label>
                      <div class="input-icon-wrap">
                        <i class="fas fa-city input-icon"></i>
                        <input type="text" name="city" id="input-city" class="form-control with-icon" value="<?php echo e($user['city'] ?? ''); ?>" placeholder="e.g. Colombo 07">
                      </div>
                    </div>

                    <div class="form-group">
                      <label class="form-label" for="input-postal">Postal Code</label>
                      <div class="input-icon-wrap">
                        <i class="fas fa-mail-bulk input-icon"></i>
                        <input type="text" name="postal_code" id="input-postal" class="form-control with-icon" value="<?php echo e($user['postal_code'] ?? ''); ?>" placeholder="e.g. 00700">
                      </div>
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                      <label class="form-label" for="input-province">Province / Region</label>
                      <div class="input-icon-wrap">
                        <i class="fas fa-map input-icon"></i>
                        <select name="province" id="input-province" class="form-control with-icon">
                          <option value="">-- Select Province --</option>
                          <?php foreach ($provinces as $prov): ?>
                            <option value="<?php echo e($prov); ?>" <?php echo (($user['province'] ?? '') === $prov) ? 'selected' : ''; ?>>
                              <?php echo e($prov); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                    </div>

                  </div>

                  <!-- Form Submit Bar -->
                  <div class="profile-card-footer form-actions-bar">
                    <button type="button" class="btn btn-outline-dark" onclick="activateTab('view')">
                      <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                      <i class="fas fa-save"></i> Save Changes
                    </button>
                  </div>

                </form>
              </div>
            </div>
          </div>

          <!-- TAB PANE 3: Order History -->
          <div class="profile-tab-pane <?php echo ($activeTab === 'orders') ? 'active' : ''; ?>" id="tab-pane-orders">
            <div class="profile-card" id="orders">
              <div class="profile-card-header">
                <div class="card-header-left">
                  <h2 class="card-title"><i class="fas fa-box text-red"></i> My Order History (<?php echo count($orders); ?>)</h2>
                  <p class="card-subtitle">Track your spare parts shipments, payment status, and order details</p>
                </div>
                <div class="card-header-right">
                  <a href="products.php" class="btn btn-outline-red btn-sm">
                    <i class="fas fa-cart-plus"></i> Shop More Parts
                  </a>
                </div>
              </div>

              <div class="profile-card-body">
                <?php if (empty($orders)): ?>
                  <div class="empty-orders-view">
                    <div class="empty-icon-wrap">
                      <i class="fas fa-box-open"></i>
                    </div>
                    <h3>No Orders Placed Yet</h3>
                    <p>You have not placed any spare parts or accessories orders yet. Explore our verified vehicle catalog today!</p>
                    <a href="products.php" class="btn btn-primary">
                      <i class="fas fa-search"></i> Browse Genuine Spare Parts
                    </a>
                  </div>
                <?php else: ?>
                  <div class="order-cards-list">
                    <?php foreach ($orders as $ord): ?>
                      <?php 
                        $st = strtolower($ord['status']);
                        $statusBadgeClass = 'badge-status-pending';
                        if ($st === 'shipped') { $statusBadgeClass = 'badge-status-shipped'; }
                        elseif ($st === 'delivered') { $statusBadgeClass = 'badge-status-delivered'; }
                        elseif ($st === 'cancelled') { $statusBadgeClass = 'badge-status-cancelled'; }
                        elseif ($st === 'processing') { $statusBadgeClass = 'badge-status-processing'; }

                        $pst = $ord['payment_status'] ?? 'Pending';
                        $payBadgeClass = ($pst === 'Paid') ? 'badge-pay-paid' : (($pst === 'Failed') ? 'badge-pay-failed' : 'badge-pay-pending');
                      ?>
                      <div class="order-card-item">
                        <div class="order-card-header">
                          <div class="order-header-main">
                            <div class="order-num-tag">
                              <i class="fas fa-receipt text-red"></i>
                              <strong><?php echo e($ord['order_number']); ?></strong>
                            </div>
                            <span class="order-date"><i class="far fa-clock"></i> <?php echo date('d M Y, h:i A', strtotime($ord['created_at'])); ?></span>
                          </div>
                          
                          <div class="order-badges-wrap">
                            <span class="order-status-badge <?php echo $payBadgeClass; ?>">
                              <i class="fas fa-money-bill-wave"></i> <?php echo e($pst); ?>
                            </span>
                            <span class="order-status-badge <?php echo $statusBadgeClass; ?>">
                              <i class="fas fa-truck-fast"></i> <?php echo e(ucfirst($ord['status'])); ?>
                            </span>
                          </div>
                        </div>

                        <div class="order-card-body">
                          <div class="order-details-grid">
                            <div>
                              <span class="detail-label">Total Amount:</span>
                              <span class="detail-val-price"><?php echo format_price($ord['total_amount']); ?></span>
                            </div>
                            <div>
                              <span class="detail-label">Items Count:</span>
                              <span class="detail-val"><?php echo (int)$ord['item_count']; ?> item<?php echo $ord['item_count'] > 1 ? 's' : ''; ?></span>
                            </div>
                            <div>
                              <span class="detail-label">Payment Method:</span>
                              <span class="detail-val"><?php echo e($ord['payment_method']); ?></span>
                            </div>
                            <div>
                              <span class="detail-label">Delivery Destination:</span>
                              <span class="detail-val"><?php echo e($ord['shipping_city'] . ', ' . $ord['shipping_province']); ?></span>
                            </div>
                          </div>
                        </div>

                        <div class="order-card-footer">
                          <div class="shipping-recipient">
                            <i class="fas fa-user text-muted"></i> Recipient: <strong><?php echo e($ord['shipping_name']); ?></strong> (<?php echo e($ord['shipping_phone']); ?>)
                          </div>
                          <a href="track-order.php?order_id=<?php echo urlencode($ord['order_number']); ?>" class="btn btn-outline-red btn-sm">
                            <i class="fas fa-location-crosshairs"></i> Track Live Status
                          </a>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- TAB PANE 4: Security & Change Password -->
          <div class="profile-tab-pane <?php echo ($activeTab === 'security') ? 'active' : ''; ?>" id="tab-pane-security">
            <div class="profile-card" id="security">
              <div class="profile-card-header">
                <div class="card-header-left">
                  <h2 class="card-title"><i class="fas fa-shield-halved text-red"></i> Password &amp; Account Security</h2>
                  <p class="card-subtitle">Keep your AUTO HUB customer account secure with a strong password</p>
                </div>
              </div>

              <div class="profile-card-body">
                <form action="profile.php" method="POST" id="password-change-form" class="profile-form" style="max-width: 600px;">
                  <input type="hidden" name="action" value="change_password">
                  <?php echo csrf_field(); ?>

                  <div class="form-group">
                    <label class="form-label" for="input-current-password">Current Password <span class="required-star">*</span></label>
                    <div class="input-password-wrap">
                      <i class="fas fa-key input-icon"></i>
                      <input type="password" name="current_password" id="input-current-password" class="form-control with-icon" required placeholder="Enter your current password">
                      <button type="button" class="btn-toggle-pw" onclick="togglePasswordVisibility('input-current-password', this)">
                        <i class="far fa-eye"></i>
                      </button>
                    </div>
                  </div>

                  <div class="form-group">
                    <label class="form-label" for="input-new-password">New Password <span class="required-star">*</span></label>
                    <div class="input-password-wrap">
                      <i class="fas fa-lock input-icon"></i>
                      <input type="password" name="new_password" id="input-new-password" class="form-control with-icon" required minlength="6" placeholder="At least 6 characters">
                      <button type="button" class="btn-toggle-pw" onclick="togglePasswordVisibility('input-new-password', this)">
                        <i class="far fa-eye"></i>
                      </button>
                    </div>
                    <small class="field-hint">Must be at least 6 characters. Use letters, numbers, and symbols for high security.</small>
                  </div>

                  <div class="form-group">
                    <label class="form-label" for="input-confirm-password">Confirm New Password <span class="required-star">*</span></label>
                    <div class="input-password-wrap">
                      <i class="fas fa-lock-check input-icon"></i>
                      <input type="password" name="confirm_password" id="input-confirm-password" class="form-control with-icon" required minlength="6" placeholder="Re-enter your new password">
                      <button type="button" class="btn-toggle-pw" onclick="togglePasswordVisibility('input-confirm-password', this)">
                        <i class="far fa-eye"></i>
                      </button>
                    </div>
                  </div>

                  <div class="profile-card-footer form-actions-bar" style="padding-left: 0; padding-right: 0; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary">
                      <i class="fas fa-key"></i> Update Password
                    </button>
                  </div>

                </form>
              </div>
            </div>
          </div>

        </div>

      </div>

    </div>
  </main>

  <!-- JavaScript for Profile Interactions -->
  <script>
    // Tab Activation Function
    function activateTab(tabName, scrollToForm = false) {
      // Update Tab Navigation Buttons
      document.querySelectorAll('.profile-tab-btn').forEach(btn => {
        if (btn.dataset.tab === tabName) {
          btn.classList.add('active');
        } else if (btn.dataset.tab) {
          btn.classList.remove('active');
        }
      });

      // Update Tab Panes
      document.querySelectorAll('.profile-tab-pane').forEach(pane => {
        if (pane.id === 'tab-pane-' + tabName) {
          pane.classList.add('active');
        } else {
          pane.classList.remove('active');
        }
      });

      // Update URL hash without reload
      if (history.pushState) {
        history.pushState(null, null, '#' + tabName);
      } else {
        location.hash = '#' + tabName;
      }

      if (scrollToForm) {
        const target = document.getElementById('tab-pane-' + tabName);
        if (target) {
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }
    }

    // Live Avatar Preview on file selection
    function previewSelectedAvatar(input) {
      if (input.files && input.files[0]) {
        const file = input.files[0];

        // Check file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
          alert('Selected image exceeds the 5MB size limit. Please choose a smaller photo.');
          input.value = '';
          return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
          const previewImg = document.getElementById('avatar-preview-img');
          const fallback = document.getElementById('avatar-preview-fallback');
          
          if (previewImg) {
            previewImg.src = e.target.result;
            previewImg.style.display = 'block';
          }
          if (fallback) {
            fallback.style.display = 'none';
          }

          // Also update header hero display avatar if present
          const heroAvatarImg = document.querySelector('#profile-avatar-display img');
          if (heroAvatarImg) {
            heroAvatarImg.src = e.target.result;
          }
        };
        reader.readAsDataURL(file);
      }
    }

    // Toggle Password Visibility
    function togglePasswordVisibility(inputId, btn) {
      const input = document.getElementById(inputId);
      const icon = btn.querySelector('i');
      if (input) {
        if (input.type === 'password') {
          input.type = 'text';
          if (icon) {
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
          }
        } else {
          input.type = 'password';
          if (icon) {
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
          }
        }
      }
    }

    // Hash navigation handler on page load
    document.addEventListener('DOMContentLoaded', () => {
      const hash = window.location.hash.replace('#', '');
      if (hash && ['view', 'edit', 'orders', 'security'].includes(hash)) {
        activateTab(hash);
      }
    });
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
