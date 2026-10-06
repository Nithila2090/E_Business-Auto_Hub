<?php
/**
 * AUTO HUB - Secure Online Card Payment Gateway Page
 * Uses official PayHere Checkout API (Hosted & Popup SDK Integration)
 *
 * Security:
 * - NO card numbers, CVV, or PINs are collected on AutoHub.
 * - Customer enters card details securely directly on PayHere's hosted gateway.
 * - Hash calculated server-side in PHP with secret key.
 */

require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/payment.php';

$pdo = get_db();
$orderNumber = trim($_GET['order_id'] ?? $_GET['order_number'] ?? '');

$order = null;
$items = [];
$payment = null;
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

        // Fetch latest payment record
        $stmtPay = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $stmtPay->execute([$order['id']]);
        $payment = $stmtPay->fetch();

        // If order is already paid, redirect to confirmation
        if ($payment && $payment['payment_status'] === 'Paid') {
            header('Location: order-confirmation.php?order_id=' . urlencode($order['order_number']));
            exit;
        }
    } else {
        $errorMsg = "Order \"{$orderNumber}\" was not found in our records.";
    }
} else {
    $errorMsg = "No order reference was provided for payment processing.";
}

$paymentConfig = get_payment_config();
$payherePayload = [];
if ($order) {
    $payherePayload = build_payhere_checkout_payload($order, [
        'shipping_name'    => $order['shipping_name'],
        'shipping_email'   => $order['shipping_email'],
        'shipping_phone'   => $order['shipping_phone'],
        'shipping_address' => $order['shipping_address'],
        'shipping_city'    => $order['shipping_city']
    ]);

    log_payhere_event('PAYMENT_REQUEST_RENDERED', [
        'order_id'       => $order['order_number'],
        'amount'         => $payherePayload['amount'],
        'currency'       => $payherePayload['currency'],
        'merchant_id'    => $payherePayload['merchant_id'],
        'generated_hash' => $payherePayload['hash'],
        'gateway_url'    => $paymentConfig['gateway_url'],
        'notify_url'     => $payherePayload['notify_url'],
        'return_url'     => $payherePayload['return_url'],
        'cancel_url'     => $payherePayload['cancel_url']
    ]);
}

$pageTitle = "Secure Card Payment | AUTO HUB";
$pageDescription = "Complete your online card payment securely through PayHere for AutoHub automotive parts.";

