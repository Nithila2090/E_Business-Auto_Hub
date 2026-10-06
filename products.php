<?php
/**
 * AUTO HUB - Product Catalog & Vehicle Fitment Search Page
 */

require_once __DIR__ . '/config/helpers.php';

$pdo = get_db();

// 1. Read GET parameters
$make = trim($_GET['make'] ?? '');
$model = trim($_GET['model'] ?? '');
$year = (int)($_GET['year'] ?? 0);
$category = trim($_GET['category'] ?? '');
$brand = trim($_GET['brand'] ?? '');
$search = trim($_GET['search'] ?? '');
$sort = trim($_GET['sort'] ?? 'featured');
$min_price = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (float)$_GET['min_price'] : null;
$max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float)$_GET['max_price'] : null;
$in_stock = isset($_GET['in_stock']) && ($_GET['in_stock'] === '1' || $_GET['in_stock'] === 'true' || $_GET['in_stock'] === 'on');

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 24;
$offset = ($page - 1) * $limit;

// 2. Build SQL WHERE
$where = ["p.status = 'active'"];
$params = [];

$joinCompatibility = false;
if (!empty($make) || !empty($model) || $year > 0) {
    $joinCompatibility = true;
    if (!empty($make)) {
        $where[] = "vmk.name = ?";
        $params[] = $make;
    }
    if (!empty($model)) {
        $where[] = "vm.name = ?";
        $params[] = $model;
    }
    if ($year > 0) {
        $where[] = "pvc.year = ?";
        $params[] = $year;
    }
}

if (!empty($category)) {
    if (is_numeric($category)) {
        $where[] = "p.category_id = ?";
        $params[] = (int)$category;
    } else {
        $where[] = "(c.slug = ? OR c.name = ?)";
        $params[] = $category;
        $params[] = $category;
    }
}

if (!empty($brand)) {
    $where[] = "p.brand = ?";
    $params[] = $brand;
}

if ($min_price !== null) {
    $where[] = "p.price >= ?";
    $params[] = $min_price;
}
if ($max_price !== null) {
    $where[] = "p.price <= ?";
    $params[] = $max_price;
}

if ($in_stock) {
    $where[] = "p.stock_quantity > 0";
}

