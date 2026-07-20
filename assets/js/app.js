/* AK Menu System - shared front-end helpers (jQuery + vanilla). */
(function (window, $) {
  'use strict';

  // CSRF token pulled from a meta tag rendered by the layout.
  const CSRF = document.querySelector('meta[name="csrf-token"]');
  const AK = {
    csrf: CSRF ? CSRF.getAttribute('content') : '',
    base: (document.querySelector('meta[name="base-url"]') || {}).content || '',
  };

  /** Standard JSON POST to an API endpoint. Returns a Promise. */
  AK.post = function (url, data) {
    const payload = data instanceof FormData ? data : Object.assign({}, data);
    if (payload instanceof FormData) {
      payload.append('csrf_token', AK.csrf);
    } else {
      payload.csrf_token = AK.csrf;
    }
    return fetch(url, {
      method: 'POST',
      headers: payload instanceof FormData
        ? { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': AK.csrf }
        : { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': AK.csrf, 'Content-Type': 'application/x-www-form-urlencoded' },
      body: payload instanceof FormData ? payload : new URLSearchParams(payload).toString(),
    }).then(r => r.json());
  };

  AK.get = function (url) {
    return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json());
  };

  /** Toast helper (uses Toastr if present, else alert fallback). */
  AK.toast = function (type, msg) {
    if (window.toastr) { toastr[type] ? toastr[type](msg) : toastr.info(msg); }
    else { console.log('[' + type + ']', msg); }
  };

  /** SweetAlert2 confirm wrapper returning a Promise<boolean>. */
  AK.confirm = function (text, title) {
    if (window.Swal) {
      return Swal.fire({
        title: title || 'Are you sure?',
        text: text || '',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'var(--primary)',
        confirmButtonText: 'Yes',
      }).then(r => r.isConfirmed);
    }
    return Promise.resolve(window.confirm(text || 'Are you sure?'));
  };

  /** Handle a standard {status,message,data} response. */
  AK.handle = function (res, onSuccess) {
    if (res && res.status === 'success') {
      AK.toast('success', res.message || 'Done');
      if (onSuccess) onSuccess(res.data || {});
    } else {
      AK.toast('error', (res && res.message) || 'Something went wrong');
    }
  };

  // Dark-mode toggle persisted in localStorage.
  AK.toggleTheme = function () {
    const cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', cur);
    localStorage.setItem('ak-theme', cur);
  };
  (function () {
    const saved = localStorage.getItem('ak-theme');
    if (saved) document.documentElement.setAttribute('data-theme', saved);
  })();

  // Mobile sidebar toggle.
  document.addEventListener('click', function (ev) {
    if (ev.target.closest('.sidebar-toggle')) {
      document.querySelector('.sidebar')?.classList.toggle('open');
    }
  });

  window.AK = AK;
})(window, window.jQuery || function () {});
