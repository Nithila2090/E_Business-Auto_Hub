/**
 * AUTO HUB – Frontend Script & UI Interactions
 * Vehicle selector AJAX, instant live search, quick view modal, drawer, and tab filters
 */

document.addEventListener('DOMContentLoaded', () => {
  initVehicleSelector();
  initLiveSearch();
  initFeaturedProducts();
  initGlobalCardEvents();
  initCountdownTimer();
  initMobileDrawer();
  initBackToTop();
  initQuickViewModal();
});

/* ==========================================================================
   1. Vehicle Compatibility Selector (AJAX from MySQL backend)
   ========================================================================== */
function initVehicleSelector() {
  const makeSelects = document.querySelectorAll('.select-make');
  const findBtns = document.querySelectorAll('.btn-find-parts');

  if (!makeSelects.length) return;

  makeSelects.forEach(select => {
    // If make is already selected on page load (e.g. products.php filter), load models
    if (select.value) {
      loadModelsForSelect(select, select.value);
    }

    // On Make Change -> Populate Models from MySQL
    select.addEventListener('change', async (e) => {
      const selectedMake = e.target.value;
      const form = select.closest('form') || select.closest('.vehicle-selector-box') || document;
      const modelSelect = form.querySelector('.select-model');
      const yearSelect = form.querySelector('.select-year');

      if (!modelSelect) return;

      if (!selectedMake) {
        modelSelect.innerHTML = '<option value="">Select Model</option>';
        modelSelect.disabled = true;
        if (yearSelect) {
          yearSelect.innerHTML = '<option value="">Select Year</option>';
          yearSelect.disabled = true;
        }
        return;
      }

      await loadModelsForSelect(select, selectedMake);
    });
  });

  async function loadModelsForSelect(selectEl, makeName) {
    const form = selectEl.closest('form') || selectEl.closest('.vehicle-selector-box') || document;
    const modelSelect = form.querySelector('.select-model');
    const yearSelect = form.querySelector('.select-year');
    if (!modelSelect) return;

    try {
      const res = await fetch(`api/vehicles.php?action=models&make=${encodeURIComponent(makeName)}`);
      const data = await res.json();

      if (data.success && data.data.length) {
        const currentSelectedModel = modelSelect.getAttribute('data-selected') || modelSelect.value || '';
        modelSelect.disabled = false;
        modelSelect.innerHTML = '<option value="">Select Model</option>';
        
        data.data.forEach(m => {
          const opt = document.createElement('option');
          opt.value = m.name;
          opt.textContent = m.name;
          if (currentSelectedModel && (currentSelectedModel === m.name || currentSelectedModel === String(m.id))) {
            opt.selected = true;
          }
          modelSelect.appendChild(opt);
        });

        // If model already selected, populate years
        if (modelSelect.value) {
          loadYearsForSelect(modelSelect, makeName, modelSelect.value);
        }
      }
    } catch (err) {
      console.error('Error fetching models', err);
    }

    // Model Change -> Load Years
    modelSelect.onchange = (e) => {
      const selectedModel = e.target.value;
      if (selectedModel) {
        loadYearsForSelect(modelSelect, makeName, selectedModel);
      } else if (yearSelect) {
        yearSelect.innerHTML = '<option value="">Select Year</option>';
        yearSelect.disabled = true;
      }
    };
  }

  async function loadYearsForSelect(modelSelectEl, makeName, modelName) {
    const form = modelSelectEl.closest('form') || modelSelectEl.closest('.vehicle-selector-box') || document;
    const yearSelect = form.querySelector('.select-year');
    if (!yearSelect) return;

    try {
      const res = await fetch(`api/vehicles.php?action=years&make=${encodeURIComponent(makeName)}&model=${encodeURIComponent(modelName)}`);
      const data = await res.json();

      if (data.success && data.data.length) {
        const currentSelectedYear = yearSelect.getAttribute('data-selected') || yearSelect.value || '';
        yearSelect.disabled = false;
        yearSelect.innerHTML = '<option value="">Select Year</option>';
        
        data.data.forEach(yr => {
          const opt = document.createElement('option');
          opt.value = yr;
          opt.textContent = yr;
          if (currentSelectedYear && currentSelectedYear === String(yr)) {
            opt.selected = true;
          }
          yearSelect.appendChild(opt);
        });
      }
    } catch (err) {
      console.error('Error fetching years', err);
    }
  }

  // Find Parts Button Handler
  findBtns.forEach(btn => {
    btn.addEventListener('click', (e) => {
      const form = btn.closest('form') || btn.closest('.vehicle-selector-box');
      if (!form) return;

      const make = form.querySelector('.select-make')?.value || '';
      const model = form.querySelector('.select-model')?.value || '';
      const year = form.querySelector('.select-year')?.value || '';

      if (!make) {
        e.preventDefault();
        Store.showToast('Please select at least a vehicle Make to find compatible parts.', 'warning');
        return;
      }

      // If form action is already products.php, let default submit or navigate
      e.preventDefault();
      const params = new URLSearchParams();
      if (make) params.set('make', make);
      if (model) params.set('model', model);
      if (year) params.set('year', year);

      window.location.href = `products.php?${params.toString()}`;
    });
  });
}