if (!empty($search)) {
    $searchTerm = "%$search%";
    $where[] = "(p.name LIKE ? OR p.brand LIKE ? OR p.sku LIKE ? OR p.part_number LIKE ? OR c.name LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// 3. Sorting SQL
$orderBy = "p.is_featured DESC, p.id ASC";
switch ($sort) {
    case 'price_low':
    case 'price-asc':
        $orderBy = "p.price ASC";
        break;
    case 'price_high':
    case 'price-desc':
        $orderBy = "p.price DESC";
        break;
    case 'rating':
        $orderBy = "p.rating DESC, p.reviews_count DESC";
        break;
    case 'popular':
    case 'bestselling':
        $orderBy = "p.is_bestseller DESC, p.reviews_count DESC";
        break;
    case 'newest':
        $orderBy = "p.is_new DESC, p.id DESC";
        break;
    default:
        $orderBy = "p.is_featured DESC, p.id ASC";
        break;
}

$whereSql = implode(' AND ', $where);

$fromSql = "FROM products p LEFT JOIN categories c ON p.category_id = c.id";
if ($joinCompatibility) {
    $fromSql .= " JOIN product_vehicle_compatibility pvc ON p.id = pvc.product_id
                 JOIN vehicle_makes vmk ON pvc.make_id = vmk.id
                 JOIN vehicle_models vm ON pvc.model_id = vm.id";
}

// 4. Execute Queries
// Count
$countSql = "SELECT COUNT(DISTINCT p.id) AS total $fromSql WHERE $whereSql";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalProducts = (int)$stmtCount->fetch()['total'];

// Products List
$selectSql = "SELECT DISTINCT p.*, c.name AS category_name, c.slug AS category_slug $fromSql WHERE $whereSql ORDER BY $orderBy LIMIT $limit OFFSET $offset";
$stmtProducts = $pdo->prepare($selectSql);
$stmtProducts->execute($params);
$products = $stmtProducts->fetchAll();

// 5. Fetch Filter Options for Sidebar
$stmtSidebarMakes = $pdo->query("SELECT id, name FROM vehicle_makes ORDER BY name ASC");
$sidebarMakes = $stmtSidebarMakes->fetchAll();

$stmtSidebarCats = $pdo->query("
    SELECT c.*, COUNT(p.id) AS product_count 
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active'
    WHERE c.status = 'active'
    GROUP BY c.id
    ORDER BY c.id ASC
");
$sidebarCategories = $stmtSidebarCats->fetchAll();

$stmtSidebarBrands = $pdo->query("
    SELECT DISTINCT brand, COUNT(id) AS brand_count 
    FROM products 
    WHERE status = 'active' 
    GROUP BY brand 
    ORDER BY brand ASC
");
$sidebarBrands = $stmtSidebarBrands->fetchAll();

// Wishlist IDs for authenticated user
$wishlistedIds = [];
if (is_logged_in()) {
    $stmtWish = $pdo->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $stmtWish->execute([current_user_id()]);
    $wishlistedIds = $stmtWish->fetchAll(PDO::FETCH_COLUMN);
}

// Vehicle Fitment search active string
$hasVehicleFilter = !empty($make);
$vehicleTitle = trim("$make $model " . ($year > 0 ? (string)$year : ''));

$pageTitle = $hasVehicleFilter 
    ? "Spare Parts for $vehicleTitle | AUTO HUB Sri Lanka" 
    : (!empty($search) ? "Search: $search | AUTO HUB" : "Shop Automotive Spare Parts | AUTO HUB Sri Lanka");

require_once __DIR__ . '/includes/header.php';
?>

  <!-- =========================================================================
       Shop Page Main Area
       ========================================================================= -->
  <main class="shop-page-wrap">
    <div class="container">
      
      <!-- Breadcrumb -->
      <nav class="breadcrumb-nav">
        <a href="index.php"><i class="fas fa-home"></i> Home</a>
        <i class="fas fa-chevron-right breadcrumb-separator"></i>
        <a href="products.php">Shop Catalog</a>
        <?php if ($hasVehicleFilter): ?>
          <i class="fas fa-chevron-right breadcrumb-separator"></i>
          <span style="color: var(--primary-red); font-weight: 600;"><?php echo e($vehicleTitle); ?></span>
        <?php elseif (!empty($category)): ?>
          <i class="fas fa-chevron-right breadcrumb-separator"></i>
          <span style="color: var(--primary-red); font-weight: 600;"><?php echo e(ucwords(str_replace('-', ' ', $category))); ?></span>
        <?php else: ?>
          <i class="fas fa-chevron-right breadcrumb-separator"></i>
          <span id="breadcrumb-current">All Products</span>
        <?php endif; ?>
      </nav>

      <!-- Vehicle Fitment Active Banner -->
      <?php if ($hasVehicleFilter): ?>
        <div class="vehicle-fitment-banner" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border: 1.5px solid var(--primary-red); border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 15px rgba(225, 29, 72, 0.15);">
          <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--primary-red); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
              <i class="fas fa-car"></i>
            </div>
            <div>
              <div style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; font-weight: 600;">
                <i class="fas fa-check-circle text-red"></i> Verified Vehicle Fitment
              </div>
              <h2 style="font-size: 1.35rem; font-weight: 800; color: white; margin: 0.15rem 0;">
                Spare Parts Compatible with: <span class="text-red"><?php echo e($vehicleTitle); ?></span>
              </h2>
              <div style="font-size: 0.82rem; color: #cbd5e1;">
                Showing <?php echo $totalProducts; ?> verified replacement component<?php echo $totalProducts !== 1 ? 's' : ''; ?>
              </div>
            </div>
          </div>
          <a href="products.php" class="btn btn-outline-dark btn-sm" style="background: rgba(255,255,255,0.08); color: white; border-color: #475569;">
            <i class="fas fa-times"></i> Clear Vehicle Filter
          </a>
        </div>
      <?php endif; ?>

      <!-- Shop Layout: Sidebar + Grid -->
      <div class="shop-layout">
        
        <!-- Left Filter Sidebar -->
        <aside class="shop-sidebar">
          <div class="sidebar-title">
            <span><i class="fas fa-filter text-red"></i> Filters</span>
            <a href="products.php" class="btn-clear-filters" style="color: var(--primary-red); font-size: 0.8rem; font-weight: 600;">Reset All</a>
          </div>

          <form id="shop-filter-form" action="products.php" method="GET">
            <!-- Retain search if present -->
            <?php if (!empty($search)): ?>
              <input type="hidden" name="search" value="<?php echo e($search); ?>">
            <?php endif; ?>

            <!-- Filter: Vehicle Make & Model -->
            <div class="filter-group">
              <h4 class="filter-group-title">Select Vehicle</h4>
              <div style="display: flex; flex-direction: column; gap: 0.6rem;">
                <select name="make" class="form-control select-make" id="shop-filter-make" style="padding: 0.5rem 0.75rem; font-size: 0.85rem;">
                  <option value="">All Vehicle Makes</option>
                  <?php foreach ($sidebarMakes as $mk): ?>
                    <option value="<?php echo e($mk['name']); ?>" <?php echo ($make === $mk['name']) ? 'selected' : ''; ?>>
                      <?php echo e($mk['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>

                <select name="model" class="form-control select-model" id="shop-filter-model" style="padding: 0.5rem 0.75rem; font-size: 0.85rem;" <?php echo empty($make) ? 'disabled' : ''; ?>>
                  <option value="">All Models</option>
                  <?php if (!empty($model)): ?>
                    <option value="<?php echo e($model); ?>" selected><?php echo e($model); ?></option>
                  <?php endif; ?>
                </select>

                <select name="year" class="form-control select-year" id="shop-filter-year" style="padding: 0.5rem 0.75rem; font-size: 0.85rem;" <?php echo empty($model) ? 'disabled' : ''; ?>>
                  <option value="">All Years</option>
                  <?php if ($year > 0): ?>
                    <option value="<?php echo $year; ?>" selected><?php echo $year; ?></option>
                  <?php endif; ?>
                </select>
              </div>
            </div>

            <!-- Filter: Category -->
            <div class="filter-group">
              <h4 class="filter-group-title">Categories</h4>
              <div class="filter-list" id="category-filter-list">
                <?php foreach ($sidebarCategories as $sCat): ?>
                  <label class="filter-checkbox-label">
                    <input type="radio" name="category" value="<?php echo e($sCat['slug']); ?>" <?php echo ($category === $sCat['slug']) ? 'checked' : ''; ?> onchange="this.form.submit()">
                    <span><?php echo e($sCat['name']); ?></span>
                    <span class="count">(<?php echo (int)$sCat['product_count']; ?>)</span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Filter: Brand -->
            <div class="filter-group">
              <h4 class="filter-group-title">Brand / Manufacturer</h4>
              <div class="filter-list" id="brand-filter-list">
                <?php foreach ($sidebarBrands as $sBrand): ?>
                  <label class="filter-checkbox-label">
                    <input type="radio" name="brand" value="<?php echo e($sBrand['brand']); ?>" <?php echo ($brand === $sBrand['brand']) ? 'checked' : ''; ?> onchange="this.form.submit()">
                    <span><?php echo e($sBrand['brand']); ?></span>
                    <span class="count">(<?php echo (int)$sBrand['brand_count']; ?>)</span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Filter: Price Range -->
            <div class="filter-group">
              <h4 class="filter-group-title">Price Range (LKR)</h4>
              <div class="price-slider-wrap">
                <input type="range" name="max_price" id="price-range-slider" min="1000" max="60000" step="1000" value="<?php echo $max_price ?: 60000; ?>" style="width: 100%; accent-color: var(--primary-red); cursor: pointer;" onchange="this.form.submit()">
                <div class="price-range-inputs" style="display: flex; justify-content: space-between; margin-top: 0.5rem;">
                  <span style="font-size: 0.85rem; color: var(--text-muted);">Max:</span>
                  <span id="price-slider-value" style="font-weight: 700; color: var(--primary-red); font-size: 0.95rem;">
                    <?php echo format_price($max_price ?: 60000); ?>
                  </span>
                </div>
              </div>
            </div>

            <!-- Filter: Availability -->
            <div class="filter-group">
              <h4 class="filter-group-title">Availability</h4>
              <label class="filter-checkbox-label">
                <input type="checkbox" name="in_stock" id="filter-instock-only" value="1" <?php echo ($in_stock || !isset($_GET['in_stock'])) ? 'checked' : ''; ?> onchange="this.form.submit()">
                <span>In Stock Only</span>
              </label>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-sm" style="margin-top: 1rem;">
              <i class="fas fa-sync-alt"></i> Apply Filters
            </button>
          </form>
        </aside>

        <!-- Right Product Grid Area -->
        <section class="shop-products-column">
          
          <!-- Controls Bar -->
          <div class="shop-controls-bar">
            <div class="results-count">
              Showing <strong id="showing-count-text"><?php echo count($products); ?></strong> of <strong><?php echo $totalProducts; ?></strong> parts
            </div>

            <div class="shop-sort-controls">
              <label for="shop-sort-select" style="font-size: 0.85rem; color: var(--text-muted);">Sort By:</label>
              <select id="shop-sort-select" class="sort-select" onchange="const url = new URL(window.location.href); url.searchParams.set('sort', this.value); window.location.href = url.toString();">
                <option value="featured" <?php echo ($sort === 'featured') ? 'selected' : ''; ?>>Featured / Default</option>
                <option value="price_low" <?php echo ($sort === 'price_low' || $sort === 'price-asc') ? 'selected' : ''; ?>>Price: Low to High</option>
                <option value="price_high" <?php echo ($sort === 'price_high' || $sort === 'price-desc') ? 'selected' : ''; ?>>Price: High to Low</option>
                <option value="rating" <?php echo ($sort === 'rating') ? 'selected' : ''; ?>>Customer Rating</option>
                <option value="bestselling" <?php echo ($sort === 'bestselling' || $sort === 'popular') ? 'selected' : ''; ?>>Best Selling</option>
                <option value="newest" <?php echo ($sort === 'newest') ? 'selected' : ''; ?>>New Arrivals</option>
              </select>
            </div>
          </div>

          <!-- Product Grid -->
          <?php if (!empty($products)): ?>
            <div class="shop-grid" id="shop-product-grid">
              <?php foreach ($products as $prod): ?>
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

            <!-- Pagination Bar -->
            <?php 
              $totalPages = ceil($totalProducts / $limit);
              if ($totalPages > 1):
                $queryParams = $_GET;
            ?>
              <div class="pagination-wrap" style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 3rem; align-items: center;">
                <?php if ($page > 1): ?>
                  <?php $queryParams['page'] = $page - 1; ?>
                  <a href="products.php?<?php echo http_build_query($queryParams); ?>" class="btn btn-outline-dark btn-sm">
                    <i class="fas fa-chevron-left"></i> Prev
                  </a>
                <?php endif; ?>

                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                  <?php $queryParams['page'] = $p; ?>
                  <a href="products.php?<?php echo http_build_query($queryParams); ?>" class="btn <?php echo ($p === $page) ? 'btn-primary' : 'btn-outline-dark'; ?> btn-sm" style="min-width: 36px; padding: 0.4rem 0.6rem;">
                    <?php echo $p; ?>
                  </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                  <?php $queryParams['page'] = $page + 1; ?>
                  <a href="products.php?<?php echo http_build_query($queryParams); ?>" class="btn btn-outline-dark btn-sm">
                    Next <i class="fas fa-chevron-right"></i>
                  </a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <!-- Empty State -->
            <div id="shop-empty-state" style="text-align: center; padding: 4rem 1.5rem; background: white; border-radius: 16px; border: 1px solid var(--border-color);">
              <i class="fas fa-search text-muted" style="font-size: 3.5rem; margin-bottom: 1.25rem; color: #cbd5e1;"></i>
              <h3 style="font-size: 1.4rem; font-weight: 800; margin-bottom: 0.5rem;">No Compatible Spare Parts Found</h3>
              <p style="color: #64748b; max-width: 480px; margin: 0 auto 1.5rem;">
                We couldn't find any parts matching your selected vehicle or filter parameters. Try adjusting your search filters or browse all parts.
              </p>
              <a href="products.php" class="btn btn-primary">
                <i class="fas fa-sync-alt"></i> Reset All Filters
              </a>
            </div>
          <?php endif; ?>

        </section>
      </div>

    </div>
  </main>

  <script>
    // Live slider value display
    const slider = document.getElementById('price-range-slider');
    const sliderVal = document.getElementById('price-slider-value');
    if (slider && sliderVal) {
      slider.addEventListener('input', (e) => {
        sliderVal.textContent = 'Rs. ' + Number(e.target.value).toLocaleString('en-LK');
      });
    }
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
