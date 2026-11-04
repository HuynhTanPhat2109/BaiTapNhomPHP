/* Native links keep navigation, modified clicks and history behavior intact. */
(() => {
  'use strict';
  const header = document.querySelector('.stryke-header');
  if (!header) return;
  const menu = header.querySelector('#nav-menu');
  const toggle = header.querySelector('#nav-toggle');
  const account = header.querySelector('#account-menu');
  const compact = window.matchMedia('(max-width: 1100px)');
  document.documentElement.classList.add('nav-enhanced');
  toggle.hidden = false;

  const setOpen = open => {
    menu.classList.toggle('is-open', open);
    menu.inert = compact.matches && !open;
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Đóng menu' : 'Mở menu');
    if (!open && account) account.open = false;
  };
  toggle.addEventListener('click', () => setOpen(!menu.classList.contains('is-open')));
  document.addEventListener('click', event => {
    if (!header.contains(event.target)) setOpen(false);
  });
  header.addEventListener('keydown', event => {
    if (event.key === 'Escape' && menu.classList.contains('is-open') && !account?.open) {
      event.preventDefault();
      setOpen(false);
      toggle.focus();
    }
  });
  menu.addEventListener('click', event => {
    if (event.target.closest('a[href]')) setOpen(false);
  });
  header.addEventListener('focusout', () => requestAnimationFrame(() => {
    if (!header.contains(document.activeElement)) setOpen(false);
  }));
  compact.addEventListener('change', () => setOpen(false));
  window.addEventListener('pageshow', () => setOpen(false));
  setOpen(false);
})();
