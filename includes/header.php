<?php
/**
 * AUTO HUB - Shared Header Component
 */
require_once __DIR__ . '/../config/helpers.php';

$pdo = get_db();

// Fetch categories for navbar dropdown
try {
    $stmtNavCat = $pdo->query("SELECT id, name, slug, icon FROM categories WHERE status = 'active' ORDER BY id ASC");
    $navCategories = $stmtNavCat->fetchAll();
} catch (Exception $e) {
    $navCategories = [];
}

$cartCount = get_cart_count();
$wishlistCount = get_wishlist_count();
$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn = is_logged_in();
$currentUser = $isLoggedIn ? [
    'name' => current_user_name(),
    'email' => current_user_email(),
    'role' => current_user_role()
] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo !empty($pageTitle) ? e($pageTitle) : 'AUTO HUB – Spare Parts & Accessories | Sri Lanka\'s #1 Auto Parts Store'; ?></title>
  <meta name="description" content="<?php echo !empty($pageDescription) ? e($pageDescription) : 'Shop 100% genuine automotive spare parts and accessories in Sri Lanka for Toyota, BMW, Honda, Nissan, Suzuki and more. Fast islandwide delivery, best prices and verified vehicle fitment.'; ?>">
  
  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="images/logo/logo.png">

  <!-- Google Fonts (Outfit & Inter) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <!-- Custom Stylesheets -->
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/responsive.css">
</head>
<body>

  <!-- =========================================================================
       1. Top Header Bar
       ========================================================================= -->
  <header class="top-header">
    <div class="container">
      <div class="top-header-right">
        <a href="track-order.php" class="top-header-link"><i class="fas fa-truck"></i> <span>Track Order</span></a>
        <span class="top-header-divider"></span>
        <a href="contact.php" class="top-header-link"><i class="fas fa-headset"></i> <span>Help Center</span></a>
        <span class="top-header-divider"></span>
        <?php if ($isLoggedIn): ?>
          <?php $navAvatar = current_user_profile_image(); ?>
          <div class="user-dropdown-wrapper">
            <a href="profile.php" class="user-dropdown-toggle" id="user-profile-toggle" role="button" aria-expanded="false" aria-haspopup="true">
              <?php if (!empty($navAvatar) && file_exists(__DIR__ . '/../' . $navAvatar)): ?>
                <img src="<?php echo e($navAvatar); ?>" alt="<?php echo e($currentUser['name']); ?>" class="top-header-avatar-thumb">
              <?php else: ?>
                <i class="fas fa-user-circle"></i>
              <?php endif; ?>
              <span class="user-name"><?php echo e($currentUser['name']); ?></span>
              <?php if (is_admin()): ?>
                <span class="badge badge-admin">Admin</span>
              <?php endif; ?>
              <i class="fas fa-chevron-down dropdown-arrow"></i>
            </a>
            <div class="user-menu-dropdown" id="user-profile-menu">
              <?php if (is_admin()): ?>
                <a href="admin/dashboard.php" class="user-dropdown-item admin-link"><i class="fas fa-gauge-high"></i> <span>Admin Panel</span></a>
                <div class="user-dropdown-divider"></div>
              <?php endif; ?>
              <a href="profile.php" class="user-dropdown-item"><i class="fas fa-user"></i> <span>My Profile</span></a>
              <a href="profile.php?tab=orders" class="user-dropdown-item"><i class="fas fa-box"></i> <span>My Orders</span></a>
              <a href="wishlist.php" class="user-dropdown-item"><i class="fas fa-heart"></i> <span>My Wishlist</span></a>
              <a href="profile.php?tab=edit" class="user-dropdown-item"><i class="fas fa-user-edit"></i> <span>Edit Profile</span></a>
              <div class="user-dropdown-divider"></div>
              <a href="logout.php" class="user-dropdown-item logout-link"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
            </div>
          </div>
        <?php else: ?>
          <div class="user-dropdown-wrapper">
            <a href="login.php" class="user-dropdown-toggle" id="guest-profile-toggle" role="button" aria-expanded="false" aria-haspopup="true">
              <i class="fas fa-user"></i> 
              <span class="user-name">Login / Register</span>
              <i class="fas fa-chevron-down dropdown-arrow"></i>
            </a>
            <div class="user-menu-dropdown" id="guest-profile-menu">
              <a href="login.php" class="user-dropdown-item"><i class="fas fa-sign-in-alt"></i> <span>Login</span></a>
              <a href="register.php" class="user-dropdown-item"><i class="fas fa-user-plus"></i> <span>Register</span></a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <!-- =========================================================================
       2. Main Navigation Bar
       ========================================================================= -->
  <nav class="main-navbar">
    <div class="container">
      <div class="navbar-inner">
        
        <!-- Mobile Menu Toggle Button -->
        <button type="button" class="mobile-menu-btn" aria-label="Toggle navigation menu" aria-expanded="false">
          <i class="fas fa-bars"></i>
        </button>

        <!-- Brand Logo -->
        <a href="index.php" class="brand-logo">
          <div class="brand-icon">
            <i class="fas fa-cog fa-spin-hover"></i>
          </div>
          <div class="brand-text">
            <span class="brand-title">AUTO <span>HUB</span></span>
            <span class="brand-subtitle">SPARE PARTS &amp; ACCESSORIES</span>
          </div>
        </a>

        <!-- Desktop Navigation Links -->
        <ul class="nav-menu">
          <li class="nav-item">
            <a href="index.php" class="nav-link <?php echo ($currentPage === 'index.php') ? 'active' : ''; ?>">Home</a>
          </li>
          <li class="nav-item">
            <a href="products.php" class="nav-link <?php echo (in_array($currentPage, ['category.php', 'products.php', 'shop.php'])) ? 'active' : ''; ?>">
              Categories <i class="fas fa-chevron-down"></i>
            </a>
            <div class="dropdown-menu">
              <?php foreach ($navCategories as $navCat): ?>
                <a href="products.php?category=<?php echo urlencode($navCat['slug']); ?>" class="dropdown-item">
                  <i class="fas <?php echo e($navCat['icon'] ?? 'fa-cogs'); ?>"></i> <?php echo e($navCat['name']); ?>
                </a>
              <?php endforeach; ?>
            </div>
          </li>
          <li class="nav-item">
            <a href="products.php" class="nav-link">Vehicles</a>
          </li>
          <li class="nav-item">
            <a href="products.php" class="nav-link <?php echo ($currentPage === 'products.php' || $currentPage === 'shop.php') ? 'active' : ''; ?>">Shop</a>
          </li>
          <li class="nav-item">
            <a href="about.php" class="nav-link <?php echo ($currentPage === 'about.php') ? 'active' : ''; ?>">About Us</a>
          </li>
          <li class="nav-item">
            <a href="contact.php" class="nav-link <?php echo ($currentPage === 'contact.php') ? 'active' : ''; ?>">Contact Us</a>
          </li>
        </ul>

        <!-- Right Side: Search, Wishlist, Cart -->
        <div class="navbar-actions">
          <div class="search-wrapper">
            <form class="search-form" action="products.php" method="GET">
              <input type="text" name="search" class="search-input" placeholder="Search parts, categories, vehicle model..." autocomplete="off" value="<?php echo e($_GET['search'] ?? ''); ?>">
              <button type="submit" class="search-btn" aria-label="Search">
                <i class="fas fa-search"></i>
              </button>
            </form>
          </div>

          <a href="wishlist.php" class="nav-icon-link" title="My Wishlist" aria-label="Wishlist">
            <i class="<?php echo ($wishlistCount > 0) ? 'fas text-red' : 'far'; ?> fa-heart"></i>
            <span class="badge-count wishlist-badge-count" style="<?php echo ($wishlistCount > 0) ? 'display:inline-flex;' : 'display:none;'; ?>"><?php echo $wishlistCount; ?></span>
          </a>

          <a href="cart.php" class="nav-icon-link" title="Shopping Cart" aria-label="Shopping Cart">
            <i class="fas fa-shopping-cart"></i>
            <span class="badge-count cart-badge-count" style="<?php echo ($cartCount > 0) ? 'display:inline-flex;' : 'display:none;'; ?>"><?php echo $cartCount; ?></span>
          </a>
        </div>

      </div>
    </div>
  </nav>
