/* Progressive enhancement only: no fetch, no form interception, no stored passwords. */
(() => {
  'use strict';
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const menu = document.getElementById('nav-menu');
  const toggle = document.getElementById('nav-toggle');
  const accountMenu = document.getElementById('account-menu');
  const accountTrigger = accountMenu?.querySelector('summary');
  if (menu && toggle && !menu.closest('.stryke-header')) {
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

// Shared row actions. Popovers escape table overflow without moving links/forms.
(() => {
  'use strict';
  const menus = [...document.querySelectorAll('[data-row-actions]')];
  if (!menus.length) return;
  const nativePopover = 'showPopover' in HTMLElement.prototype;
  let active = null;
  const panelOf = menu => menu.querySelector('.list-action-menu__panel');
  const controlsOf = panel => [...panel.querySelectorAll('a[href], button:not([disabled]), select:not([disabled])')];
  const triggerOf = menu => menu.querySelector('summary');
  const close = (restoreFocus = false) => {
    if (!active) return;
    const menu = active;
    active = null;
    const panel = panelOf(menu);
    if (nativePopover && panel.matches(':popover-open')) panel.hidePopover();
    menu.open = false;
    triggerOf(menu).setAttribute('aria-expanded', 'false');
    if (restoreFocus) triggerOf(menu).focus({ preventScroll: true });
  };
  const position = menu => {
    if (!nativePopover) return;
    const panel = panelOf(menu), anchor = triggerOf(menu).getBoundingClientRect();
    const below = innerHeight - anchor.bottom >= panel.offsetHeight + 8;
    panel.dataset.side = below ? 'below' : 'above';
    panel.style.left = Math.max(8, Math.min(anchor.right - panel.offsetWidth, innerWidth - panel.offsetWidth - 8)) + 'px';
    panel.style.top = Math.max(8, Math.min(below ? anchor.bottom + 6 : anchor.top - panel.offsetHeight - 6, innerHeight - panel.offsetHeight - 8)) + 'px';
  };
  const open = (menu, last = false) => {
    close();
    active = menu;
    menu.open = true;
    triggerOf(menu).setAttribute('aria-expanded', 'true');
    if (nativePopover) panelOf(menu).showPopover();
    position(menu);
    const links = controlsOf(panelOf(menu));
    (last ? links.at(-1) : links[0])?.focus({ preventScroll: true });
  };
  menus.forEach(menu => {
    const trigger = triggerOf(menu), panel = panelOf(menu);
    if (nativePopover) {
      panel.setAttribute('popover', 'auto');
      panel.addEventListener('toggle', event => {
        if (event.newState === 'closed' && active === menu) close();
      });
    }
    trigger.addEventListener('click', event => {
      event.preventDefault();
      if (active === menu) close(true); else open(menu);
    });
    trigger.addEventListener('keydown', event => {
      if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
      event.preventDefault();
      open(menu, event.key === 'ArrowUp');
    });
    panel.addEventListener('keydown', event => {
      if (event.target.matches('select, input, textarea')) return;
      const links = controlsOf(panel), index = links.indexOf(document.activeElement);
      if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
      event.preventDefault();
      const next = event.key === 'Home' ? 0 : event.key === 'End' ? links.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
      links[next]?.focus();
    });
    menu.addEventListener('focusout', () => requestAnimationFrame(() => {
      if (active === menu && !menu.contains(document.activeElement)) close();
    }));
  });
  document.addEventListener('click', event => {
    if (active && !active.contains(event.target)) close();
  });
  document.addEventListener('keydown', event => {
    if (active && event.key === 'Escape') { event.preventDefault(); close(true); }
  });
  document.addEventListener('scroll', event => {
    if (active && !panelOf(active).contains(event.target)) position(active);
  }, true);
  window.addEventListener('resize', () => close());
  window.addEventListener('pageshow', () => close());
  document.addEventListener('workspace:close-row-menu', () => close());
})();
