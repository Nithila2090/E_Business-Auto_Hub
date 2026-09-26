<?php
/**
 * AUTO HUB - Admin Edit Product
 */

$adminPageTitle = "Edit Product";
require_once __DIR__ . '/includes/header.php';

$pdo = get_db();
$id = (int)($_GET['id'] ?? 0);
$error = '';

if ($id <= 0) {
    header('Location: products.php');
    exit;
}

// Fetch Product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: products.php');
    exit;
}

// Fetch Categories & Makes
$categories = $pdo->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();
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
    $status = $_POST['status'] ?? 'active';
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $isBestSeller = isset($_POST['is_bestseller']) ? 1 : 0;
    $isNew = isset($_POST['is_new']) ? 1 : 0;

    $compatMakeId = (int)($_POST['compat_make_id'] ?? 0);
    $compatModelId = (int)($_POST['compat_model_id'] ?? 0);
    $compatYears = $_POST['compat_years'] ?? [];

    if (empty($name) || empty($brand) || $categoryId <= 0 || $price <= 0 || empty($sku)) {
        $error = 'Please fill all required fields.';
    } else {
        $imagePath = $product['image'];
        if (!empty($_FILES['image']['name'])) {
            $uploadRes = handle_image_upload($_FILES['image'], 'products');
            if ($uploadRes['success']) {
                $imagePath = $uploadRes['file_path'];
            } else {
                $error = $uploadRes['error'];
            }
        }

        if (empty($error)) {
            $discount = 0;
            if ($oldPrice && $oldPrice > $price) {
                $discount = round((1 - ($price / $oldPrice)) * 100);
            }

            $stmtUpd = $pdo->prepare("
                UPDATE products SET
                    category_id = ?, brand = ?, name = ?, description = ?, short_description = ?,
                    price = ?, old_price = ?, discount = ?, stock_quantity = ?, image = ?,
                    sku = ?, part_number = ?, is_featured = ?, is_bestseller = ?, is_new = ?, status = ?
                WHERE id = ?
            ");
            $stmtUpd->execute([
                $categoryId, $brand, $name, $desc, $shortDesc,
                $price, $oldPrice, $discount, $stock, $imagePath,
                $sku, $partNumber, $isFeatured, $isBestSeller, $isNew, $status, $id
            ]);

            // Add additional compatibility if selected
            if ($compatMakeId > 0 && $compatModelId > 0 && !empty($compatYears)) {
                $stmtCompat = $pdo->prepare("INSERT IGNORE INTO product_vehicle_compatibility (product_id, make_id, model_id, year) VALUES (?, ?, ?, ?)");
                foreach ($compatYears as $yr) {
                    $stmtCompat->execute([$id, $compatMakeId, $compatModelId, (int)$yr]);
                }
            }

            header('Location: products.php?success=' . urlencode("Product updated successfully!"));
            exit;
        }
    }
}

