/**
 * Kapada Station - Common Utility Functions
 * 
 * This file contains shared utility functions used across the application.
 * Include this file in all pages after config.js and before other scripts.
 * 
 * @version 1.0.0
 */

/* =============================================================================
   Constants & Configuration
   ============================================================================= */

/**
 * Status badge CSS classes for booking status
 * @constant {Object}
 */
const STATUS_CLASSES = Object.freeze({
  pending: 'status-badge-pending',
  confirmed: 'status-badge-confirmed',
  active: 'status-badge-active',
  returned: 'status-badge-returned',
  cancelled: 'status-badge-cancelled'
});

/**
 * Default pagination settings
 * @constant {Object}
 */
const PAGINATION = Object.freeze({
  DEFAULT_LIMIT: 12,
  MAX_LIMIT: 100,
  MIN_LIMIT: 1
});

/* =============================================================================
   DOM Utility Functions
   ============================================================================= */

/**
 * Safely get an element by ID, with optional error handling
 * @param {string} id - Element ID
 * @param {boolean} throwOnMissing - Whether to throw error if element not found
 * @returns {HTMLElement|null}
 */
function getElement(id, throwOnMissing = false) {
  const element = document.getElementById(id);
  if (!element && throwOnMissing) {
    throw new Error(`Element with ID '${id}' not found`);
  }
  return element;
}

/**
 * Safely set innerHTML of an element
 * @param {string|HTMLElement} selector - Element ID or HTMLElement
 * @param {string} html - HTML content to set
 */
function setHTML(selector, html) {
  const el = typeof selector === 'string' ? document.getElementById(selector) : selector;
  if (el) {
    el.innerHTML = html;
  }
}

/**
 * Safely set textContent of an element
 * @param {string|HTMLElement} selector - Element ID or HTMLElement
 * @param {string} text - Text content to set
 */
function setText(selector, text) {
  const el = typeof selector === 'string' ? document.getElementById(selector) : selector;
  if (el) {
    el.textContent = text;
  }
}

/* =============================================================================
   Validation Functions
   ============================================================================= */

/**
 * Validate email format
 * @param {string} email - Email address to validate
 * @returns {boolean}
 */
function isValidEmail(email) {
  if (!email || typeof email !== 'string') return false;
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email.trim());
}

/**
 * Validate phone number format (Indian format)
 * @param {string} phone - Phone number to validate
 * @returns {boolean}
 */
function isValidPhone(phone) {
  if (!phone || typeof phone !== 'string') return false;
  const phoneRegex = /^[0-9+\-\s]{7,20}$/;
  return phoneRegex.test(phone.trim());
}

/**
 * Validate date string (YYYY-MM-DD format)
 * @param {string} dateStr - Date string to validate
 * @returns {boolean}
 */
function isValidDate(dateStr) {
  if (!dateStr || typeof dateStr !== 'string') return false;
  const date = new Date(dateStr);
  return !isNaN(date.getTime());
}

/**
 * Check if a date is in the future (including today)
 * @param {string} dateStr - Date string to check
 * @returns {boolean}
 */
function isFutureDate(dateStr) {
  if (!isValidDate(dateStr)) return false;
  const date = new Date(dateStr);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return date >= today;
}

/* =============================================================================
   Rendering Helpers
   ============================================================================= */

/**
 * Render a status badge HTML
 * @param {string} status - Status value (pending, confirmed, active, returned, cancelled)
 * @returns {string} HTML string for status badge
 */
function renderStatusBadge(status) {
  const normalizedStatus = (status || 'pending').toLowerCase();
  const cssClass = STATUS_CLASSES[normalizedStatus] || STATUS_CLASSES.pending;
  // cssClass is from our predefined constants, only status text needs escaping
  return `<span class="${cssClass}">${escapeHtml(normalizedStatus.toUpperCase())}</span>`;
}

/**
 * Render a loading spinner
 * @param {string} size - Spinner size (sm, md, lg)
 * @returns {string} HTML string for loading spinner
 */
function renderLoadingSpinner(size = 'md') {
  const sizeClass = size === 'sm' ? 'spinner-border-sm' : '';
  return `<div class="text-center py-4"><div class="spinner-border ${sizeClass} text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>`;
}