/* ==========================================================================
   2. Instant Live Search Bar (Autocomplete via API)
   ========================================================================== */
function initLiveSearch() {
  const searchInputs = document.querySelectorAll('.search-input');
  const searchForms = document.querySelectorAll('.search-form');

  searchInputs.forEach(input => {
    const wrapper = input.closest('.search-wrapper') || input.parentElement;
    let suggestionsContainer = wrapper.querySelector('.search-suggestions');

    if (!suggestionsContainer) {
      suggestionsContainer = document.createElement('div');
      suggestionsContainer.className = 'search-suggestions';
      wrapper.appendChild(suggestionsContainer);
    }

    let searchTimeout = null;

    input.addEventListener('input', (e) => {
      const query = e.target.value.trim();
      clearTimeout(searchTimeout);

      if (query.length < 2) {
        suggestionsContainer.innerHTML = '';
        suggestionsContainer.classList.remove('show');
        return;
      }

      searchTimeout = setTimeout(async () => {
        try {
          const res = await fetch(`api/products.php?search_suggestions=${encodeURIComponent(query)}`);
          const data = await res.json();

          if (!data.success || !data.data || data.data.length === 0) {
            suggestionsContainer.innerHTML = `
              <div style="padding: 1rem; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                No spare parts found for "<strong>${escapeHtml(query)}</strong>"
              </div>
            `;
            suggestionsContainer.classList.add('show');
            return;
          }

          suggestionsContainer.innerHTML = data.data.map(item => `
            <div class="suggestion-item" data-id="${item.id}">
              <img src="${item.image}" alt="${escapeHtml(item.name)}" class="suggestion-img">
              <div class="suggestion-details">
                <div class="suggestion-title">${escapeHtml(item.name)}</div>
                <div class="suggestion-price">${Store.formatPrice(item.price)} <span style="font-size: 0.72rem; color: #64748b;">(${escapeHtml(item.brand)})</span></div>
              </div>
            </div>
          `).join('');

          suggestionsContainer.classList.add('show');

          suggestionsContainer.querySelectorAll('.suggestion-item').forEach(itemEl => {
            itemEl.addEventListener('click', () => {
              const id = itemEl.getAttribute('data-id');
              window.location.href = `product-details.php?id=${id}`;
            });
          });

        } catch (err) {
          console.error('Search suggestion error', err);
        }
      }, 250);
    });

    // Close suggestions on outside click
    document.addEventListener('click', (e) => {
      if (!wrapper.contains(e.target)) {
        suggestionsContainer.classList.remove('show');
      }
    });
  });

  // Handle Search Form Submit
  searchForms.forEach(form => {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const input = form.querySelector('.search-input');
      const query = input ? input.value.trim() : '';
      if (query) {
        window.location.href = `products.php?search=${encodeURIComponent(query)}`;
      }
    });
  });
}

/* ==========================================================================
   3. Featured Products & Tabs Renderer (Home page)
   ========================================================================== */
function initFeaturedProducts() {
  const container = document.getElementById('featured-products-grid');
  if (!container) return;

  const tabButtons = document.querySelectorAll('.product-tabs .tab-btn');

  tabButtons.forEach(btn => {
    btn.addEventListener('click', async () => {
      tabButtons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const tabType = btn.getAttribute('data-tab');

      try {
        container.style.opacity = '0.5';
        const res = await fetch(`api/products.php?tab=${encodeURIComponent(tabType)}&limit=8`);
        const data = await res.json();
        container.style.opacity = '1';

        if (data.success && data.data) {
          container.innerHTML = data.data.map(p => createProductCardHtml(p)).join('');
        }
      } catch (e) {
        container.style.opacity = '1';
        console.error(e);
      }
    });
  });
}

/* ==========================================================================
   4. Helper: Create Product Card HTML
   ========================================================================== */
