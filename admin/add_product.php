<?php
/**
 * AUTO HUB - Admin Add Product
 */

$adminPageTitle = "Add New Product";
require_once __DIR__ . '/includes/header.php';

$pdo = get_db();
$error = '';

// Fetch categories
$categories = $pdo->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();

// Fetch makes & models for vehicle compatibility assignment
$makes = $pdo->query("SELECT id, name FROM vehicle_makes ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $oldPrice = !empty($_POST['old_price']) ? (float)$_POST['old_price'] : null;
    $stock = (int)($_POST['stock_quantity'] ?? 0);
    $sku = trim($_POST['sku'] ?? '');
    $partNumber = trim($_POST['part_number'] ?? '');
    $shortDesc = trim($_POST['short_description'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $isBestSeller = isset($_POST['is_bestseller']) ? 1 : 0;
    $isNew = isset($_POST['is_new']) ? 1 : 0;

    // Compatibility selections
    $compatMakeId = (int)($_POST['compat_make_id'] ?? 0);
    $compatModelId = (int)($_POST['compat_model_id'] ?? 0);
    $compatYears = $_POST['compat_years'] ?? []; // Array of years

    if (empty($name) || empty($brand) || $categoryId <= 0 || $price <= 0 || empty($sku)) {
        $error = 'Please fill all required fields (Name, Brand, Category, Price, SKU).';
    } else {
        // Check duplicate SKU
        $stmtSku = $pdo->prepare("SELECT id FROM products WHERE sku = ? LIMIT 1");
        $stmtSku->execute([$sku]);
        if ($stmtSku->fetch()) {
            $error = "Product with SKU '$sku' already exists. Please use a unique SKU.";
        } else {
            // Handle Image
            $imagePath = 'images/products/brake_pad.jpg'; // default fallback
            if (!empty($_FILES['image']['name'])) {
                $uploadRes = handle_image_upload($_FILES['image'], 'products');
                if ($uploadRes['success']) {
                    $imagePath = $uploadRes['file_path'];
                } else {
                    $error = $uploadRes['error'];
                }
            }

            if (empty($error)) {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
                // Ensure unique slug
                $slug .= '-' . mt_rand(100, 999);

                $discount = 0;
                if ($oldPrice && $oldPrice > $price) {
                    $discount = round((1 - ($price / $oldPrice)) * 100);
                }

                $stmtInsert = $pdo->prepare("
                    INSERT INTO products (
                        category_id, brand, name, slug, description, short_description,
                        price, old_price, discount, stock_quantity, image, sku, part_number,
                        is_featured, is_bestseller, is_new, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ");
                $stmtInsert->execute([
                    $categoryId, $brand, $name, $slug, $desc, $shortDesc,
                    $price, $oldPrice, $discount, $stock, $imagePath, $sku, $partNumber,
                    $isFeatured, $isBestSeller, $isNew
                ]);
                $productId = (int)$pdo->lastInsertId();

                // Save Vehicle Compatibility
                if ($compatMakeId > 0 && $compatModelId > 0 && !empty($compatYears)) {
                    $stmtCompat = $pdo->prepare("INSERT INTO product_vehicle_compatibility (product_id, make_id, model_id, year) VALUES (?, ?, ?, ?)");
                    foreach ($compatYears as $yr) {
                        $stmtCompat->execute([$productId, $compatMakeId, $compatModelId, (int)$yr]);
                    }
                }

                header('Location: products.php?success=' . urlencode("Product '$name' added successfully!"));
                exit;
            }
        }
    }
}
?>

<div class="admin-card" style="max-width: 900px; margin: 0 auto;">
  <div class="admin-card-header">
    <h2 class="admin-card-title"><i class="fas fa-plus text-red"></i> Create New Spare Part Product</h2>
    <a href="products.php" class="admin-btn admin-btn-secondary admin-btn-sm"><i class="fas fa-arrow-left"></i> Back to List</a>
  </div>

  <?php if (!empty($error)): ?>
    <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; padding: 0.85rem 1.25rem; border-radius: 8px; font-size: 0.9rem; margin-bottom: 1.5rem;">
      <i class="fas fa-exclamation-triangle"></i> <?php echo e($error); ?>
    </div>
  <?php endif; ?>

  <form action="add_product.php" method="POST" enctype="multipart/form-data">
    
    <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 1.5rem;">
      
      <!-- Left Column: Details -->
      <div>
        <div class="admin-form-group">
          <label class="admin-label">Product Name *</label>
          <input type="text" name="name" class="admin-input" required placeholder="e.g. Brembo Premium Ceramic Front Brake Pads" value="<?php echo e($_POST['name'] ?? ''); ?>">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="admin-form-group">
            <label class="admin-label">Brand / Manufacturer *</label>
            <input type="text" name="brand" class="admin-input" required placeholder="e.g. Brembo, Bosch, Denso" value="<?php echo e($_POST['brand'] ?? ''); ?>">
          </div>
          <div class="admin-form-group">
            <label class="admin-label">Category *</label>
            <select name="category_id" class="admin-select" required>
              <option value="">Select Category</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                  <?php echo e($cat['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="admin-form-group">
            <label class="admin-label">SKU Identifier *</label>
            <input type="text" name="sku" class="admin-input" required placeholder="e.g. BP-TY-1005" value="<?php echo e($_POST['sku'] ?? ''); ?>">
          </div>
          <div class="admin-form-group">
            <label class="admin-label">OEM Part Number</label>
            <input type="text" name="part_number" class="admin-input" placeholder="e.g. 04465-02220" value="<?php echo e($_POST['part_number'] ?? ''); ?>">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
          <div class="admin-form-group">
            <label class="admin-label">Price (LKR) *</label>
            <input type="number" step="0.01" name="price" class="admin-input" required placeholder="8500.00" value="<?php echo e($_POST['price'] ?? ''); ?>">
          </div>
          <div class="admin-form-group">
            <label class="admin-label">Old / Regular Price</label>
            <input type="number" step="0.01" name="old_price" class="admin-input" placeholder="9800.00" value="<?php echo e($_POST['old_price'] ?? ''); ?>">
          </div>
          <div class="admin-form-group">
            <label class="admin-label">Stock Quantity *</label>
            <input type="number" name="stock_quantity" class="admin-input" required placeholder="25" value="<?php echo e($_POST['stock_quantity'] ?? '20'); ?>">
          </div>
        </div>

        <div class="admin-form-group">
          <label class="admin-label">Short Summary</label>
          <input type="text" name="short_description" class="admin-input" placeholder="Brief 1-sentence product summary" value="<?php echo e($_POST['short_description'] ?? ''); ?>">
        </div>

        <div class="admin-form-group">
          <label class="admin-label">Detailed Description</label>
          <textarea name="description" class="admin-textarea" rows="4" placeholder="Full product specifications, material details, and warranty terms"><?php echo e($_POST['description'] ?? ''); ?></textarea>
        </div>
      </div>

      <!-- Right Column: Media & Compatibility -->
      <div>
        <div class="admin-form-group">
          <label class="admin-label">Product Image</label>
          <input type="file" name="image" class="admin-input" accept="image/*">
          <small style="font-size: 0.75rem; color: var(--admin-text-muted);">Supports JPG, PNG, WEBP (&lt; 5MB)</small>
        </div>

        <!-- Flags -->
        <div style="background: var(--admin-bg); border: 1px solid var(--admin-border); border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
          <label class="admin-label" style="margin-bottom: 0.75rem;">Display Badges &amp; Features</label>
          <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.85rem;">
            <label style="display: flex; align-items: center; gap: 0.5rem; color: #cbd5e1; cursor: pointer;">
              <input type="checkbox" name="is_featured" value="1" checked> Featured on Homepage
            </label>
            <label style="display: flex; align-items: center; gap: 0.5rem; color: #cbd5e1; cursor: pointer;">
              <input type="checkbox" name="is_bestseller" value="1"> Best Seller Badge
            </label>
            <label style="display: flex; align-items: center; gap: 0.5rem; color: #cbd5e1; cursor: pointer;">
              <input type="checkbox" name="is_new" value="1"> New Arrival Badge
            </label>
          </div>
        </div>

        <!-- Vehicle Compatibility Mapping Section -->
        <div style="background: var(--admin-bg); border: 1px solid var(--admin-border); border-radius: 8px; padding: 1rem;">
          <label class="admin-label" style="color: var(--primary-red);"><i class="fas fa-car"></i> Vehicle Fitment Assignment</label>
          
          <div class="admin-form-group">
            <select name="compat_make_id" id="admin-compat-make" class="admin-select" onchange="loadAdminModels(this.value)">
              <option value="">Select Vehicle Make</option>
              <?php foreach ($makes as $mk): ?>
                <option value="<?php echo $mk['id']; ?>"><?php echo e($mk['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="admin-form-group">
            <select name="compat_model_id" id="admin-compat-model" class="admin-select" disabled>
              <option value="">Select Model</option>
            </select>
          </div>

          <div class="admin-form-group">
            <label class="admin-label" style="font-size: 0.8rem;">Select Compatible Years:</label>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.35rem; font-size: 0.78rem; max-height: 120px; overflow-y: auto; padding: 0.5rem; background: #0f172a; border-radius: 6px;">
              <?php foreach (range(2026, 2012) as $yr): ?>
                <label style="display: flex; align-items: center; gap: 0.3rem; cursor: pointer;">
                  <input type="checkbox" name="compat_years[]" value="<?php echo $yr; ?>" checked> <?php echo $yr; ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

      </div>

    </div>

    <div style="margin-top: 2rem; border-top: 1px solid var(--admin-border); padding-top: 1.5rem; display: flex; gap: 1rem;">
      <button type="submit" class="admin-btn admin-btn-primary" style="padding: 0.75rem 2rem;">
        <i class="fas fa-check"></i> Save &amp; Publish Product
      </button>
      <a href="products.php" class="admin-btn admin-btn-secondary">Cancel</a>
    </div>

  </form>
</div>

<script>
  async function loadAdminModels(makeId) {
    const modelSelect = document.getElementById('admin-compat-model');
    if (!makeId) {
      modelSelect.innerHTML = '<option value="">Select Model</option>';
      modelSelect.disabled = true;
      return;
    }
    try {
      const res = await fetch(`../api/vehicles.php?action=models&make_id=${makeId}`);
      const data = await res.json();
      if (data.success) {
        modelSelect.innerHTML = '<option value="">Select Model</option>';
        data.data.forEach(m => {
          modelSelect.innerHTML += `<option value="${m.id}">${m.name}</option>`;
        });
        modelSelect.disabled = false;
      }
    } catch (e) {
      console.error(e);
    }
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
