// Global utilities for Kapada Station

function showAlert(message, type = 'success') {
  const container = document.getElementById('alert-container');
  if (!container) return;
  const id = 'alert-' + Date.now();
  const iconMap = { success: 'check-circle', danger: 'exclamation-circle', warning: 'exclamation-triangle', info: 'info-circle' };
  const icon = iconMap[type] || 'info-circle';
  const html = `
    <div id="${id}" class="alert alert-${type} alert-dismissible fade show d-flex align-items-center" role="alert">
      <i class="fas fa-${icon} me-2"></i>
      <span>${message}</span>
      <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>`;
  container.insertAdjacentHTML('beforeend', html);
  setTimeout(() => {
    const el = document.getElementById(id);
    if (el) el.remove();
  }, 5000);
}

function showLoading(selector) {
  const el = typeof selector === 'string' ? document.querySelector(selector) : selector;
  if (!el) return;
  el.dataset.originalContent = el.innerHTML;
  el.innerHTML = '<div class="d-flex justify-content-center align-items-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
}

function hideLoading(selector, content) {
  const el = typeof selector === 'string' ? document.querySelector(selector) : selector;
  if (!el) return;
  el.innerHTML = content !== undefined ? content : (el.dataset.originalContent || '');
}

function formatCurrency(amount) {
  const num = parseFloat(amount) || 0;
  return '₹' + num.toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function truncateText(text, length = 100) {
  if (!text) return '';
  return text.length > length ? text.substring(0, length) + '...' : text;
}

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

  // Logout handler
  document.addEventListener('click', function (e) {
    if (e.target && (e.target.id === 'logout-btn' || e.target.closest('#logout-btn'))) {
      clearAuth();
      window.location.href = getRootPath() + 'index.html';
    }
  });
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.appendChild(document.createTextNode(str || ''));
  return div.innerHTML;
}

// Path helpers based on current page location
function getDepth() {
  const path = window.location.pathname;
  if (path.includes('/pages/admin/')) return 'admin';
  if (path.includes('/pages/')) return 'pages';
  return 'root';
}

function getRootPath() {
  const d = getDepth();
  if (d === 'admin') return '../../';
  if (d === 'pages') return '../';
  return '';
}

function getPagesPath() {
  const d = getDepth();
  if (d === 'admin') return '../';
  if (d === 'pages') return '';
  return 'pages/';
}

function getAdminPath() {
  const d = getDepth();
  if (d === 'admin') return '';
  if (d === 'pages') return 'admin/';
  return 'pages/admin/';
}

function getLoginPath() {
  return getPagesPath() + 'login.html';
}

function getRegisterPath() {
  return getPagesPath() + 'register.html';
}

function getProfilePath() {
  return getPagesPath() + 'profile.html';
}

function getBookingPath() {
  return getPagesPath() + 'profile.html#bookings';
}

document.addEventListener('DOMContentLoaded', function () {
  initNav();
});