function createProductCardHtml(product) {
  const discountHtml = product.old_price && product.old_price > product.price 
    ? `<span class="badge badge-discount">-${product.discount || Math.round((1 - product.price/product.old_price)*100)}%</span>` 
    : '';
  const bestSellerHtml = product.is_bestseller ? `<span class="badge badge-bestseller">Best Seller</span>` : '';
  const newBadgeHtml = product.is_new ? `<span class="badge" style="background:#0284c7; color:white;">New</span>` : '';

  let starsHtml = '';
  const rating = Math.round(Number(product.rating) || 5);
  for (let i = 1; i <= 5; i++) {
    starsHtml += (i <= rating) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
  }

  return `
    <div class="product-card" data-id="${product.id}">
      <div class="product-card-top">
        <div class="product-badge-group">
          ${discountHtml}
          ${bestSellerHtml}
          ${newBadgeHtml}
        </div>
        <button class="btn-wishlist" data-id="${product.id}" title="Add to Wishlist">
          <i class="far fa-heart"></i>
        </button>
        <a href="product-details.php?id=${product.id}" class="product-img-link">
          <img src="${product.image}" alt="${escapeHtml(product.name)}" class="product-img" loading="lazy">
        </a>
      </div>

      <div class="product-card-body">
        <div class="product-meta">
          <span class="product-category">${escapeHtml(product.category_name || 'Spare Part')}</span>
          <span class="product-brand">${escapeHtml(product.brand)}</span>
        </div>

        <h3 class="product-title">
          <a href="product-details.php?id=${product.id}">${escapeHtml(product.name)}</a>
        </h3>

        <div class="product-fitment-note">
          <i class="fas fa-check-circle"></i> SKU: ${escapeHtml(product.sku)}
        </div>

        <div class="product-rating">
          <div class="stars">${starsHtml}</div>
          <span class="rating-count">(${product.reviews_count || 12})</span>
        </div>

        <div class="product-price-row">
          <div class="price-wrap">
            <span class="current-price">${Store.formatPrice(product.price)}</span>
            ${product.old_price > product.price ? `<span class="old-price">${Store.formatPrice(product.old_price)}</span>` : ''}
          </div>
          <span class="stock-status in-stock"><i class="fas fa-check"></i> In Stock</span>
        </div>
      </div>

      <div class="product-card-actions product-card-footer">
        <button class="quick-view-btn btn-quick-view" data-id="${product.id}" type="button">
          <i class="fa-regular fa-eye"></i> Quick View
        </button>
        <button class="add-to-cart-btn btn-add-cart" data-id="${product.id}" type="button">
          <i class="fa-solid fa-cart-shopping"></i> Add to Cart
        </button>
      </div>
    </div>
  `;
}

/* ==========================================================================
   5. Global Event Delegation for Product Card Actions
   ========================================================================== */
function initGlobalCardEvents() {
  document.addEventListener('click', async (e) => {
    // Add to Cart
    const addBtn = e.target.closest('.btn-add-cart, .add-to-cart-btn');
    if (addBtn) {
      e.preventDefault();
      const id = addBtn.getAttribute('data-id');
      if (id) {
        addBtn.disabled = true;
        const prevText = addBtn.innerHTML;
        addBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        await Store.addToCartAsync(id, 1);
        addBtn.disabled = false;
        addBtn.innerHTML = prevText;
      }
      return;
    }

    // Wishlist Toggle
    const wishBtn = e.target.closest('.btn-wishlist');
    if (wishBtn) {
      e.preventDefault();
      const id = wishBtn.getAttribute('data-id');
      if (id) {
        await Store.toggleWishlistAsync(id, wishBtn);
      }
      return;
    }

    // Quick View
    const qvBtn = e.target.closest('.btn-quick-view, .quick-view-btn');
    if (qvBtn) {
      e.preventDefault();
      const id = qvBtn.getAttribute('data-id');
      if (id) {
        openQuickViewModal(id);
      }
      return;
    }
  });
}

/* ==========================================================================
   6. Quick View Modal
   ========================================================================== */
function initQuickViewModal() {
  const modal = document.getElementById('quick-view-modal');
  const closeBtn = document.getElementById('modal-close-btn');

  if (closeBtn && modal) {
    closeBtn.addEventListener('click', () => {
      modal.classList.remove('show');
    });

    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        modal.classList.remove('show');
      }
    });
  }
}

