<?php
/**
 * AUTO HUB - Wishlist Page
 * Saved favorite automotive parts and accessories
 */

require_once __DIR__ . '/config/helpers.php';

$pdo = get_db();
$isLoggedIn = is_logged_in();
$wishlistItems = [];

if ($isLoggedIn) {
    $stmt = $pdo->prepare("
        SELECT w.id AS wishlist_id, p.*, c.name AS category_name, c.slug AS category_slug
        FROM wishlist w
        JOIN products p ON w.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE w.user_id = ?
        ORDER BY w.id DESC
    ");
    $stmt->execute([current_user_id()]);
    $wishlistItems = $stmt->fetchAll();
}

$pageTitle = "My Wishlist | AUTO HUB – Spare Parts & Accessories";
$pageDescription = "Manage your saved automotive spare parts and accessories wishlist at AUTO HUB Sri Lanka.";

require_once __DIR__ . '/includes/header.php';
?>

  <!-- Wishlist Page Content -->
  <main class="shop-page-wrap">
    <div class="container">
      
      <div class="section-title-wrap" style="text-align: left; margin-bottom: 2rem;">
        <h1 class="section-title" style="font-size: 2.2rem;">MY WISHLIST</h1>
        <p class="section-subtitle">Keep track of your favorite automotive parts and accessories</p>
      </div>

      <?php if (!$isLoggedIn): ?>
        <!-- Login required notice -->
        <div style="text-align: center; padding: 4.5rem 1.5rem; background: white; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); max-width: 600px; margin: 0 auto;">
          <div style="width: 70px; height: 70px; border-radius: 50%; background: #fee2e2; color: var(--primary-red); display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 1.5rem;">
            <i class="far fa-heart"></i>
          </div>
          <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem;">Sign In to Save Your Wishlist</h2>
          <p style="color: #64748b; margin-bottom: 2rem;">
            Create an account or sign in to sync your saved spare parts across all your devices and get restock alerts.
          </p>
          <div style="display: flex; gap: 1rem; justify-content: center;">
            <a href="login.php" class="btn btn-primary btn-lg">
              <i class="fas fa-sign-in-alt"></i> Sign In
            </a>
            <a href="register.php" class="btn btn-outline-dark btn-lg">
              <i class="fas fa-user-plus"></i> Create Account
            </a>
          </div>
        </div>
      <?php elseif (empty($wishlistItems)): ?>
        <!-- Empty State -->
        <div id="wishlist-empty-state" style="text-align: center; padding: 5rem 1.5rem; background: white; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
          <i class="far fa-heart text-muted" style="font-size: 4rem; margin-bottom: 1.5rem; color: #cbd5e1;"></i>
          <h2 style="font-size: 1.6rem; font-weight: 800; margin-bottom: 0.5rem;">Your Wishlist is Empty</h2>
          <p style="color: #64748b; margin-bottom: 2rem; max-width: 460px; margin-left: auto; margin-right: auto;">
            Click the heart icon on any automotive part to save it for later review or instant purchasing.
          </p>
          <a href="products.php" class="btn btn-primary btn-lg">
            <i class="fas fa-search"></i> Browse Spare Parts Catalog
          </a>
        </div>
      <?php else: ?>
        <!-- Wishlist Grid -->
        <div class="products-grid" id="wishlist-products-grid">
          <?php foreach ($wishlistItems as $prod): ?>
            <?php 
              $hasDiscount = !empty($prod['old_price']) && $prod['old_price'] > $prod['price'];
              $discountVal = $prod['discount'] > 0 ? $prod['discount'] : ($hasDiscount ? round((1 - $prod['price'] / $prod['old_price']) * 100) : 0);
            ?>
            <div class="product-card" data-id="<?php echo $prod['id']; ?>">
              <div class="product-card-top">
                <div class="product-badge-group">
                  <?php if ($hasDiscount): ?>
                    <span class="badge badge-discount">-<?php echo $discountVal; ?>%</span>
                  <?php endif; ?>
                </div>
                <button class="btn-wishlist active" data-id="<?php echo $prod['id']; ?>" title="Remove from Wishlist" onclick="removeWishlistItem(<?php echo $prod['id']; ?>)">
                  <i class="fas fa-heart"></i>
                </button>
                <a href="product-details.php?id=<?php echo $prod['id']; ?>" class="product-img-link">
                  <img src="<?php echo e($prod['image']); ?>" alt="<?php echo e($prod['name']); ?>" class="product-img" loading="lazy">
                </a>
              </div>

              <div class="product-card-body">
                <div class="product-meta">
                  <span class="product-category"><?php echo e($prod['category_name'] ?? 'Spare Part'); ?></span>
                  <span class="product-brand"><?php echo e($prod['brand']); ?></span>
                </div>

                <h3 class="product-title">
                  <a href="product-details.php?id=<?php echo $prod['id']; ?>"><?php echo e($prod['name']); ?></a>
                </h3>

                <div class="product-fitment-note">
                  <i class="fas fa-check-circle"></i> SKU: <?php echo e($prod['sku']); ?>
                </div>

                <div class="product-price-row">
                  <div class="price-wrap">
                    <span class="current-price"><?php echo format_price($prod['price']); ?></span>
                    <?php if ($hasDiscount): ?>
                      <span class="old-price"><?php echo format_price($prod['old_price']); ?></span>
                    <?php endif; ?>
                  </div>
                  <?php if ($prod['stock_quantity'] > 0): ?>
                    <span class="stock-status in-stock"><i class="fas fa-check"></i> In Stock</span>
                  <?php else: ?>
                    <span class="stock-status" style="color: #ef4444;"><i class="fas fa-times"></i> Out of Stock</span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="product-card-footer">
                <button class="btn-add-cart" data-id="<?php echo $prod['id']; ?>" style="flex: 1;" <?php echo ($prod['stock_quantity'] < 1) ? 'disabled' : ''; ?>>
                  <i class="fas fa-shopping-cart"></i> Add to Cart
                </button>
                <button class="btn-quick-view" onclick="removeWishlistItem(<?php echo $prod['id']; ?>)" title="Remove" style="color: #ef4444;">
                  <i class="fas fa-trash-alt"></i>
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>
  </main>

  <script>
    async function removeWishlistItem(productId) {
      try {
        const res = await fetch('api/wishlist.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'remove', product_id: productId })
        });
        const data = await res.json();
        if (data.success) {
          Store.showToast(data.message, 'info');
          setTimeout(() => window.location.reload(), 400);
        }
      } catch (err) {
        console.error(err);
      }
    }
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
