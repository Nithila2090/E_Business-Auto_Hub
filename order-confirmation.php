<?php
/**
 * AUTO HUB - Order Confirmation & Verified Payment Status Page
 * Displays live database-verified status: Paid, Pending, Failed, Cancelled, or COD
 *
 * Security:
 * - Reads verified status strictly from MySQL database.
 * - Does NOT mark orders as paid simply based on return_url navigation.
 */

require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/payment.php';

$pdo = get_db();
$orderNumber = trim($_GET['order_id'] ?? $_GET['order_number'] ?? '');
$paymentStatusParam = strtolower(trim($_GET['payment_status'] ?? $_GET['status'] ?? ''));

$order = null;
$payment = null;
$items = [];
$errorMsg = null;

if (!empty($orderNumber)) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? OR id = ? LIMIT 1");
    $stmt->execute([$orderNumber, is_numeric($orderNumber) ? (int)$orderNumber : 0]);
    $order = $stmt->fetch();

    if ($order) {
        // Fetch items
        $stmtItems = $pdo->prepare("
            SELECT oi.*, p.name, p.brand, p.image, p.sku
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmtItems->execute([$order['id']]);
        $items = $stmtItems->fetchAll();

        // Fetch latest payment record from database
        $stmtPay = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $stmtPay->execute([$order['id']]);
        $payment = $stmtPay->fetch();
    } else {
        $errorMsg = "Order \"{$orderNumber}\" was not found in our store database.";
    }
} else {
    $errorMsg = "No order reference specified.";
}

// Database-verified payment and order status
$dbPaymentStatus = $payment['payment_status'] ?? 'Pending';
$paymentMethod = $order['payment_method'] ?? 'Cash on Delivery';
$isOnlineCard = in_array(strtolower($paymentMethod), ['online card payment', 'card', 'payhere']);
$isCOD = in_array(strtolower($paymentMethod), ['cash on delivery', 'cod']);

// Determine effective status
$isPaid = ($dbPaymentStatus === 'Paid');
$isFailed = ($dbPaymentStatus === 'Failed') || ($paymentStatusParam === 'failed');
$isCancelled = ($dbPaymentStatus === 'Cancelled') || ($paymentStatusParam === 'cancelled');
$isPendingOnline = ($isOnlineCard && !$isPaid && !$isFailed && !$isCancelled);

$paymentConfig = get_payment_config();

// Page titles based on status
if ($isPaid) {
    $pageTitle = "Payment Successful | AUTO HUB";
} elseif ($isFailed) {
    $pageTitle = "Payment Failed | AUTO HUB";
} elseif ($isCancelled) {
    $pageTitle = "Payment Cancelled | AUTO HUB";
} elseif ($isPendingOnline) {
    $pageTitle = "Payment Pending | AUTO HUB";
} else {
    $pageTitle = "Order Confirmation | AUTO HUB";
}
$pageDescription = "View your AUTO HUB order invoice summary, payment verification status, and delivery tracking.";

