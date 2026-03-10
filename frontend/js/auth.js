// Auth utility functions for Kapada Station

function getToken() {
  return localStorage.getItem('kapada_token') || '';
}

function getUser() {
  try {
    const u = localStorage.getItem('kapada_user');
    return u ? JSON.parse(u) : null;
  } catch (e) {
    return null;
  }
}

function isLoggedIn() {
  const token = getToken();
  return token && token.length > 0;
}

function isAdmin() {
  const user = getUser();
  return user && user.role === 'admin';
}

function setAuth(token, user) {
  localStorage.setItem('kapada_token', token);
  localStorage.setItem('kapada_user', JSON.stringify(user));
}

function clearAuth() {
  localStorage.removeItem('kapada_token');
  localStorage.removeItem('kapada_user');
}

function authHeaders() {
  return {
    'Authorization': 'Bearer ' + getToken(),
    'Content-Type': 'application/json'
  };
}

async function fetchAPI(path, options = {}) {
  try {
    const url = API_BASE + path;
    const headers = Object.assign(authHeaders(), options.headers || {});
    const response = await fetch(url, Object.assign({}, options, { headers }));

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

function getLoginUrl(returnUrl) {
  // Determine relative path to login based on current page depth
  const path = window.location.pathname;
  const depth = (path.match(/\//g) || []).length;
  // Rough heuristic: if in pages/admin, use ../../pages/login.html
  // if in pages/, use ../pages/login.html or login.html
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

// Page protection: run on DOMContentLoaded
document.addEventListener('DOMContentLoaded', function () {
  const body = document.body;

  if (body.dataset.requiresAdmin === 'true') {
    if (!isLoggedIn() || !isAdmin()) {
      clearAuth();
      window.location.href = getLoginUrl();
      return;
    }
  }

  if (body.dataset.requiresAuth === 'true') {
    if (!isLoggedIn()) {
      window.location.href = getLoginUrl();
      return;
    }
  }
});
