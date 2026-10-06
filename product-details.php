<?php
/**
 * AUTO HUB - Product Details Page
 * Dynamic product info, gallery, specifications, vehicle compatibility list, and purchasing
 */

require_once __DIR__ . '/config/helpers.php';

$pdo = get_db();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: products.php');
    exit;
}

// 1. Fetch Product
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.slug AS category_slug
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = ? AND p.status = 'active'
    LIMIT 1
");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: products.php');
    exit;
}

// Specs
$specs = !empty($product['specs']) ? json_decode($product['specs'], true) : [];

// Gallery
$gallery = !empty($product['gallery']) 
    ? array_filter(array_map('trim', explode(',', $product['gallery']))) 
    : [$product['image']];
if (empty($gallery)) {
    $gallery = [$product['image']];
}

// 2. Fetch Vehicle Compatibility
$stmtCompat = $pdo->prepare("
    SELECT vmk.name AS make, vm.name AS model, pvc.year
    FROM product_vehicle_compatibility pvc
    JOIN vehicle_makes vmk ON pvc.make_id = vmk.id
    JOIN vehicle_models vm ON pvc.model_id = vm.id
    WHERE pvc.product_id = ?
    ORDER BY vmk.name, vm.name, pvc.year DESC
");
$stmtCompat->execute([$id]);
$compatRows = $stmtCompat->fetchAll();

// Group compatibility
$groupedCompat = [];
foreach ($compatRows as $row) {
    $key = $row['make'] . ' ' . $row['model'];
    if (!isset($groupedCompat[$key])) {
        $groupedCompat[$key] = ['make' => $row['make'], 'model' => $row['model'], 'years' => []];
    }
    $groupedCompat[$key]['years'][] = (int)$row['year'];
}

$fitmentList = [];
foreach ($groupedCompat as $k => $d) {
    $minY = min($d['years']);
    $maxY = max($d['years']);
    $yStr = ($minY === $maxY) ? (string)$minY : "$minY - $maxY";
    $fitmentList[] = "{$d['make']} {$d['model']} ($yStr)";
}

// 3. Fetch Related Products
$stmtRelated = $pdo->prepare("
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.category_id = ? AND p.id != ? AND p.status = 'active'
    ORDER BY p.is_featured DESC, p.id DESC
    LIMIT 4
");
$stmtRelated->execute([$product['category_id'], $id]);
$relatedProducts = $stmtRelated->fetchAll();

// Check if in wishlist
$isWish = false;
if (is_logged_in()) {
    $stmtWish = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmtWish->execute([current_user_id(), $id]);
    $isWish = (bool)$stmtWish->fetch();
}

$hasDiscount = !empty($product['old_price']) && $product['old_price'] > $product['price'];
$discountVal = $product['discount'] > 0 ? $product['discount'] : ($hasDiscount ? round((1 - $product['price'] / $product['old_price']) * 100) : 0);

$pageTitle = e($product['name']) . " | AUTO HUB Sri Lanka";
$pageDescription = e($product['short_description'] ?? $product['name']);

require_once __DIR__ . '/includes/header.php';
?>

  <!-- =========================================================================
       Product Details Section
       ========================================================================= -->
  <main class="product-details-wrap">
    <div class="container">
      
      <!-- Breadcrumbs -->
      <nav class="breadcrumb-nav">
        <a href="index.php"><i class="fas fa-home"></i> Home</a>
        <i class="fas fa-chevron-right breadcrumb-separator"></i>
        <a href="products.php">Shop</a>
        <i class="fas fa-chevron-right breadcrumb-separator"></i>
        <a href="products.php?category=<?php echo urlencode($product['category_slug'] ?? ''); ?>">
          <?php echo e($product['category_name'] ?? 'Category'); ?>
        </a>
        <i class="fas fa-chevron-right breadcrumb-separator"></i>
        <span style="color: var(--text-main); font-weight: 600;"><?php echo e($product['name']); ?></span>
      </nav>

      <!-- Main Product Details Grid -->
      <div class="product-detail-grid">
        
        <!-- Left: Image Gallery -->
        <div class="product-gallery-col">
          <div class="gallery-main-box">
            <img src="<?php echo e($gallery[0] ?? $product['image']); ?>" alt="<?php echo e($product['name']); ?>" id="detail-main-img" class="gallery-main-img">
          </div>
          <?php if (count($gallery) > 1): ?>
            <div class="gallery-thumbnails" id="detail-thumbnails">
              <?php foreach ($gallery as $idx => $img): ?>
                <div class="thumb-box <?php echo ($idx === 0) ? 'active' : ''; ?>" onclick="document.getElementById('detail-main-img').src='<?php echo e($img); ?>'; document.querySelectorAll('.thumb-box').forEach(b => b.classList.remove('active')); this.classList.add('active');">
                  <img src="<?php echo e($img); ?>" alt="Thumbnail <?php echo $idx + 1; ?>">
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Right: Product Info & Purchase Controls -->
        <div class="product-info-col">
          <div class="product-meta-header">
            <span class="badge badge-bestseller"><?php echo e($product['brand']); ?></span>
            <?php if ($product['stock_quantity'] > 0): ?>
              <span class="badge badge-instock"><i class="fas fa-check-circle"></i> In Stock (<?php echo $product['stock_quantity']; ?> available)</span>
            <?php else: ?>
              <span class="badge" style="background:#ef4444; color:white;"><i class="fas fa-times-circle"></i> Out of Stock</span>
            <?php endif; ?>
          </div>

          <h1 class="product-detail-title"><?php echo e($product['name']); ?></h1>
          
          <div class="product-sku-badge">
            Part Number: <strong><?php echo e($product['part_number'] ?: 'OEM-SPEC'); ?></strong> &bull; SKU: <strong><?php echo e($product['sku']); ?></strong>
          </div>

          <div class="product-rating" style="margin-bottom: 1rem;">
            <div class="stars">
              <?php 
                $rating = (float)($product['rating'] ?? 5);
                for ($i = 1; $i <= 5; $i++) {
                  echo ($i <= round($rating)) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                }
              ?>
            </div>
            <span class="rating-count">(<?php echo number_format($rating, 1); ?> out of 5 stars &bull; <?php echo (int)($product['reviews_count'] ?? 24); ?> reviews)</span>
          </div>

          <!-- Pricing Box -->
          <div class="product-detail-price-box">
            <span class="detail-current-price"><?php echo format_price($product['price']); ?></span>
            <?php if ($hasDiscount): ?>
              <span class="detail-old-price"><?php echo format_price($product['old_price']); ?></span>
              <span class="badge badge-discount" style="font-size: 0.85rem; padding: 0.35rem 0.65rem;">
                -<?php echo $discountVal; ?>% OFF
              </span>
            <?php endif; ?>
          </div>

          <!-- Vehicle Compatibility Fitment Banner -->
          <div class="fitment-checker-box">
            <i class="fas fa-check-circle"></i>
            <div>
              <div class="fitment-checker-text">Guaranteed Fitment Verified</div>
              <div style="font-size: 0.8rem; color: #15803d;">Direct bolt-on replacement for listed vehicle makes and models</div>
            </div>
          </div>

          <!-- Short Description -->
          <p style="font-size: 0.95rem; color: #475569; line-height: 1.6; margin-bottom: 1.5rem;">
            <?php echo e($product['short_description'] ?? 'Premium grade automotive spare part engineered to meet and exceed original equipment manufacturer standards.'); ?>
          </p>

          <!-- Quantity Stepper & Buttons -->
          <div class="product-actions-wrap">
            <div class="quantity-stepper">
              <button class="qty-btn" id="btn-qty-minus" onclick="const input = document.getElementById('detail-qty-input'); if(input.value > 1) input.value--;" aria-label="Decrease quantity">-</button>
              <input type="number" class="qty-input" id="detail-qty-input" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>" readonly>
              <button class="qty-btn" id="btn-qty-plus" onclick="const input = document.getElementById('detail-qty-input'); if(input.value < <?php echo $product['stock_quantity']; ?>) input.value++;" aria-label="Increase quantity">+</button>
            </div>

            <button class="btn btn-primary btn-lg" id="btn-detail-add-cart" data-id="<?php echo $product['id']; ?>" style="flex: 1;" <?php echo ($product['stock_quantity'] < 1) ? 'disabled' : ''; ?>>
              <i class="fas fa-shopping-cart"></i> Add to Cart
            </button>

            <button class="btn btn-secondary btn-lg" id="btn-detail-buy-now" data-id="<?php echo $product['id']; ?>" style="flex: 1;" <?php echo ($product['stock_quantity'] < 1) ? 'disabled' : ''; ?>>
              <i class="fas fa-bolt"></i> Buy Now
            </button>

            <button class="btn-wishlist <?php echo $isWish ? 'active' : ''; ?>" id="btn-detail-wishlist" data-id="<?php echo $product['id']; ?>" style="position: static; width: 48px; height: 48px; font-size: 1.25rem;" title="<?php echo $isWish ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
              <i class="<?php echo $isWish ? 'fas' : 'far'; ?> fa-heart"></i>
            </button>
          </div>

          <!-- Trust Badges -->
          <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; padding-top: 1.25rem; border-top: 1px solid var(--border-color); margin-top: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.82rem; color: #64748b;">
              <i class="fas fa-shield-alt text-red"></i> 100% Genuine
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.82rem; color: #64748b;">
              <i class="fas fa-truck text-red"></i> Islandwide Delivery
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.82rem; color: #64748b;">
              <i class="fas fa-undo text-red"></i> 14-Day Returns
            </div>
          </div>

        </div>

      </div>

      <!-- Specs, Compatibility & Full Description Tabs / Layout -->
      <div class="product-tabs-section" style="margin-top: 3.5rem;">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
          
          <!-- Detailed Specs Table -->
          <div style="background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 2rem; box-shadow: var(--shadow-sm);">
            <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
              <i class="fas fa-cogs text-red"></i> Technical Specifications
            </h3>
            <table class="specs-table" style="width: 100%; font-size: 0.9rem;">
              <tbody>
                <tr>
                  <td style="padding: 0.75rem 0; color: #64748b; font-weight: 600; width: 40%; border-bottom: 1px solid var(--border-color-light);">Brand / Maker</td>
                  <td style="padding: 0.75rem 0; font-weight: 700; color: var(--text-main); border-bottom: 1px solid var(--border-color-light);"><?php echo e($product['brand']); ?></td>
                </tr>
                <tr>
                  <td style="padding: 0.75rem 0; color: #64748b; font-weight: 600; border-bottom: 1px solid var(--border-color-light);">OEM Part Number</td>
                  <td style="padding: 0.75rem 0; font-weight: 700; color: var(--text-main); border-bottom: 1px solid var(--border-color-light);"><?php echo e($product['part_number'] ?: 'Standard OEM'); ?></td>
                </tr>
                <tr>
                  <td style="padding: 0.75rem 0; color: #64748b; font-weight: 600; border-bottom: 1px solid var(--border-color-light);">SKU Identifier</td>
                  <td style="padding: 0.75rem 0; font-weight: 700; color: var(--text-main); border-bottom: 1px solid var(--border-color-light);"><?php echo e($product['sku']); ?></td>
                </tr>
                <tr>
                  <td style="padding: 0.75rem 0; color: #64748b; font-weight: 600; border-bottom: 1px solid var(--border-color-light);">System Category</td>
                  <td style="padding: 0.75rem 0; font-weight: 700; color: var(--text-main); border-bottom: 1px solid var(--border-color-light);"><?php echo e($product['category_name']); ?></td>
                </tr>
                <?php if (!empty($specs)): ?>
                  <?php foreach ($specs as $key => $val): ?>
                    <tr>
                      <td style="padding: 0.75rem 0; color: #64748b; font-weight: 600; border-bottom: 1px solid var(--border-color-light);"><?php echo e($key); ?></td>
                      <td style="padding: 0.75rem 0; font-weight: 700; color: var(--text-main); border-bottom: 1px solid var(--border-color-light);"><?php echo e($val); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Verified Vehicle Compatibility List -->
          <div style="background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 2rem; box-shadow: var(--shadow-sm);">
            <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
              <i class="fas fa-car text-red"></i> Verified Vehicle Compatibility
            </h3>
            <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 1rem;">
              This spare part has guaranteed fitment with the following vehicle models:
            </p>
            <?php if (!empty($fitmentList)): ?>
              <ul style="display: flex; flex-direction: column; gap: 0.6rem;">
                <?php foreach ($fitmentList as $fit): ?>
                  <li style="display: flex; align-items: center; gap: 0.6rem; font-size: 0.9rem; background: #f8fafc; padding: 0.6rem 0.85rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <i class="fas fa-check-circle" style="color: #16a34a;"></i>
                    <strong style="color: var(--text-main);"><?php echo e($fit); ?></strong>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p style="font-size: 0.9rem; color: #64748b;">Universal or Multi-Model OEM Fitment.</p>
            <?php endif; ?>
          </div>

        </div>

        <!-- Full Detailed Description -->
        <div style="background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 2rem; margin-top: 2rem; box-shadow: var(--shadow-sm);">
          <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-align-left text-red"></i> Detailed Product Overview
          </h3>
          <div style="font-size: 0.95rem; color: #334155; line-height: 1.7;">
            <?php echo nl2br(e($product['description'] ?? $product['short_description'])); ?>
          </div>
        </div>

      </div>

      <!-- Related Products Grid -->
      <?php if (!empty($relatedProducts)): ?>
        <div style="margin-top: 4rem;">
          <div class="section-title-wrap" style="text-align: left; margin-bottom: 1.75rem;">
            <h2 class="section-title" style="font-size: 1.6rem;">RELATED SPARE PARTS</h2>
            <p class="section-subtitle">More genuine components in <?php echo e($product['category_name']); ?></p>
          </div>

          <div class="products-grid">
            <?php foreach ($relatedProducts as $rel): ?>
              <div class="product-card" data-id="<?php echo $rel['id']; ?>">
                <div class="product-card-top">
                  <a href="product-details.php?id=<?php echo $rel['id']; ?>" class="product-img-link">
                    <img src="<?php echo e($rel['image']); ?>" alt="<?php echo e($rel['name']); ?>" class="product-img" loading="lazy">
                  </a>
                </div>
                <div class="product-card-body">
                  <div class="product-meta">
                    <span class="product-category"><?php echo e($rel['category_name']); ?></span>
                    <span class="product-brand"><?php echo e($rel['brand']); ?></span>
                  </div>
                  <h3 class="product-title">
                    <a href="product-details.php?id=<?php echo $rel['id']; ?>"><?php echo e($rel['name']); ?></a>
                  </h3>
                  <div class="product-price-row">
                    <span class="current-price"><?php echo format_price($rel['price']); ?></span>
                    <span class="stock-status in-stock"><i class="fas fa-check"></i> In Stock</span>
                  </div>
                </div>
                <div class="product-card-actions product-card-footer">
                  <button class="quick-view-btn btn-quick-view" data-id="<?php echo $rel['id']; ?>" type="button">
                    <i class="fa-regular fa-eye"></i> Quick View
                  </button>
                  <button class="add-to-cart-btn btn-add-cart" data-id="<?php echo $rel['id']; ?>" type="button">
                    <i class="fa-solid fa-cart-shopping"></i> Add to Cart
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </main>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      // Add to cart from details page
      const addBtn = document.getElementById('btn-detail-add-cart');
      const buyNowBtn = document.getElementById('btn-detail-buy-now');
      const qtyInput = document.getElementById('detail-qty-input');

      if (addBtn) {
        addBtn.addEventListener('click', async () => {
          const id = addBtn.getAttribute('data-id');
          const qty = parseInt(qtyInput ? qtyInput.value : 1);
          await Store.addToCartAsync(id, qty);
        });
      }

      if (buyNowBtn) {
        buyNowBtn.addEventListener('click', async () => {
          const id = buyNowBtn.getAttribute('data-id');
          const qty = parseInt(qtyInput ? qtyInput.value : 1);
          const success = await Store.addToCartAsync(id, qty);
          if (success) {
            window.location.href = 'checkout.php';
          }
        });
      }

      // Wishlist toggle
      const wishBtn = document.getElementById('btn-detail-wishlist');
      if (wishBtn) {
        wishBtn.addEventListener('click', async () => {
          const id = wishBtn.getAttribute('data-id');
          await Store.toggleWishlistAsync(id, wishBtn);
        });
      }
    });
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