require_once __DIR__ . '/includes/header.php';
?>

  <!-- Main Payment Section with AutoHub Dark Automotive Theme -->
  <main class="shop-page-wrap" style="background: var(--dark-bg); min-height: 80vh; padding: 3rem 0 5rem;">
    <div class="container" style="max-width: 900px; margin: 0 auto;">
      
      <?php if ($errorMsg || !$order): ?>
        <!-- Error State -->
        <div style="background: var(--dark-surface); border: 1px solid var(--dark-border); border-radius: 16px; padding: 3.5rem 2rem; text-align: center; box-shadow: var(--shadow-dark);">
          <div style="width: 70px; height: 70px; border-radius: 50%; background: rgba(225, 29, 72, 0.15); color: var(--primary-red); display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 1.5rem;">
            <i class="fas fa-exclamation-triangle"></i>
          </div>
          <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-white); margin-bottom: 0.5rem;">Payment Session Expired or Not Found</h1>
          <p style="color: var(--text-light); margin-bottom: 2rem;"><?php echo e($errorMsg); ?></p>
          <div style="display: flex; gap: 1rem; justify-content: center;">
            <a href="checkout.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Return to Checkout</a>
            <a href="products.php" class="btn btn-outline-dark" style="color: var(--text-white); border-color: var(--dark-border);"><i class="fas fa-search"></i> Browse Catalog</a>
          </div>
        </div>

      <?php else: ?>
        
        <!-- Header Title -->
        <div style="text-align: center; margin-bottom: 2.5rem;">
          <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(225, 29, 72, 0.12); color: var(--primary-red); padding: 0.4rem 1.2rem; border-radius: 30px; font-weight: 700; font-size: 0.85rem; margin-bottom: 1rem; border: 1px solid rgba(225, 29, 72, 0.25);">
            <i class="fas fa-shield-alt"></i> 256-BIT SSL ENCRYPTED GATEWAY
          </div>
          <h1 style="font-size: 2.4rem; font-weight: 900; color: var(--text-white); margin-bottom: 0.5rem; letter-spacing: -0.5px;">
            COMPLETE YOUR <span class="text-red">PAYMENT</span>
          </h1>
          <p style="color: var(--text-light); font-size: 1rem; max-width: 550px; margin: 0 auto;">
            Review your order summary and proceed to the PayHere secure payment portal to complete your transaction with Visa or Mastercard.
          </p>
        </div>

        <?php if (!empty($paymentConfig['sandbox'])): ?>
          <!-- Sandbox Diagnostic Banner -->
          <div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; color: #fde047;">
            <div style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.88rem;">
              <i class="fas fa-vial" style="font-size: 1.3rem; color: #f59e0b;"></i>
              <div>
                <strong>Sandbox Testing Mode Active</strong> &bull; Merchant ID: <code style="background: rgba(0,0,0,0.4); padding: 0.1rem 0.4rem; border-radius: 4px; color: #fff;"><?php echo e($paymentConfig['merchant_id']); ?></code>
                <div style="font-size: 0.78rem; opacity: 0.85; margin-top: 0.15rem;">Official PayHere Sandbox test cards are supported.</div>
              </div>
            </div>
            <span style="background: #f59e0b; color: #000; font-size: 0.7rem; font-weight: 800; padding: 0.2rem 0.6rem; border-radius: 20px; text-transform: uppercase;">SANDBOX</span>
          </div>

          <?php if ($paymentConfig['merchant_secret'] === '4TXi14jUf1G4VzC1l5e4P521r5X0Y93k23v5K87p1q4=' && $paymentConfig['merchant_id'] !== '1211149'): ?>
            <!-- Warning: Demo Secret in Use -->
            <div style="background: rgba(225, 29, 72, 0.15); border: 1px solid var(--primary-red); border-radius: 12px; padding: 1.1rem 1.25rem; margin-bottom: 2rem; color: #fca5a5; font-size: 0.88rem; line-height: 1.6;">
              <div style="font-weight: 800; color: #fff; font-size: 0.95rem; margin-bottom: 0.35rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-exclamation-triangle text-red"></i> Action Required: Configure Merchant Secret for ID <?php echo e($paymentConfig['merchant_id']); ?>
              </div>
              <div>
                Your project is currently using the demo placeholder secret key. Because PayHere validates the checksum using the <strong>actual secret generated for Merchant ID <?php echo e($paymentConfig['merchant_id']); ?></strong> in your PayHere portal, PayHere will return <em>"Unauthorized payment request"</em> until you paste your real secret into your <code>.env</code> file or <code>config/payhere.php</code>.
              </div>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <div class="cart-layout" style="grid-template-columns: 1.2fr 0.8fr; gap: 2rem;">
          
          <!-- Left Column: Payment Gateway Action Card -->
          <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <!-- Gateway Box -->
            <div style="background: var(--dark-surface); border: 1px solid var(--dark-border); border-radius: 16px; padding: 2rem; box-shadow: var(--shadow-dark);">
              
              <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--dark-border); padding-bottom: 1.25rem; margin-bottom: 1.5rem;">
                <div>
                  <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-light); font-weight: 700; letter-spacing: 0.5px;">Selected Payment Method</div>
                  <div style="font-size: 1.2rem; font-weight: 800; color: var(--text-white); display: flex; align-items: center; gap: 0.5rem; margin-top: 0.25rem;">
                    <i class="fas fa-credit-card text-red"></i> Online Card Payment
                  </div>
                </div>
                <div style="text-align: right;">
                  <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-size: 0.78rem; font-weight: 800; padding: 0.25rem 0.75rem; border-radius: 20px; border: 1px solid rgba(16, 185, 129, 0.3);">
                    <i class="fas fa-shield-halved"></i> SECURE GATEWAY
                  </span>
                </div>
              </div>

              <!-- Supported Cards Row -->
              <div style="background: var(--dark-card); border: 1px solid var(--dark-border); border-radius: 12px; padding: 1.25rem; margin-bottom: 1.75rem;">
                <div style="font-size: 0.82rem; color: var(--text-light); margin-bottom: 0.75rem; font-weight: 600;">
                  Supported Cards &amp; Channels via PayHere Sri Lanka:
                </div>
                <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                  <span style="background: #ffffff; color: #1a1f71; font-weight: 900; font-size: 0.9rem; padding: 0.3rem 0.8rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.4rem;">
                    <i class="fab fa-cc-visa" style="font-size: 1.2rem; color: #1a1f71;"></i> VISA
                  </span>
                  <span style="background: #ffffff; color: #eb001b; font-weight: 900; font-size: 0.9rem; padding: 0.3rem 0.8rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.4rem;">
                    <i class="fab fa-cc-mastercard" style="font-size: 1.2rem; color: #eb001b;"></i> Mastercard
                  </span>
                  <span style="background: #ffffff; color: #0070ba; font-weight: 900; font-size: 0.9rem; padding: 0.3rem 0.8rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.4rem;">
                    <i class="fab fa-cc-amex" style="font-size: 1.2rem; color: #0070ba;"></i> AMEX
                  </span>
                  <span style="background: #ffffff; color: #0f172a; font-weight: 800; font-size: 0.82rem; padding: 0.3rem 0.8rem; border-radius: 6px;">
                    eZ Cash / Genie
                  </span>
                </div>
              </div>

              <!-- Security Notice -->
              <div style="background: rgba(225, 29, 72, 0.08); border: 1px solid rgba(225, 29, 72, 0.2); border-radius: 12px; padding: 1.25rem; margin-bottom: 2rem; font-size: 0.85rem; color: #cbd5e1; line-height: 1.6;">
                <div style="font-weight: 700; color: #fff; margin-bottom: 0.3rem; display: flex; align-items: center; gap: 0.4rem;">
                  <i class="fas fa-lock text-red"></i> 100% Secure Hosted Checkout
                </div>
                You will be seamlessly connected to PayHere's 256-bit encrypted checkout gateway. AutoHub does not collect, store, or process your raw card details.
              </div>

              <!-- Official PayHere Hosted Form -->
              <form id="payhere-checkout-form" method="POST" action="<?php echo e($paymentConfig['gateway_url']); ?>">
                <?php foreach ($payherePayload as $key => $val): ?>
                  <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e($val); ?>">
                <?php endforeach; ?>

                <button type="submit" id="btn-payhere-submit" class="btn btn-primary btn-block btn-lg" style="padding: 1.15rem; font-size: 1.15rem; font-weight: 800; border-radius: 12px; box-shadow: var(--shadow-red); display: flex; align-items: center; justify-content: center; gap: 0.75rem; width: 100%;">
                  <i class="fas fa-lock"></i> Pay Securely with PayHere (<?php echo format_price($order['total_amount']); ?>)
                </button>
              </form>

              <!-- Alternative Option (JS SDK Popup) -->
              <div style="margin-top: 1.25rem; text-align: center;">
                <button type="button" id="btn-payhere-popup" class="btn btn-outline-dark btn-sm" style="color: var(--text-light); border-color: var(--dark-border); font-size: 0.82rem;" onclick="startPayHerePopup()">
                  <i class="fas fa-window-restore"></i> Pay with PayHere Popup Window
                </button>
              </div>

            </div>

            <!-- Delivery Information Box -->
            <div style="background: var(--dark-surface); border: 1px solid var(--dark-border); border-radius: 16px; padding: 1.75rem; box-shadow: var(--shadow-dark);">
              <h3 style="font-size: 1rem; font-weight: 800; color: var(--text-white); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-map-marker-alt text-red"></i> Delivery Destination
              </h3>
              <div style="font-size: 0.88rem; color: var(--text-light); line-height: 1.6;">
                <strong style="color: var(--text-white);"><?php echo e($order['shipping_name']); ?></strong> &bull; <?php echo e($order['shipping_phone']); ?><br>
                <?php echo e($order['shipping_address']); ?>, <?php echo e($order['shipping_city']); ?> <?php echo e($order['shipping_postal_code']); ?><br>
                <span style="color: #94a3b8;"><?php echo e($order['shipping_province']); ?></span>
              </div>
            </div>

          </div>

          <!-- Right Column: Order Summary -->
          <div>
            <div style="background: var(--dark-surface); border: 1px solid var(--dark-border); border-radius: 16px; padding: 2rem; box-shadow: var(--shadow-dark); position: sticky; top: 2rem;">
              
              <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--dark-border);">
                <h2 style="font-size: 1.2rem; font-weight: 800; color: var(--text-white); margin: 0;">Order Summary</h2>
                <span style="font-family: monospace; font-size: 0.85rem; font-weight: 700; color: var(--primary-red);"><?php echo e($order['order_number']); ?></span>
              </div>

              <!-- Item list -->
              <div style="display: flex; flex-direction: column; gap: 0.85rem; margin-bottom: 1.5rem; max-height: 260px; overflow-y: auto; padding-right: 0.25rem;">
                <?php foreach ($items as $it): ?>
                  <div style="display: flex; gap: 0.75rem; align-items: center; padding-bottom: 0.75rem; border-bottom: 1px solid rgba(255,255,255,0.06);">
                    <img src="<?php echo e($it['image']); ?>" alt="" style="width: 48px; height: 48px; object-fit: cover; border-radius: 8px; border: 1px solid var(--dark-border);">
                    <div style="flex: 1; font-size: 0.82rem;">
                      <div style="font-weight: 700; color: var(--text-white); line-height: 1.25;"><?php echo e($it['name']); ?></div>
                      <div style="color: var(--text-light); font-size: 0.75rem; margin-top: 0.15rem;">Qty: <?php echo $it['quantity']; ?> &bull; <?php echo format_price($it['price']); ?></div>
                    </div>
                    <div style="font-weight: 800; font-size: 0.88rem; color: var(--text-white);">
                      <?php echo format_price($it['price'] * $it['quantity']); ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <!-- Calculation rows -->
              <div style="display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.88rem; color: var(--text-light); padding-top: 0.5rem; border-top: 1px solid var(--dark-border);">
                
                <div style="display: flex; justify-content: space-between;">
                  <span>Subtotal</span>
                  <span style="color: var(--text-white); font-weight: 600;">
                    <?php echo format_price($order['total_amount'] - $order['delivery_fee'] + $order['discount_amount']); ?>
                  </span>
                </div>

                <?php if ($order['discount_amount'] > 0): ?>
                  <div style="display: flex; justify-content: space-between; color: var(--primary-red);">
                    <span>Discount</span>
                    <span style="font-weight: 600;">- <?php echo format_price($order['discount_amount']); ?></span>
                  </div>
                <?php endif; ?>

                <div style="display: flex; justify-content: space-between;">
                  <span>Courier Delivery Fee</span>
                  <span style="color: var(--text-white); font-weight: 600;"><?php echo format_price($order['delivery_fee']); ?></span>
                </div>

                <div style="display: flex; justify-content: space-between; font-size: 1.25rem; font-weight: 900; color: var(--text-white); padding-top: 0.75rem; margin-top: 0.5rem; border-top: 1px dashed var(--dark-border);">
                  <span>Total Amount</span>
                  <span style="color: var(--primary-red);"><?php echo format_price($order['total_amount']); ?></span>
                </div>

              </div>

              <div style="margin-top: 1.75rem; padding-top: 1rem; border-top: 1px solid var(--dark-border); text-align: center; font-size: 0.78rem; color: var(--text-light);">
                <i class="fas fa-lock text-red"></i> PayHere Certified PCI-DSS Level 1 Gateway
              </div>

            </div>
          </div>

        </div>

      <?php endif; ?>

    </div>
  </main>

  <!-- PayHere JS SDK -->
  <script src="https://www.payhere.lk/lib/payhere.js"></script>

  <script>
    const paymentData = <?php echo json_encode($payherePayload); ?>;
    const orderNumber = <?php echo json_encode($orderNumber); ?>;

    // Handle Form Submit with loading state
    const checkoutForm = document.getElementById('payhere-checkout-form');
    if (checkoutForm) {
      checkoutForm.addEventListener('submit', function() {
        const btn = document.getElementById('btn-payhere-submit');
        if (btn) {
          btn.disabled = true;
          btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redirecting to PayHere...';
        }
      });
    }

    // PayHere Popup SDK alternative
    function startPayHerePopup() {
      if (typeof payhere === 'undefined') {
        // If JS SDK blocked or unavailable, submit hosted form
        document.getElementById('payhere-checkout-form').submit();
        return;
      }

      payhere.onCompleted = function onPaymentCompleted(orderId) {
        if (typeof Store !== 'undefined' && Store.showToast) {
          Store.showToast('Payment completed successfully!', 'success');
        }
        window.location.href = `order-confirmation.php?order_id=${encodeURIComponent(orderNumber)}`;
      };

      payhere.onDismissed = function onPaymentDismissed() {
        if (typeof Store !== 'undefined' && Store.showToast) {
          Store.showToast('Payment window was dismissed.', 'warning');
        }
      };

      payhere.onError = function onPaymentError(error) {
        console.error('PayHere Popup Error:', error);
        // If domain origin error occurs on localhost, fallback to hosted form
        if (error && (error.includes('Unauthorized') || error.includes('domain') || error.includes('merchant'))) {
          alert('Note: PayHere popup origin check triggered on localhost. Redirecting to PayHere secure hosted checkout page...');
          document.getElementById('payhere-checkout-form').submit();
        } else {
          window.location.href = `order-confirmation.php?payment_status=failed&order_id=${encodeURIComponent(orderNumber)}`;
        }
      };

      payhere.startPayment(paymentData);
    }
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
