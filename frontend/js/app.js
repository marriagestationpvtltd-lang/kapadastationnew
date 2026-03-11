/**
 * Kapada Station - Global Application Utilities
 * 
 * This file contains shared utility functions used across all pages.
 * Include this file in all HTML pages after config.js and auth.js.
 * 
 * @package KapadaStation
 * @version 1.0.0
 */

// =============================================================================
// Alert / Notification System
// =============================================================================

/**
 * Show an alert message
 * 
 * Displays a dismissible Bootstrap alert that auto-hides after 5 seconds.
 * 
 * @param {string} message - Message to display
 * @param {string} type - Alert type: 'success', 'danger', 'warning', 'info'
 * 
 * @example
 * showAlert('Profile saved successfully!', 'success');
 * showAlert('Please fill all required fields.', 'warning');
 */
function showAlert(message, type = 'success') {
  const container = document.getElementById('alert-container');
  if (!container) return;
  
  const id = 'alert-' + Date.now();
  const iconMap = {
    success: 'check-circle',
    danger: 'exclamation-circle',
    warning: 'exclamation-triangle',
    info: 'info-circle'
  };
  const icon = iconMap[type] || 'info-circle';
  
  const html = `
    <div id="${id}" class="alert alert-${type} alert-dismissible fade show d-flex align-items-center" role="alert">
      <i class="fas fa-${icon} me-2"></i>
      <span>${escapeHtml(message)}</span>
      <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>`;
  
  container.insertAdjacentHTML('beforeend', html);
  
  // Auto-dismiss after 5 seconds
  setTimeout(() => {
    const el = document.getElementById(id);
    if (el) el.remove();
  }, 5000);
}

// =============================================================================
// Loading State Management
// =============================================================================

/**
 * Show loading spinner in an element
 * 
 * @param {string|HTMLElement} selector - Element ID or HTMLElement
 */
function showLoading(selector) {
  const el = typeof selector === 'string' ? document.querySelector(selector) : selector;
  if (!el) return;
  
  el.dataset.originalContent = el.innerHTML;
  el.innerHTML = `
    <div class="d-flex justify-content-center align-items-center py-4">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
    </div>`;
}

/**
 * Hide loading spinner and restore content
 * 
 * @param {string|HTMLElement} selector - Element ID or HTMLElement
 * @param {string} content - Optional new content (uses original if not provided)
 */
function hideLoading(selector, content) {
  const el = typeof selector === 'string' ? document.querySelector(selector) : selector;
  if (!el) return;
  
  el.innerHTML = content !== undefined ? content : (el.dataset.originalContent || '');
}

// =============================================================================
// Formatting Functions
// =============================================================================

/**
 * Format a number as Indian Rupees currency
 * 
 * @param {number|string} amount - Amount to format
 * @returns {string} Formatted currency string (e.g., '₹1,500')
 */
function formatCurrency(amount) {
  const num = parseFloat(amount) || 0;
  return '₹' + num.toLocaleString('en-IN', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  });
}

/**
 * Format a date string
 * 
 * @param {string} dateStr - Date string (ISO format)
 * @returns {string} Formatted date (e.g., '15 Mar 2024')
 */
function formatDate(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-IN', {
    day: '2-digit',
    month: 'short',
    year: 'numeric'
  });
}

/**
 * Truncate text to a maximum length
 * 
 * @param {string} text - Text to truncate
 * @param {number} length - Maximum length (default: 100)
 * @returns {string} Truncated text with ellipsis if needed
 */
function truncateText(text, length = 100) {
  if (!text) return '';
  return text.length > length ? text.substring(0, length) + '...' : text;
}

// =============================================================================
// Security Functions
// =============================================================================

/**
 * Escape HTML special characters to prevent XSS
 * 
 * @param {string} str - String to escape
 * @returns {string} HTML-escaped string
 */
function escapeHtml(str) {
  const div = document.createElement('div');
  div.appendChild(document.createTextNode(str || ''));
  return div.innerHTML;
}

// =============================================================================
// Navigation Helpers
// =============================================================================

