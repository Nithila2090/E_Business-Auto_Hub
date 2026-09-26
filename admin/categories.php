<?php
/**
 * AUTO HUB - Admin Categories Management
 */

$adminPageTitle = "Manage Categories";
require_once __DIR__ . '/includes/header.php';

$pdo = get_db();
$error = '';
$msg = '';

$editId = (int)($_GET['edit'] ?? 0);
$editCategory = null;

if ($editId > 0) {
    $stmtEdit = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmtEdit->execute([$editId]);
    $editCategory = $stmtEdit->fetch();
}

// Handle Delete Category
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    // Check if products exist in category
    $prodCount = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $prodCount->execute([$delId]);
    if ($prodCount->fetchColumn() > 0) {
        $error = 'Cannot delete category containing active products. Reassign or delete products first.';
    } else {
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$delId]);
        $msg = 'Category deleted successfully.';
    }
}

// Handle Create / Update Category
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fa-cogs');
    $status = $_POST['status'] ?? 'active';

    if (empty($name)) {
        $error = 'Category name is required.';
    } else {
        if (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        }

        $imagePath = $editCategory['image'] ?? 'images/categories/brake.jpg';
        if (!empty($_FILES['image']['name'])) {
            $uploadRes = handle_image_upload($_FILES['image'], 'categories');
            if ($uploadRes['success']) {
                $imagePath = $uploadRes['file_path'];
            }
        }

        if ($editCategory) {
            $stmtUpd = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, icon = ?, image = ?, status = ? WHERE id = ?");
            $stmtUpd->execute([$name, $slug, $description, $icon, $imagePath, $status, $editCategory['id']]);
            $msg = "Category '$name' updated successfully!";
            $editCategory = null;
        } else {
            $stmtIns = $pdo->prepare("INSERT INTO categories (name, slug, description, icon, image, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtIns->execute([$name, $slug, $description, $icon, $imagePath, $status]);
            $msg = "Category '$name' created successfully!";
        }
    }
}

// Fetch all categories
$categories = $pdo->query("
    SELECT c.*, COUNT(p.id) AS product_count 
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id
    ORDER BY c.id ASC
")->fetchAll();
?>

<?php if (!empty($msg)): ?>
  <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; padding: 0.85rem 1.25rem; border-radius: 8px; font-size: 0.9rem; margin-bottom: 1.5rem;">
    <i class="fas fa-check-circle"></i> <?php echo e($msg); ?>
  </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
  <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; padding: 0.85rem 1.25rem; border-radius: 8px; font-size: 0.9rem; margin-bottom: 1.5rem;">
    <i class="fas fa-exclamation-triangle"></i> <?php echo e($error); ?>
  </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 0.8fr 1.2fr; gap: 2rem;">
  
  <!-- Add/Edit Form -->
  <div class="admin-card">
    <div class="admin-card-header">
      <h2 class="admin-card-title">
        <i class="fas fa-layer-group text-red"></i> <?php echo $editCategory ? 'Edit Category' : 'Add New Category'; ?>
      </h2>
    </div>

    <form action="categories.php<?php echo $editCategory ? '?edit=' . $editCategory['id'] : ''; ?>" method="POST" enctype="multipart/form-data">
      <div class="admin-form-group">
        <label class="admin-label">Category Name *</label>
        <input type="text" name="name" class="admin-input" required placeholder="e.g. Brake System" value="<?php echo e($editCategory['name'] ?? ''); ?>">
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Slug (URL identifier)</label>
        <input type="text" name="slug" class="admin-input" placeholder="e.g. brake-system" value="<?php echo e($editCategory['slug'] ?? ''); ?>">
      </div>

      <div class="admin-form-group">
        <label class="admin-label">FontAwesome Icon Class</label>
        <input type="text" name="icon" class="admin-input" placeholder="e.g. fa-compact-disc" value="<?php echo e($editCategory['icon'] ?? 'fa-cogs'); ?>">
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Category Banner Image</label>
        <?php if (!empty($editCategory['image'])): ?>
          <img src="../<?php echo e($editCategory['image']); ?>" alt="Current Banner" style="width: 100%; height: 80px; object-fit: cover; border-radius: 6px; margin-bottom: 0.5rem;">
        <?php endif; ?>
        <input type="file" name="image" class="admin-input" accept="image/*">
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Description</label>
        <textarea name="description" class="admin-textarea" rows="3" placeholder="Brief summary of parts in this category"><?php echo e($editCategory['description'] ?? ''); ?></textarea>
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Status</label>
        <select name="status" class="admin-select">
          <option value="active" <?php echo (isset($editCategory['status']) && $editCategory['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
          <option value="inactive" <?php echo (isset($editCategory['status']) && $editCategory['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
        </select>
      </div>

      <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem;">
        <button type="submit" class="admin-btn admin-btn-primary" style="flex: 1; justify-content: center;">
          <i class="fas fa-save"></i> <?php echo $editCategory ? 'Save Changes' : 'Create Category'; ?>
        </button>
        <?php if ($editCategory): ?>
          <a href="categories.php" class="admin-btn admin-btn-secondary">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Categories Table -->
  <div class="admin-card">
    <div class="admin-card-header">
      <h2 class="admin-card-title">Existing Categories (<?php echo count($categories); ?>)</h2>
    </div>

    <table class="admin-table">
      <thead>
        <tr>
          <th>Category</th>
          <th>Icon</th>
          <th>Products</th>
          <th>Status</th>
          <th style="text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($categories as $c): ?>
          <tr>
            <td>
              <div style="display: flex; align-items: center; gap: 0.75rem;">
                <img src="../<?php echo e($c['image']); ?>" alt="<?php echo e($c['name']); ?>" style="width: 42px; height: 42px; object-fit: cover; border-radius: 6px;">
                <div>
                  <strong style="color: white;"><?php echo e($c['name']); ?></strong>
                  <div style="font-size: 0.72rem; color: var(--admin-text-muted);"><?php echo e($c['slug']); ?></div>
                </div>
              </div>
            </td>
            <td><i class="fas <?php echo e($c['icon']); ?>" style="color: var(--primary-red);"></i></td>
            <td><strong><?php echo (int)$c['product_count']; ?></strong> parts</td>
            <td>
              <span class="admin-badge <?php echo ($c['status'] === 'active') ? 'admin-badge-success' : 'admin-badge-danger'; ?>">
                <?php echo e($c['status']); ?>
              </span>
            </td>
            <td style="text-align: right;">
              <div style="display: inline-flex; gap: 0.35rem;">
                <a href="categories.php?edit=<?php echo $c['id']; ?>" class="admin-btn admin-btn-secondary admin-btn-sm" title="Edit">
                  <i class="fas fa-edit"></i>
                </a>
                <a href="categories.php?delete=<?php echo $c['id']; ?>" class="admin-btn admin-btn-danger admin-btn-sm" onclick="return confirm('Delete category <?php echo e($c['name']); ?>?')" title="Delete">
                  <i class="fas fa-trash"></i>
                </a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
