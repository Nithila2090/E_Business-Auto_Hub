/**
 * AUTO HUB - Store & State Management
 * Connects Frontend UI with Backend PHP REST APIs & Local State
 */

const Store = {
  CART_KEY: 'autohub_cart',
  WISHLIST_KEY: 'autohub_wishlist',
  COUPON_KEY: 'autohub_coupon',

  // Format currency to Sri Lankan Rupee (e.g., Rs. 8,500)
  formatPrice(amount) {
    if (isNaN(amount)) return 'Rs. 0';
    return 'Rs. ' + Math.round(amount).toLocaleString('en-LK');
  },

  // Asynchronous Add Item to Cart (calls /api/cart.php)
  async addToCartAsync(productId, quantity = 1) {
    try {
      const res = await fetch('api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add', product_id: productId, quantity: quantity })
      });
      const data = await res.json();

      if (data.success) {
        this.updateCartBadge(data.cart_count);
        this.showToast(data.message, 'success');
        window.dispatchEvent(new CustomEvent('autohub:cartUpdated', { detail: { cartCount: data.cart_count } }));
        return true;
      } else {
        this.showToast(data.message || 'Could not add product to cart', 'error');
        return false;
      }
    } catch (e) {
      console.error('Error adding to cart API', e);
      this.showToast('Network error while adding to cart.', 'error');
      return false;
    }
  },

  // Asynchronous Toggle Wishlist (calls /api/wishlist.php)
  async toggleWishlistAsync(productId, buttonEl = null) {
    try {
      const res = await fetch('api/wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'toggle', product_id: productId })
      });
      const data = await res.json();

      if (data.require_login) {
        this.showToast('Please <a href="login.php" style="color:white;text-decoration:underline;font-weight:bold;">sign in</a> to save items to your wishlist.', 'warning');
        return false;
      }

      if (data.success) {
        this.updateWishlistBadge(data.count);
        this.showToast(data.message, data.in_wishlist ? 'success' : 'info');

        // Update button visual state
        if (buttonEl) {
          if (data.in_wishlist) {
            buttonEl.classList.add('active');
            buttonEl.title = 'Remove from Wishlist';
            const icon = buttonEl.querySelector('i');
            if (icon) {
              icon.classList.remove('far');
              icon.classList.add('fas');
            }
          } else {
            buttonEl.classList.remove('active');
            buttonEl.title = 'Add to Wishlist';
            const icon = buttonEl.querySelector('i');
            if (icon) {
              icon.classList.remove('fas');
              icon.classList.add('far');
            }
          }
        }
        window.dispatchEvent(new CustomEvent('autohub:wishlistUpdated', { detail: { count: data.count } }));
        return data.in_wishlist;
      } else {
        this.showToast(data.message || 'Error updating wishlist', 'error');
        return false;
      }
    } catch (e) {
      console.error('Error toggling wishlist', e);
      this.showToast('Network error while updating wishlist.', 'error');
      return false;
    }
  },

  // Update Cart badge counter
  updateCartBadge(count) {
    document.querySelectorAll('.cart-badge-count').forEach(el => {
      el.textContent = count;
      el.style.display = count > 0 ? 'inline-flex' : 'none';
    });
  },

  // Update Wishlist badge counter
  updateWishlistBadge(count) {
    document.querySelectorAll('.wishlist-badge-count').forEach(el => {
      el.textContent = count;
      el.style.display = count > 0 ? 'inline-flex' : 'none';
    });
  },

  // Sync state from backend on init
  async syncBackendStatus() {
    try {
      const res = await fetch('api/auth.php?action=status');
      const data = await res.json();
      if (data.success) {
        this.updateCartBadge(data.cart_count);
        this.updateWishlistBadge(data.wishlist_count);
      }
    } catch (e) {
      // Offline fallback
    }
  },

  // Toast Notification System
  showToast(message, type = 'info') {
    let container = document.getElementById('autohub-toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'autohub-toast-container';
      container.className = 'autohub-toast-container';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `autohub-toast autohub-toast-${type}`;

    let iconClass = 'fa-info-circle';
    if (type === 'success') iconClass = 'fa-check-circle';
    if (type === 'error') iconClass = 'fa-exclamation-circle';
    if (type === 'warning') iconClass = 'fa-exclamation-triangle';

    toast.innerHTML = `
      <div class="toast-icon"><i class="fas ${iconClass}"></i></div>
      <div class="toast-content">${message}</div>
      <button class="toast-close" aria-label="Close">&times;</button>
    `;

    container.appendChild(toast);

    // Animation & auto remove
    requestAnimationFrame(() => {
      toast.classList.add('show');
    });

    const closeBtn = toast.querySelector('.toast-close');
    const removeToast = () => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    };

    closeBtn.addEventListener('click', removeToast);
    setTimeout(removeToast, 4000);
  }
};

// Initialize backend badges on load
document.addEventListener('DOMContentLoaded', () => {
  Store.syncBackendStatus();
});
