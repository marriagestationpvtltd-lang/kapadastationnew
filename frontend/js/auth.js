/**
 * Kapada Station - Authentication Utilities
 * 
 * This file contains authentication-related functions for the frontend.
 * Handles JWT token storage, validation, and API communication.
 * 
 * @package KapadaStation
 * @version 1.0.0
 */

// ─── Storage Keys ────────────────────────────────────────────────────────────
const TOKEN_KEY = 'kapada_token';
const USER_KEY = 'kapada_user';

// ─── Token Management ────────────────────────────────────────────────────────

/**
 * Get the stored JWT token
 * @returns {string} The JWT token or empty string
 */
function getToken() {
  return localStorage.getItem(TOKEN_KEY) || '';
}

/**
 * Get the stored user object
 * @returns {Object|null} The user object or null
 */
function getUser() {
  try {
    const u = localStorage.getItem(USER_KEY);
    return u ? JSON.parse(u) : null;
  } catch (e) {
    console.error('Error parsing user data:', e);
    return null;
  }
}

/**
 * Check if user is currently logged in
 * @returns {boolean}
 */
function isLoggedIn() {
  const token = getToken();
  return token && token.length > 0;
}

/**
 * Check if current user has admin role
 * @returns {boolean}
 */
function isAdmin() {
  const user = getUser();
  return user && user.role === 'admin';
}

/**
 * Save authentication data
 * @param {string} token - JWT token
 * @param {Object} user - User object
 */
function setAuth(token, user) {
  localStorage.setItem(TOKEN_KEY, token);
  localStorage.setItem(USER_KEY, JSON.stringify(user));
}

/**
 * Clear all authentication data (logout)
 */
function clearAuth() {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(USER_KEY);
}

// ─── API Communication ───────────────────────────────────────────────────────

/**
 * Get authorization headers for API requests
 * @returns {Object} Headers object with Authorization and Content-Type
 */
function authHeaders() {
  return {
    'Authorization': 'Bearer ' + getToken(),
    'Content-Type': 'application/json'
  };
}

/**
 * Make an authenticated API request
 * 
 * Automatically handles 401 responses by clearing auth and redirecting to login.
 * 
 * @param {string} path - API endpoint path (e.g., '/auth/profile.php')
 * @param {Object} options - Fetch options (method, body, etc.)
 * @returns {Promise<Object|null>} Response data or null on auth failure
 * 
 * @example
 * const data = await fetchAPI('/auth/profile.php');
 * const result = await fetchAPI('/bookings/create.php', {
 *   method: 'POST',
 *   body: JSON.stringify({ product_id: 1 })
 * });
 */
async function fetchAPI(path, options = {}) {
  try {
    const url = API_BASE + path;
    const headers = Object.assign(authHeaders(), options.headers || {});
    const response = await fetch(url, Object.assign({}, options, { headers }));

    // Handle authentication failure
    if (response.status === 401) {
      clearAuth();
      const loginUrl = getLoginUrl();
      window.location.href = loginUrl;
      return null;
    }

    return await response.json();
  } catch (err) {
    console.error('fetchAPI error:', err);
    return { success: false, message: 'Network error. Please try again.' };
  }
}

// ─── URL Helpers ─────────────────────────────────────────────────────────────

/**
 * Get the login URL with optional return URL
 * @param {string} returnUrl - URL to return to after login
 * @returns {string} Login page URL
 */
function getLoginUrl(returnUrl) {
  // Determine relative path to login based on current page depth
  const path = window.location.pathname;
  let loginPath;
  
  if (path.includes('/pages/admin/')) {
    loginPath = '../../pages/login.html';
  } else if (path.includes('/pages/')) {
    loginPath = 'login.html';
  } else {
    loginPath = 'pages/login.html';
  }
  
  const ret = returnUrl || encodeURIComponent(window.location.href);
  return loginPath + '?returnUrl=' + ret;
}

// ─── Page Protection ─────────────────────────────────────────────────────────

/**
 * Initialize page protection based on data attributes
 * 
 * Pages can use these attributes on the body tag:
 * - data-requires-auth="true" - Requires logged-in user
 * - data-requires-admin="true" - Requires admin user
 */
document.addEventListener('DOMContentLoaded', function () {
  const body = document.body;

  // Check admin requirement first (more restrictive)
  if (body.dataset.requiresAdmin === 'true') {
    if (!isLoggedIn() || !isAdmin()) {
      clearAuth();
      window.location.href = getLoginUrl();
      return;
    }
  }

  // Check basic auth requirement
  if (body.dataset.requiresAuth === 'true') {
    if (!isLoggedIn()) {
      window.location.href = getLoginUrl();
      return;
    }
  }
});
