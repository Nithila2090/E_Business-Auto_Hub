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
      <div class="top-header-left">
        <a href="tel:0707275599"><i class="fas fa-phone-alt"></i> 070 727 5599</a>
        <span class="top-header-divider"></span>
        <a href="mailto:nithilarodrigo@gmail.com"><i class="fas fa-envelope"></i> nithilarodrigo@gmail.com</a>
      </div>
      <div class="top-header-right">
        <a href="track-order.php"><i class="fas fa-truck"></i> Track Order</a>
        <span class="top-header-divider"></span>
        <a href="contact.php"><i class="fas fa-headset"></i> Help Center</a>
        <span class="top-header-divider"></span>
        <?php if ($isLoggedIn): ?>
          <div class="user-dropdown-wrapper" style="position: relative; display: inline-block;">
            <a href="profile.php" class="user-dropdown-toggle" style="display: inline-flex; align-items: center; gap: 0.4rem; color: #f8fafc; font-weight: 600;">
              <i class="fas fa-user-circle text-red"></i> 
              <span><?php echo e($currentUser['name']); ?></span>
              <?php if (is_admin()): ?>
                <span class="badge" style="background: var(--primary-red); color: white; font-size: 0.65rem; padding: 0.15rem 0.4rem; border-radius: 4px;">Admin</span>
              <?php endif; ?>
              <i class="fas fa-chevron-down" style="font-size: 0.7rem; opacity: 0.8;"></i>
            </a>
            <div class="user-menu-dropdown" style="display: none; position: absolute; right: 0; top: 100%; background: #0f172a; border: 1px solid #334155; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); min-width: 180px; z-index: 1000; padding: 0.5rem 0; margin-top: 0.5rem;">
              <a href="profile.php" style="display: flex; align-items: center; gap: 0.6rem; padding: 0.5rem 1rem; color: #f8fafc; font-size: 0.85rem;"><i class="fas fa-user text-red"></i> My Profile</a>
              <a href="profile.php#orders" style="display: flex; align-items: center; gap: 0.6rem; padding: 0.5rem 1rem; color: #f8fafc; font-size: 0.85rem;"><i class="fas fa-box text-red"></i> Order History</a>
              <a href="wishlist.php" style="display: flex; align-items: center; gap: 0.6rem; padding: 0.5rem 1rem; color: #f8fafc; font-size: 0.85rem;"><i class="fas fa-heart text-red"></i> My Wishlist</a>
              <?php if (is_admin()): ?>
                <div style="border-top: 1px solid #334155; margin: 0.35rem 0;"></div>
                <a href="admin/dashboard.php" style="display: flex; align-items: center; gap: 0.6rem; padding: 0.5rem 1rem; color: #38bdf8; font-size: 0.85rem;"><i class="fas fa-gauge-high"></i> Admin Panel</a>
              <?php endif; ?>
              <div style="border-top: 1px solid #334155; margin: 0.35rem 0;"></div>
              <a href="logout.php" style="display: flex; align-items: center; gap: 0.6rem; padding: 0.5rem 1rem; color: #ef4444; font-size: 0.85rem;"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
          </div>
        <?php else: ?>
          <a href="login.php"><i class="fas fa-user"></i> Login / Register</a>
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
        <button class="mobile-menu-btn" aria-label="Toggle navigation menu">
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