/**
 * Render an empty state message
 * @param {string} message - Message to display
 * @param {string} icon - Font Awesome icon class (e.g., 'fa-box-open')
 * @param {string} linkText - Optional link text
 * @param {string} linkUrl - Optional link URL (should be a relative path)
 * @returns {string} HTML string for empty state
 */
function renderEmptyState(message, icon = 'fa-inbox', linkText = '', linkUrl = '') {
  // Validate icon against allowed Font Awesome icon pattern
  const safeIcon = /^fa-[a-z0-9-]+$/i.test(icon) ? icon : 'fa-inbox';
  
  let html = `<div class="text-center py-4 text-muted">
    <i class="fas ${safeIcon} fa-3x mb-3 d-block"></i>
    <p class="mb-2">${escapeHtml(message)}</p>`;
  
  if (linkText && linkUrl) {
    // Validate linkUrl is a relative path (no dangerous protocols)
    // Block javascript:, data:, vbscript:, and any URL with protocol
    const urlLower = linkUrl.toLowerCase().trim();
    const isDangerousScheme = urlLower.startsWith('javascript:') || 
                              urlLower.startsWith('data:') || 
                              urlLower.startsWith('vbscript:') ||
                              urlLower.includes('://');
    if (!isDangerousScheme) {
      html += `<a href="${escapeHtml(linkUrl)}" class="btn btn-primary btn-sm">${escapeHtml(linkText)}</a>`;
    }
  }
  
  html += '</div>';
  return html;
}

/**
 * Render an error message
 * @param {string} message - Error message
 * @returns {string} HTML string for error message
 */
function renderErrorMessage(message) {
  return `<div class="alert alert-danger" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i>${escapeHtml(message)}
  </div>`;
}

/* =============================================================================
   Product Card Renderer
   ============================================================================= */

/**
 * Render a product card
 * @param {Object} product - Product data object
 * @returns {string} HTML string for product card
 */
function renderProductCard(product) {
  const img = (product.images && product.images[0])
    ? `${UPLOAD_BASE}/products/${encodeURIComponent(product.images[0])}`
    : `https://via.placeholder.com/400x250/6c3483/ffffff?text=${encodeURIComponent(product.name || 'Outfit')}`;
  
  const statusBadge = product.status === 'active'
    ? '<span class="badge bg-success position-absolute top-0 start-0 m-2">Available</span>'
    : '<span class="badge bg-danger position-absolute top-0 start-0 m-2">Unavailable</span>';
  
  return `
    <div class="col-md-4 col-sm-6">
      <div class="product-card">
        <div class="position-relative overflow-hidden">
          <img src="${img}" alt="${escapeHtml(product.name)}" class="card-img-top" 
               onerror="this.src='https://via.placeholder.com/400x250/6c3483/ffffff?text=No+Image'">
          <span class="price-badge">${formatCurrency(product.rental_price)}/day</span>
          ${statusBadge}
        </div>
        <div class="card-body">
          <span class="badge mb-1" style="background:var(--primary)">${escapeHtml(product.category_name || product.category_type || '')}</span>
          <h5 class="card-title">${escapeHtml(product.name)}</h5>
          <p class="text-muted small mb-2">${truncateText(product.description || '', 80)}</p>
          <div class="d-flex justify-content-between align-items-center">
            <span class="text-muted small">Deposit: ${formatCurrency(product.deposit_amount)}</span>
            <a href="product-detail.html?id=${product.id}" class="btn btn-sm btn-primary">View Details</a>
          </div>
        </div>
      </div>
    </div>`;
}

/* =============================================================================
   Pagination Renderer
   ============================================================================= */

// Whitelist of allowed pagination callback function names
const ALLOWED_PAGINATION_CALLBACKS = ['loadProducts', 'loadBookings', 'loadUsers', 'loadPayments', 'loadItems'];

/**
 * Render pagination controls
 * @param {number} currentPage - Current page number
 * @param {number} totalPages - Total number of pages
 * @param {string} onPageChange - Callback function name (must be in whitelist)
 * @returns {string} HTML string for pagination
 */