/**
 * Determine current page depth for path calculations
 * @returns {string} 'admin', 'pages', or 'root'
 */
function getDepth() {
  const path = window.location.pathname;
  if (path.includes('/pages/admin/')) return 'admin';
  if (path.includes('/pages/')) return 'pages';
  return 'root';
}

/**
 * Get relative path to site root
 * @returns {string} Relative path (e.g., '../../', '../', '')
 */
function getRootPath() {
  const d = getDepth();
  if (d === 'admin') return '../../';
  if (d === 'pages') return '../';
  return '';
}

/**
 * Get relative path to pages directory
 * @returns {string} Relative path
 */
function getPagesPath() {
  const d = getDepth();
  if (d === 'admin') return '../';
  if (d === 'pages') return '';
  return 'pages/';
}

/**
 * Get relative path to admin directory
 * @returns {string} Relative path
 */
function getAdminPath() {
  const d = getDepth();
  if (d === 'admin') return '';
  if (d === 'pages') return 'admin/';
  return 'pages/admin/';
}

/**
 * Get path to login page
 * @returns {string} Login page path
 */
function getLoginPath() {
  return getPagesPath() + 'login.html';
}

/**
 * Get path to register page
 * @returns {string} Register page path
 */
function getRegisterPath() {
  return getPagesPath() + 'register.html';
}

/**
 * Get path to profile page
 * @returns {string} Profile page path
 */
function getProfilePath() {
  return getPagesPath() + 'profile.html';
}

/**
 * Get path to bookings section
 * @returns {string} Bookings page path with anchor
 */
function getBookingPath() {
  return getPagesPath() + 'profile.html#bookings';
}

// =============================================================================
// Navigation Bar Initialization
// =============================================================================

/**
 * Initialize the navigation bar based on authentication state
 * 
 * Updates the #nav-auth-links element with appropriate links
 * for logged-in users or guests.
 */
function initNav() {
  const container = document.getElementById('nav-auth-links');
  if (!container) return;

  if (isLoggedIn()) {
    const user = getUser();
    const name = user ? (user.full_name || user.name || user.email) : 'Account';
    const adminLink = isAdmin()
      ? `<li><a class="dropdown-item" href="${getAdminPath()}dashboard.html"><i class="fas fa-cog me-2"></i>Admin Panel</a></li><li><hr class="dropdown-divider"></li>`
      : '';

    container.innerHTML = `
      <ul class="navbar-nav">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle d-flex align-items-center gap-1" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
            <i class="fas fa-user-circle fs-5"></i>
            <span>${escapeHtml(name)}</span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            ${adminLink}
            <li><a class="dropdown-item" href="${getProfilePath()}"><i class="fas fa-user me-2"></i>My Profile</a></li>
            <li><a class="dropdown-item" href="${getBookingPath()}"><i class="fas fa-box me-2"></i>My Bookings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><button class="dropdown-item text-danger" id="logout-btn"><i class="fas fa-sign-out-alt me-2"></i>Logout</button></li>
          </ul>
        </li>
      </ul>`;
  } else {
    container.innerHTML = `
      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link" href="${getLoginPath()}"><i class="fas fa-sign-in-alt me-1"></i>Login</a></li>
        <li class="nav-item"><a class="nav-link btn btn-sm ms-2 px-3" style="background:var(--accent);color:white;border-radius:20px" href="${getRegisterPath()}"><i class="fas fa-user-plus me-1"></i>Register</a></li>
      </ul>`;
  }
}

// =============================================================================
// DOM Ready Initialization
// =============================================================================

document.addEventListener('DOMContentLoaded', function () {
  // Initialize navigation
  initNav();

  // Global logout handler - works on all pages including admin
  // (admin pages have sidebar logout button without #nav-auth-links)
  document.addEventListener('click', function (e) {
    if (e.target && (e.target.id === 'logout-btn' || e.target.closest('#logout-btn'))) {
      clearAuth();
      window.location.href = getRootPath() + 'index.html';
    }
  });
});
