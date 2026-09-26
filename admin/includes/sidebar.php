<?php
/**
 * AUTO HUB - Admin Sidebar Component
 */
$cur = basename($_SERVER['PHP_SELF']);
?>
<aside class="admin-sidebar">
  <div class="admin-sidebar-header">
    <div style="width: 38px; height: 38px; border-radius: 8px; background: var(--primary-red); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
      <i class="fas fa-cog"></i>
    </div>
    <div>
      <div style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.15rem; color: white; line-height: 1;">AUTO <span style="color: var(--primary-red);">HUB</span></div>
      <div style="font-size: 0.68rem; color: #94a3b8; letter-spacing: 0.5px; margin-top: 0.2rem;">ADMIN PORTAL</div>
    </div>
  </div>

  <ul class="admin-sidebar-menu">
    <li>
      <a href="dashboard.php" class="admin-menu-link <?php echo ($cur === 'dashboard.php') ? 'active' : ''; ?>">
        <i class="fas fa-gauge-high"></i> Dashboard
      </a>
    </li>
    <li>
      <a href="products.php" class="admin-menu-link <?php echo in_array($cur, ['products.php', 'add_product.php', 'edit_product.php']) ? 'active' : ''; ?>">
        <i class="fas fa-boxes-stacked"></i> Products
      </a>
    </li>
    <li>
      <a href="categories.php" class="admin-menu-link <?php echo ($cur === 'categories.php') ? 'active' : ''; ?>">
        <i class="fas fa-layer-group"></i> Categories
      </a>
    </li>
    <li>
      <a href="vehicles.php" class="admin-menu-link <?php echo ($cur === 'vehicles.php') ? 'active' : ''; ?>">
        <i class="fas fa-car-side"></i> Vehicle Data
      </a>
    </li>
    <li>
      <a href="orders.php" class="admin-menu-link <?php echo ($cur === 'orders.php') ? 'active' : ''; ?>">
        <i class="fas fa-cart-shopping"></i> Customer Orders
      </a>
    </li>
    <li>
      <a href="users.php" class="admin-menu-link <?php echo ($cur === 'users.php') ? 'active' : ''; ?>">
        <i class="fas fa-users"></i> Users &amp; Customers
      </a>
    </li>
  </ul>

  <div style="padding: 1.25rem; border-top: 1px solid var(--admin-border); font-size: 0.78rem; color: #64748b; text-align: center;">
    <div>AUTO HUB &bull; PHP 8 + MySQL</div>
    <div style="margin-top: 0.25rem; color: #475569;">v2.0 XAMPP Local Build</div>
  </div>
</aside>