async function openQuickViewModal(productId) {
  const modal = document.getElementById('quick-view-modal');
  const body = document.getElementById('quick-view-body');
  if (!modal || !body) return;

  body.innerHTML = '<div style="padding: 3rem; text-align: center; color: var(--text-muted);"><i class="fas fa-spinner fa-spin fa-2x"></i><p style="margin-top: 0.5rem;">Loading product details...</p></div>';
  modal.classList.add('show');

  try {
    const res = await fetch(`api/products.php?id=${productId}`);
    const data = await res.json();

    if (!data.success || !data.data) {
      body.innerHTML = '<div style="padding: 2rem; text-align: center;">Product details could not be loaded.</div>';
      return;
    }

    const p = data.data;
    const hasDiscount = p.old_price && p.old_price > p.price;

    body.innerHTML = `
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; align-items: center;">
        <div>
          <img src="${p.image}" alt="${escapeHtml(p.name)}" style="width: 100%; border-radius: 12px; max-height: 320px; object-fit: cover;">
        </div>
        <div>
          <span class="badge badge-bestseller" style="margin-bottom: 0.5rem; display: inline-block;">${escapeHtml(p.brand)}</span>
          <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.5rem;">${escapeHtml(p.name)}</h2>
          <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 1rem;">SKU: <strong>${escapeHtml(p.sku)}</strong> &bull; Part No: ${escapeHtml(p.part_number || 'OEM')}</div>

          <div style="margin-bottom: 1rem;">
            <span style="font-size: 1.5rem; font-weight: 800; color: var(--primary-red);">${Store.formatPrice(p.price)}</span>
            ${hasDiscount ? `<span style="font-size: 1rem; color: #94a3b8; text-decoration: line-through; margin-left: 0.5rem;">${Store.formatPrice(p.old_price)}</span>` : ''}
          </div>

          <p style="font-size: 0.88rem; color: #475569; line-height: 1.5; margin-bottom: 1.5rem;">
            ${escapeHtml(p.short_description || p.description || '')}
          </p>

          <div style="display: flex; gap: 0.75rem;">
            <button class="btn btn-primary btn-add-cart" data-id="${p.id}" style="flex: 1;">
              <i class="fas fa-shopping-cart"></i> Add to Cart
            </button>
            <a href="product-details.php?id=${p.id}" class="btn btn-outline-dark">
              View Full Specs
            </a>
          </div>
        </div>
      </div>
    `;
  } catch (err) {
    console.error('Quick view error', err);
  }
}

/* ==========================================================================
   7. Countdown Timer
   ========================================================================== */
function initCountdownTimer() {
  const daysEl = document.getElementById('cd-days');
  const hoursEl = document.getElementById('cd-hours');
  const minsEl = document.getElementById('cd-mins');
  const secsEl = document.getElementById('cd-secs');

  if (!daysEl) return;

  let totalSeconds = 2 * 86400 + 14 * 3600 + 45 * 60 + 30;

  setInterval(() => {
    if (totalSeconds <= 0) return;
    totalSeconds--;

    const d = Math.floor(totalSeconds / 86400);
    const h = Math.floor((totalSeconds % 86400) / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    const s = totalSeconds % 60;

    daysEl.textContent = String(d).padStart(2, '0');
    hoursEl.textContent = String(h).padStart(2, '0');
    minsEl.textContent = String(m).padStart(2, '0');
    secsEl.textContent = String(s).padStart(2, '0');
  }, 1000);
}

/* ==========================================================================
   8. Mobile Drawer & Back to Top
   ========================================================================== */
function initMobileDrawer() {
  const menuBtn = document.querySelector('.mobile-menu-btn');
  const drawer = document.getElementById('mobile-nav-drawer');
  const overlay = document.getElementById('mobile-nav-overlay');
  const closeBtn = document.getElementById('drawer-close-btn');

  if (!drawer || !overlay) return;

  const openDrawer = () => {
    drawer.classList.add('show');
    overlay.classList.add('show');
    document.body.style.overflow = 'hidden';
  };

  const closeDrawer = () => {
    drawer.classList.remove('show');
    overlay.classList.remove('show');
    document.body.style.overflow = '';
  };

  if (menuBtn) menuBtn.addEventListener('click', openDrawer);
  if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
  if (overlay) overlay.addEventListener('click', closeDrawer);
}

function initBackToTop() {
  const btn = document.getElementById('back-to-top');
  if (!btn) return;

  window.addEventListener('scroll', () => {
    if (window.scrollY > 400) {
      btn.classList.add('show');
    } else {
      btn.classList.remove('show');
    }
  });

  btn.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
}

function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}
