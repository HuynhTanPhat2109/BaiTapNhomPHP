(() => {
    'use strict';
    const modal = document.querySelector('[data-proof-modal]');
    const image = modal?.querySelector('[data-proof-image]');
    const closeButton = modal?.querySelector('[data-close-proof]');
    const error = modal?.querySelector('[data-proof-error]');
    if (!modal || !image || !closeButton) return;
    let previousFocus = null;

    function closeModal() { modal.close(); }
    modal.addEventListener('close', () => {
        image.removeAttribute('src');
        document.body.classList.remove('booking-history-proof-open');
        previousFocus?.focus();
    });
    image.addEventListener('error', () => {
        if (!modal.open || !image.hasAttribute('src')) return;
        image.hidden = true;
        if (error) error.hidden = false;
    });
    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-view-proof]');
        if (!trigger) return;
        const src = trigger.dataset.proofSrc;
        if (!src || new URL(src, location.href).origin !== location.origin) return;
        previousFocus = trigger;
        image.hidden = false;
        if (error) error.hidden = true;
        image.alt = trigger.getAttribute('aria-label') || 'Ảnh minh chứng chuyển khoản';
        image.src = src;
        modal.showModal();
        document.body.classList.add('booking-history-proof-open');
        closeButton.focus();
    });
    closeButton.addEventListener('click', closeModal);
    modal.addEventListener('click', event => {
        if (event.target === modal || event.target.classList.contains('booking-history-proof-container')) closeModal();
    });
    // Native dialog supplies Escape, focus trapping and inert background under CSP.
})();
