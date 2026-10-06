<?php
/**
 * AUTO HUB - Vehicles Showcase & Fitment Selector Page
 */

require_once __DIR__ . '/config/helpers.php';

$pdo = get_db();

// 1. Fetch Makes with model counts
$stmtMakes = $pdo->query("
    SELECT vm.*, COUNT(DISTINCT vmd.id) AS model_count
    FROM vehicle_makes vm
    LEFT JOIN vehicle_models vmd ON vmd.make_id = vm.id
    GROUP BY vm.id
    ORDER BY vm.name ASC
");
$makes = $stmtMakes->fetchAll();

// 2. Fetch popular models for each make
$stmtModels = $pdo->query("
    SELECT vm.name AS make_name, vmd.name AS model_name, vmd.id AS model_id
    FROM vehicle_models vmd
    JOIN vehicle_makes vm ON vmd.make_id = vm.id
    ORDER BY vm.name ASC, vmd.name ASC
");
$allModels = $stmtModels->fetchAll();
$modelsByMake = [];
foreach ($allModels as $m) {
    $modelsByMake[$m['make_name']][] = $m['model_name'];
}

$pageTitle = "Select Vehicle | AUTO HUB – Vehicle Fitment & Spare Parts";
$pageDescription = "Find genuine spare parts with 100% verified vehicle fitment for Toyota, BMW, Honda, Nissan, Suzuki, and more at AUTO HUB Sri Lanka.";

require_once __DIR__ . '/includes/header.php';
?>

  <!-- Vehicles Page Hero Banner -->
  <section style="background: linear-gradient(135deg, #070a12 0%, #0f172a 100%); color: white; padding: 4rem 0 3.5rem; border-bottom: 3px solid var(--primary-red); position: relative; overflow: hidden;">
    <div class="container">
      
      <!-- Breadcrumbs -->
      <nav class="breadcrumb-nav" style="margin-bottom: 1.5rem; background: rgba(255, 255, 255, 0.05); padding: 0.5rem 1rem; border-radius: 8px; display: inline-flex; border: 1px solid rgba(255, 255, 255, 0.1);">
        <a href="index.php" style="color: #94a3b8;"><i class="fas fa-home"></i> Home</a>
        <i class="fas fa-chevron-right breadcrumb-separator" style="color: #64748b; font-size: 0.75rem; margin: 0 0.5rem;"></i>
        <span style="color: var(--primary-red); font-weight: 600;">Vehicle Selector</span>
      </nav>

      <div style="text-align: center; max-width: 780px; margin: 0 auto 2.5rem;">
        <div class="hero-badge-pill" style="margin: 0 auto 1rem;">
          <i class="fas fa-shield-alt"></i> 100% Verified OEM Fitment Database
        </div>
        <h1 style="font-size: 2.8rem; font-weight: 900; color: white; margin-bottom: 0.75rem; letter-spacing: -0.5px;">
          SELECT YOUR <span class="text-red">VEHICLE</span>
        </h1>
        <p style="font-size: 1.05rem; color: #94a3b8; line-height: 1.6;">
          Select your vehicle make, model and year to instantly filter our inventory for 100% guaranteed compatible spare parts, brake kits, filters, and accessories.
        </p>
      </div>

      <!-- Vehicle Selector Box -->
      <div class="vehicle-selector-box" style="max-width: 960px; margin: 0 auto; background: rgba(15, 23, 42, 0.95); border: 1px solid #334155; box-shadow: 0 20px 40px rgba(0,0,0,0.5);">
        <div class="selector-header" style="color: white; font-weight: 700; font-size: 1.1rem; margin-bottom: 1.25rem;">
          <i class="fas fa-car text-red"></i> Find Exact Parts for Your Vehicle
        </div>

        <form class="selector-form" action="products.php" method="GET" style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 1rem; align-items: center;">
          
          <div class="select-group" style="position: relative;">
            <i class="fas fa-car" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--primary-red);"></i>
            <select name="make" class="form-select-custom select-make" id="vehicles-page-make" required style="padding-left: 2.5rem; width: 100%;" aria-label="Select Make">
              <option value="">1. Select Make</option>
              <?php foreach ($makes as $mk): ?>
                <option value="<?php echo e($mk['name']); ?>" data-id="<?php echo $mk['id']; ?>">
                  <?php echo e($mk['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="select-group" style="position: relative;">
            <i class="fas fa-car-side" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--primary-red);"></i>
            <select name="model" class="form-select-custom select-model" id="vehicles-page-model" disabled style="padding-left: 2.5rem; width: 100%;" aria-label="Select Model">
              <option value="">2. Select Model</option>
            </select>
          </div>

          <div class="select-group" style="position: relative;">
            <i class="fas fa-calendar-alt" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--primary-red);"></i>
            <select name="year" class="form-select-custom select-year" id="vehicles-page-year" disabled style="padding-left: 2.5rem; width: 100%;" aria-label="Select Year">
              <option value="">3. Select Year</option>
            </select>
          </div>

          <button type="submit" class="btn btn-primary btn-find-parts" style="height: 48px; padding: 0 1.75rem; white-space: nowrap;">
            <i class="fas fa-search"></i> FIND PARTS
          </button>
        </form>
      </div>

    </div>
  </section>

  <!-- Popular Vehicle Brands Grid -->
  <main class="shop-page-wrap">
    <div class="container">
      
      <div class="section-title-wrap" style="text-align: left; margin-bottom: 2.5rem;">
        <div style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--primary-red); font-weight: 700; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.25rem;">
          <i class="fas fa-layer-group"></i> Supported Manufacturers
        </div>
        <h2 class="section-title" style="font-size: 2.2rem;">BROWSE BY VEHICLE BRAND</h2>
        <p class="section-subtitle">Click any automotive manufacturer to explore compatible OEM &amp; aftermarket spare parts</p>
      </div>

      <!-- Vehicle Makes Cards Grid -->
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 4rem;">
        <?php foreach ($makes as $mk): ?>
          <?php 
            $makeName = $mk['name'];
            $popularModels = $modelsByMake[$makeName] ?? [];
            $sampleModels = array_slice($popularModels, 0, 4);
          ?>
          <div style="background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 1.75rem; box-shadow: var(--shadow-sm); transition: all 0.25s ease; display: flex; flex-direction: column; justify-content: space-between;" class="vehicle-brand-card">
            <div>
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <div style="width: 52px; height: 52px; border-radius: 12px; background: rgba(225, 29, 72, 0.08); color: var(--primary-red); display: flex; align-items: center; justify-content: center; font-size: 1.6rem; border: 1px solid rgba(225, 29, 72, 0.15);">
                  <i class="fas fa-car"></i>
                </div>
                <span class="badge badge-bestseller" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">
                  <?php echo (int)$mk['model_count']; ?> Models
                </span>
              </div>

              <h3 style="font-size: 1.35rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.5rem;">
                <?php echo e($makeName); ?>
              </h3>

              <?php if (!empty($sampleModels)): ?>
                <div style="margin-bottom: 1.25rem;">
                  <span style="font-size: 0.78rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; display: block; margin-bottom: 0.35rem;">Popular Models:</span>
                  <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                    <?php foreach ($sampleModels as $modName): ?>
                      <a href="products.php?make=<?php echo urlencode($makeName); ?>&model=<?php echo urlencode($modName); ?>" style="font-size: 0.8rem; background: #f1f5f9; color: var(--text-body); padding: 0.2rem 0.55rem; border-radius: 6px; text-decoration: none; border: 1px solid #e2e8f0; transition: all 0.15s ease;">
                        <?php echo e($modName); ?>
                      </a>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>

            <a href="products.php?make=<?php echo urlencode($makeName); ?>" class="btn btn-outline-dark btn-sm" style="width: 100%; justify-content: center; margin-top: 0.75rem;">
              <i class="fas fa-search"></i> View <?php echo e($makeName); ?> Parts
            </a>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Trust Pillars / Fitment Guarantee -->
      <div style="background: var(--dark-surface); border-radius: 20px; padding: 3rem 2.5rem; color: white; border: 1px solid #1e293b; box-shadow: var(--shadow-lg);">
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 2.5rem; text-align: center;">
          <div>
            <div style="width: 56px; height: 56px; border-radius: 50%; background: rgba(225, 29, 72, 0.15); color: var(--primary-red); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 1rem; border: 1px solid rgba(225, 29, 72, 0.3);">
              <i class="fas fa-check-double"></i>
            </div>
            <h4 style="font-size: 1.15rem; font-weight: 800; color: white; margin-bottom: 0.5rem;">Guaranteed Fitment</h4>
            <p style="font-size: 0.88rem; color: #94a3b8; line-height: 1.5;">Every component in our catalog is mapped to OEM chassis codes and engine specifications.</p>
          </div>

          <div>
            <div style="width: 56px; height: 56px; border-radius: 50%; background: rgba(56, 189, 248, 0.15); color: #38bdf8; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 1rem; border: 1px solid rgba(56, 189, 248, 0.3);">
              <i class="fas fa-truck-fast"></i>
            </div>
            <h4 style="font-size: 1.15rem; font-weight: 800; color: white; margin-bottom: 0.5rem;">Islandwide Delivery</h4>
            <p style="font-size: 0.88rem; color: #94a3b8; line-height: 1.5;">Direct courier dispatch to all 9 provinces with door-to-door delivery tracking.</p>
          </div>

          <div>
            <div style="width: 56px; height: 56px; border-radius: 50%; background: rgba(16, 185, 129, 0.15); color: #34d399; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 1rem; border: 1px solid rgba(16, 185, 129, 0.3);">
              <i class="fas fa-shield-halved"></i>
            </div>
            <h4 style="font-size: 1.15rem; font-weight: 800; color: white; margin-bottom: 0.5rem;">14-Day Returns</h4>
            <p style="font-size: 0.88rem; color: #94a3b8; line-height: 1.5;">Hassle-free replacement guarantee if an item does not fit your vehicle correctly.</p>
          </div>
        </div>
      </div>

    </div>
  </main>

  <style>
    .vehicle-brand-card:hover {
      transform: translateY(-4px);
      border-color: var(--primary-red) !important;
      box-shadow: 0 12px 25px rgba(225, 29, 72, 0.12) !important;
    }
  </style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
