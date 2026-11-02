/* Sidebar interaction only; forms and authentication keep their existing flow. */
(() => {
  'use strict';
  const sidebar = document.getElementById('admin-sidebar');
  const main = document.getElementById('admin-main');
  const toggle = document.getElementById('admin-menu-toggle');
  const close = document.getElementById('admin-sidebar-close');
  const backdrop = document.getElementById('admin-backdrop');
  if (!sidebar || !main || !toggle || !close || !backdrop) return;
  const mobile = window.matchMedia('(max-width: 1023px)');
  const collapse = document.querySelector('[data-sidebar-collapse]');
  let compact = false;
  try { compact = localStorage.getItem('stryke.sidebar.compact') === '1'; } catch {}
  let opened = false;
  document.body.classList.add('sidebar-enhanced');

  const setOpen = (value, restoreFocus = false) => {
    opened = mobile.matches && value;
    document.body.classList.toggle('sidebar-open', opened);
    toggle.setAttribute('aria-expanded', String(opened));
    toggle.setAttribute('aria-label', opened ? 'Đóng menu quản trị' : 'Mở menu quản trị');
    backdrop.hidden = !opened;
    // Release the focus destination before making the other region inert.
    if (opened) {
      sidebar.inert = false;
      requestAnimationFrame(() => {
        if (opened) close.focus({ preventScroll: true });
      });
    } else if (restoreFocus) {
      main.inert = false;
      toggle.focus();
    }
    main.inert = opened;
    sidebar.inert = mobile.matches && !opened;
    if (opened) {
      sidebar.setAttribute('role', 'dialog');
      sidebar.setAttribute('aria-modal', 'true');
    } else {
      sidebar.removeAttribute('role');
      sidebar.removeAttribute('aria-modal');
    }
  };
  const resize = () => {
    document.body.classList.toggle('sidebar-compact', !mobile.matches && compact);
    if (collapse) {
      collapse.hidden = mobile.matches;
      collapse.setAttribute('aria-expanded', String(!compact));
      collapse.setAttribute('aria-label', compact ? 'Mở rộng Sidebar' : 'Thu gọn Sidebar');
    }
    toggle.hidden = !mobile.matches;
    close.hidden = !mobile.matches;
    setOpen(false);
    if (mobile.matches && sidebar.contains(document.activeElement)) toggle.focus();
  };
  toggle.addEventListener('click', () => setOpen(!opened, opened));
  collapse?.addEventListener('click', () => {
    compact = !compact;
    try { localStorage.setItem('stryke.sidebar.compact', compact ? '1' : '0'); } catch {}
    resize();
  });
  close.addEventListener('click', () => setOpen(false, true));
  backdrop.addEventListener('click', () => setOpen(false, true));
  sidebar.addEventListener('click', event => {
    if (opened && event.target.closest('a[href]')) setOpen(false, true);
  });
  document.addEventListener('keydown', event => {
    if (!opened) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      setOpen(false, true);
    } else if (event.key === 'Tab') {
      const links = [...sidebar.querySelectorAll('a[href], button:not([hidden])')];
      const first = links[0];
      const last = links[links.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }
  });
  mobile.addEventListener('change', resize);
  window.addEventListener('pageshow', resize);
  resize();
})();

// Confirmation UI only; native POST fields, CSRF and validation remain on the original form.
(() => {
  'use strict';
  const dialog = document.getElementById('workspace-confirm');
  if (!dialog || typeof dialog.showModal !== 'function') return;
  let pending = null, approved = null, returnFocus = null;
  document.addEventListener('submit', event => {
    const form = event.target;
    if (!form.matches('form[data-confirm]')) return;
    if (approved === form) { approved = null; return; }
    event.preventDefault();
    if (dialog.open) return;
    pending = { form, submitter: event.submitter };
    returnFocus = form.closest('[data-row-actions]')?.querySelector('summary') || event.submitter;
    dialog.querySelector('#workspace-confirm-description').textContent = form.dataset.confirm;
    document.dispatchEvent(new Event('workspace:close-row-menu'));
    dialog.showModal();
  });
  dialog.querySelector('[data-confirm-cancel]').addEventListener('click', () => dialog.close());
  dialog.querySelector('[data-confirm-accept]').addEventListener('click', () => {
    const request = pending;
    if (!request) return;
    approved = request.form;
    dialog.close();
    request.form.requestSubmit(request.submitter || undefined);
    approved = null;
  });
  dialog.addEventListener('close', () => { pending = null; returnFocus?.focus({ preventScroll: true }); });
  dialog.addEventListener('click', event => {
    const box = dialog.getBoundingClientRect();
    if (event.target === dialog && (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom)) dialog.close();
  });
})();
