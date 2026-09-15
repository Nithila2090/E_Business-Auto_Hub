<?php
/**
 * AUTO HUB - Homepage
 * Dynamic Vehicle Compatibility Selector, Category Showcase & Featured Products
 */

require_once __DIR__ . '/config/helpers.php';

$pdo = get_db();

// 1. Fetch Vehicle Makes
$stmtMakes = $pdo->query("SELECT id, name FROM vehicle_makes ORDER BY name ASC");
$makes = $stmtMakes->fetchAll();

// 2. Fetch Categories
$stmtCategories = $pdo->query("
    SELECT c.*, COUNT(p.id) AS product_count 
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active'
    WHERE c.status = 'active'
    GROUP BY c.id
    ORDER BY c.id ASC
");
$categories = $stmtCategories->fetchAll();

// 3. Fetch Featured Products (Default 8 products)
$stmtProducts = $pdo->query("
    SELECT p.*, c.name AS category_name, c.slug AS category_slug
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.status = 'active'
    ORDER BY p.is_featured DESC, p.id ASC
    LIMIT 8
");
$featuredProducts = $stmtProducts->fetchAll();

// Fetch wishlisted product IDs if user is logged in
$wishlistedIds = [];
if (is_logged_in()) {
    $stmtWish = $pdo->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $stmtWish->execute([current_user_id()]);
    $wishlistedIds = $stmtWish->fetchAll(PDO::FETCH_COLUMN);
}

$pageTitle = "AUTO HUB – Spare Parts & Accessories | Sri Lanka's #1 Auto Parts Store";
$pageDescription = "Shop genuine automotive spare parts in Sri Lanka with guaranteed vehicle fitment for Toyota, BMW, Honda, Nissan, Suzuki and more.";

require_once __DIR__ . '/includes/header.php';
?>

  <!-- =========================================================================
       3. Hero Section (Automotive Banner + Vehicle Compatibility Selector)
       ========================================================================= -->
  <section class="hero-section">
    <div class="container">
      <div class="hero-grid">
        
        <!-- Left Hero Content -->
        <div class="hero-content">
          <div class="hero-badge-pill">
            <i class="fas fa-shield-alt"></i> 100% Genuine Sri Lankan Auto Store
          </div>

          <h1 class="hero-title">
            FIND THE RIGHT <br>
            <span class="text-red">PARTS FOR YOUR CAR</span>
          </h1>

          <p class="hero-description">
            Search verified compatible spare parts for Toyota, BMW, Honda, Nissan, Suzuki and more. Premium quality parts with guaranteed vehicle fitment and islandwide delivery.
          </p>

          <!-- Vehicle Compatibility Selectors Box -->
          <div class="vehicle-selector-box">
            <div class="selector-header">
              <i class="fas fa-sliders-h"></i> Select Your Vehicle
            </div>

            <form class="selector-form" action="products.php" method="GET">
              <div class="select-group">
                <i class="fas fa-car"></i>
                <select name="make" class="form-select-custom select-make" id="home-select-make" aria-label="Select Vehicle Make">
                  <option value="">Select Make</option>
                  <?php foreach ($makes as $mk): ?>
                    <option value="<?php echo e($mk['name']); ?>" data-id="<?php echo $mk['id']; ?>">
                      <?php echo e($mk['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="select-group">
                <i class="fas fa-car-side"></i>
                <select name="model" class="form-select-custom select-model" id="home-select-model" aria-label="Select Vehicle Model" disabled>
                  <option value="">Select Model</option>
                </select>
              </div>

              <div class="select-group">
                <i class="fas fa-calendar-alt"></i>
                <select name="year" class="form-select-custom select-year" id="home-select-year" aria-label="Select Vehicle Year" disabled>
                  <option value="">Select Year</option>
                </select>
              </div>

              <button type="submit" class="btn btn-primary btn-find-parts">
                <i class="fas fa-search"></i> FIND PARTS
              </button>
            </form>
          </div>
        </div>

        <!-- Right Hero Visual -->
        <div class="hero-visual">
          <div class="hero-main-img-wrapper">
            <img src="images/hero/hero.jpg" alt="Automotive Performance Car" class="hero-main-img">
          </div>

          <!-- Floating Badges -->
          <div class="hero-float-badge hero-badge-1">
            <div class="float-badge-icon">
              <i class="fas fa-check-circle"></i>
            </div>
            <div class="float-badge-text">
              <span class="float-badge-title">OEM Certified</span>
              <span class="float-badge-sub">Guaranteed Fitment</span>
            </div>
          </div>

          <div class="hero-float-badge hero-badge-2">
            <div class="float-badge-icon">
              <i class="fas fa-shipping-fast"></i>
            </div>
            <div class="float-badge-text">
              <span class="float-badge-title">Islandwide Express</span>
              <span class="float-badge-sub">24-48h Delivery</span>
            </div>
          </div>
        </div>

      </div>

      <!-- Hero Benefits (3 Columns) -->
      <div class="hero-benefits">
        <div class="benefit-item">
          <div class="benefit-icon">
            <i class="fas fa-certificate"></i>
          </div>
          <div>
            <h3 class="benefit-title">100% Genuine Parts</h3>
            <p class="benefit-sub">Quality you can trust from authorized manufacturers</p>
          </div>
        </div>

        <div class="benefit-item">
          <div class="benefit-icon">
            <i class="fas fa-truck"></i>
          </div>
          <div>
            <h3 class="benefit-title">Fast Delivery</h3>
            <p class="benefit-sub">Islandwide delivery across all 9 provinces in Sri Lanka</p>
          </div>
        </div>

        <div class="benefit-item">
          <div class="benefit-icon">
            <i class="fas fa-tags"></i>
          </div>
          <div>
            <h3 class="benefit-title">Best Price Guarantee</h3>
            <p class="benefit-sub">Direct importer pricing on high-demand auto spares</p>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- =========================================================================
       4. Popular Categories Grid (6 Cards)
       ========================================================================= -->
  <section class="section categories-section">
    <div class="container">
      
      <div class="section-title-wrap">
        <h2 class="section-title">EXPLORE CATEGORIES</h2>
        <p class="section-subtitle">Find exact replacement components by automotive system</p>
      </div>

      <div class="category-grid categories-grid">
        <?php foreach ($categories as $cat): ?>
          <a href="products.php?category=<?php echo urlencode($cat['slug']); ?>" class="category-card">
            <div class="category-img-box">
              <img src="<?php echo e($cat['image'] ?? 'images/categories/brake.jpg'); ?>" alt="<?php echo e($cat['name']); ?>" class="category-img" loading="lazy">
              <div class="category-overlay"></div>
            </div>
            <div class="category-card-body category-content">
              <div class="category-icon">
                <i class="fas <?php echo e($cat['icon'] ?? 'fa-cogs'); ?>"></i>
              </div>
              <h3 class="category-card-title category-title"><?php echo e($cat['name']); ?></h3>
              <p class="category-card-desc category-tagline category-description"><?php echo e($cat['description'] ?? 'Browse genuine parts'); ?></p>
              <span class="category-link-text category-explore-btn category-explore">Explore (<?php echo (int)$cat['product_count']; ?>) <i class="fas fa-arrow-right arrow"></i></span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

    </div>
  </section>

  <!-- =========================================================================
       5. Featured Products & Tabs Section
       ========================================================================= -->
  <section class="section featured-products-section">
    <div class="container">
      
      <div class="section-title-wrap">
        <h2 class="section-title">FEATURED SPARE PARTS</h2>
        <p class="section-subtitle">Top-rated genuine OEM replacements in stock for fast islandwide dispatch</p>
      </div>

      <!-- Filter Tabs -->
      <div class="product-tabs">
        <button class="tab-btn active" data-tab="all">All Parts</button>
        <button class="tab-btn" data-tab="bestseller">Best Sellers</button>
        <button class="tab-btn" data-tab="new">New Arrivals</button>
        <button class="tab-btn" data-tab="sale">On Sale</button>
      </div>

      <!-- Products Grid -->
      <div class="products-grid" id="featured-products-grid">
        <?php foreach ($featuredProducts as $prod): ?>
          <?php 
            $isWish = in_array((int)$prod['id'], $wishlistedIds);
            $hasDiscount = !empty($prod['old_price']) && $prod['old_price'] > $prod['price'];
            $discountVal = $prod['discount'] > 0 ? $prod['discount'] : ($hasDiscount ? round((1 - $prod['price'] / $prod['old_price']) * 100) : 0);
          ?>
          <div class="product-card" data-id="<?php echo $prod['id']; ?>">
            <div class="product-card-top">
              <div class="product-badge-group">
                <?php if ($hasDiscount): ?>
                  <span class="badge badge-discount">-<?php echo $discountVal; ?>%</span>
                <?php endif; ?>
                <?php if (!empty($prod['is_bestseller'])): ?>
                  <span class="badge badge-bestseller">Best Seller</span>
                <?php endif; ?>
                <?php if (!empty($prod['is_new'])): ?>
                  <span class="badge" style="background:#0284c7; color:white;">New</span>
                <?php endif; ?>
              </div>
              <button class="btn-wishlist <?php echo $isWish ? 'active' : ''; ?>" data-id="<?php echo $prod['id']; ?>" title="<?php echo $isWish ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                <i class="<?php echo $isWish ? 'fas' : 'far'; ?> fa-heart"></i>
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

              <div class="product-rating">
                <div class="stars">
                  <?php 
                    $rating = (float)($prod['rating'] ?? 5);
                    for ($i = 1; $i <= 5; $i++) {
                      echo ($i <= round($rating)) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                    }
                  ?>
                </div>
                <span class="rating-count">(<?php echo (int)($prod['reviews_count'] ?? 12); ?>)</span>
              </div>

              <div class="product-price-row">
                <div class="price-wrap">
                  <span class="current-price"><?php echo format_price($prod['price']); ?></span>
                  <?php if ($hasDiscount): ?>
                    <span class="old-price"><?php echo format_price($prod['old_price']); ?></span>
                  <?php endif; ?>
                </div>
                <span class="stock-status in-stock"><i class="fas fa-check"></i> In Stock</span>
              </div>
            </div>

            <div class="product-card-actions product-card-footer">
              <button class="quick-view-btn btn-quick-view" data-id="<?php echo $prod['id']; ?>" type="button">
                <i class="fa-regular fa-eye"></i> Quick View
              </button>
              <button class="add-to-cart-btn btn-add-cart" data-id="<?php echo $prod['id']; ?>" type="button" <?php echo ($prod['stock_quantity'] < 1) ? 'disabled' : ''; ?>>
                <i class="fa-solid fa-cart-shopping"></i> Add to Cart
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="text-center" style="margin-top: 3rem;">
        <a href="products.php" class="btn btn-primary btn-lg">
          <i class="fas fa-th"></i> View Full Spare Parts Catalog
        </a>
      </div>

    </div>
  </section>

  <!-- =========================================================================
       6. Promotional Banner (Promo / Deal of the Month)
       ========================================================================= -->
  <section class="section promo-banner-section">
    <div class="container">
      <div class="promo-banner-card">
        <div class="promo-banner-content">
          <span class="badge badge-discount" style="font-size: 0.9rem; padding: 0.4rem 0.8rem; margin-bottom: 1rem; display: inline-block;">
            SPECIAL LIMITED OFFER
          </span>
          <h2 class="promo-title">
            GET 10% OFF ON ALL <br>
            <span class="text-red">GENUINE BRAKE &amp; FILTER KITS</span>
          </h2>
          <p class="promo-text">
            Upgrade your car safety with authentic Brembo, Bosch, and Denso parts. Use promo coupon code <strong>AUTOHUB10</strong> at checkout.
          </p>

          <!-- Countdown Timer -->
          <div class="countdown-timer" id="promo-countdown">
            <div class="countdown-box">
              <span class="countdown-num" id="cd-days">02</span>
              <span class="countdown-label">Days</span>
            </div>
            <div class="countdown-box">
              <span class="countdown-num" id="cd-hours">14</span>
              <span class="countdown-label">Hours</span>
            </div>
            <div class="countdown-box">
              <span class="countdown-num" id="cd-mins">45</span>
              <span class="countdown-label">Mins</span>
            </div>
            <div class="countdown-box">
              <span class="countdown-num" id="cd-secs">30</span>
              <span class="countdown-label">Secs</span>
            </div>
          </div>

          <a href="products.php?category=brake-system" class="btn btn-primary btn-lg">
            <i class="fas fa-bolt"></i> Shop Discounted Parts Now
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- =========================================================================
       7. Why Choose AUTO HUB Section
       ========================================================================= -->
  <section class="section trust-section">
    <div class="container">
      <div class="section-title-wrap">
        <h2 class="section-title">WHY AUTO HUB SRI LANKA?</h2>
        <p class="section-subtitle">The standard for genuine automotive replacement parts and guaranteed fitment</p>
      </div>

      <div class="features-grid">
        <div class="feature-card">
          <div class="feature-icon"><i class="fas fa-check-double"></i></div>
          <h3 class="feature-title">Guaranteed Fitment</h3>
          <p class="feature-text">Select your vehicle make, model and year. Our database matches exact OEM dimensions and bolt patterns.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon"><i class="fas fa-award"></i></div>
          <h3 class="feature-title">Direct Importer Sourcing</h3>
          <p class="feature-text">100% genuine parts imported directly from Japan, Germany, and OEM authorized global automotive distributors.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon"><i class="fas fa-shipping-fast"></i></div>
          <h3 class="feature-title">Islandwide Express</h3>
          <p class="feature-text">Dispatched from our Colombo logistics hub with fast door-to-door courier tracking to all provinces.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
          <h3 class="feature-title">Warranty &amp; Returns</h3>
          <p class="feature-text">Enjoy up to 24-month manufacturer warranties and hassle-free 14-day replacement guarantees.</p>
        </div>
      </div>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
