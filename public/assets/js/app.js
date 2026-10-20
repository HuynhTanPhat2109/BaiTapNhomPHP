/* Progressive enhancement only: no fetch, no form interception, no stored passwords. */
(() => {
  'use strict';
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const menu = document.getElementById('nav-menu');
  const toggle = document.getElementById('nav-toggle');
  const accountMenu = document.getElementById('account-menu');
  const accountTrigger = accountMenu?.querySelector('summary');
  if (menu && toggle) {
    document.documentElement.classList.add('nav-enhanced');
    toggle.hidden = false;
    const closeMenu = () => {
      menu.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', 'Mở menu');
      if (accountMenu) accountMenu.open = false;
    };
    toggle.addEventListener('click', () => {
      const opened = menu.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', String(opened));
      toggle.setAttribute('aria-label', opened ? 'Đóng menu' : 'Mở menu');
    });
    document.addEventListener('click', event => {
      if (!menu.contains(event.target) && !toggle.contains(event.target)) closeMenu();
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && menu.classList.contains('is-open') && !accountMenu?.open) {
        closeMenu();
        toggle.focus();
      }
    });
  }
  if (accountMenu && accountTrigger) {
    document.addEventListener('click', event => {
      if (!accountMenu.contains(event.target)) accountMenu.open = false;
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && accountMenu.open) {
        accountMenu.open = false;
        accountTrigger.focus();
      }
    });
  }
  const eye = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
  const eyeOff = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.5 5.1 12 5c6.5 0 10 7 10 7a22 22 0 0 1-3 4M6.2 6.2A24 24 0 0 0 2 12s3.5 7 10 7a12 12 0 0 0 5.8-1.8M10 10a3 3 0 0 0 4 4"/></svg>';
  document.querySelectorAll('.pw-toggle').forEach(button => {
    const input = document.getElementById(button.dataset.target);
    if (!input) return;
    button.hidden = false;
    button.innerHTML = eye;
    button.setAttribute('aria-pressed', 'false');
    button.addEventListener('click', () => {
      const showing = input.type === 'password';
      input.type = showing ? 'text' : 'password';
      button.innerHTML = showing ? eyeOff : eye;
      button.setAttribute('aria-label', showing ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
      button.setAttribute('aria-pressed', String(showing));
    });
  });
  const invalidAccountField = document.querySelector('.account-page [aria-invalid="true"]');
  if (invalidAccountField) invalidAccountField.focus({ preventScroll: false });
  const shell = document.querySelector('[data-auth-shell]');
  if (!shell) return;
  // Place keyboard focus at the first relevant feedback after a server response.
  const firstInvalid = shell.querySelector('[aria-invalid="true"]');
  const formFeedback = shell.querySelector('.login-error, .alert--danger, .alert--success');
  if (firstInvalid) {
    firstInvalid.focus({ preventScroll: false });
  } else if (formFeedback) {
    formFeedback.setAttribute('tabindex', '-1');
    formFeedback.focus({ preventScroll: false });
  }
  const hasFeedback = shell.querySelector('[role="alert"], [role="status"]');
  if (!reducedMotion.matches && !hasFeedback) shell.classList.add('auth-enter');
  let navigating = false;
  let submitting = false;
  let navigationTimer;
  shell.querySelectorAll('form').forEach(form => form.addEventListener('submit', () => {
    submitting = true;
    window.clearTimeout(navigationTimer);
    shell.classList.remove('auth-leaving');
  }));
  document.querySelectorAll('[data-auth-link]').forEach(link => {
    link.addEventListener('click', event => {
      if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
          || link.target === '_blank' || reducedMotion.matches || submitting) return;
      const target = new URL(link.href, location.href);
      if (target.origin !== location.origin || target.href === location.href) return;
      if (navigating) { event.preventDefault(); return; }
      event.preventDefault();
      navigating = true;
      shell.classList.remove('auth-enter');
      shell.classList.add('auth-leaving');
      navigationTimer = window.setTimeout(() => location.assign(target.href), 180);
    });
  });
  window.addEventListener('pageshow', () => {
    navigating = false;
    submitting = false;
    shell.classList.remove('auth-leaving');
  });
})();