// Existing compatibility list
$stmtExistingCompat = $pdo->prepare("
    SELECT pvc.id AS compat_id, vmk.name AS make, vm.name AS model, pvc.year
    FROM product_vehicle_compatibility pvc
    JOIN vehicle_makes vmk ON pvc.make_id = vmk.id
    JOIN vehicle_models vm ON pvc.model_id = vm.id
    WHERE pvc.product_id = ?
    ORDER BY vmk.name, vm.name, pvc.year DESC
");
$stmtExistingCompat->execute([$id]);
$existingCompat = $stmtExistingCompat->fetchAll();
?>

<div class="admin-card" style="max-width: 900px; margin: 0 auto;">
  <div class="admin-card-header">
    <h2 class="admin-card-title"><i class="fas fa-edit text-red"></i> Edit Product: <?php echo e($product['name']); ?></h2>
    <a href="products.php" class="admin-btn admin-btn-secondary admin-btn-sm"><i class="fas fa-arrow-left"></i> Back to List</a>
  </div>

  <?php if (!empty($error)): ?>
    <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; padding: 0.85rem 1.25rem; border-radius: 8px; font-size: 0.9rem; margin-bottom: 1.5rem;">
      <i class="fas fa-exclamation-triangle"></i> <?php echo e($error); ?>
    </div>
  <?php endif; ?>

  <form action="edit_product.php?id=<?php echo $id; ?>" method="POST" enctype="multipart/form-data">
    
    <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 1.5rem;">
      
      <!-- Left Column -->
      <div>
        <div class="admin-form-group">
          <label class="admin-label">Product Name *</label>
          <input type="text" name="name" class="admin-input" required value="<?php echo e($product['name']); ?>">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="admin-form-group">
            <label class="admin-label">Brand / Manufacturer *</label>
            <input type="text" name="brand" class="admin-input" required value="<?php echo e($product['brand']); ?>">
          </div>
          <div class="admin-form-group">
            <label class="admin-label">Category *</label>
            <select name="category_id" class="admin-select" required>
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo ($product['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                  <?php echo e($cat['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="admin-form-group">
            <label class="admin-label">SKU Identifier *</label>
            <input type="text" name="sku" class="admin-input" required value="<?php echo e($product['sku']); ?>">
          </div>
          <div class="admin-form-group">
            <label class="admin-label">OEM Part Number</label>
            <input type="text" name="part_number" class="admin-input" value="<?php echo e($product['part_number'] ?? ''); ?>">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
          <div class="admin-form-group">
            <label class="admin-label">Price (LKR) *</label>
            <input type="number" step="0.01" name="price" class="admin-input" required value="<?php echo e($product['price']); ?>">
          </div>
          <div class="admin-form-group">
            <label class="admin-label">Old Price</label>
            <input type="number" step="0.01" name="old_price" class="admin-input" value="<?php echo e($product['old_price'] ?? ''); ?>">
          </div>
          <div class="admin-form-group">
            <label class="admin-label">Stock Quantity *</label>
            <input type="number" name="stock_quantity" class="admin-input" required value="<?php echo e($product['stock_quantity']); ?>">
          </div>
        </div>

        <div class="admin-form-group">
          <label class="admin-label">Short Summary</label>
          <input type="text" name="short_description" class="admin-input" value="<?php echo e($product['short_description'] ?? ''); ?>">
        </div>

        <div class="admin-form-group">
          <label class="admin-label">Detailed Description</label>
          <textarea name="description" class="admin-textarea" rows="4"><?php echo e($product['description'] ?? ''); ?></textarea>
        </div>
      </div>

      <!-- Right Column -->
      <div>
        <div class="admin-form-group">
          <label class="admin-label">Current Product Image</label>
          <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.5rem;">
            <img src="../<?php echo e($product['image']); ?>" alt="Current Product" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px; border: 1px solid var(--admin-border);">
            <input type="file" name="image" class="admin-input" accept="image/*">
          </div>
          <small style="font-size: 0.75rem; color: var(--admin-text-muted);">Leave empty to keep existing image</small>
        </div>

        <div style="background: var(--admin-bg); border: 1px solid var(--admin-border); border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
          <div class="admin-form-group">
            <label class="admin-label">Product Status</label>
            <select name="status" class="admin-select">
              <option value="active" <?php echo ($product['status'] === 'active') ? 'selected' : ''; ?>>Active (Visible in Store)</option>
              <option value="inactive" <?php echo ($product['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive (Hidden)</option>
            </select>
          </div>

          <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.85rem;">
            <label style="display: flex; align-items: center; gap: 0.5rem; color: #cbd5e1; cursor: pointer;">
              <input type="checkbox" name="is_featured" value="1" <?php echo (!empty($product['is_featured'])) ? 'checked' : ''; ?>> Featured on Homepage
            </label>
            <label style="display: flex; align-items: center; gap: 0.5rem; color: #cbd5e1; cursor: pointer;">
              <input type="checkbox" name="is_bestseller" value="1" <?php echo (!empty($product['is_bestseller'])) ? 'checked' : ''; ?>> Best Seller Badge
            </label>
            <label style="display: flex; align-items: center; gap: 0.5rem; color: #cbd5e1; cursor: pointer;">
              <input type="checkbox" name="is_new" value="1" <?php echo (!empty($product['is_new'])) ? 'checked' : ''; ?>> New Arrival Badge
            </label>
          </div>
        </div>

        <!-- Add Vehicle Compatibility -->
        <div style="background: var(--admin-bg); border: 1px solid var(--admin-border); border-radius: 8px; padding: 1rem;">
          <label class="admin-label" style="color: var(--primary-red);"><i class="fas fa-plus-circle"></i> Add Vehicle Compatibility</label>
          
          <div class="admin-form-group">
            <select name="compat_make_id" id="admin-compat-make" class="admin-select" onchange="loadAdminModels(this.value)">
              <option value="">Select Make</option>
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
            <label class="admin-label" style="font-size: 0.8rem;">Select Years:</label>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.35rem; font-size: 0.78rem; max-height: 90px; overflow-y: auto; padding: 0.5rem; background: #0f172a; border-radius: 6px;">
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

    <!-- Existing Compatibility Records -->
    <div style="margin-top: 1.5rem; background: var(--admin-bg); border: 1px solid var(--admin-border); border-radius: 8px; padding: 1rem;">
      <h4 style="font-size: 0.9rem; font-weight: 700; color: white; margin-bottom: 0.5rem;">Current Compatibility Fitments (<?php echo count($existingCompat); ?> records)</h4>
      <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
        <?php foreach ($existingCompat as $ec): ?>
          <span style="font-size: 0.78rem; background: #0f172a; border: 1px solid var(--admin-border); padding: 0.2rem 0.6rem; border-radius: 4px; color: #cbd5e1;">
            <?php echo e($ec['make']); ?> <?php echo e($ec['model']); ?> (<?php echo $ec['year']; ?>)
          </span>
        <?php endforeach; ?>
      </div>
    </div>

    <div style="margin-top: 2rem; border-top: 1px solid var(--admin-border); padding-top: 1.5rem; display: flex; gap: 1rem;">
      <button type="submit" class="admin-btn admin-btn-primary" style="padding: 0.75rem 2rem;">
        <i class="fas fa-save"></i> Save Product Changes
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
