<?php
/**
 * AUTO HUB - Admin Users & Customers Management
 */

$adminPageTitle = "Manage Users";
require_once __DIR__ . '/includes/header.php';

$pdo = get_db();

// Fetch users with order count
$stmtUsers = $pdo->query("
    SELECT u.*, COUNT(o.id) AS order_count, COALESCE(SUM(o.total_amount), 0) AS total_spent
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id AND o.status != 'cancelled'
    GROUP BY u.id
    ORDER BY u.id ASC
");
$users = $stmtUsers->fetchAll();
?>

<div class="admin-card">
  <div class="admin-card-header">
    <h2 class="admin-card-title"><i class="fas fa-users text-red"></i> Registered Accounts (<?php echo count($users); ?>)</h2>
  </div>

  <table class="admin-table">
    <thead>
      <tr>
        <th style="width: 50px;">ID</th>
        <th>User Details</th>
        <th>Phone</th>
        <th>Role</th>
        <th>Orders Placed</th>
        <th>Total Spent</th>
        <th style="text-align: right;">Joined Date</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><strong style="color: var(--admin-text-muted);">#<?php echo $u['id']; ?></strong></td>
          <td>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
              <div style="width: 36px; height: 36px; border-radius: 50%; background: #1e293b; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-weight: bold;">
                <?php echo strtoupper(substr($u['full_name'], 0, 1)); ?>
              </div>
              <div>
                <strong style="color: white;"><?php echo e($u['full_name']); ?></strong>
                <div style="font-size: 0.78rem; color: var(--admin-text-muted);"><?php echo e($u['email']); ?></div>
              </div>
            </div>
          </td>
          <td><span style="color: #cbd5e1;"><?php echo e($u['phone'] ?: 'N/A'); ?></span></td>
          <td>
            <span class="admin-badge <?php echo ($u['role'] === 'admin') ? 'admin-badge-danger' : 'admin-badge-info'; ?>">
              <?php echo e($u['role']); ?>
            </span>
          </td>
          <td><strong><?php echo (int)$u['order_count']; ?></strong> orders</td>
          <td><strong class="text-red"><?php echo format_price($u['total_spent']); ?></strong></td>
          <td style="text-align: right; color: var(--admin-text-muted); font-size: 0.8rem;"><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
