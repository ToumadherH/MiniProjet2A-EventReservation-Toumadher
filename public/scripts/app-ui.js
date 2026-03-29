window.EventApp = {
  apiBase: '/api',
  tokenKey: 'event_app_jwt',
  loginPath: '/app/login',

  getToken() {
    return localStorage.getItem(this.tokenKey) || '';
  },

  setToken(token) {
    localStorage.setItem(this.tokenKey, token);
    this.syncAuthUi();
  },

  clearToken() {
    localStorage.removeItem(this.tokenKey);
    this.syncAuthUi();
  },

  isLoginPage() {
    return window.location.pathname === this.loginPath;
  },

  redirectToLogin(message = '') {
    this.clearToken();
    if (this.isLoginPage()) {
      if (message) this.flash('loginFlash', message);
      return;
    }

    const query = message ? `?reason=${encodeURIComponent(message)}` : '';
    window.location.href = `${this.loginPath}${query}`;
  },

  syncAuthUi() {
    const statusNode = document.getElementById('authStatus');
    const logoutButton = document.getElementById('logoutButton');
    const hasToken = Boolean(this.getToken());

    if (statusNode) {
      statusNode.textContent = hasToken ? 'Authenticated' : 'Not authenticated';
      statusNode.classList.toggle('ok', hasToken);
      statusNode.classList.toggle('warn', !hasToken);
    }

    if (logoutButton) {
      logoutButton.disabled = !hasToken;
    }
  },

  bindLogout() {
    const logoutButton = document.getElementById('logoutButton');
    if (!logoutButton || logoutButton.dataset.bound === '1') {
      return;
    }

    logoutButton.dataset.bound = '1';
    logoutButton.addEventListener('click', () => {
      this.redirectToLogin('Session closed. Please sign in again.');
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

  async request(path, options = {}) {
    const response = await fetch(`${this.apiBase}${path}`, {
      ...options,
      headers: this.authHeaders(options.headers || {}),
    });

    if (response.status === 401 || response.status === 403) {
      this.redirectToLogin('Your session expired or you are not authorized.');
      throw new Error('Unauthorized request.');
    }

    const text = await response.text();
    const data = text ? JSON.parse(text) : null;

    if (!response.ok) {
      const message = data && data.error ? data.error : `HTTP ${response.status}`;
      throw new Error(message);
    }

    return data;
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

window.addEventListener('DOMContentLoaded', () => {
  EventApp.syncAuthUi();
  EventApp.bindLogout();

  const params = new URLSearchParams(window.location.search);
  const reason = params.get('reason');
  if (reason && EventApp.isLoginPage()) {
    EventApp.flash('loginFlash', reason);
  }
});
