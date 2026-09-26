<?php
/**
 * AUTO HUB - Admin Products Management
 */

$adminPageTitle = "Manage Products";
require_once __DIR__ . '/includes/header.php';

$pdo = get_db();

// Filters
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(p.name LIKE ? OR p.brand LIKE ? OR p.sku LIKE ? OR p.part_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $where[] = "p.category_id = ?";
    $params[] = (int)$category;
}

$whereSql = implode(' AND ', $where);
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE $whereSql
    ORDER BY p.id DESC
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Categories for filter
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
?>

<?php if (!empty($_GET['success'])): ?>
  <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; padding: 0.85rem 1.25rem; border-radius: 8px; font-size: 0.9rem; margin-bottom: 1.5rem;">
    <i class="fas fa-check-circle"></i> <?php echo e($_GET['success']); ?>
  </div>
<?php endif; ?>

<div class="admin-card">
  <div class="admin-card-header">
    <div style="display: flex; gap: 1rem; align-items: center; flex: 1;">
      <form action="products.php" method="GET" style="display: flex; gap: 0.75rem; flex: 1; max-width: 500px;">
        <input type="text" name="search" class="admin-input" placeholder="Search by name, SKU, brand..." value="<?php echo e($search); ?>" style="padding: 0.5rem 0.85rem;">
        <select name="category" class="admin-select" onchange="this.form.submit()" style="width: auto; padding: 0.5rem 0.85rem;">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?php echo $cat['id']; ?>" <?php echo ($category == $cat['id']) ? 'selected' : ''; ?>>
              <?php echo e($cat['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="admin-btn admin-btn-secondary admin-btn-sm"><i class="fas fa-search"></i></button>
      </form>
    </div>

    <a href="add_product.php" class="admin-btn admin-btn-primary">
      <i class="fas fa-plus"></i> Add New Product
    </a>
  </div>

  <?php if (empty($products)): ?>
    <div style="text-align: center; padding: 3rem; color: var(--admin-text-muted);">
      <i class="fas fa-box-open" style="font-size: 3rem; margin-bottom: 1rem;"></i>
      <div>No products found matching your search.</div>
    </div>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th style="width: 60px;">Image</th>
          <th>Product Details</th>
          <th>Category</th>
          <th>Brand</th>
          <th>Price</th>
          <th>Stock</th>
          <th>Status</th>
          <th style="text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $prod): ?>
          <tr>
            <td>
              <img src="../<?php echo e($prod['image']); ?>" alt="<?php echo e($prod['name']); ?>" style="width: 48px; height: 48px; object-fit: cover; border-radius: 8px; border: 1px solid var(--admin-border);">
            </td>
            <td>
              <div style="font-weight: 700; color: white; margin-bottom: 0.2rem;"><?php echo e($prod['name']); ?></div>
              <div style="font-size: 0.75rem; color: var(--admin-text-muted);">SKU: <strong><?php echo e($prod['sku']); ?></strong> &bull; OEM: <?php echo e($prod['part_number'] ?: 'N/A'); ?></div>
            </td>
            <td><span style="color: var(--admin-text-muted); font-size: 0.85rem;"><?php echo e($prod['category_name']); ?></span></td>
            <td><strong style="color: #cbd5e1;"><?php echo e($prod['brand']); ?></strong></td>
            <td>
              <strong style="color: white;"><?php echo format_price($prod['price']); ?></strong>
              <?php if ($prod['old_price'] > $prod['price']): ?>
                <div style="font-size: 0.72rem; color: var(--primary-red); text-decoration: line-through;"><?php echo format_price($prod['old_price']); ?></div>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($prod['stock_quantity'] > 10): ?>
                <span class="admin-badge admin-badge-success"><?php echo $prod['stock_quantity']; ?> in stock</span>
              <?php elseif ($prod['stock_quantity'] > 0): ?>
                <span class="admin-badge admin-badge-warning"><?php echo $prod['stock_quantity']; ?> low stock</span>
              <?php else: ?>
                <span class="admin-badge admin-badge-danger">Out of stock</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="admin-badge <?php echo ($prod['status'] === 'active') ? 'admin-badge-success' : 'admin-badge-danger'; ?>">
                <?php echo e($prod['status']); ?>
              </span>
            </td>
            <td style="text-align: right;">
              <div style="display: inline-flex; gap: 0.4rem;">
                <a href="edit_product.php?id=<?php echo $prod['id']; ?>" class="admin-btn admin-btn-secondary admin-btn-sm" title="Edit Product">
                  <i class="fas fa-edit"></i> Edit
                </a>
                <a href="delete_product.php?id=<?php echo $prod['id']; ?>" class="admin-btn admin-btn-danger admin-btn-sm" onclick="return confirm('Are you sure you want to delete this product?')" title="Delete Product">
                  <i class="fas fa-trash"></i>
                </a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
