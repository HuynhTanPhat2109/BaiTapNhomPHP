(() => {
  'use strict';
  let trigger;
  document.addEventListener('click', event => {
    const button = event.target.closest('[data-booking-action]');
    if (!button) return;
    const dialog = document.getElementById(button.dataset.bookingTarget);
    const form = dialog?.querySelector(`[data-booking-form="${CSS.escape(button.dataset.bookingAction)}"]`);
    if (!form) return;
    trigger = button.closest('[data-row-actions]')?.querySelector('summary') || button;
    document.dispatchEvent(new Event('workspace:close-row-menu'));
    dialog.querySelectorAll('[data-booking-form]').forEach(item => { item.hidden = item !== form; });
    dialog.querySelector('h2').textContent = form.querySelector('h3').textContent;
    dialog.showModal();
  });
  document.querySelectorAll('[data-booking-dialog]').forEach(dialog => {
    dialog.querySelectorAll('[data-booking-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => trigger?.focus({ preventScroll: true }));
    dialog.addEventListener('click', event => {
      const rect = dialog.getBoundingClientRect();
      if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
    });
  });
})();
