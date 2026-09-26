<?php
/**
 * AUTO HUB - Admin Order & Payment Management
 * View orders, inspect payment transaction records, and update order/shipping statuses
 */

$adminPageTitle = "Manage Orders";
require_once __DIR__ . '/includes/header.php';

$pdo = get_db();
$msg = '';
$error = '';

// Handle Status Updates
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $orderId = (int)($_POST['order_id'] ?? 0);

    // Update Order Status
    if (isset($_POST['update_status'])) {
        $newStatus = trim($_POST['status'] ?? 'Pending Payment');
        $allowedStatuses = ['Pending Payment', 'pending', 'Processing', 'processing', 'Confirmed', 'confirmed', 'Shipped', 'shipped', 'Delivered', 'delivered', 'Cancelled', 'cancelled', 'Payment Failed'];
        
        if ($orderId > 0 && in_array($newStatus, $allowedStatuses, true)) {
            $stmtUpd = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmtUpd->execute([$newStatus, $orderId]);
            $msg = "Order status updated to '" . strtoupper($newStatus) . "' successfully!";
        }
    }

    // Update Payment Status (Admin override / COD completion)
    if (isset($_POST['update_payment_status'])) {
        $newPayStatus = trim($_POST['payment_status'] ?? 'Pending');
        $allowedPayStatuses = ['Pending', 'Paid', 'Failed', 'Cancelled', 'Refunded'];

        if ($orderId > 0 && in_array($newPayStatus, $allowedPayStatuses, true)) {
            $stmtCheck = $pdo->prepare("SELECT id FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
            $stmtCheck->execute([$orderId]);
            $existingPay = $stmtCheck->fetch();

            if ($existingPay) {
                $paidAt = ($newPayStatus === 'Paid') ? date('Y-m-d H:i:s') : null;
                $stmtPayUpd = $pdo->prepare("UPDATE payments SET payment_status = ?, paid_at = COALESCE(paid_at, ?) WHERE id = ?");
                $stmtPayUpd->execute([$newPayStatus, $paidAt, $existingPay['id']]);
            } else {
                $stmtOrd = $pdo->prepare("SELECT total_amount, payment_method FROM orders WHERE id = ?");
                $stmtOrd->execute([$orderId]);
                $ordData = $stmtOrd->fetch();
                if ($ordData) {
                    $stmtPayIns = $pdo->prepare("INSERT INTO payments (order_id, payment_method, amount, payment_status, paid_at, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmtPayIns->execute([$orderId, $ordData['payment_method'], $ordData['total_amount'], $newPayStatus, $newPayStatus === 'Paid' ? date('Y-m-d H:i:s') : null]);
                }
            }

            // If payment marked as Paid, set order to Confirmed if still in pending
            if ($newPayStatus === 'Paid') {
                $pdo->prepare("UPDATE orders SET status = 'Confirmed' WHERE id = ? AND status IN ('Pending Payment', 'pending')")->execute([$orderId]);
            }

            $msg = "Payment status updated to '{$newPayStatus}' successfully!";
        }
    }
}

// Active Filter
$statusFilter = $_GET['status'] ?? '';
$where = ["1=1"];
$params = [];

if (!empty($statusFilter)) {
    if ($statusFilter === 'pending') {
        $where[] = "(o.status = 'pending' OR o.status = 'Pending Payment')";
    } else {
        $where[] = "(LOWER(o.status) = ?)";
        $params[] = strtolower($statusFilter);
    }
}

$whereSql = implode(' AND ', $where);
$stmt = $pdo->prepare("
    SELECT o.*, 
           COUNT(DISTINCT oi.id) AS item_count,
           p.payment_status,
           p.transaction_id,
           p.paid_at,
           p.payment_method AS payment_record_method
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN (
        SELECT p1.* FROM payments p1
        INNER JOIN (SELECT order_id, MAX(id) as max_id FROM payments GROUP BY order_id) p2
        ON p1.id = p2.max_id
    ) p ON p.order_id = o.id
    WHERE $whereSql
    GROUP BY o.id
    ORDER BY o.id DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// View specific order modal/details
$viewId = (int)($_GET['view'] ?? 0);
$viewOrder = null;
if ($viewId > 0) {
    $stmtView = $pdo->prepare("
        SELECT o.*, 
               p.payment_status,
               p.transaction_id,
               p.paid_at,
               p.payment_method AS payment_record_method,
               p.id AS payment_id
        FROM orders o
        LEFT JOIN (
            SELECT p1.* FROM payments p1
            INNER JOIN (SELECT order_id, MAX(id) as max_id FROM payments GROUP BY order_id) p2
            ON p1.id = p2.max_id
        ) p ON p.order_id = o.id
        WHERE o.id = ? 
        LIMIT 1
    ");
    $stmtView->execute([$viewId]);
    $viewOrder = $stmtView->fetch();

    if ($viewOrder) {
        $stmtItems = $pdo->prepare("
            SELECT oi.*, p.name, p.brand, p.image, p.sku
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmtItems->execute([$viewId]);
        $viewOrder['items'] = $stmtItems->fetchAll();
    }
}
?>

<?php if (!empty($msg)): ?>
  <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; padding: 0.85rem 1.25rem; border-radius: 8px; font-size: 0.9rem; margin-bottom: 1.5rem;">
    <i class="fas fa-check-circle"></i> <?php echo e($msg); ?>
  </div>
<?php endif; ?>

<!-- Single Order Detailed View Box (If Selected) -->
<?php if ($viewOrder): ?>
  <div class="admin-card" style="border: 2px solid var(--primary-red); margin-bottom: 2rem;">
    <div class="admin-card-header">
      <div>
        <h2 class="admin-card-title">
          <i class="fas fa-receipt text-red"></i> Order Details: <?php echo e($viewOrder['order_number']); ?>
        </h2>
        <div style="font-size: 0.8rem; color: var(--admin-text-muted);">
          Placed on: <?php echo date('d M Y, h:i A', strtotime($viewOrder['created_at'])); ?>
        </div>
      </div>
      <a href="orders.php" class="admin-btn admin-btn-secondary admin-btn-sm"><i class="fas fa-times"></i> Close</a>
    </div>

    <!-- 3-Column Layout: Products, Customer, Payment & Status -->
    <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 2rem;">
      
      <!-- Items in Order -->
      <div>
        <h3 style="font-size: 1rem; color: white; margin-bottom: 1rem;">Products Ordered</h3>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Item</th>
              <th>Unit Price</th>
              <th>Qty</th>
              <th>Line Total</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($viewOrder['items'] as $it): ?>
              <tr>
                <td>
                  <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <img src="../<?php echo e($it['image']); ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;">
                    <div>
                      <strong style="color: white; font-size: 0.85rem;"><?php echo e($it['name']); ?></strong>
                      <div style="font-size: 0.72rem; color: var(--admin-text-muted);">SKU: <?php echo e($it['sku']); ?></div>
                    </div>
                  </div>
                </td>
                <td><?php echo format_price($it['price']); ?></td>
                <td><strong><?php echo $it['quantity']; ?></strong></td>
                <td><strong style="color: white;"><?php echo format_price($it['price'] * $it['quantity']); ?></strong></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div style="display: flex; justify-content: flex-end; margin-top: 1rem; font-size: 0.95rem; gap: 2rem;">
          <div>Delivery Fee: <strong><?php echo format_price($viewOrder['delivery_fee']); ?></strong></div>
          <div>Total Amount: <strong class="text-red" style="font-size: 1.2rem;"><?php echo format_price($viewOrder['total_amount']); ?></strong></div>
        </div>

        <!-- Payment Details Summary Box -->
        <div style="margin-top: 1.5rem; background: #0b1120; border: 1px solid var(--admin-border); border-radius: 10px; padding: 1.25rem;">
          <h4 style="font-size: 0.9rem; color: white; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-shield-alt text-red"></i> Payment Transaction Information
          </h4>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.85rem;">
            <div>
              <span style="color: var(--admin-text-muted);">Payment Method:</span>
              <strong style="color: white; display: block; margin-top: 0.2rem;"><?php echo e($viewOrder['payment_method']); ?></strong>
            </div>
            <div>
              <span style="color: var(--admin-text-muted);">Payment Status:</span>
              <div style="margin-top: 0.2rem;">
                <?php 
                  $ps = $viewOrder['payment_status'] ?? 'Pending';
                  $pBadge = 'admin-badge-warning';
                  if ($ps === 'Paid') $pBadge = 'admin-badge-success';
                  elseif ($ps === 'Failed' || $ps === 'Cancelled') $pBadge = 'admin-badge-danger';
                ?>
                <span class="admin-badge <?php echo $pBadge; ?>" style="font-weight: 700;"><?php echo e($ps); ?></span>
              </div>
            </div>
            <div>
              <span style="color: var(--admin-text-muted);">Transaction ID:</span>
              <strong style="color: #38bdf8; font-family: monospace; display: block; margin-top: 0.2rem;">
                <?php echo !empty($viewOrder['transaction_id']) ? e($viewOrder['transaction_id']) : 'N/A (Pending)'; ?>
              </strong>
            </div>
            <div>
              <span style="color: var(--admin-text-muted);">Paid At:</span>
              <span style="color: white; display: block; margin-top: 0.2rem;">
                <?php echo !empty($viewOrder['paid_at']) ? date('d M Y, h:i A', strtotime($viewOrder['paid_at'])) : 'Pending Settlement'; ?>
              </span>
            </div>
          </div>
          <div style="margin-top: 0.75rem; font-size: 0.72rem; color: var(--admin-text-muted); border-top: 1px dashed var(--admin-border); padding-top: 0.5rem;">
            <i class="fas fa-lock"></i> Sensitive credentials (card numbers, CVV) are never stored in the database.
          </div>
        </div>
      </div>

      <!-- Shipping & Status Update Forms -->
      <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        
        <!-- Customer Destination Box -->
        <div style="background: var(--admin-bg); border: 1px solid var(--admin-border); border-radius: 10px; padding: 1.5rem;">
          <h3 style="font-size: 1rem; color: white; margin-bottom: 0.75rem;">Customer Delivery Destination</h3>
          <div style="font-size: 0.88rem; color: var(--admin-text); line-height: 1.6;">
            <strong><?php echo e($viewOrder['shipping_name']); ?></strong><br>
            Phone: <a href="tel:<?php echo e($viewOrder['shipping_phone']); ?>" style="color: var(--primary-red);"><?php echo e($viewOrder['shipping_phone']); ?></a><br>
            Email: <?php echo e($viewOrder['shipping_email']); ?><br>
            Address: <?php echo e($viewOrder['shipping_address']); ?>, <?php echo e($viewOrder['shipping_city']); ?> <?php echo e($viewOrder['shipping_postal_code']); ?><br>
            Province: <span style="color: var(--admin-text-muted);"><?php echo e($viewOrder['shipping_province']); ?></span>
            <?php if (!empty($viewOrder['delivery_instructions'])): ?>
              <div style="margin-top: 0.5rem; background: #0f172a; padding: 0.5rem; border-radius: 6px; font-size: 0.8rem; border: 1px solid var(--admin-border);">
                <strong>Instructions:</strong> <?php echo e($viewOrder['delivery_instructions']); ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Update Order Status Form -->
        <div style="background: var(--admin-bg); border: 1px solid var(--admin-border); border-radius: 10px; padding: 1.5rem;">
          <form action="orders.php?view=<?php echo $viewOrder['id']; ?>" method="POST">
            <input type="hidden" name="update_status" value="1">
            <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">

            <label class="admin-label">Update Order Status</label>
            <div style="display: flex; gap: 0.75rem; margin-bottom: 1.5rem;">
              <select name="status" class="admin-select" style="flex: 1;">
                <?php 
                  $curSt = strtolower($viewOrder['status']);
                  $statusOptions = [
                    'Pending Payment' => 'Pending Payment',
                    'Processing'      => 'Processing',
                    'Confirmed'       => 'Confirmed',
                    'Shipped'         => 'Shipped (In Transit)',
                    'Delivered'       => 'Delivered',
                    'Cancelled'       => 'Cancelled'
                  ];
                ?>
                <?php foreach ($statusOptions as $val => $lbl): ?>
                  <option value="<?php echo $val; ?>" <?php echo (strtolower($val) === $curSt || ($val === 'Pending Payment' && $curSt === 'pending')) ? 'selected' : ''; ?>>
                    <?php echo $lbl; ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">
                <i class="fas fa-save"></i> Save Status
              </button>
            </div>
          </form>

          <!-- Update Payment Status Form -->
          <form action="orders.php?view=<?php echo $viewOrder['id']; ?>" method="POST">
            <input type="hidden" name="update_payment_status" value="1">
            <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">

            <label class="admin-label">Update Payment Status (Manual Settlement)</label>
            <div style="display: flex; gap: 0.75rem;">
              <select name="payment_status" class="admin-select" style="flex: 1;">
                <?php 
                  $curPay = $viewOrder['payment_status'] ?? 'Pending';
                  $payOptions = ['Pending', 'Paid', 'Failed', 'Cancelled', 'Refunded'];
                ?>
                <?php foreach ($payOptions as $po): ?>
                  <option value="<?php echo $po; ?>" <?php echo ($po === $curPay) ? 'selected' : ''; ?>>
                    <?php echo $po; ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="admin-btn admin-btn-secondary admin-btn-sm">
                <i class="fas fa-money-check-alt"></i> Save Payment
              </button>
            </div>
          </form>
        </div>

      </div>

    </div>
  </div>
<?php endif; ?>

<!-- Orders Table -->
<div class="admin-card">
  <div class="admin-card-header">
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
      <a href="orders.php" class="admin-btn <?php echo empty($statusFilter) ? 'admin-btn-primary' : 'admin-btn-secondary'; ?> admin-btn-sm">All Orders</a>
      <a href="orders.php?status=pending" class="admin-btn <?php echo ($statusFilter === 'pending') ? 'admin-btn-primary' : 'admin-btn-secondary'; ?> admin-btn-sm">Pending</a>
      <a href="orders.php?status=confirmed" class="admin-btn <?php echo ($statusFilter === 'confirmed') ? 'admin-btn-primary' : 'admin-btn-secondary'; ?> admin-btn-sm">Confirmed</a>
      <a href="orders.php?status=processing" class="admin-btn <?php echo ($statusFilter === 'processing') ? 'admin-btn-primary' : 'admin-btn-secondary'; ?> admin-btn-sm">Processing</a>
      <a href="orders.php?status=shipped" class="admin-btn <?php echo ($statusFilter === 'shipped') ? 'admin-btn-primary' : 'admin-btn-secondary'; ?> admin-btn-sm">Shipped</a>
      <a href="orders.php?status=delivered" class="admin-btn <?php echo ($statusFilter === 'delivered') ? 'admin-btn-primary' : 'admin-btn-secondary'; ?> admin-btn-sm">Delivered</a>
      <a href="orders.php?status=cancelled" class="admin-btn <?php echo ($statusFilter === 'cancelled') ? 'admin-btn-primary' : 'admin-btn-secondary'; ?> admin-btn-sm">Cancelled</a>
    </div>
  </div>

  <?php if (empty($orders)): ?>
    <div style="text-align: center; padding: 3rem; color: var(--admin-text-muted);">
      <i class="fas fa-cart-shopping" style="font-size: 3rem; margin-bottom: 1rem;"></i>
      <div>No orders matching current filter.</div>
    </div>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>Order #</th>
          <th>Date</th>
          <th>Customer Info</th>
          <th>Items</th>
          <th>Amount</th>
          <th>Payment Method</th>
          <th>Payment Status</th>
          <th>Transaction ID</th>
          <th>Order Status</th>
          <th style="text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $ord): ?>
          <?php 
            $st = strtolower($ord['status']);
            $badgeCls = 'admin-badge-info';
            if ($st === 'shipped') $badgeCls = 'admin-badge-warning';
            elseif ($st === 'delivered' || $st === 'confirmed') $badgeCls = 'admin-badge-success';
            elseif ($st === 'cancelled') $badgeCls = 'admin-badge-danger';
            elseif ($st === 'pending payment' || $st === 'pending') $badgeCls = 'admin-badge-warning';

            $pst = $ord['payment_status'] ?? 'Pending';
            $pBadgeCls = 'admin-badge-warning';
            if ($pst === 'Paid') $pBadgeCls = 'admin-badge-success';
            elseif ($pst === 'Failed' || $pst === 'Cancelled') $pBadgeCls = 'admin-badge-danger';
          ?>
          <tr>
            <td><strong style="color: white; font-family: monospace; font-size: 0.9rem;"><?php echo e($ord['order_number']); ?></strong></td>
            <td style="font-size: 0.8rem; color: var(--admin-text-muted);"><?php echo date('d M Y, h:i A', strtotime($ord['created_at'])); ?></td>
            <td>
              <strong style="color: white;"><?php echo e($ord['shipping_name']); ?></strong>
              <div style="font-size: 0.75rem; color: var(--admin-text-muted);"><?php echo e($ord['shipping_phone']); ?> &bull; <?php echo e($ord['shipping_city']); ?></div>
            </td>
            <td><span class="admin-badge admin-badge-info"><?php echo (int)$ord['item_count']; ?> item<?php echo $ord['item_count'] > 1 ? 's' : ''; ?></span></td>
            <td><strong class="text-red"><?php echo format_price($ord['total_amount']); ?></strong></td>
            <td><span style="font-size: 0.8rem; font-weight: 600;"><?php echo e($ord['payment_method']); ?></span></td>
            <td><span class="admin-badge <?php echo $pBadgeCls; ?>"><?php echo e($pst); ?></span></td>
            <td>
              <span style="font-family: monospace; font-size: 0.75rem; color: #38bdf8;">
                <?php echo !empty($ord['transaction_id']) ? e($ord['transaction_id']) : '<span style="color: var(--admin-text-muted);">-</span>'; ?>
              </span>
            </td>
            <td><span class="admin-badge <?php echo $badgeCls; ?>"><?php echo e($ord['status']); ?></span></td>
            <td style="text-align: right;">
              <a href="orders.php?view=<?php echo $ord['id']; ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                <i class="fas fa-eye"></i> View &amp; Update
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