function renderPagination(currentPage, totalPages, onPageChange = 'loadProducts') {
  if (totalPages <= 1) return '';
  
  // Validate callback function name against whitelist to prevent XSS
  const safeCallback = ALLOWED_PAGINATION_CALLBACKS.includes(onPageChange) ? onPageChange : 'loadProducts';
  
  let html = '<nav><ul class="pagination justify-content-center">';
  
  // Previous button
  html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
    <a class="page-link" href="#" onclick="${safeCallback}(${currentPage - 1});return false;">Previous</a>
  </li>`;
  
  // Page numbers (show max 5 pages around current)
  const startPage = Math.max(1, currentPage - 2);
  const endPage = Math.min(totalPages, currentPage + 2);
  
  if (startPage > 1) {
    html += `<li class="page-item"><a class="page-link" href="#" onclick="${safeCallback}(1);return false;">1</a></li>`;
    if (startPage > 2) {
      html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }
  }
  
  for (let i = startPage; i <= endPage; i++) {
    html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
      <a class="page-link" href="#" onclick="${safeCallback}(${i});return false;">${i}</a>
    </li>`;
  }
  
  if (endPage < totalPages) {
    if (endPage < totalPages - 1) {
      html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }
    html += `<li class="page-item"><a class="page-link" href="#" onclick="${safeCallback}(${totalPages});return false;">${totalPages}</a></li>`;
  }
  
  // Next button
  html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
    <a class="page-link" href="#" onclick="${safeCallback}(${currentPage + 1});return false;">Next</a>
  </li>`;
  
  html += '</ul></nav>';
  return html;
}

/* =============================================================================
   Button State Management
   ============================================================================= */

/**
 * Set a button to loading state
 * @param {string|HTMLElement} selector - Button element or ID
 * @param {string} loadingText - Text to show while loading
 */
function setButtonLoading(selector, loadingText = 'Loading...') {
  const btn = typeof selector === 'string' ? document.getElementById(selector) : selector;
  if (!btn) return;
  
  btn.dataset.originalText = btn.innerHTML;
  btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>${escapeHtml(loadingText)}`;
  btn.disabled = true;
}

/**
 * Reset a button from loading state
 * @param {string|HTMLElement} selector - Button element or ID
 */
function resetButtonLoading(selector) {
  const btn = typeof selector === 'string' ? document.getElementById(selector) : selector;
  if (!btn) return;
  
  if (btn.dataset.originalText) {
    btn.innerHTML = btn.dataset.originalText;
    delete btn.dataset.originalText;
  }
  btn.disabled = false;
}

/* =============================================================================
   URL Utilities
   ============================================================================= */

/**
 * Get URL parameter value
 * @param {string} name - Parameter name
 * @returns {string|null} Parameter value or null
 */
function getUrlParam(name) {
  const params = new URLSearchParams(window.location.search);
  return params.get(name);
}

/**
 * Build a URL with query parameters
 * @param {string} baseUrl - Base URL
 * @param {Object} params - Object with parameter key-value pairs
 * @returns {string} Complete URL with query string
 */
function buildUrl(baseUrl, params = {}) {
  const url = new URL(baseUrl, window.location.origin);
  Object.entries(params).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== '') {
      url.searchParams.set(key, value);
    }
  });
  return url.toString();
}

/* =============================================================================
   Date Utilities
   ============================================================================= */

/**
 * Get today's date in YYYY-MM-DD format
 * @returns {string}
 */
function getTodayISO() {
  return new Date().toISOString().split('T')[0];
}

/**
 * Calculate number of days between two dates
 * @param {string} startDate - Start date (YYYY-MM-DD)
 * @param {string} endDate - End date (YYYY-MM-DD)
 * @returns {number} Number of days (inclusive)
 */
function calculateDays(startDate, endDate) {
  const start = new Date(startDate);
  const end = new Date(endDate);
  const diffTime = Math.abs(end - start);
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
}

/* =============================================================================
   Console Logging (Development Mode)
   ============================================================================= */

/**
 * Development-mode logging helper
 * @param {string} level - Log level (log, warn, error, info)
 * @param {string} context - Context or component name
 * @param  {...any} args - Additional arguments
 */
function devLog(level, context, ...args) {
  // Only log in development (localhost or with debug flag)
  const isDev = window.location.hostname === 'localhost' || 
                window.location.hostname === '127.0.0.1' ||
                localStorage.getItem('debug') === 'true';
  
  if (isDev && console[level]) {
    console[level](`[${context}]`, ...args);
  }
}
