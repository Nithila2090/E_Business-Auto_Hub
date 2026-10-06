<?php
/**
 * AUTO HUB - Checkout Page
 * Shipping details, Payment method selection, and Order placement
 */

require_once __DIR__ . '/config/helpers.php';

$pdo = get_db();
$cartId = get_or_create_cart_id();
$userId = current_user_id();

// Fetch cart items
$stmt = $pdo->prepare("
    SELECT ci.quantity, p.id AS product_id, p.name, p.brand, p.price, p.image, p.sku
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.id
    WHERE ci.cart_id = ?
");
$stmt->execute([$cartId]);
$cartItems = $stmt->fetchAll();

if (empty($cartItems)) {
    header('Location: cart.php');
    exit;
}

$subtotal = 0;
$totalQty = 0;
foreach ($cartItems as $item) {
    $subtotal += (float)$item['price'] * (int)$item['quantity'];
    $totalQty += (int)$item['quantity'];
}

// Coupon
$coupon = $_SESSION['applied_coupon'] ?? null;
$discount = 0;
if ($coupon && !empty($coupon['discountPercent'])) {
    $discount = ($subtotal * $coupon['discountPercent']) / 100;
}

$shipping = 350;
if ($coupon && !empty($coupon['freeShipping'])) {
    $shipping = 0;
}

$grandTotal = max(0, $subtotal - $discount + $shipping);

// Prefill user info if logged in
$userName = '';
$userEmail = '';
$userPhone = '';
$userAddress = '';
$userCity = '';
$userPostal = '';
$userProvince = '';

if ($userId) {
    $stmtUser = $pdo->prepare("SELECT full_name, email, phone, address, city, postal_code, province FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $u = $stmtUser->fetch();
    if ($u) {
        $userName = $u['full_name'];
        $userEmail = $u['email'];
        $userPhone = $u['phone'] ?? '';
        $userAddress = $u['address'] ?? '';
        $userCity = $u['city'] ?? '';
        $userPostal = $u['postal_code'] ?? '';
        $userProvince = $u['province'] ?? '';
    }
}

$pageTitle = "Checkout | AUTO HUB – Spare Parts & Accessories";
$pageDescription = "Secure 256-bit encrypted checkout for automotive spare parts across Sri Lanka. Cash on delivery and credit card options.";

require_once __DIR__ . '/includes/header.php';
?>

  <!-- =========================================================================
       Checkout Main Section
       ========================================================================= -->
  <main class="checkout-page-wrap">
    <div class="container">
      
      <div class="section-title-wrap" style="text-align: left; margin-bottom: 2rem;">
        <h1 class="section-title" style="font-size: 2rem;">CHECKOUT</h1>
        <p class="section-subtitle">Please enter your delivery address and choose a payment method</p>
      </div>

      <div class="cart-layout" style="grid-template-columns: 1.25fr 0.75fr;">
        
        <!-- Left: Address & Payment Form -->
        <div style="display: flex; flex-direction: column; gap: 2rem;">
          
          <!-- Shipping Address Box -->
          <div style="background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 2rem; box-shadow: var(--shadow-sm);">
            <h2 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.6rem;">
              <i class="fas fa-shipping-fast text-red"></i> 1. Delivery &amp; Contact Information
            </h2>

            <form id="checkout-form">
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                  <label class="form-label">Full Name *</label>
                  <input type="text" class="form-control" id="cust-name" name="shipping_name" required placeholder="e.g. Kasun Jayasuriya" value="<?php echo e($userName ?: 'Kasun Jayasuriya'); ?>">
                </div>
                <div class="form-group">
                  <label class="form-label">Phone Number *</label>
                  <input type="tel" class="form-control" id="cust-phone" name="shipping_phone" required placeholder="070 727 5599" value="<?php echo e($userPhone ?: '070 727 5599'); ?>">
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Email Address *</label>
                <input type="email" class="form-control" id="cust-email" name="shipping_email" required placeholder="name@example.com" value="<?php echo e($userEmail ?: 'kasun@autohub.lk'); ?>">
              </div>

              <div class="form-group">
                <label class="form-label">Street Address *</label>
                <input type="text" class="form-control" id="cust-address" name="shipping_address" required placeholder="House/Flat No, Street Name" value="<?php echo e($userAddress ?: 'No. 45/2, Flower Road'); ?>">
              </div>

              <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                  <label class="form-label">City *</label>
                  <input type="text" class="form-control" id="cust-city" name="shipping_city" required placeholder="e.g. Colombo 07" value="<?php echo e($userCity ?: 'Colombo 07'); ?>">
                </div>
                <div class="form-group">
                  <label class="form-label">Postal Code *</label>
                  <input type="text" class="form-control" id="cust-postal" name="shipping_postal_code" required placeholder="e.g. 00700" value="<?php echo e($userPostal ?: '00700'); ?>">
                </div>
                <div class="form-group">
                  <label class="form-label">District / Province *</label>
                  <select class="form-control" id="cust-district" name="shipping_province" required onchange="onProvinceChange(this.value)">
                    <option value="Western Province (Colombo, Gampaha, Kalutara)" <?php echo (str_contains($userProvince, 'Western')) ? 'selected' : ''; ?>>Western Province (Rs. 350)</option>
                    <option value="Central Province (Kandy, Matale, Nuwara Eliya)" <?php echo (str_contains($userProvince, 'Central')) ? 'selected' : ''; ?>>Central Province (Rs. 450)</option>
                    <option value="Southern Province (Galle, Matara, Hambantota)" <?php echo (str_contains($userProvince, 'Southern')) ? 'selected' : ''; ?>>Southern Province (Rs. 450)</option>
                    <option value="North Western Province (Kurunegala, Puttalam)" <?php echo (str_contains($userProvince, 'North Western')) ? 'selected' : ''; ?>>North Western Province (Rs. 450)</option>
                    <option value="Sabaragamuwa Province (Ratnapura, Kegalle)" <?php echo (str_contains($userProvince, 'Sabaragamuwa')) ? 'selected' : ''; ?>>Sabaragamuwa Province (Rs. 450)</option>
                    <option value="Eastern Province (Trincomalee, Batticaloa, Ampara)" <?php echo (str_contains($userProvince, 'Eastern')) ? 'selected' : ''; ?>>Eastern Province (Rs. 500)</option>
                    <option value="Northern Province (Jaffna, Kilinochchi, Vavuniya)" <?php echo (str_contains($userProvince, 'Northern')) ? 'selected' : ''; ?>>Northern Province (Rs. 500)</option>
                    <option value="North Central Province (Anuradhapura, Polonnaruwa)" <?php echo (str_contains($userProvince, 'North Central')) ? 'selected' : ''; ?>>North Central Province (Rs. 500)</option>
                    <option value="Uva Province (Badulla, Monaragala)" <?php echo (str_contains($userProvince, 'Uva')) ? 'selected' : ''; ?>>Uva Province (Rs. 500)</option>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Delivery Instructions (Optional)</label>
                <textarea class="form-control" name="delivery_instructions" rows="2" placeholder="e.g. Please call before arriving or leave with security."></textarea>
              </div>

              <!-- Payment Options Box -->
              <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                <h2 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.6rem;">
                  <i class="fas fa-credit-card text-red"></i> 2. Select Payment Method
                </h2>

                <div style="display: flex; flex-direction: column; gap: 1rem;">
                  <!-- Option 1: Cash on Delivery -->
                  <label class="payment-method-card" style="display: flex; align-items: center; gap: 1rem; padding: 1.1rem; border: 1.5px solid var(--primary-red); background: #fff1f2; border-radius: 12px; cursor: pointer; transition: all 0.2s ease;">
                    <input type="radio" name="payment_method" value="cod" checked onchange="updatePaymentMethodUI(this.value)" style="accent-color: var(--primary-red); width: 18px; height: 18px;">
                    <div>
                      <div style="font-weight: 700; color: var(--text-main); font-size: 0.98rem;"><i class="fas fa-money-bill-wave text-red" style="margin-right: 0.35rem;"></i> Cash on Delivery (COD)</div>
                      <div style="font-size: 0.82rem; color: #64748b; margin-top: 0.2rem;">Pay in cash to courier driver upon receiving your spare parts package</div>
                    </div>
                  </label>

                  <!-- Option 2: Online Card Payment (PayHere) -->
                  <label class="payment-method-card" style="display: flex; align-items: center; gap: 1rem; padding: 1.1rem; border: 1px solid var(--border-color); background: white; border-radius: 12px; cursor: pointer; transition: all 0.2s ease;">
                    <input type="radio" name="payment_method" value="card" onchange="updatePaymentMethodUI(this.value)" style="accent-color: var(--primary-red); width: 18px; height: 18px;">
                    <div>
                      <div style="font-weight: 700; color: var(--text-main); font-size: 0.98rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-credit-card text-red"></i> Online Card Payment (Visa / Mastercard)
                        <span style="background: #e0f2fe; color: #0284c7; font-size: 0.72rem; padding: 0.15rem 0.5rem; border-radius: 12px; font-weight: 700;">SECURE GATEWAY</span>
                      </div>
                      <div style="font-size: 0.82rem; color: #64748b; margin-top: 0.2rem;">Instant online card payment via 256-bit encrypted PayHere checkout (Sandbox &amp; Live)</div>
                    </div>
                  </label>
                </div>
              </div>

              <button type="submit" id="btn-place-order" class="btn btn-primary btn-block btn-lg" style="margin-top: 2rem; font-size: 1.1rem; padding: 1rem;">
                <i class="fas fa-check-circle"></i> Place Order (<?php echo format_price($grandTotal); ?>)
              </button>
            </form>
          </div>

        </div>

        <!-- Right: Order Summary -->
        <div>
          <div class="order-summary-card">
            <h2 class="summary-title">Your Order (<?php echo $totalQty; ?> items)</h2>

            <div id="checkout-items-list" style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem; max-height: 280px; overflow-y: auto;">
              <?php foreach ($cartItems as $it): ?>
                <div style="display: flex; gap: 0.75rem; align-items: center; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                  <img src="<?php echo e($it['image']); ?>" alt="<?php echo e($it['name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0;">
                  <div style="flex: 1; font-size: 0.85rem;">
                    <div style="font-weight: 700; color: var(--text-main); line-height: 1.2;"><?php echo e($it['name']); ?></div>
                    <div style="color: #64748b; font-size: 0.78rem;">Qty: <?php echo $it['quantity']; ?> &bull; <?php echo format_price($it['price']); ?></div>
                  </div>
                  <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-main);">
                    <?php echo format_price($it['price'] * $it['quantity']); ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="summary-row">
              <span>Subtotal</span>
              <span id="chk-subtotal" class="fw-bold"><?php echo format_price($subtotal); ?></span>
            </div>

            <div class="summary-row" id="chk-discount-row" style="<?php echo $discount > 0 ? 'display: flex;' : 'display: none;'; ?> color: var(--primary-red);">
              <span>Coupon Discount</span>
              <span id="chk-discount" class="fw-bold">- <?php echo format_price($discount); ?></span>
            </div>

            <div class="summary-row">
              <span>Delivery Fee</span>
              <span id="chk-shipping" class="fw-bold"><?php echo format_price($shipping); ?></span>
            </div>

            <div class="summary-row total">
              <span>Total Amount</span>
              <span class="total-amount" id="chk-grand-total"><?php echo format_price($grandTotal); ?></span>
            </div>

            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color-light); font-size: 0.8rem; color: #64748b; text-align: center;">
              <i class="fas fa-lock text-red"></i> Verified 100% Genuine Auto Spares Guarantee
            </div>
          </div>
        </div>

      </div>

    </div>
  </main>

  <!-- PayHere Hosted/Popup JS SDK -->
  <script src="https://www.payhere.lk/lib/payhere.js"></script>

  <script>
    let currentTotal = <?php echo (float)$grandTotal; ?>;
    let selectedPaymentMethod = 'cod';

    function updatePaymentMethodUI(method) {
      selectedPaymentMethod = method;
      document.querySelectorAll('.payment-method-card').forEach(card => {
        const radio = card.querySelector('input[type="radio"]');
        if (radio.checked) {
          card.style.borderColor = 'var(--primary-red)';
          card.style.background = '#fff1f2';
        } else {
          card.style.borderColor = 'var(--border-color)';
          card.style.background = 'white';
        }
      });

      const btn = document.getElementById('btn-place-order');
      const formattedTotal = 'Rs. ' + Math.round(currentTotal).toLocaleString('en-LK');
      if (method === 'card') {
        btn.innerHTML = `<i class="fas fa-credit-card"></i> Proceed to Card Payment (${formattedTotal})`;
      } else {
        btn.innerHTML = `<i class="fas fa-check-circle"></i> Place Order (${formattedTotal})`;
      }
    }

    function onProvinceChange(province) {
      let fee = 350;
      if (province.includes('Eastern') || province.includes('Northern') || province.includes('North Central') || province.includes('Uva')) {
        fee = 500;
      } else if (province.includes('Central') || province.includes('Southern') || province.includes('North Western') || province.includes('Sabaragamuwa')) {
        fee = 450;
      }
      const subtotal = <?php echo (float)$subtotal; ?>;
      const discount = <?php echo (float)$discount; ?>;
      const hasFreeShip = <?php echo (!empty($coupon['freeShipping'])) ? 'true' : 'false'; ?>;
      if (hasFreeShip) fee = 0;

      currentTotal = Math.max(0, subtotal - discount + fee);
      document.getElementById('chk-shipping').textContent = 'Rs. ' + fee.toLocaleString('en-LK');
      document.getElementById('chk-grand-total').textContent = 'Rs. ' + currentTotal.toLocaleString('en-LK');
      updatePaymentMethodUI(selectedPaymentMethod);
    }

    document.getElementById('checkout-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = document.getElementById('btn-place-order');
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Order...';

      const formData = new FormData(e.target);
      const payload = {
        action: 'create',
        shipping_name: formData.get('shipping_name'),
        shipping_phone: formData.get('shipping_phone'),
        shipping_email: formData.get('shipping_email'),
        shipping_address: formData.get('shipping_address'),
        shipping_city: formData.get('shipping_city'),
        shipping_postal_code: formData.get('shipping_postal_code'),
        shipping_province: formData.get('shipping_province'),
        payment_method: formData.get('payment_method'),
        delivery_instructions: formData.get('delivery_instructions')
      };

      try {
        const res = await fetch('api/orders.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
          if (typeof Store !== 'undefined' && Store.showToast) {
            Store.showToast(data.message || 'Order created successfully!', 'success');
          }
          
          if (data.payment_required) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Directing to Payment Gateway...';
            setTimeout(() => {
              window.location.href = data.redirect || `payment.php?order_id=${encodeURIComponent(data.order_number)}`;
            }, 500);
          } else {
            // Cash on Delivery
            btn.innerHTML = '<i class="fas fa-check-circle"></i> Order Placed!';
            setTimeout(() => {
              window.location.href = data.redirect || `order-confirmation.php?order_id=${encodeURIComponent(data.order_number)}`;
            }, 600);
          }
        } else {
          if (typeof Store !== 'undefined' && Store.showToast) {
            Store.showToast(data.message || 'Order creation failed.', 'error');
          } else {
            alert(data.message || 'Order creation failed.');
          }
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-check-circle"></i> Place Order';
        }
      } catch (err) {
        console.error(err);
        if (typeof Store !== 'undefined' && Store.showToast) {
          Store.showToast('Network error while placing order.', 'error');
        } else {
          alert('Network error while placing order.');
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Place Order';
      }
    });
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
