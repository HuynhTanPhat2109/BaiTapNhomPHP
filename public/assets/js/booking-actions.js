(() => {
  'use strict';
  let trigger;
  const viewer = document.getElementById('booking-receipt-viewer');
  let imageTrigger;
  document.addEventListener('click', event => {
    const link = event.target.closest('[data-receipt-open]');
    if (!link || !viewer) return;
    event.preventDefault();
    imageTrigger = link;
    viewer.querySelector('img').src = link.href;
    viewer.classList.remove('is-zoomed');
    viewer.querySelector('[data-receipt-zoom]').setAttribute('aria-pressed', 'false');
    viewer.querySelector('[data-receipt-zoom]').textContent = 'Phóng to';
    viewer.showModal();
  });
  if (viewer) {
    viewer.querySelector('[data-receipt-close]').addEventListener('click', () => viewer.close());
    viewer.querySelector('[data-receipt-zoom]').addEventListener('click', event => {
      const zoomed = viewer.classList.toggle('is-zoomed');
      event.currentTarget.setAttribute('aria-pressed', String(zoomed));
      event.currentTarget.textContent = zoomed ? 'Vừa khung' : 'Phóng to';
    });
    viewer.addEventListener('close', () => imageTrigger?.focus({ preventScroll: true }));
  }
  document.addEventListener('click', event => {
    const button = event.target.closest('[data-booking-action]');
    if (!button) return;
    const dialog = document.getElementById(button.dataset.bookingTarget);
    const form = dialog?.querySelector(`[data-booking-form="${CSS.escape(button.dataset.bookingAction)}"]`);
    if (!form) return;
    const previous = button.closest('[data-booking-form]');
    if (previous && previous !== form) ['so_tien_thuc_nhan', 'ma_giao_dich'].forEach(name => {
      const from = previous.elements.namedItem(name), to = form.elements.namedItem(name);
      if (from && to && to.value === '') to.value = from.value;
    });
    if (!dialog.open) trigger = button.closest('[data-row-actions]')?.querySelector('summary') || button;
    document.dispatchEvent(new Event('workspace:close-row-menu'));
    dialog.querySelectorAll('[data-booking-form]').forEach(item => { item.hidden = item !== form; });
    dialog.querySelector('h2').textContent = form.dataset.bookingLabel;
    if (!dialog.open) dialog.showModal();
    else form.querySelector('input:not([type="hidden"]), textarea')?.focus({ preventScroll: true });
  });
  document.querySelectorAll('[data-booking-dialog]').forEach(dialog => {
    dialog.querySelectorAll('[data-booking-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => trigger?.focus({ preventScroll: true }));
    dialog.addEventListener('click', event => {
      const rect = dialog.getBoundingClientRect();
      if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
    });
  });
  const forms = [...document.querySelectorAll('[data-booking-form], [data-manual-payment], .booking-operations form')];
  const refresh = form => {
    if (form.matches('[data-receipt-payment]')) {
      form.querySelector('[type="submit"]').disabled = Boolean(form.dataset.sending) || form.dataset.paymentReady !== '1';
      return;
    }
    if (!form.matches('[data-manual-payment]')) return;
    const amount = form.querySelector('[name="so_tien_thuc_nhan"]');
    const transaction = form.querySelector('[name="ma_giao_dich"]');
    const matches = amount.value !== '' && Number(amount.value) === Number(form.dataset.expectedAmount);
    const valid = form.checkValidity() && matches && transaction.value.trim().length >= 3;
    form.querySelector('[type="submit"]').disabled = Boolean(form.dataset.sending) || !valid;
    const hint = form.querySelector('[data-payment-guidance]');
    if (hint) hint.textContent = !matches && amount.value !== ''
      ? 'Số tiền thực nhận chưa khớp số tiền phải thu. Kiểm tra lại hoặc chuyển sang đối soát.'
      : valid ? 'Đã đủ thông tin đối chiếu thủ công. Backend sẽ kiểm tra lại khi xác nhận.'
        : 'Nhập đủ giao dịch thực tế và xác nhận đã đối chiếu để tiếp tục.';
  };
  forms.forEach(form => {
    form.addEventListener('input', () => refresh(form));
    form.addEventListener('change', () => refresh(form));
    refresh(form);
  });
  // Registered after the shared confirmation handler: its first, cancelled submit stays editable.
  document.addEventListener('submit', event => {
    const form = event.target;
    if (!forms.includes(form) || event.defaultPrevented) return;
    if (form.matches('[data-receipt-payment]') && form.dataset.paymentReady !== '1') { event.preventDefault(); return; }
    if (form.dataset.sending) { event.preventDefault(); return; }
    form.dataset.sending = '1';
    form.setAttribute('aria-busy', 'true');
    form.querySelectorAll('[type="submit"]').forEach(button => { button.disabled = true; });
  });
  window.addEventListener('pageshow', () => forms.forEach(form => {
    delete form.dataset.sending;
    form.removeAttribute('aria-busy');
    form.querySelectorAll('[type="submit"]').forEach(button => { button.disabled = false; });
    refresh(form);
  }));
})();
