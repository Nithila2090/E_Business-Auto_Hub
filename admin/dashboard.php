<?php
/**
 * AUTO HUB - Admin Dashboard Overview
 */

$adminPageTitle = "Dashboard Overview";
require_once __DIR__ . '/includes/header.php';

$pdo = get_db();

// Metrics calculations
$totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status != 'cancelled'")->fetchColumn();
$totalOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
$totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();

// Low stock items (stock <= 10)
$stmtLowStock = $pdo->query("
    SELECT p.id, p.name, p.brand, p.stock_quantity, p.price, p.image, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.stock_quantity <= 10 AND p.status = 'active'
    ORDER BY p.stock_quantity ASC
    LIMIT 6
");
$lowStockProducts = $stmtLowStock->fetchAll();

// Recent orders
$stmtRecentOrders = $pdo->query("
    SELECT o.*, COUNT(oi.id) AS items_count
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    GROUP BY o.id
    ORDER BY o.id DESC
    LIMIT 6
");
$recentOrders = $stmtRecentOrders->fetchAll();
?>

<!-- Metric Cards Grid -->
<div class="metric-grid">
  
  <div class="metric-card">
    <div class="metric-icon-box" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
      <i class="fas fa-money-bill-trend-up"></i>
    </div>
    <div>
      <div class="metric-value"><?php echo format_price($totalRevenue); ?></div>
      <div class="metric-label">Total Revenue (LKR)</div>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-icon-box" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8;">
      <i class="fas fa-cart-shopping"></i>
    </div>
    <div>
      <div class="metric-value"><?php echo $totalOrders; ?></div>
      <div class="metric-label">Total Orders</div>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-icon-box" style="background: rgba(225, 29, 72, 0.15); color: var(--primary-red);">
      <i class="fas fa-boxes-stacked"></i>
    </div>
    <div>
      <div class="metric-value"><?php echo $totalProducts; ?></div>
      <div class="metric-label">Active Parts in Catalog</div>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-icon-box" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">
      <i class="fas fa-users"></i>
    </div>
    <div>
      <div class="metric-value"><?php echo $totalCustomers; ?></div>
      <div class="metric-label">Registered Customers</div>
    </div>
  </div>

</div>

<!-- Main Dashboard Grid -->
<div style="display: grid; grid-template-columns: 1.25fr 0.75fr; gap: 2rem;">
  
  <!-- Recent Orders Table -->
  <div class="admin-card">
    <div class="admin-card-header">
      <h2 class="admin-card-title"><i class="fas fa-cart-shopping text-red"></i> Recent Customer Orders</h2>
      <a href="orders.php" class="admin-btn admin-btn-secondary admin-btn-sm">View All Orders</a>
    </div>

    <?php if (empty($recentOrders)): ?>
      <div style="text-align: center; padding: 2rem; color: var(--admin-text-muted);">No orders placed yet.</div>
    <?php else: ?>
      <table class="admin-table">
        <thead>
          <tr>
            <th>Order Number</th>
            <th>Customer</th>
            <th>Total</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentOrders as $ord): ?>
            <?php 
              $st = strtolower($ord['status']);
              $badgeCls = 'admin-badge-info';
              if ($st === 'shipped') $badgeCls = 'admin-badge-warning';
              elseif ($st === 'delivered') $badgeCls = 'admin-badge-success';
              elseif ($st === 'cancelled') $badgeCls = 'admin-badge-danger';
            ?>
            <tr>
              <td>
                <strong><?php echo e($ord['order_number']); ?></strong>
                <div style="font-size: 0.75rem; color: var(--admin-text-muted);"><?php echo date('d M, h:i A', strtotime($ord['created_at'])); ?></div>
              </td>
              <td>
                <div><?php echo e($ord['shipping_name']); ?></div>
                <div style="font-size: 0.75rem; color: var(--admin-text-muted);"><?php echo e($ord['shipping_city']); ?></div>
              </td>
              <td><strong><?php echo format_price($ord['total_amount']); ?></strong></td>
              <td><span class="admin-badge <?php echo $badgeCls; ?>"><?php echo e($ord['status']); ?></span></td>
              <td>
                <a href="orders.php?view=<?php echo $ord['id']; ?>" class="admin-btn admin-btn-secondary admin-btn-sm">Manage</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- Low Stock Alert Box -->
  <div class="admin-card">
    <div class="admin-card-header">
      <h2 class="admin-card-title"><i class="fas fa-triangle-exclamation" style="color: #fbbf24;"></i> Low Stock Alerts</h2>
      <a href="products.php" class="admin-btn admin-btn-secondary admin-btn-sm">Catalog</a>
    </div>

    <?php if (empty($lowStockProducts)): ?>
      <div style="text-align: center; padding: 2rem; color: var(--admin-text-muted);">
        <i class="fas fa-circle-check" style="font-size: 2rem; color: #34d399; margin-bottom: 0.5rem;"></i>
        <div>All inventory levels are healthy!</div>
      </div>
    <?php else: ?>
      <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        <?php foreach ($lowStockProducts as $lp): ?>
          <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: var(--admin-bg); border: 1px solid var(--admin-border); border-radius: 8px;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
              <img src="../<?php echo e($lp['image']); ?>" alt="<?php echo e($lp['name']); ?>" style="width: 42px; height: 42px; object-fit: cover; border-radius: 6px;">
              <div>
                <div style="font-weight: 600; font-size: 0.85rem; color: white;"><?php echo e($lp['name']); ?></div>
                <div style="font-size: 0.75rem; color: var(--admin-text-muted);"><?php echo e($lp['brand']); ?> &bull; <?php echo format_price($lp['price']); ?></div>
              </div>
            </div>
            <div>
              <span class="admin-badge admin-badge-danger"><?php echo (int)$lp['stock_quantity']; ?> left</span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
