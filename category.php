<?php
/**
 * AUTO HUB - Category Showcase Page
 */

require_once __DIR__ . '/config/helpers.php';

$pdo = get_db();
$categoryId = $_GET['id'] ?? '';

if (!empty($categoryId)) {
    header("Location: products.php?category=" . urlencode($categoryId));
    exit;
}

$stmtCategories = $pdo->query("
    SELECT c.*, COUNT(p.id) AS product_count 
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active'
    WHERE c.status = 'active'
    GROUP BY c.id
    ORDER BY c.id ASC
");
$categories = $stmtCategories->fetchAll();

$pageTitle = "Spare Parts Categories | AUTO HUB – Sri Lanka";
$pageDescription = "Browse genuine OEM and aftermarket automotive spare parts categorized by vehicle systems: Brake Systems, Engine Parts, Suspension, Electrical, Transmission, and Body Parts.";

require_once __DIR__ . '/includes/header.php';
?>

  <!-- Categories Hero Banner -->
  <section style="background: linear-gradient(135deg, #070a12 0%, #0f172a 100%); color: white; padding: 4rem 0 3.5rem; border-bottom: 3px solid var(--primary-red); position: relative;">
    <div class="container">
      
      <!-- Breadcrumb -->
      <nav class="breadcrumb-nav" style="margin-bottom: 1.5rem; background: rgba(255, 255, 255, 0.05); padding: 0.5rem 1rem; border-radius: 8px; display: inline-flex; border: 1px solid rgba(255, 255, 255, 0.1);">
        <a href="index.php" style="color: #94a3b8;"><i class="fas fa-home"></i> Home</a>
        <i class="fas fa-chevron-right breadcrumb-separator" style="color: #64748b; font-size: 0.75rem; margin: 0 0.5rem;"></i>
        <span style="color: var(--primary-red); font-weight: 600;">Categories</span>
      </nav>

      <div style="text-align: center; max-width: 760px; margin: 0 auto;">
        <div class="hero-badge-pill" style="margin: 0 auto 1rem;">
          <i class="fas fa-layer-group"></i> Complete Automotive System Catalogs
        </div>
        <h1 style="font-size: 2.8rem; font-weight: 900; color: white; margin-bottom: 0.75rem; letter-spacing: -0.5px;">
          SPARE PARTS <span class="text-red">CATEGORIES</span>
        </h1>
        <p style="font-size: 1.05rem; color: #94a3b8; line-height: 1.6;">
          Explore certified OEM and high performance aftermarket components engineered for reliability, safety, and guaranteed vehicle compatibility.
        </p>
      </div>

    </div>
  </section>

  <!-- Categories Grid Section -->
  <main class="shop-page-wrap">
    <div class="container">
      
      <div class="section-title-wrap">
        <div class="section-pill-tag">
          <i class="fas fa-layer-group"></i> System Breakdown
        </div>
        <h2 class="section-title">EXPLORE BY COMPONENT GROUP</h2>
        <p class="section-subtitle">Select a category below to browse matching replacement parts with filterable fitment</p>
      </div>

      <div class="category-grid categories-grid" style="margin-bottom: 4rem;">
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
              <p class="category-card-desc category-tagline category-description"><?php echo e($cat['description'] ?? 'Browse genuine replacement components.'); ?></p>
              <span class="category-link-text category-explore-btn category-explore">
                Explore Parts (<?php echo (int)$cat['product_count']; ?>) <i class="fas fa-arrow-right arrow"></i>
              </span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- Category Sourcing Guide / Guarantee Strip -->
      <div style="background: white; border: 1px solid var(--border-color); border-radius: 20px; padding: 2.5rem 2rem; box-shadow: var(--shadow-sm);">
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; text-align: center;">
          <div style="display: flex; flex-direction: column; align-items: center;">
            <div style="width: 52px; height: 52px; border-radius: 12px; background: rgba(225, 29, 72, 0.08); color: var(--primary-red); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem;">
              <i class="fas fa-certificate"></i>
            </div>
            <h4 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.35rem;">OEM Standard Certified</h4>
            <p style="font-size: 0.88rem; color: #64748b; line-height: 1.5;">Directly sourced from manufacturer-approved automotive tier-1 suppliers.</p>
          </div>

          <div style="display: flex; flex-direction: column; align-items: center;">
            <div style="width: 52px; height: 52px; border-radius: 12px; background: rgba(225, 29, 72, 0.08); color: var(--primary-red); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem;">
              <i class="fas fa-boxes-packing"></i>
            </div>
            <h4 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.35rem;">10,000+ In Stock</h4>
            <p style="font-size: 0.88rem; color: #64748b; line-height: 1.5;">Stored in our climate-controlled Colombo central logistics hub.</p>
          </div>

          <div style="display: flex; flex-direction: column; align-items: center;">
            <div style="width: 52px; height: 52px; border-radius: 12px; background: rgba(225, 29, 72, 0.08); color: var(--primary-red); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem;">
              <i class="fas fa-screwdriver-wrench"></i>
            </div>
            <h4 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.35rem;">Technical Advice</h4>
            <p style="font-size: 0.88rem; color: #64748b; line-height: 1.5;">Our auto technicians verify fitment before shipping each order.</p>
          </div>
        </div>
      </div>

    </div>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
