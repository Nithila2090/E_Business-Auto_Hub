<?php
/**
 * AUTO HUB - Shared Footer Component
 */
?>
  <!-- =========================================================================
       Footer Section
       ========================================================================= -->
  <footer class="footer">
    <div class="container">
      <div class="footer-grid">
        
        <!-- Col 1: Brand Info -->
        <div class="footer-col">
          <a href="index.php" class="brand-logo" style="margin-bottom: 1.25rem;">
            <div class="brand-icon">
              <i class="fas fa-cog"></i>
            </div>
            <div class="brand-text">
              <span class="brand-title" style="color: white;">AUTO <span>HUB</span></span>
              <span class="brand-subtitle" style="color: #94a3b8;">SPARE PARTS &amp; ACCESSORIES</span>
            </div>
          </a>
          <p class="footer-about-text">
            Sri Lanka's leading automotive spare parts e-commerce destination. Supplying 100% genuine OEM &amp; aftermarket parts for Toyota, BMW, Honda, Nissan, Suzuki, and more with verified vehicle compatibility and islandwide express delivery.
          </p>
          <div class="social-links">
            <a href="https://facebook.com" target="_blank" rel="noopener" class="social-btn" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
            <a href="https://instagram.com" target="_blank" rel="noopener" class="social-btn" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
            <a href="https://youtube.com" target="_blank" rel="noopener" class="social-btn" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
            <a href="https://whatsapp.com" target="_blank" rel="noopener" class="social-btn" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
          </div>
        </div>

        <!-- Col 2: Quick Links -->
        <div class="footer-col">
          <h3>Quick Links</h3>
          <div class="footer-links">
            <a href="index.php"><i class="fas fa-chevron-right"></i> Home</a>
            <a href="products.php"><i class="fas fa-chevron-right"></i> Browse All Parts</a>
            <a href="products.php?sort=bestselling"><i class="fas fa-chevron-right"></i> Best Selling Parts</a>
            <a href="products.php?sort=newest"><i class="fas fa-chevron-right"></i> New Arrivals</a>
            <a href="about.php"><i class="fas fa-chevron-right"></i> About AUTO HUB</a>
            <a href="contact.php"><i class="fas fa-chevron-right"></i> Contact Us</a>
          </div>
        </div>

        <!-- Col 3: Customer Service -->
        <div class="footer-col">
          <h3>Customer Support</h3>
          <div class="footer-links">
            <a href="track-order.php"><i class="fas fa-chevron-right"></i> Track My Order</a>
            <a href="cart.php"><i class="fas fa-chevron-right"></i> Shopping Cart</a>
            <a href="wishlist.php"><i class="fas fa-chevron-right"></i> Wishlist</a>
            <a href="profile.php"><i class="fas fa-chevron-right"></i> My Account &amp; Orders</a>
            <a href="contact.php#faq"><i class="fas fa-chevron-right"></i> Warranty &amp; Return Policy</a>
            <a href="contact.php#faq"><i class="fas fa-chevron-right"></i> FAQs</a>
          </div>
        </div>

        <!-- Col 4: Contact & Locations -->
        <div class="footer-col">
          <h3>Contact AUTO HUB</h3>
          <div class="footer-contact-list">
            <div class="footer-contact-item">
              <i class="fas fa-phone-alt"></i>
              <div>
                <strong>Customer Hotline:</strong><br>
                <a href="tel:0707275599" style="color: #cbd5e1;">070 727 5599</a>
              </div>
            </div>
            <div class="footer-contact-item">
              <i class="fas fa-envelope"></i>
              <div>
                <strong>Email Support:</strong><br>
                <a href="mailto:nithilarodrigo@gmail.com" style="color: #cbd5e1;">nithilarodrigo@gmail.com</a>
              </div>
            </div>
            <div class="footer-contact-item">
              <i class="fas fa-map-marker-alt"></i>
              <div>
                <strong>Main Warehouse &amp; Hub:</strong><br>
                No. 142, Galle Road, Colombo 03, Sri Lanka
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Bottom Bar -->
    <div class="footer-bottom">
      <div class="container">
        <div>
          &copy; <?php echo date('Y'); ?> <strong>AUTO HUB</strong> – Sri Lanka. All Rights Reserved. OEM part numbers &amp; trademarks are used for reference only.
        </div>
        <div class="payment-methods" style="display: flex; gap: 0.75rem; font-size: 1.3rem; color: #94a3b8;">
          <i class="fab fa-cc-visa" title="Visa"></i>
          <i class="fab fa-cc-mastercard" title="Mastercard"></i>
          <i class="fas fa-money-bill-wave" title="Cash on Delivery"></i>
          <i class="fas fa-university" title="Direct Bank Transfer"></i>
        </div>
      </div>
    </div>
  </footer>

  <!-- =========================================================================
       Mobile Navigation Drawer
       ========================================================================= -->
  <div class="mobile-nav-overlay" id="mobile-nav-overlay"></div>
  <div class="mobile-nav-drawer" id="mobile-nav-drawer">
    <div class="drawer-header">
      <a href="index.php" class="brand-logo">
        <div class="brand-icon" style="width: 36px; height: 36px;">
          <i class="fas fa-cog"></i>
        </div>
        <div class="brand-text">
          <span class="brand-title" style="color: white; font-size: 1.25rem;">AUTO <span>HUB</span></span>
        </div>
      </a>
      <button class="drawer-close-btn" id="drawer-close-btn" aria-label="Close menu">&times;</button>
    </div>

    <div class="drawer-body">
      <div class="drawer-links">
        <a href="index.php" class="drawer-link"><i class="fas fa-home"></i> Home</a>
        <a href="products.php" class="drawer-link"><i class="fas fa-th-large"></i> Shop All Parts</a>
        <a href="wishlist.php" class="drawer-link"><i class="fas fa-heart"></i> Wishlist</a>
        <a href="cart.php" class="drawer-link"><i class="fas fa-shopping-cart"></i> Cart</a>
        <a href="track-order.php" class="drawer-link"><i class="fas fa-truck"></i> Track Order</a>
        <a href="about.php" class="drawer-link"><i class="fas fa-info-circle"></i> About Us</a>
        <a href="contact.php" class="drawer-link"><i class="fas fa-envelope"></i> Contact Us</a>
        <?php if (is_logged_in()): ?>
          <a href="profile.php" class="drawer-link"><i class="fas fa-user-circle"></i> My Profile</a>
          <?php if (is_admin()): ?>
            <a href="admin/dashboard.php" class="drawer-link" style="color: #38bdf8;"><i class="fas fa-gauge-high"></i> Admin Dashboard</a>
          <?php endif; ?>
          <a href="logout.php" class="drawer-link" style="color: #ef4444;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        <?php else: ?>
          <a href="login.php" class="drawer-link" style="color: var(--primary-red);"><i class="fas fa-sign-in-alt"></i> Login / Register</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Back to Top Button -->
  <button id="back-to-top" class="back-to-top" aria-label="Scroll to top">
    <i class="fas fa-arrow-up"></i>
  </button>

  <!-- Quick View Modal Container -->
  <div id="quick-view-modal" class="modal-overlay">
    <div class="modal-content">
      <button class="modal-close" id="modal-close-btn" aria-label="Close Quick View">&times;</button>
      <div id="quick-view-body"></div>
    </div>
  </div>

  <!-- Toast Notification Container -->
  <div id="autohub-toast-container" class="autohub-toast-container"></div>

  <!-- Client-side Scripts -->
  <script src="js/data.js"></script>
  <script src="js/store.js"></script>
  <script src="js/script.js"></script>

  <script>
    // Header user dropdown toggle
    document.addEventListener('DOMContentLoaded', () => {
      const userToggle = document.querySelector('.user-dropdown-toggle');
      const userMenu = document.querySelector('.user-menu-dropdown');
      if (userToggle && userMenu) {
        userToggle.addEventListener('click', (e) => {
          e.preventDefault();
          userMenu.style.display = userMenu.style.display === 'block' ? 'none' : 'block';
        });
        document.addEventListener('click', (e) => {
          if (!userToggle.contains(e.target) && !userMenu.contains(e.target)) {
            userMenu.style.display = 'none';
          }
        });
      }
    });
  </script>

</body>
</html>
