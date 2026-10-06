<?php
/**
 * AUTO HUB - Track Order Page
 * Live parcel status tracking and delivery milestones
 */

require_once __DIR__ . '/config/helpers.php';

$pdo = get_db();
$orderQuery = trim($_GET['order_id'] ?? $_GET['order_number'] ?? '');
$order = null;
$error = null;

if (!empty($orderQuery)) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? OR id = ? LIMIT 1");
    $stmt->execute([$orderQuery, is_numeric($orderQuery) ? (int)$orderQuery : 0]);
    $order = $stmt->fetch();

    if ($order) {
        $stmtItems = $pdo->prepare("
            SELECT oi.*, p.name, p.image, p.sku, p.brand 
            FROM order_items oi 
            LEFT JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?
        ");
        $stmtItems->execute([$order['id']]);
        $order['items'] = $stmtItems->fetchAll();

        // Milestone step
        $statuses = ['pending', 'processing', 'shipped', 'delivered'];
        $idx = array_search(strtolower($order['status']), $statuses);
        $order['step'] = ($idx !== false) ? $idx + 1 : 1;
    } else {
        $error = "No order found matching tracking ID \"$orderQuery\". Please check your receipt.";
    }
}

$pageTitle = "Track Order | AUTO HUB – Spare Parts & Accessories";
$pageDescription = "Track your automotive spare parts delivery live across Sri Lanka with real-time parcel status.";

