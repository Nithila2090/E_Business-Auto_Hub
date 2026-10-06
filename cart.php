<?php
/**
 * AUTO HUB - Shopping Cart Page
 * Manage items, adjust quantities, apply promotional coupons, calculate islandwide shipping
 */

require_once __DIR__ . '/config/helpers.php';

$pdo = get_db();
$cartId = get_or_create_cart_id();

// Fetch items
$stmt = $pdo->prepare("
    SELECT ci.id AS item_id, ci.quantity, p.id AS product_id, p.name, p.slug, p.brand, 
           p.price, p.old_price, p.discount, p.image, p.stock_quantity, p.sku,
           c.name AS category_name
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE ci.cart_id = ?
    ORDER BY ci.id DESC
");
$stmt->execute([$cartId]);
$cartItems = $stmt->fetchAll();

$subtotal = 0;
foreach ($cartItems as &$item) {
    $itemTotal = (float)$item['price'] * (int)$item['quantity'];
    $item['item_total'] = $itemTotal;
    $subtotal += $itemTotal;
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

$pageTitle = "Shopping Cart | AUTO HUB – Spare Parts & Accessories";
$pageDescription = "Review your shopping cart, calculate islandwide delivery fees, apply discount coupons and proceed to checkout.";

require_once __DIR__ . '/includes/header.php';
?>

  <!-- =========================================================================
       Cart Page Main Section
       ========================================================================= -->
  <main class="cart-page-wrap">
    <div class="container">
      
      <div class="section-title-wrap" style="text-align: left; margin-bottom: 2rem;">
        <h1 class="section-title" style="font-size: 2.2rem;">SHOPPING CART</h1>
        <p class="section-subtitle">Review your selected automotive spare parts before checkout</p>
      </div>

      <!-- Empty Cart State -->
      <div id="cart-empty-container" style="<?php echo empty($cartItems) ? 'display: block;' : 'display: none;'; ?> text-align: center; padding: 5rem 1.5rem; background: white; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
        <i class="fas fa-shopping-cart text-muted" style="font-size: 4rem; margin-bottom: 1.5rem; color: #cbd5e1;"></i>
        <h2 style="font-size: 1.6rem; font-weight: 800; margin-bottom: 0.5rem;">Your Shopping Cart is Empty</h2>
        <p style="color: #64748b; margin-bottom: 2rem; max-width: 480px; margin-left: auto; margin-right: auto;">
          You haven't added any automotive spare parts to your shopping cart yet. Select your vehicle make and model to find compatible parts.
        </p>
        <a href="products.php" class="btn btn-primary btn-lg">
          <i class="fas fa-search"></i> Browse Spare Parts Catalog
        </a>
      </div>

      <!-- Cart Content Layout -->
      <div class="cart-layout" id="cart-content-layout" style="<?php echo empty($cartItems) ? 'display: none;' : 'display: grid;'; ?>">
        
        <!-- Left: Cart Items Table -->
        <div class="cart-items-card">
          <table class="cart-table">
            <thead>
              <tr>
                <th style="width: 45%;">Product</th>
                <th style="width: 18%;">Price</th>
                <th style="width: 20%;">Quantity</th>
                <th style="width: 17%; text-align: right;">Total</th>
              </tr>
            </thead>
            <tbody id="cart-tbody">
              <?php foreach ($cartItems as $it): ?>
                <tr data-id="<?php echo $it['product_id']; ?>">
                  <td>
                    <div class="cart-product-cell">
                      <img src="<?php echo e($it['image']); ?>" alt="<?php echo e($it['name']); ?>" class="cart-item-img">
                      <div class="cart-item-info">
                        <a href="product-details.php?id=<?php echo $it['product_id']; ?>" class="cart-item-title"><?php echo e($it['name']); ?></a>
                        <div class="cart-item-meta">
                          <span>Brand: <strong><?php echo e($it['brand']); ?></strong></span>
                          <span>SKU: <?php echo e($it['sku']); ?></span>
                        </div>
                        <button type="button" class="btn-remove-item" onclick="removeCartItem(<?php echo $it['product_id']; ?>)">
                          <i class="fas fa-trash-alt"></i> Remove
                        </button>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div class="cart-price-cell"><?php echo format_price($it['price']); ?></div>
                  </td>
                  <td>
                    <div class="quantity-stepper">
                      <button class="qty-btn" onclick="updateCartItemQty(<?php echo $it['product_id']; ?>, <?php echo $it['quantity'] - 1; ?>)">-</button>
                      <input type="number" class="qty-input" value="<?php echo $it['quantity']; ?>" readonly>
                      <button class="qty-btn" onclick="updateCartItemQty(<?php echo $it['product_id']; ?>, <?php echo $it['quantity'] + 1; ?>)">+</button>
                    </div>
                  </td>
                  <td style="text-align: right;">
                    <div class="cart-total-cell" style="font-weight: 700; color: var(--text-main);"><?php echo format_price($it['item_total']); ?></div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--border-color);">
            <a href="products.php" class="btn btn-outline-dark btn-sm">
              <i class="fas fa-arrow-left"></i> Continue Shopping
            </a>
            <button class="btn btn-outline-red btn-sm" id="btn-clear-cart" onclick="clearEntireCart()">
              <i class="fas fa-trash-alt"></i> Clear Cart
            </button>
          </div>
        </div>

        <!-- Right: Summary & Checkout Details -->
        <div class="order-summary-card">
          <h2 class="summary-title">Order Summary</h2>

          <div class="summary-row">
            <span>Subtotal</span>
            <span id="summary-subtotal" class="fw-bold"><?php echo format_price($subtotal); ?></span>
          </div>

          <!-- Coupon Code Input -->
          <div class="coupon-form" style="margin: 1rem 0;">
            <input type="text" class="coupon-input" id="coupon-code-input" placeholder="Coupon Code (e.g. AUTOHUB10)" value="<?php echo e($coupon['code'] ?? ''); ?>">
            <button type="button" class="btn btn-secondary btn-sm" id="btn-apply-coupon" onclick="applyCouponCode()">Apply</button>
          </div>

          <div id="coupon-active-badge" style="<?php echo $coupon ? 'display: flex;' : 'display: none;'; ?> margin-bottom: 1rem; font-size: 0.82rem; color: #16a34a; background: #f0fdf4; padding: 0.4rem 0.75rem; border-radius: 6px; border: 1px solid #bbf7d0; justify-content: space-between; align-items: center;">
            <span id="coupon-desc-text"><?php echo e($coupon['description'] ?? ''); ?></span>
            <button id="btn-remove-coupon" onclick="removeCouponCode()" style="background: none; border: none; color: #ef4444; cursor: pointer; font-weight: bold;">&times;</button>
          </div>

          <div class="summary-row" id="discount-row" style="<?php echo $discount > 0 ? 'display: flex;' : 'display: none;'; ?> color: var(--primary-red);">
            <span>Coupon Discount</span>
            <span id="summary-discount" class="fw-bold">- <?php echo format_price($discount); ?></span>
          </div>

          <!-- Delivery Destination Selector -->
          <div style="margin: 1.25rem 0; padding-top: 1rem; border-top: 1px solid var(--border-color-light);">
            <label class="form-label" style="font-size: 0.85rem;"><i class="fas fa-map-marker-alt text-red"></i> Shipping Destination</label>
            <select class="form-control" id="province-select" onchange="calculateShipping(this.value)" style="font-size: 0.85rem; padding: 0.5rem 0.75rem;">
              <option value="Western Province (Colombo, Gampaha, Kalutara)">Western Province (Rs. 350)</option>
              <option value="Central Province (Kandy, Matale, Nuwara Eliya)">Central Province (Rs. 450)</option>
              <option value="Southern Province (Galle, Matara, Hambantota)">Southern Province (Rs. 450)</option>
              <option value="North Western Province (Kurunegala, Puttalam)">North Western Province (Rs. 450)</option>
              <option value="Sabaragamuwa Province (Ratnapura, Kegalle)">Sabaragamuwa Province (Rs. 450)</option>
              <option value="Eastern Province (Trincomalee, Batticaloa, Ampara)">Eastern Province (Rs. 500)</option>
              <option value="Northern Province (Jaffna, Kilinochchi, Vavuniya)">Northern Province (Rs. 500)</option>
              <option value="North Central Province (Anuradhapura, Polonnaruwa)">North Central Province (Rs. 500)</option>
              <option value="Uva Province (Badulla, Monaragala)">Uva Province (Rs. 500)</option>
            </select>
          </div>

          <div class="summary-row">
            <span>Estimated Shipping</span>
            <span id="summary-shipping" class="fw-bold"><?php echo format_price($shipping); ?></span>
          </div>

          <div class="summary-row total">
            <span>Estimated Total</span>
            <span class="total-amount" id="summary-grand-total"><?php echo format_price($grandTotal); ?></span>
          </div>

          <a href="checkout.php" class="btn btn-primary btn-block btn-lg" style="margin-top: 1.5rem; text-decoration: none;">
            <i class="fas fa-lock"></i> Proceed to Checkout
          </a>

          <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--border-color-light); font-size: 0.8rem; color: #64748b; text-align: center;">
            <i class="fas fa-shield-alt text-red"></i> 256-Bit SSL Encrypted Islandwide Checkout
          </div>
        </div>

      </div>

    </div>
  </main>

  <script>
    async function updateCartItemQty(productId, newQty) {
      try {
        const res = await fetch('api/cart.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'update', product_id: productId, quantity: newQty })
        });
        const data = await res.json();
        if (data.success) {
          window.location.reload();
        } else {
          Store.showToast(data.message, 'warning');
        }
      } catch (err) {
        console.error(err);
      }
    }

    async function removeCartItem(productId) {
      try {
        const res = await fetch('api/cart.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'remove', product_id: productId })
        });
        const data = await res.json();
        if (data.success) {
          Store.showToast('Item removed from cart', 'info');
          setTimeout(() => window.location.reload(), 500);
        }
      } catch (err) {
        console.error(err);
      }
    }

    async function clearEntireCart() {
      if (!confirm('Are you sure you want to clear your shopping cart?')) return;
      try {
        const res = await fetch('api/cart.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'clear' })
        });
        const data = await res.json();
        if (data.success) {
          window.location.reload();
        }
      } catch (err) {
        console.error(err);
      }
    }

    async function applyCouponCode() {
      const code = document.getElementById('coupon-code-input').value.trim();
      if (!code) {
        Store.showToast('Please enter a coupon code.', 'warning');
        return;
      }
      try {
        const res = await fetch('api/cart.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'apply_coupon', coupon_code: code })
        });
        const data = await res.json();
        if (data.success) {
          Store.showToast(data.message, 'success');
          setTimeout(() => window.location.reload(), 600);
        } else {
          Store.showToast(data.message, 'error');
        }
      } catch (err) {
        console.error(err);
      }
    }

    async function removeCouponCode() {
      try {
        const res = await fetch('api/cart.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'remove_coupon' })
        });
        const data = await res.json();
        if (data.success) {
          Store.showToast('Coupon removed.', 'info');
          setTimeout(() => window.location.reload(), 500);
        }
      } catch (err) {
        console.error(err);
      }
    }

    function calculateShipping(province) {
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

      document.getElementById('summary-shipping').textContent = 'Rs. ' + fee.toLocaleString('en-LK');
      document.getElementById('summary-grand-total').textContent = 'Rs. ' + Math.max(0, subtotal - discount + fee).toLocaleString('en-LK');
    }
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
