window.EventApp = {
  apiBase: '/api',
  tokenKey: 'event_app_jwt',
  profileKey: 'event_app_profile',
  loginPath: '/app/login',
  userPath: '/app/reservations',
  adminPath: '/app/admin',

  getToken() {
    return localStorage.getItem(this.tokenKey) || '';
  },

  setToken(token) {
    localStorage.setItem(this.tokenKey, token);
    this.syncAuthUi();
  },

  clearToken() {
    localStorage.removeItem(this.tokenKey);
    localStorage.removeItem(this.profileKey);
    this.syncAuthUi();
  },

  setProfile(profile) {
    localStorage.setItem(this.profileKey, JSON.stringify(profile || {}));
  },

  getProfile() {
    const raw = localStorage.getItem(this.profileKey);
    if (!raw) return null;

    try {
      const parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : null;
    } catch {
      return null;
    }
  },

  hasRole(profile, role) {
    const roles = profile && Array.isArray(profile.roles) ? profile.roles : [];
    return roles.includes(role);
  },

  isLoginPage() {
    return window.location.pathname === this.loginPath;
  },

  redirectToLogin(message = '') {
    this.clearToken();
    const query = message ? `?reason=${encodeURIComponent(message)}` : '';
    window.location.href = `${this.loginPath}${query}`;
  },

  syncAuthUi() {
    const statusNode = document.getElementById('authStatus');
    const logoutButton = document.getElementById('logoutButton');
    const hasToken = Boolean(this.getToken());

    if (statusNode) {
      statusNode.textContent = hasToken ? 'Signed in' : 'Guest';
      statusNode.classList.toggle('ok', hasToken);
      statusNode.classList.toggle('warn', !hasToken);
    }

    if (logoutButton) {
      logoutButton.disabled = !hasToken;
    }

    document.querySelectorAll('[data-guest-only]').forEach((node) => {
      node.style.display = hasToken ? 'none' : '';
    });
  },

  bindLogout() {
    const logoutButton = document.getElementById('logoutButton');
    if (!logoutButton || logoutButton.dataset.bound === '1') {
      return;
    }

    logoutButton.dataset.bound = '1';
    logoutButton.addEventListener('click', () => {
      this.clearToken();
      window.location.href = '/';
    });
  },

  authHeaders(extra = {}) {
    const headers = { ...extra };
    const token = this.getToken();
    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }
    return headers;
  },

  getCsrfToken() {
    const node = document.querySelector('meta[name="api-csrf-token"]');
    return node ? node.getAttribute('content') || '' : '';
  },

  async request(path, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    const unsafe = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method);
    const headers = this.authHeaders(options.headers || {});
    if (unsafe && !headers['X-CSRF-Token']) {
      headers['X-CSRF-Token'] = this.getCsrfToken();
    }

    const response = await fetch(`${this.apiBase}${path}`, {
      ...options,
      headers,
    });

    if (response.status === 401 || response.status === 403) {
      this.redirectToLogin('Please sign in to continue.');
      throw new Error('Unauthorized request.');
    }

    const text = await response.text();
    let data = null;
    if (text) {
      try {
        data = JSON.parse(text);
      } catch {
        data = null;
      }
    }

    if (!response.ok) {
      const message = data && data.error ? data.error : `HTTP ${response.status}`;
      throw new Error(message);
    }

    return data;
  },

  async fetchProfile(force = false) {
    if (!force) {
      const cached = this.getProfile();
      if (cached) {
        return cached;
      }
    }

    if (!this.getToken()) {
      return null;
    }

    try {
      const profile = await this.request('/me');
      this.setProfile(profile);
      this.syncRoleNavigation(profile);
      return profile;
    } catch {
      this.clearToken();
      return null;
    }
  },

  syncRoleNavigation(profile = null) {
    const activeProfile = profile || this.getProfile() || { roles: [] };
    const isAdmin = this.hasRole(activeProfile, 'ROLE_ADMIN');
    const isUser = this.hasRole(activeProfile, 'ROLE_USER');

    document.querySelectorAll('[data-admin-link]').forEach((node) => {
      node.style.display = isAdmin ? '' : 'none';
    });

    document.querySelectorAll('[data-user-link]').forEach((node) => {
      node.style.display = isUser && !isAdmin ? '' : 'none';
    });
  },

  async redirectAfterLogin() {
    const profile = await this.fetchProfile(true);
    if (!profile) {
      this.redirectToLogin('Unable to load your account.');
      return;
    }

    if (this.hasRole(profile, 'ROLE_ADMIN')) {
      window.location.href = this.adminPath;
      return;
    }

    window.location.href = this.userPath;
  },

  async protectPage({ requireAdmin = false, requireUser = false } = {}) {
    const profile = await this.fetchProfile();
    if (!profile) {
      this.redirectToLogin('Please sign in first.');
      return null;
    }

    const isAdmin = this.hasRole(profile, 'ROLE_ADMIN');
    const isUser = this.hasRole(profile, 'ROLE_USER');

    if (requireAdmin && !isAdmin) {
      window.location.href = this.userPath;
      return null;
    }

    if (requireUser && isAdmin) {
      window.location.href = this.adminPath;
      return null;
    }

    if (requireUser && !isUser) {
      this.redirectToLogin('Your account is not authorized.');
      return null;
    }

    this.syncRoleNavigation(profile);
    return profile;
  },

  showProtectedPage(selector = '.protected-page') {
    const node = document.querySelector(selector);
    if (node) {
      node.classList.add('is-visible');
    }
  },

  flash(nodeId, message) {
    const node = document.getElementById(nodeId);
    if (!node) return;
    node.textContent = message;
    node.style.display = 'block';
  },

  clearFlash(nodeId) {
    const node = document.getElementById(nodeId);
    if (!node) return;
    node.textContent = '';
    node.style.display = 'none';
  },

  setLoading(button, loadingText = 'Loading...') {
    if (!button) return;
    if (!button.dataset.originalText) {
      button.dataset.originalText = button.textContent;
    }
    button.disabled = true;
    button.textContent = loadingText;
  },

  clearLoading(button) {
    if (!button) return;
    button.disabled = false;
    button.textContent = button.dataset.originalText || button.textContent;
  },

  setFieldError(inputId, message) {
    const input = document.getElementById(inputId);
    if (!input) return;

    input.classList.add('invalid');

    const container = input.closest('.field') || input.parentElement;
    if (!container) return;

    let errorNode = container.querySelector('.field-error');
    if (!errorNode) {
      errorNode = document.createElement('div');
      errorNode.className = 'field-error';
      container.appendChild(errorNode);
    }
    errorNode.textContent = message;
  },

  clearFieldError(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;

    input.classList.remove('invalid');
    const container = input.closest('.field') || input.parentElement;
    const errorNode = container ? container.querySelector('.field-error') : null;
    if (errorNode) {
      errorNode.textContent = '';
    }
  },

  isIsoDate(value) {
    if (!value || typeof value !== 'string') return false;
    const isoRegex = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?(\.\d+)?([+-]\d{2}:\d{2}|Z)$/;
    if (!isoRegex.test(value)) return false;
    const parsed = new Date(value);
    return !Number.isNaN(parsed.getTime());
  },
};

window.addEventListener('DOMContentLoaded', async () => {
  EventApp.syncAuthUi();
  EventApp.bindLogout();

  const params = new URLSearchParams(window.location.search);
  const reason = params.get('reason');
  if (reason && EventApp.isLoginPage()) {
    EventApp.flash('loginFlash', reason);
  }

  const profile = await EventApp.fetchProfile();
  if (profile) {
    EventApp.syncRoleNavigation(profile);
  }
});
