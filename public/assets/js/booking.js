'use strict';
(() => {
    const navigation = document.querySelector('[data-booking-navigation]');
    const cancelForm = document.querySelector('[data-booking-cancel]');
    if (navigation) {
        const pageUrl = location.href;
        const marker = 'strykeBookingNavigation';
        let leaving = false, destination = null;
        const cancelError = document.querySelector('[data-cancel-error]');
        const cancelButton = cancelForm?.querySelector('button');
        // One same-document entry intercepts ordinary Back. Refresh reuses the guard.
        function armBack() {
            if (history.state?.[marker] === pageUrl && history.state?.step === 'guard') return;
            history.replaceState({[marker]:pageUrl, step:'base'}, '', pageUrl);
            history.pushState({[marker]:pageUrl, step:'guard'}, '', pageUrl);
        }
        function goToSchedule(url) {
            destination = url;
            if (history.state?.[marker] === pageUrl && history.state?.step === 'guard') {
                // Consume our own guard only; the route from PHP determines the destination.
                history.go(-1);
            } else location.replace(destination);
        }
        async function leave() {
            if (leaving) return;
            if (!cancelForm) { goToSchedule(navigation.dataset.backUrl); return; }
            leaving = true;
            cancelButton.disabled = true;
            cancelButton.setAttribute('aria-busy', 'true');
            cancelError.hidden = true;
            const request = new AbortController();
            const timeout = setTimeout(() => request.abort(), 12000);
            try {
                const response = await fetch(cancelForm.action, {
                    method:'POST', body:new FormData(cancelForm),
                    headers:{Accept:'application/json'}, signal:request.signal
                });
                const result = await response.json().catch(() => ({}));
                if (!response.ok || !result.redirect) throw new Error(result.error || 'Chưa xác nhận được việc hủy. Vui lòng thử lại; nếu phiên đã hết hạn, tải lại trang và đăng nhập.');
                goToSchedule(result.redirect);
            } catch (error) {
                cancelError.hidden = false;
                cancelError.querySelector('[data-cancel-message]').textContent = error.name === 'AbortError'
                    ? 'Chưa nhận được kết quả hủy. Vui lòng thử lại; yêu cầu lặp không hủy hoặc hoàn tồn hai lần.' : error.message;
                cancelError.querySelector('button').focus();
                armBack();
            } finally {
                clearTimeout(timeout);
                leaving = false;
                cancelButton.disabled = false;
                cancelButton.removeAttribute('aria-busy');
            }
        }
        armBack();
        window.addEventListener('popstate', event => {
            if (destination) { location.replace(destination); return; }
            if (event.state?.[marker] === pageUrl && event.state?.step === 'base') leave();
        });
        cancelForm?.addEventListener('submit', event => { event.preventDefault(); leave(); });
        document.querySelector('[data-retry-cancel]')?.addEventListener('click', leave);
        document.querySelector('[data-booking-back]')?.addEventListener('click', event => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault(); leave();
        });
        window.addEventListener('pageshow', event => { if (event.persisted) location.reload(); });
    }
    const form = document.querySelector('[data-booking-form]');
    if (form) {
        let timer, request, revision=0;
        const confirm = () => form.querySelector('[data-confirm-booking]');
        const feedback = message => { const node=form.querySelector('[data-booking-feedback]'); if(node) { node.textContent=message; node.hidden=!message; } };
        async function review() {
            const current=++revision;
            request?.abort(); request=new AbortController();
            const controller=request;
            const timeout=setTimeout(()=>controller.abort(),12000);
            if(confirm()) confirm().disabled=true;
            feedback('Đang cập nhật tổng tiền…');
            try {
                // Guest login/register buttons are named "action" and shadow form.action.
                const response=await fetch(form.getAttribute('action'),{method:'POST',body:new FormData(form),signal:controller.signal});
                const page=new DOMParser().parseFromString(await response.text(),'text/html');
                if(current!==revision) return;
                const summary=page.querySelector('.booking-summary');
                const quote=page.querySelector('[name=quote]');
                const lines=page.querySelector('.booking-schedule-list');
                const services=page.querySelector('.booking-dialog-list');
                if(!response.ok || !summary || !quote || !lines
                    || (!!form.querySelector('.booking-dialog-list') !== !!services)) {
                    const message=page.querySelector('.booking-error')?.textContent
                        || (response.status===403 ? 'Phiên hoặc mã CSRF không còn hợp lệ. Vui lòng tải lại trang rồi thử lại.'
                        : response.status===410 ? 'Lựa chọn đã hết hạn. Vui lòng chọn lại lịch.'
                        : 'Không thể cập nhật báo giá'+(response.status ? ' (HTTP '+response.status+')' : '')+'. Vui lòng tải lại trang để kiểm tra dịch vụ và thử lại.');
                    throw new Error(message);
                }
                // Keep the customer's note and the modal itself alive. Only server-owned
                // quote fragments are replaced, together, after the latest response wins.
                form.querySelector('.booking-summary').replaceWith(summary);
                form.querySelector('.booking-schedule-list').replaceWith(lines);
                if (services) form.querySelector('.booking-dialog-list').replaceChildren(...services.childNodes);
                form.elements.quote.value=quote.value;
                form.removeAttribute('aria-busy');
                form.dispatchEvent(new Event('booking:quoted'));
                feedback('Đã cập nhật giá và dịch vụ · Chưa giữ sân');
            } catch(error) {
                if(current===revision) {
                    form.removeAttribute('aria-busy');
                    form.dispatchEvent(new Event('booking:quote-failed'));
                    feedback(error.name==='AbortError' ? 'Máy chủ phản hồi quá lâu. Báo giá chưa được cập nhật; vui lòng thử lại.' : error.message);
                }
            } finally { clearTimeout(timeout); }
        }
        function changed() {
            clearTimeout(timer); request?.abort(); revision++;
            if(confirm()) confirm().disabled=true;
            form.setAttribute('aria-busy','true');
            feedback('Đang cập nhật báo giá. Số tiền mới chỉ được xác nhận sau khi máy chủ phản hồi.');
            form.dispatchEvent(new Event('booking:quote-pending'));
            timer=setTimeout(review,300);
        }
        // Delegation also covers service controls refreshed by an AJAX response.
        form.addEventListener('click',event=>{
            const button=event.target.closest('[data-quantity], [data-remove-service]');
            if(!button || button.disabled || !form.contains(button)) return;
            if(button.matches('[data-remove-service]')) {
                button.closest('[data-service-row]').querySelector('input').value='0';
                changed(); return;
            }
            const input=button.parentElement.querySelector('input');
            input.value=String(Math.max(0,Math.min(Number(input.max),Number(input.value||0)+Number(button.dataset.quantity))));
            changed();
        });
        form.addEventListener('input',event=>{
            if(event.target.matches('input[type=number],textarea')) changed();
        });
        form.addEventListener('submit',event=>{
            if(!form.reportValidity()) { event.preventDefault(); return; }
            clearTimeout(timer); request?.abort(); revision++;
            // Disable after the browser has captured the clicked submit button's name/value.
            setTimeout(()=>{form.querySelectorAll('button[type=submit],button[name=action],[data-confirm-booking]').forEach(button=>button.disabled=true);},0);
            feedback('Đang xử lý yêu cầu…');
        });
    }
    const waiting=document.querySelector('[data-booking-wait]');
    if(!waiting) return;
    let epoch=Date.parse(waiting.dataset.now), start=performance.now(), state=waiting.dataset.state;
    const deadline=Date.parse(waiting.dataset.expires.replace(' ','T')+'+07:00');
    const counter=waiting.querySelector('[data-countdown]'), message=waiting.querySelector('[data-wait-message]');
    const labels = {CHO_THANH_TOAN:'Sân đang được tạm giữ', DA_HUY:'Đã hủy giữ sân', HET_HAN:'Đã hết thời gian giữ sân', DA_XAC_NHAN:'Booking đã được xác nhận', HOAN_THANH:'Booking đã hoàn thành', CHO_XAC_NHAN:'Booking chưa được giữ sân'};
    let checking=false;
    async function refresh() {
        if(checking) return; checking=true;
        try {
            const response=await fetch(waiting.dataset.statusUrl,{headers:{Accept:'application/json'},cache:'no-store'});
            if(!response.ok) throw new Error();
            const data=await response.json(); epoch=Date.parse(data.now);start=performance.now();state=data.state;
            tick();
        } catch { message.textContent='Chưa cập nhật được trạng thái. Thời hạn giữ sân không được gia hạn.'; }
        finally { checking=false; }
    }
    function tick() {
        const seconds=Number.isFinite(deadline)?Math.max(0,Math.ceil((deadline-epoch-(performance.now()-start))/1000)):0;
        counter.textContent=state==='CHO_THANH_TOAN' ? String(Math.floor(seconds/60)).padStart(2,'0')+':'+String(seconds%60).padStart(2,'0') : '00:00';
        message.textContent=state==='CHO_THANH_TOAN' && seconds===0 ? 'Thời gian giữ sân đã hết. Đang cập nhật trạng thái…' : (labels[state] || 'Trạng thái booking đã thay đổi');
        waiting.querySelector('[data-payment-pending]').hidden=state!=='CHO_THANH_TOAN' || seconds===0;
        if (cancelButtonForState()) cancelButtonForState().setAttribute('aria-label', state==='CHO_THANH_TOAN' && seconds>0 ? 'Hủy giữ sân và về lịch sân' : 'Về lịch sân');
    }
    function cancelButtonForState() { return cancelForm?.querySelector('button'); }
    tick(); setInterval(tick,1000); setInterval(refresh,5000);
    document.addEventListener('visibilitychange',()=>{if(!document.hidden) refresh();});
})();