require_once __DIR__ . '/includes/header.php';
?>

  <!-- Main Section -->
  <main class="shop-page-wrap" style="padding: 3rem 0 5rem;">
    <div class="container" style="max-width: 820px; margin: 0 auto;">
      
      <?php if ($errorMsg || !$order): ?>
        <!-- Order Not Found State -->
        <div style="background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 3.5rem 2rem; text-align: center; box-shadow: var(--shadow-sm);">
          <div style="width: 70px; height: 70px; border-radius: 50%; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 1.5rem;">
            <i class="fas fa-exclamation-triangle"></i>
          </div>
          <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.5rem;">Order Not Found</h1>
          <p style="color: #64748b; margin-bottom: 2rem;"><?php echo e($errorMsg); ?></p>
          <div style="display: flex; gap: 1rem; justify-content: center;">
            <a href="products.php" class="btn btn-primary"><i class="fas fa-search"></i> Browse Catalog</a>
            <a href="cart.php" class="btn btn-outline-dark"><i class="fas fa-shopping-cart"></i> View Cart</a>
          </div>
        </div>

      <?php elseif ($isPaid): ?>
        <!-- ===================================================================
             1. PAYMENT SUCCESSFUL (VERIFIED FROM DATABASE)
             =================================================================== -->
        <div style="background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 3rem 2.5rem; box-shadow: var(--shadow-md);" id="confirmation-container">
          
          <div style="text-align: center; margin-bottom: 2.5rem;">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: #dcfce7; color: #16a34a; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto 1.25rem; border: 3px solid #86efac; box-shadow: 0 4px 14px rgba(22, 163, 74, 0.2);">
              <i class="fas fa-check-circle"></i>
            </div>

            <h1 style="font-size: 2.2rem; font-weight: 900; color: var(--text-main); margin-bottom: 0.4rem;">
              Payment Successful
            </h1>
            <p style="color: #64748b; font-size: 1rem; max-width: 520px; margin: 0 auto;">
              Thank you for your payment! Your transaction has been securely verified and your order is now confirmed for dispatch.
            </p>
          </div>

          <!-- Order & Payment Highlights -->
          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.75rem; margin-bottom: 2.5rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1.5rem; text-align: center;">
              
              <div>
                <div style="font-size: 0.78rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.35rem;">Order ID</div>
                <div style="font-size: 1.15rem; font-weight: 800; color: var(--primary-red); font-family: monospace;"><?php echo e($order['order_number']); ?></div>
              </div>

              <div>
                <div style="font-size: 0.78rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.35rem;">Payment Status</div>
                <div>
                  <span style="background: #dcfce7; color: #15803d; padding: 0.3rem 0.8rem; border-radius: 20px; font-weight: 800; font-size: 0.82rem; text-transform: uppercase; display: inline-flex; align-items: center; gap: 0.3rem;">
                    <i class="fas fa-check"></i> Paid
                  </span>
                </div>
              </div>

              <div>
                <div style="font-size: 0.78rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.35rem;">Payment Method</div>
                <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-main);"><?php echo e($order['payment_method']); ?></div>
              </div>

              <div>
                <div style="font-size: 0.78rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.35rem;">Total Amount</div>
                <div style="font-size: 1.25rem; font-weight: 900; color: var(--text-main);"><?php echo format_price($order['total_amount']); ?></div>
              </div>

            </div>

            <?php if (!empty($payment['transaction_id'])): ?>
              <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px dashed #cbd5e1; font-size: 0.82rem; color: #64748b; text-align: center;">
                PayHere Transaction Reference: <strong style="color: var(--text-main); font-family: monospace;"><?php echo e($payment['transaction_id']); ?></strong>
                <?php if (!empty($payment['paid_at'])): ?>
                  &bull; Paid on: <?php echo date('d M Y, h:i A', strtotime($payment['paid_at'])); ?>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Products Breakdown -->
          <div style="margin-bottom: 2.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
              <i class="fas fa-boxes-packing text-red"></i> Order Details (<?php echo count($items); ?> Items)
            </h3>

            <div style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
              <?php foreach ($items as $it): ?>
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid #f1f5f9; gap: 1rem;">
                  <div style="display: flex; align-items: center; gap: 1rem;">
                    <img src="<?php echo e($it['image']); ?>" alt="" style="width: 52px; height: 52px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <div>
                      <strong style="color: var(--text-main); font-size: 0.92rem;"><?php echo e($it['name']); ?></strong>
                      <div style="font-size: 0.78rem; color: #64748b;">SKU: <?php echo e($it['sku']); ?> &bull; Qty: <?php echo $it['quantity']; ?></div>
                    </div>
                  </div>
                  <div style="text-align: right; font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                    <?php echo format_price($it['price'] * $it['quantity']); ?>
                  </div>
                </div>
              <?php endforeach; ?>

              <!-- Subtotal & Delivery Row -->
              <div style="padding: 1rem 1.25rem; background: #f8fafc; font-size: 0.88rem; color: #64748b; display: flex; flex-direction: column; gap: 0.4rem;">
                <div style="display: flex; justify-content: space-between;">
                  <span>Delivery Fee (Islandwide Courier)</span>
                  <span style="font-weight: 600; color: var(--text-main);"><?php echo format_price($order['delivery_fee']); ?></span>
                </div>
                <?php if ($order['discount_amount'] > 0): ?>
                  <div style="display: flex; justify-content: space-between; color: var(--primary-red);">
                    <span>Coupon Discount</span>
                    <span style="font-weight: 600;">- <?php echo format_price($order['discount_amount']); ?></span>
                  </div>
                <?php endif; ?>
                <div style="display: flex; justify-content: space-between; font-size: 1.05rem; font-weight: 800; color: var(--text-main); padding-top: 0.5rem; border-top: 1px solid #e2e8f0;">
                  <span>Total Amount</span>
                  <span class="text-red"><?php echo format_price($order['total_amount']); ?></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Customer & Delivery Information -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2.5rem;">
            
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem;">
              <h4 style="font-size: 0.95rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-map-marker-alt text-red"></i> Delivery Destination
              </h4>
              <div style="font-size: 0.88rem; color: #475569; line-height: 1.6;">
                <strong><?php echo e($order['shipping_name']); ?></strong> &bull; <?php echo e($order['shipping_phone']); ?><br>
                <?php echo e($order['shipping_address']); ?>, <?php echo e($order['shipping_city']); ?> <?php echo e($order['shipping_postal_code']); ?><br>
                <?php echo e($order['shipping_province']); ?>
              </div>
            </div>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem;">
              <h4 style="font-size: 0.95rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-truck-fast text-red"></i> Estimated Delivery
              </h4>
              <div style="font-size: 0.88rem; color: #475569; line-height: 1.6;">
                <strong>Standard Islandwide Courier</strong><br>
                Estimated Arrival: <strong>2 – 3 Business Days</strong><br>
                <span style="color: #16a34a; font-weight: 600;"><i class="fas fa-shield-check"></i> 100% Genuine Auto Parts Guaranteed</span>
              </div>
            </div>

          </div>

          <!-- Bottom Action Buttons -->
          <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="track-order.php?order_id=<?php echo urlencode($order['order_number']); ?>" class="btn btn-primary btn-lg" style="padding: 0.85rem 2rem; font-size: 1rem;">
              <i class="fas fa-receipt"></i> View &amp; Track Order
            </a>
            <a href="products.php" class="btn btn-outline-dark btn-lg" style="padding: 0.85rem 2rem; font-size: 1rem;">
              <i class="fas fa-shopping-bag"></i> Continue Shopping
            </a>
            <a href="profile.php" class="btn btn-outline-dark btn-lg" style="padding: 0.85rem 2rem; font-size: 1rem;">
              <i class="fas fa-user"></i> My Account
            </a>
          </div>

        </div>

      <?php elseif ($isPendingOnline): ?>
        <!-- ===================================================================
             2. PAYMENT PENDING STATE
             =================================================================== -->
        <div style="background: white; border: 1px solid #fef08a; border-radius: 16px; padding: 3rem 2.5rem; box-shadow: var(--shadow-md); text-align: center;" id="pending-box">
          
          <div style="width: 80px; height: 80px; border-radius: 50%; background: #fef9c3; color: #ca8a04; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto 1.5rem; border: 3px solid #fde047;">
            <i class="fas fa-clock" id="pending-icon"></i>
          </div>

          <h1 style="font-size: 2rem; font-weight: 900; color: #854d0e; margin-bottom: 0.5rem;">
            Payment Pending
          </h1>
          <p style="color: #64748b; font-size: 1rem; max-width: 540px; margin: 0 auto 2rem;">
            Your transaction for Order <strong>#<?php echo e($order['order_number']); ?></strong> is currently awaiting confirmation from the PayHere payment gateway.
          </p>

          <!-- Status Card -->
          <div style="background: #fefce8; border: 1px solid #fef08a; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; text-align: left; max-width: 550px; margin-left: auto; margin-right: auto;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; font-size: 0.9rem;">
              <span style="color: #64748b;">Order ID:</span>
              <strong style="color: var(--text-main); font-family: monospace; font-size: 1rem;"><?php echo e($order['order_number']); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; font-size: 0.9rem;">
              <span style="color: #64748b;">Payment Status:</span>
              <span style="background: #fef9c3; color: #a16207; padding: 0.2rem 0.6rem; border-radius: 6px; font-weight: 700; font-size: 0.8rem; text-transform: uppercase;">
                Payment Pending
              </span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; font-size: 0.9rem;">
              <span style="color: #64748b;">Payment Method:</span>
              <span style="font-weight: 600; color: var(--text-main);"><?php echo e($order['payment_method']); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding-top: 0.75rem; border-top: 1px dashed #fef08a; font-size: 1.1rem; font-weight: 800;">
              <span style="color: var(--text-main);">Total Amount:</span>
              <span style="color: var(--primary-red);"><?php echo format_price($order['total_amount']); ?></span>
            </div>
          </div>

          <!-- Status Indicator / Spinner -->
          <div id="polling-indicator" style="font-size: 0.88rem; color: #64748b; margin-bottom: 2rem;">
            <i class="fas fa-spinner fa-spin text-red"></i> Checking payment status in real-time...
          </div>

          <?php if (!empty($paymentConfig['sandbox'])): ?>
            <!-- Local Sandbox Test Simulation Tool -->
            <div style="background: rgba(245, 158, 11, 0.08); border: 1px dashed #f59e0b; border-radius: 12px; padding: 1.25rem; margin-bottom: 2rem; max-width: 550px; margin-left: auto; margin-right: auto; text-align: left;">
              <div style="font-size: 0.82rem; font-weight: 700; color: #b45309; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.4rem;">
                <i class="fas fa-vial"></i> Sandbox Testing Tools:
              </div>
              <p style="font-size: 0.8rem; color: #78350f; margin-bottom: 0.75rem;">
                On localhost, PayHere webhook servers cannot make inbound HTTP calls without ngrok. You can simulate the verified PayHere IPN callback for this order:
              </p>
              <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <button type="button" class="btn btn-sm btn-primary" onclick="simulateSandboxStatus('simulate_success')">
                  <i class="fas fa-check"></i> Simulate Payment Success
                </button>
                <button type="button" class="btn btn-sm btn-outline-dark" onclick="simulateSandboxStatus('simulate_fail')">
                  <i class="fas fa-times"></i> Simulate Failed
                </button>
              </div>
            </div>
          <?php endif; ?>

          <!-- Action Buttons -->
          <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="payment.php?order_id=<?php echo urlencode($order['order_number']); ?>" class="btn btn-primary btn-lg" style="padding: 0.85rem 2rem; font-size: 1rem;">
              <i class="fas fa-credit-card"></i> Open Payment Gateway
            </a>
            <a href="track-order.php?order_id=<?php echo urlencode($order['order_number']); ?>" class="btn btn-outline-dark btn-lg" style="padding: 0.85rem 2rem; font-size: 1rem;">
              <i class="fas fa-truck"></i> Track Order
            </a>
          </div>

        </div>

      <?php elseif ($isFailed): ?>
        <!-- ===================================================================
             3. PAYMENT FAILED STATE
             =================================================================== -->
        <div style="background: white; border: 1px solid #fecdd3; border-radius: 16px; padding: 3rem 2rem; box-shadow: var(--shadow-md); text-align: center;">
          
          <div style="width: 80px; height: 80px; border-radius: 50%; background: #fee2e2; color: #e11d48; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto 1.5rem; border: 3px solid #fda4af;">
            <i class="fas fa-times-circle"></i>
          </div>

          <h1 style="font-size: 2rem; font-weight: 900; color: #9f1239; margin-bottom: 0.5rem;">Payment Failed</h1>
          <p style="color: #64748b; font-size: 1rem; max-width: 520px; margin: 0 auto 2rem;">
            We could not complete your online transaction. No amount was charged to your card. Please try again or choose another payment method.
          </p>

          <!-- Order Summary Card -->
          <div style="background: #fff1f2; border: 1px solid #fecdd3; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; text-align: left; max-width: 550px; margin-left: auto; margin-right: auto;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; font-size: 0.9rem;">
              <span style="color: #64748b;">Order ID:</span>
              <strong style="color: var(--text-main); font-family: monospace; font-size: 1rem;"><?php echo e($order['order_number']); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; font-size: 0.9rem;">
              <span style="color: #64748b;">Payment Status:</span>
              <span style="background: #fee2e2; color: #b91c1c; padding: 0.2rem 0.6rem; border-radius: 6px; font-weight: 700; font-size: 0.8rem; text-transform: uppercase;">
                Payment Failed
              </span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; font-size: 0.9rem;">
              <span style="color: #64748b;">Order Status:</span>
              <span style="background: #fef3c7; color: #b45309; padding: 0.2rem 0.6rem; border-radius: 6px; font-weight: 700; font-size: 0.8rem; text-transform: uppercase;">
                <?php echo e($order['status']); ?>
              </span>
            </div>
            <div style="display: flex; justify-content: space-between; padding-top: 0.75rem; border-top: 1px dashed #fecdd3; font-size: 1.1rem; font-weight: 800;">
              <span style="color: var(--text-main);">Total Payable:</span>
              <span style="color: var(--primary-red);"><?php echo format_price($order['total_amount']); ?></span>
            </div>
          </div>

          <!-- Action Buttons -->
          <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="payment.php?order_id=<?php echo urlencode($order['order_number']); ?>" class="btn btn-primary btn-lg" style="padding: 0.85rem 2rem; font-size: 1rem;">
              <i class="fas fa-redo-alt"></i> Try Again
            </a>
            <a href="cart.php" class="btn btn-outline-dark btn-lg" style="padding: 0.85rem 2rem; font-size: 1rem;">
              <i class="fas fa-shopping-cart"></i> Return to Cart
            </a>
          </div>

        </div>

      <?php elseif ($isCancelled): ?>
        <!-- ===================================================================
             4. PAYMENT CANCELLED STATE
             =================================================================== -->
        <div style="background: white; border: 1px solid #cbd5e1; border-radius: 16px; padding: 3rem 2rem; box-shadow: var(--shadow-md); text-align: center;">
          
          <div style="width: 80px; height: 80px; border-radius: 50%; background: #f1f5f9; color: #64748b; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto 1.5rem; border: 3px solid #cbd5e1;">
            <i class="fas fa-ban"></i>
          </div>

          <h1 style="font-size: 2rem; font-weight: 900; color: #334155; margin-bottom: 0.5rem;">Payment Cancelled</h1>
          <p style="color: #64748b; font-size: 1rem; max-width: 520px; margin: 0 auto 2rem;">
            You have cancelled the online card transaction for Order <strong>#<?php echo e($order['order_number']); ?></strong>. No funds were charged to your account.
          </p>

          <!-- Order Summary Card -->
          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; text-align: left; max-width: 550px; margin-left: auto; margin-right: auto;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; font-size: 0.9rem;">
              <span style="color: #64748b;">Order ID:</span>
              <strong style="color: var(--text-main); font-family: monospace; font-size: 1rem;"><?php echo e($order['order_number']); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; font-size: 0.9rem;">
              <span style="color: #64748b;">Payment Status:</span>
              <span style="background: #e2e8f0; color: #475569; padding: 0.2rem 0.6rem; border-radius: 6px; font-weight: 700; font-size: 0.8rem; text-transform: uppercase;">
                Payment Cancelled
              </span>
            </div>
            <div style="display: flex; justify-content: space-between; padding-top: 0.75rem; border-top: 1px dashed #e2e8f0; font-size: 1.1rem; font-weight: 800;">
              <span style="color: var(--text-main);">Total Amount:</span>
              <span style="color: var(--primary-red);"><?php echo format_price($order['total_amount']); ?></span>
            </div>
          </div>

          <!-- Action Buttons -->
          <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="payment.php?order_id=<?php echo urlencode($order['order_number']); ?>" class="btn btn-primary btn-lg" style="padding: 0.85rem 2rem; font-size: 1rem;">
              <i class="fas fa-credit-card"></i> Retry Payment
            </a>
            <a href="products.php" class="btn btn-outline-dark btn-lg" style="padding: 0.85rem 2rem; font-size: 1rem;">
              <i class="fas fa-shopping-bag"></i> Continue Shopping
            </a>
          </div>

        </div>

      <?php else: ?>
        <!-- ===================================================================
             5. CASH ON DELIVERY (COD) ORDER PLACED STATE
             =================================================================== -->
        <div style="background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 3rem 2.5rem; box-shadow: var(--shadow-md);">
          
          <div style="text-align: center; margin-bottom: 2.5rem;">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: #dcfce7; color: #16a34a; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto 1.25rem; border: 3px solid #86efac; box-shadow: 0 4px 14px rgba(22, 163, 74, 0.2);">
              <i class="fas fa-check-circle"></i>
            </div>

            <h1 style="font-size: 2.2rem; font-weight: 900; color: var(--text-main); margin-bottom: 0.4rem;">
              Order Placed Successfully
            </h1>
            <p style="color: #64748b; font-size: 1rem; max-width: 520px; margin: 0 auto;">
              Thank you for shopping with AUTO HUB! Your Cash on Delivery order is now being processed for islandwide courier delivery.
            </p>
          </div>

          <!-- Order & Payment Highlights -->
          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.75rem; margin-bottom: 2.5rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1.5rem; text-align: center;">
              
              <div>
                <div style="font-size: 0.78rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.35rem;">Order ID</div>
                <div style="font-size: 1.15rem; font-weight: 800; color: var(--primary-red); font-family: monospace;"><?php echo e($order['order_number']); ?></div>
              </div>

              <div>
                <div style="font-size: 0.78rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.35rem;">Payment Status</div>
                <div>
                  <span style="background: #fef3c7; color: #b45309; padding: 0.3rem 0.8rem; border-radius: 20px; font-weight: 800; font-size: 0.82rem; text-transform: uppercase;">
                    <i class="fas fa-money-bill-wave"></i> Pending (COD)
                  </span>
                </div>
              </div>

              <div>
                <div style="font-size: 0.78rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.35rem;">Payment Method</div>
                <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-main);">Cash on Delivery</div>
              </div>

              <div>
                <div style="font-size: 0.78rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.35rem;">Total Amount</div>
                <div style="font-size: 1.25rem; font-weight: 900; color: var(--text-main);"><?php echo format_price($order['total_amount']); ?></div>
              </div>

            </div>
          </div>

          <!-- Order Summary Details -->
          <div style="margin-bottom: 2.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
              <i class="fas fa-boxes-packing text-red"></i> Order Details (<?php echo count($items); ?> Items)
            </h3>

            <div style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
              <?php foreach ($items as $it): ?>
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid #f1f5f9; gap: 1rem;">
                  <div style="display: flex; align-items: center; gap: 1rem;">
                    <img src="<?php echo e($it['image']); ?>" alt="" style="width: 52px; height: 52px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <div>
                      <strong style="color: var(--text-main); font-size: 0.92rem;"><?php echo e($it['name']); ?></strong>
                      <div style="font-size: 0.78rem; color: #64748b;">SKU: <?php echo e($it['sku']); ?> &bull; Qty: <?php echo $it['quantity']; ?></div>
                    </div>
                  </div>
                  <div style="text-align: right; font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                    <?php echo format_price($it['price'] * $it['quantity']); ?>
                  </div>
                </div>
              <?php endforeach; ?>

              <div style="padding: 1rem 1.25rem; background: #f8fafc; font-size: 0.88rem; color: #64748b; display: flex; flex-direction: column; gap: 0.4rem;">
                <div style="display: flex; justify-content: space-between;">
                  <span>Delivery Fee</span>
                  <span style="font-weight: 600; color: var(--text-main);"><?php echo format_price($order['delivery_fee']); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 1.05rem; font-weight: 800; color: var(--text-main); padding-top: 0.5rem; border-top: 1px solid #e2e8f0;">
                  <span>Total Payable to Courier</span>
                  <span class="text-red"><?php echo format_price($order['total_amount']); ?></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Customer and Delivery Destination -->
          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; margin-bottom: 2.5rem;">
            <h4 style="font-size: 0.95rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
              <i class="fas fa-map-marker-alt text-red"></i> Delivery Destination
            </h4>
            <div style="font-size: 0.88rem; color: #475569; line-height: 1.6;">
              <strong><?php echo e($order['shipping_name']); ?></strong> &bull; <?php echo e($order['shipping_phone']); ?><br>
              <?php echo e($order['shipping_address']); ?>, <?php echo e($order['shipping_city']); ?> <?php echo e($order['shipping_postal_code']); ?><br>
              <?php echo e($order['shipping_province']); ?>
            </div>
          </div>

          <!-- Bottom Action Buttons -->
          <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="track-order.php?order_id=<?php echo urlencode($order['order_number']); ?>" class="btn btn-primary btn-lg" style="padding: 0.85rem 2rem; font-size: 1rem;">
              <i class="fas fa-receipt"></i> View &amp; Track Order
            </a>
            <a href="products.php" class="btn btn-outline-dark btn-lg" style="padding: 0.85rem 2rem; font-size: 1rem;">
              <i class="fas fa-shopping-bag"></i> Continue Shopping
            </a>
            <a href="profile.php" class="btn btn-outline-dark btn-lg" style="padding: 0.85rem 2rem; font-size: 1rem;">
              <i class="fas fa-user"></i> My Account
            </a>
          </div>

        </div>
      <?php endif; ?>

    </div>
  </main>

  <!-- Live Payment Polling & Simulation Scripts -->
  <script>
    const orderNum = <?php echo json_encode($orderNumber); ?>;
    const isPending = <?php echo ($isPendingOnline ? 'true' : 'false'); ?>;

    // Real-time status polling for pending card payments
    if (isPending && orderNum) {
      let pollCount = 0;
      const maxPolls = 8;
      const pollInterval = setInterval(async () => {
        pollCount++;
        try {
          const res = await fetch(`api/payment-verify.php?order_id=${encodeURIComponent(orderNum)}`);
          const data = await res.json();
          if (data.success && data.payment_status === 'Paid') {
            clearInterval(pollInterval);
            if (typeof Store !== 'undefined' && Store.showToast) {
              Store.showToast('Payment Verified! Updating invoice...', 'success');
            }
            window.location.href = `order-confirmation.php?order_id=${encodeURIComponent(orderNum)}`;
          }
        } catch (e) {
          console.error(e);
        }

        if (pollCount >= maxPolls) {
          clearInterval(pollInterval);
          const indicator = document.getElementById('polling-indicator');
          if (indicator) {
            indicator.innerHTML = '<i class="fas fa-info-circle"></i> Awaiting gateway confirmation. Refresh page or track order status below.';
          }
        }
      }, 2500);
    }

    // Sandbox Simulator Trigger
    async function simulateSandboxStatus(action) {
      if (!orderNum) return;
      try {
        const res = await fetch(`api/payment-verify.php?action=${action}&order_id=${encodeURIComponent(orderNum)}`);
        const data = await res.json();
        if (data.success) {
          window.location.href = `order-confirmation.php?order_id=${encodeURIComponent(orderNum)}`;
        } else {
          alert(data.message || 'Simulation failed');
        }
      } catch (err) {
        console.error(err);
      }
    }
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
