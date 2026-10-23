'use strict';
(() => {
    const filterDrawer = document.getElementById('filter-drawer');
    const detailDrawer = document.getElementById('detail-drawer');
    let detailRequest;
    function openDrawer(dialog) {
        dialog.showModal();
        document.body.classList.add('drawer-open');
    }
    document.querySelectorAll('.court-drawer').forEach(dialog => {
        dialog.querySelector('[data-close-drawer]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', event => {
            const rect = dialog.getBoundingClientRect();
            if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
        });
        dialog.addEventListener('close', () => {
            document.body.classList.remove('drawer-open');
            if (dialog === detailDrawer && detailRequest) detailRequest.abort();
        });
    });
    document.querySelector('[data-open-filter]')?.addEventListener('click', () => openDrawer(filterDrawer));
    document.querySelectorAll('.facility-detail-link').forEach(link => link.addEventListener('click', async event => {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        if (detailRequest) detailRequest.abort();
        const request = new AbortController();
        detailRequest = request;
        const content = document.getElementById('facility-detail-content');
        content.textContent = 'Đang tải thông tin cơ sở…';
        content.setAttribute('aria-busy', 'true');
        openDrawer(detailDrawer);
        try {
            const response = await fetch(link.dataset.panelUrl, {signal: request.signal, headers: {'Accept': 'text/html'}});
            if (!response.ok) throw new Error('Không tải được cơ sở');
            const html = await response.text();
            if (!request.signal.aborted) content.innerHTML = html; // Same-origin escaped server template.
        } catch (error) {
            if (error.name !== 'AbortError') {
                content.textContent = 'Cơ sở không còn khả dụng hoặc kết nối bị gián đoạn. ';
                const fallback = document.createElement('a');
                fallback.href = link.href;
                fallback.textContent = 'Thử mở trang chi tiết';
                content.append(fallback);
            }
        } finally { if (!request.signal.aborted) content.removeAttribute('aria-busy'); }
    }));
    const bookingChoice = document.getElementById('booking-choice');
    let bookingOpener;
    document.addEventListener('click', event => {
        const link = event.target.closest('.booking-choice-link');
        if (!link || !bookingChoice || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        bookingOpener = link;
        document.getElementById('booking-normal').href = link.dataset.scheduleUrl || link.href;
        bookingChoice.showModal();
        document.body.classList.add('drawer-open');
    });
    bookingChoice?.querySelector('[data-close-booking]').addEventListener('click', () => bookingChoice.close());
    bookingChoice?.addEventListener('click', event => {
        const rect = bookingChoice.getBoundingClientRect();
        if (event.target === bookingChoice && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) bookingChoice.close();
    });
    bookingChoice?.addEventListener('close', () => {
        if (!document.querySelector('dialog[open]')) document.body.classList.remove('drawer-open');
        bookingOpener?.focus();
    });
    const province = document.getElementById('browse-province');
    const ward = document.getElementById('browse-ward');
    if (province && ward) {
        const groups = [...ward.querySelectorAll('optgroup')].map(g => g.cloneNode(true));
        const filter = (selected = '') => {
            ward.replaceChildren(new Option('Tất cả phường/xã', ''));
            groups.filter(g => g.dataset.province === province.value).forEach(g => ward.append(g.cloneNode(true)));
            ward.value = selected;
            if (ward.selectedIndex < 0) ward.selectedIndex = 0;
            ward.disabled = !province.value;
        };
        filter(ward.value);
        province.addEventListener('change', () => filter());
    }
})();