/* =========================================================
   STRYKE - SERVICE SELECTION MODAL
   Add AFTER the existing booking.js code.
========================================================= */

(() => {
    'use strict';

    const form = document.querySelector('[data-booking-form]');
    const dialog = document.querySelector('[data-service-dialog]');

    if (!form || !dialog) return;

    const openButton = document.querySelector('[data-open-services]');
    const closeButton = dialog.querySelector('[data-close-services]');
    const applyButton = dialog.querySelector('[data-apply-services]');
    const searchInput = dialog.querySelector('[data-service-search]');
    const selectedContainer = document.querySelector('[data-selected-services]');
    const rows = () => Array.from(dialog.querySelectorAll('[data-service-row]'));
    let quotePending = false;
    let quoteFailed = false;

    const getInput = row =>
        row.querySelector('input[type="number"]');

    const getQuantity = row =>
        Number(getInput(row)?.value || 0);

    const normalizeQuantity = input => {
        if (!input || input.disabled) return 0;

        const max = Number(input.max || 999999);
        const value = Number(input.value || 0);

        const safe = Number.isFinite(value)
            ? Math.max(0, Math.min(max, Math.trunc(value)))
            : 0;

        input.value = String(safe);
        return safe;
    };

    const formatNumber = value =>
        value.toLocaleString('vi-VN');

    function renderSelected() {
        if (!selectedContainer) return;

        selectedContainer.replaceChildren();

        const selected = rows().filter(row => getQuantity(row) > 0);

        if (!selected.length) {
            const empty = document.createElement('p');
            empty.className = 'booking-empty-services';
            empty.textContent = 'Bạn chưa chọn dịch vụ nào.';
            selectedContainer.append(empty);
            return;
        }

        const list = document.createElement('ul');
        list.className = 'booking-selected-list';

        for (const row of selected) {
            const quantity = getQuantity(row);

            const name = row.querySelector(
                '.booking-service-name h3'
            )?.textContent?.trim() || 'Dịch vụ';

            const amount = quoteFailed ? 'Chưa cập nhật được' : quotePending ? 'Đang cập nhật…' : row.querySelector(
                '[data-service-amount]'
            )?.textContent?.trim() || '';

            const item = document.createElement('li');

            const info = document.createElement('div');

            const nameNode = document.createElement('span');
            nameNode.className = 'booking-selected-name';
            nameNode.textContent = name;

            const quantityNode = document.createElement('span');
            quantityNode.className = 'booking-selected-quantity';
            quantityNode.textContent =
                `Số lượng: ${formatNumber(quantity)}`;

            const amountNode = document.createElement('span');
            amountNode.className = 'booking-selected-amount';
            amountNode.textContent = amount;

            info.append(nameNode, quantityNode);
            item.append(info, amountNode);
            list.append(item);
        }

        selectedContainer.append(list);
    }

    function filterServices() {
        const keyword = searchInput.value
            .trim()
            .toLocaleLowerCase('vi');

        let visible = 0;

        for (const row of rows()) {
            const name = row.querySelector(
                '.booking-service-name h3'
            )?.textContent?.toLocaleLowerCase('vi') || '';

            const matches = name.includes(keyword);

            row.hidden = !matches;

            if (matches) visible++;
        }

        const searchEmpty = dialog.querySelector('[data-service-search-empty]');
        if (searchEmpty) {
            searchEmpty.hidden = visible > 0;
        }
    }

    function openDialog() {
        if (typeof dialog.showModal !== 'function') return;

        searchInput.value = '';
        filterServices();

        dialog.showModal();
        searchInput.focus();
    }

    function closeDialog() {
        dialog.close();
        openButton?.focus();
    }

    openButton?.addEventListener('click', openDialog);

    closeButton?.addEventListener('click', closeDialog);

    searchInput?.addEventListener('input', filterServices);

    dialog.addEventListener('click', event => {
        if (event.target === dialog) {
            closeDialog();
        }
    });

    // Keep the existing booking.js quantity handlers.
    // These listeners only update the selected-service preview.
    dialog.addEventListener('input', event => {
        if (event.target.matches('input[type="number"]')) {
            normalizeQuantity(event.target);
            renderSelected();
        }
    });

    dialog.addEventListener('click', event => {
        if (
            event.target.closest('[data-quantity]') ||
            event.target.closest('[data-remove-service]')
        ) {
            // Existing booking.js changes the input synchronously.
            queueMicrotask(renderSelected);
        }
    });

    function applyServices() {
    for (const row of rows()) {
        normalizeQuantity(getInput(row));
    }

    renderSelected();
    dialog.close();

    const firstInput = dialog.querySelector('input[type="number"]');

    if (firstInput) {
        firstInput.dispatchEvent(
            new Event('input', { bubbles: true })
        );
    }

    openButton?.focus();
}

    applyButton?.addEventListener('click', applyServices);

    form.addEventListener('booking:quote-pending', () => {
        quotePending = true;
        quoteFailed = false;
        renderSelected();
    });
    form.addEventListener('booking:quote-failed', () => {
        quotePending = false;
        quoteFailed = true;
        renderSelected();
    });
    form.addEventListener('booking:quoted', () => {
        quotePending = false;
        quoteFailed = false;
        filterServices();
        renderSelected();
    });

        renderSelected();
})();