require_once __DIR__ . '/includes/header.php';
?>

  <!-- Banner -->
  <section style="background: linear-gradient(135deg, #070a12 0%, #0f172a 100%); color: white; padding: 3.5rem 0; border-bottom: 3px solid var(--primary-red); text-align: center;">
    <div class="container">
      <h1 style="font-size: 2.5rem; font-weight: 900; color: white; margin-bottom: 0.5rem;">
        TRACK YOUR <span class="text-red">SHIPMENT</span>
      </h1>
      <p style="font-size: 1rem; color: #94a3b8; max-width: 540px; margin: 0 auto;">
        Enter your AUTO HUB Order Tracking ID to view the live dispatch and delivery milestone of your spare parts.
      </p>
    </div>
  </section>

  <!-- Track Order Main Section -->
  <main class="shop-page-wrap">
    <div class="container">
      
      <!-- Order Search Card -->
      <div style="max-width: 650px; margin: 0 auto 2.5rem; background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 2rem; box-shadow: var(--shadow-md);">
        <form action="track-order.php" method="GET" style="display: flex; gap: 0.75rem;">
          <input type="text" name="order_id" class="form-control" placeholder="Enter Order ID (e.g. AH-89421)" required value="<?php echo e($orderQuery); ?>" style="font-size: 1rem; font-weight: 600; text-transform: uppercase;">
          <button type="submit" class="btn btn-primary btn-lg" style="padding: 0.75rem 1.75rem;">
            <i class="fas fa-search"></i> Track
          </button>
        </form>
        
        <div style="margin-top: 1rem; font-size: 0.85rem; color: #64748b; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
          <i class="fas fa-info-circle text-red"></i>
          <span>Enter the tracking order number (e.g. <strong>AUTO-2026-XXXXX</strong>) from your order confirmation or receipt.</span>
        </div>
      </div>

      <!-- Result or Error -->
      <?php if ($error): ?>
        <div style="max-width: 650px; margin: 0 auto; text-align: center; background: #fff1f2; border: 1px solid #fecdd3; border-radius: 12px; padding: 2rem;">
          <i class="fas fa-exclamation-triangle text-red" style="font-size: 2.5rem; margin-bottom: 0.75rem;"></i>
          <h3 style="font-size: 1.25rem; font-weight: 700; color: #9f1239; margin-bottom: 0.5rem;">Order Not Found</h3>
          <p style="color: #475569; font-size: 0.9rem;"><?php echo e($error); ?></p>
        </div>
      <?php elseif ($order): ?>
        <div style="max-width: 850px; margin: 0 auto; background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 2.5rem; box-shadow: var(--shadow-md);">
          
          <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1.25rem; margin-bottom: 2rem;">
            <div>
              <span style="font-size: 0.8rem; text-transform: uppercase; color: #64748b; font-weight: 600;">Tracking Details for:</span>
              <h2 style="font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin: 0.15rem 0;">
                Order <span class="text-red"><?php echo e($order['order_number']); ?></span>
              </h2>
              <div style="font-size: 0.85rem; color: #64748b;">
                Placed on: <strong><?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></strong>
              </div>
            </div>

            <div>
              <?php 
                $st = strtolower($order['status']);
                $badgeBg = '#e0f2fe'; $badgeColor = '#0369a1';
                if ($st === 'shipped') { $badgeBg = '#fef3c7'; $badgeColor = '#b45309'; }
                elseif ($st === 'delivered') { $badgeBg = '#dcfce7'; $badgeColor = '#15803d'; }
                elseif ($st === 'cancelled') { $badgeBg = '#fee2e2'; $badgeColor = '#b91c1c'; }
              ?>
              <span style="background: <?php echo $badgeBg; ?>; color: <?php echo $badgeColor; ?>; padding: 0.45rem 1rem; border-radius: 20px; font-weight: 700; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">
                <i class="fas fa-circle" style="font-size: 0.5rem; vertical-align: middle; margin-right: 0.3rem;"></i>
                <?php echo e($order['status']); ?>
              </span>
            </div>
          </div>

          <!-- Progress Stepper -->
          <div style="margin: 2.5rem 0 3rem;">
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); position: relative; text-align: center;">
              
              <!-- Connecting Line -->
              <div style="position: absolute; top: 22px; left: 12%; right: 12%; height: 4px; background: #e2e8f0; z-index: 1;">
                <div style="height: 100%; background: var(--primary-red); width: <?php echo (($order['step'] - 1) / 3) * 100; ?>%; transition: width 0.4s ease;"></div>
              </div>

              <!-- Step 1 -->
              <div style="position: relative; z-index: 2;">
                <div style="width: 46px; height: 46px; border-radius: 50%; background: <?php echo ($order['step'] >= 1) ? 'var(--primary-red)' : '#e2e8f0'; ?>; color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; font-size: 1.1rem; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                  <i class="fas fa-receipt"></i>
                </div>
                <div style="font-weight: 700; font-size: 0.85rem; color: var(--text-main);">Order Placed</div>
                <div style="font-size: 0.75rem; color: #64748b;">Confirmed</div>
              </div>

              <!-- Step 2 -->
              <div style="position: relative; z-index: 2;">
                <div style="width: 46px; height: 46px; border-radius: 50%; background: <?php echo ($order['step'] >= 2) ? 'var(--primary-red)' : '#e2e8f0'; ?>; color: <?php echo ($order['step'] >= 2) ? 'white' : '#94a3b8'; ?>; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; font-size: 1.1rem; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                  <i class="fas fa-boxes-packing"></i>
                </div>
                <div style="font-weight: 700; font-size: 0.85rem; color: var(--text-main);">Processing</div>
                <div style="font-size: 0.75rem; color: #64748b;">Quality Checked</div>
              </div>

              <!-- Step 3 -->
              <div style="position: relative; z-index: 2;">
                <div style="width: 46px; height: 46px; border-radius: 50%; background: <?php echo ($order['step'] >= 3) ? 'var(--primary-red)' : '#e2e8f0'; ?>; color: <?php echo ($order['step'] >= 3) ? 'white' : '#94a3b8'; ?>; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; font-size: 1.1rem; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                  <i class="fas fa-truck-fast"></i>
                </div>
                <div style="font-weight: 700; font-size: 0.85rem; color: var(--text-main);">Dispatched</div>
                <div style="font-size: 0.75rem; color: #64748b;">In Transit</div>
              </div>

              <!-- Step 4 -->
              <div style="position: relative; z-index: 2;">
                <div style="width: 46px; height: 46px; border-radius: 50%; background: <?php echo ($order['step'] >= 4) ? '#16a34a' : '#e2e8f0'; ?>; color: <?php echo ($order['step'] >= 4) ? 'white' : '#94a3b8'; ?>; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; font-size: 1.1rem; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                  <i class="fas fa-house-chimney-check"></i>
                </div>
                <div style="font-weight: 700; font-size: 0.85rem; color: var(--text-main);">Delivered</div>
                <div style="font-size: 0.75rem; color: #64748b;">Handed Over</div>
              </div>

            </div>
          </div>

          <!-- Order Summary Details -->
          <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 2rem; border-top: 1px solid var(--border-color); padding-top: 2rem;">
            
            <!-- Items in Order -->
            <div>
              <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem;">Items in Package</h3>
              <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <?php foreach ($order['items'] as $item): ?>
                  <div style="display: flex; gap: 0.75rem; align-items: center; background: #f8fafc; padding: 0.75rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <img src="<?php echo e($item['image']); ?>" alt="<?php echo e($item['name']); ?>" style="width: 48px; height: 48px; object-fit: cover; border-radius: 6px;">
                    <div style="flex: 1; font-size: 0.85rem;">
                      <div style="font-weight: 700; color: var(--text-main);"><?php echo e($item['name']); ?></div>
                      <div style="color: #64748b; font-size: 0.78rem;">Qty: <?php echo $item['quantity']; ?> &bull; <?php echo format_price($item['price']); ?></div>
                    </div>
                    <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-main);">
                      <?php echo format_price($item['price'] * $item['quantity']); ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Shipping Information -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem;">
              <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-location-dot text-red"></i> Delivery Destination
              </h3>
              <div style="font-size: 0.88rem; color: #334155; line-height: 1.6;">
                <strong><?php echo e($order['shipping_name']); ?></strong><br>
                <i class="fas fa-phone text-muted" style="font-size: 0.75rem;"></i> <?php echo e($order['shipping_phone']); ?><br>
                <i class="fas fa-map-marker-alt text-muted" style="font-size: 0.75rem;"></i> <?php echo e($order['shipping_address']); ?>, <?php echo e($order['shipping_city']); ?><br>
                <span style="color: #64748b;"><?php echo e($order['shipping_province']); ?></span>
              </div>

              <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid #e2e8f0; font-size: 0.88rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
                  <span>Payment Method:</span>
                  <strong><?php echo e($order['payment_method']); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
                  <span>Payment Status:</span>
                  <?php 
                    $pStmt = $pdo->prepare("SELECT payment_status, transaction_id FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
                    $pStmt->execute([$order['id']]);
                    $payInfo = $pStmt->fetch();
                    $pStatus = $payInfo['payment_status'] ?? 'Pending';
                    $pColor = ($pStatus === 'Paid') ? '#15803d' : (($pStatus === 'Failed') ? '#b91c1c' : '#b45309');
                    $pBg = ($pStatus === 'Paid') ? '#dcfce7' : (($pStatus === 'Failed') ? '#fee2e2' : '#fef3c7');
                  ?>
                  <span style="background: <?php echo $pBg; ?>; color: <?php echo $pColor; ?>; padding: 0.15rem 0.6rem; border-radius: 12px; font-weight: 700; font-size: 0.78rem; text-transform: uppercase;">
                    <?php echo e($pStatus); ?>
                  </span>
                </div>
                <?php if (!empty($payInfo['transaction_id'])): ?>
                  <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem; font-size: 0.78rem; color: #64748b;">
                    <span>Transaction ID:</span>
                    <span style="font-family: monospace;"><?php echo e($payInfo['transaction_id']); ?></span>
                  </div>
                <?php endif; ?>
                <div style="display: flex; justify-content: space-between; font-weight: 800; font-size: 1rem; color: var(--text-main); margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px dashed #cbd5e1;">
                  <span>Total Amount:</span>
                  <span class="text-red"><?php echo format_price($order['total_amount']); ?></span>
                </div>
              </div>
            </div>

          </div>

        </div>
      <?php endif; ?>

    </div>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
