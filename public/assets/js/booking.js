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
                setTimeout(() => { if (destination) location.replace(destination); }, 350);
            } else location.replace(destination);
        }
        async function leave() {
            if (leaving) return;
            if (!cancelForm) { goToSchedule(navigation.dataset.backUrl); return; }
            leaving = true;
            const request = new AbortController();
            const timeout = setTimeout(() => request.abort(), 12000);
            try {
                if (cancelButton) {
                    cancelButton.disabled = true;
                    cancelButton.setAttribute('aria-busy', 'true');
                }
                if (cancelError) cancelError.hidden = true;
                const response = await fetch(cancelForm.action, {
                    method:'POST', body:new FormData(cancelForm),
                    headers:{Accept:'application/json'}, signal:request.signal
                });
                const result = await response.json().catch(() => ({}));
                if (!response.ok || !result.redirect) throw new Error(result.error || 'Chưa xác nhận được việc hủy. Vui lòng thử lại; nếu phiên đã hết hạn, tải lại trang và đăng nhập.');
                goToSchedule(result.redirect);
            } catch (error) {
                if (cancelError) {
                    cancelError.hidden = false;
                    const messageEl = cancelError.querySelector('[data-cancel-message]');
                    if (messageEl) {
                        messageEl.textContent = error.name === 'AbortError'
                            ? 'Chưa nhận được kết quả hủy. Vui lòng thử lại; yêu cầu lặp không hủy hoặc hoàn tồn hai lần.' : error.message;
                    }
                    cancelError.querySelector('button')?.focus();
                }
                armBack();
            } finally {
                clearTimeout(timeout);
                leaving = false;
                if (cancelButton) {
                    cancelButton.disabled = false;
                    cancelButton.removeAttribute('aria-busy');
                }
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
        const dialog=form.querySelector('[data-service-dialog]');
        const note=form.querySelector('textarea[name=note]');
        const open=form.querySelector('[data-open-services]');
        const apply=dialog?.querySelector('[data-apply-services]');
        const list=form.querySelector('[data-selected-services]');
        const rows=()=>[...form.querySelectorAll('[data-service-row]')];
        const quantities=()=>Object.fromEntries(rows().map(row=>[row.dataset.serviceRow,Number(row.querySelector('input').value)||0]));
        let applied=quantities(), busy=false, synchronized=true, timer;
        let acceptedNote=note?.value || '';
        const confirm = () => form.querySelector('[data-confirm-booking]');

        const loginButton = () =>
            form.querySelector('button[name="action"][value="login"]');

        function updateActionButtons() {
            const confirmButton = confirm();
            const login = loginButton();

            if (confirmButton) {
                confirmButton.disabled = busy || !synchronized;
            }

            if (login) {
                login.disabled = busy;
            }
        }
        const feedback=message=>{
            for(const node of form.querySelectorAll('[data-booking-feedback], [data-service-feedback]')) {
                node.textContent=message;node.hidden=!message;
            }
        };
        function restore() {
            rows().forEach(row => {
                row.querySelector('input').value =
                    String(applied[row.dataset.serviceRow] || 0);

                updateDraftAmount(row);
            });
        }
        function serviceData(data, values) {
            for(const key of [...data.keys()]) if(/^services\[/.test(key)) data.delete(key);
            for(const [id,quantity] of Object.entries(values)) if(quantity>0) data.set('services['+id+']',String(quantity));
        }
        // Native confirmation/login submits must never include un-applied modal edits.
        form.addEventListener('formdata',event=>serviceData(event.formData,applied));
        function renderSelected() {
            if(!list)return;
            list.replaceChildren();
            const ul=document.createElement('ul');ul.className='booking-selected-list';
            rows().forEach(row=>{
                const id=row.dataset.serviceRow,quantity=applied[id]||0;
                if(!quantity)return;
                const name=row.querySelector('.booking-service-name h3').textContent.trim();
                const li=document.createElement('li'),info=document.createElement('div');
                const title = document.createElement('span');
                title.className = 'booking-selected-name';
                title.textContent = name;

                const count = document.createElement('span');
                count.className = 'booking-selected-quantity';
                count.textContent = '× ' + quantity.toLocaleString('vi-VN');
                const amount=document.createElement('span');amount.className='booking-selected-amount';amount.textContent=row.querySelector('[data-service-amount]').textContent.trim();
                const remove=document.createElement('button');remove.type='button';remove.className='booking-selected-remove';remove.dataset.removeApplied=id;
                remove.title='Xóa '+name;remove.setAttribute('aria-label','Xóa '+name);remove.disabled=busy;
                remove.innerHTML='<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/></svg>';
                amount.append(remove);info.append(title,count);li.append(info,amount);ul.append(li);
            });
            if(ul.childElementCount)list.append(ul);
            else {const empty=document.createElement('p');empty.className='booking-empty-services';empty.textContent='Bạn chưa chọn dịch vụ nào.';list.append(empty);}
        }
        function filter() {
            if(!dialog)return;
            const keyword=dialog.querySelector('[data-service-search]').value.trim().toLocaleLowerCase('vi');
            rows().forEach(row=>row.hidden=!row.querySelector('h3').textContent.toLocaleLowerCase('vi').includes(keyword));
            dialog.querySelector('[data-service-search-empty]').hidden=rows().some(row=>!row.hidden);
        }
        function close() {
            if(busy)return;
            restore();dialog?.close();open?.focus();
            if(acceptedNote!==(note?.value||'')) review({...applied},false);
        }
        open?.addEventListener('click',()=>{
            if(busy)return;clearTimeout(timer);restore();filter();feedback('');dialog.showModal();dialog.querySelector('[data-service-search]').focus();
        });
        dialog?.querySelector('[data-close-services]').addEventListener('click',close);
        dialog?.addEventListener('cancel',event=>{event.preventDefault();close();});
        dialog?.addEventListener('close',()=>{if(!busy)restore();});
        dialog?.addEventListener('click',event=>{if(event.target===dialog)close();});
        dialog?.querySelector('[data-service-search]').addEventListener('input',filter);
        function updateDraftAmount(row) {
            const input = row.querySelector('input[type="number"]');
            const amount = row.querySelector('[data-service-amount]');

            if (!input || !amount) return;

            const quantity = Number(input.value) || 0;
            const unitPriceCents = Number(row.dataset.unitPriceCents);

            if (!Number.isFinite(unitPriceCents)) return;

            const totalCents = quantity * unitPriceCents;

            amount.textContent = quantity > 0
                ? new Intl.NumberFormat('vi-VN').format(totalCents / 100) + ' đ'
                : '—';
        }
        function normalize(input) {
            const value=Number(input.value);
            input.value=String(Number.isFinite(value)?Math.max(0,Math.min(Number(input.max),Math.trunc(value))):0);
        }
        form.addEventListener('click',event=>{
            const button=event.target.closest('[data-quantity], [data-remove-service], [data-remove-applied]');
            if(!button||button.disabled||busy)return;
            if(button.hasAttribute('data-remove-applied')) {
                const candidate={...applied,[button.dataset.removeApplied]:0};review(candidate,false);return;
            }
            const row = button.closest('[data-service-row]');
            const input = row.querySelector('input');

            input.value = button.hasAttribute('data-remove-service')
                ? '0'
                : String(Number(input.value || 0) + Number(button.dataset.quantity));

            normalize(input);
            updateDraftAmount(row);
                    });
        dialog?.addEventListener('input', event => {
            if (!event.target.matches('input[type="number"]')) return;

            normalize(event.target);
            updateDraftAmount(event.target.closest('[data-service-row]'));
        });
        apply?.addEventListener('click',()=>{
            if(busy)return;
            rows().forEach(row=>normalize(row.querySelector('input')));
            const candidate=quantities();
            if(synchronized&&acceptedNote===(note?.value||'')&&JSON.stringify(candidate)===JSON.stringify(applied)){close();return;}
            review(candidate,true);
        });
        async function review(candidate,closeAfter) {
            if(busy)return;
            clearTimeout(timer);busy=true;synchronized=false;
            const controls=[...form.querySelectorAll('button,input[type=number],textarea')].map(node=>[node,node.disabled]);
            controls.forEach(([node])=>node.disabled=true);
            form.setAttribute('aria-busy','true');feedback('Đang cập nhật báo giá…');
            const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),12000);
            const sentNote=note?.value||'';
            try {
                const body=new FormData(form);serviceData(body,candidate);body.set('note',sentNote);
                const response=await fetch(form.getAttribute('action'),{method:'POST',body,signal:controller.signal});
                const page=new DOMParser().parseFromString(await response.text(),'text/html');
                const summary=page.querySelector('.booking-summary'),quote=page.querySelector('[name=quote]'),lines=page.querySelector('.booking-schedule-list'),services=page.querySelector('.booking-dialog-list');
                if(!response.ok||!summary||!quote||!lines||!!dialog!==!!services)throw new Error(page.querySelector('.booking-error')?.textContent||'Không thể cập nhật báo giá (HTTP '+response.status+'). Vui lòng thử lại hoặc tải lại trang.');
                form.querySelector('.booking-summary').replaceWith(summary);
                form.querySelector('.booking-schedule-list').replaceWith(lines);
                if(services)dialog.querySelector('.booking-dialog-list').replaceChildren(...services.childNodes);
                form.elements.quote.value=quote.value;
                applied=quantities();acceptedNote=sentNote;synchronized=true;
                renderSelected();filter();
                if(closeAfter)dialog.close();
                feedback('Đã cập nhật giá và dịch vụ · Chưa giữ sân');
            } catch(error) {
                // A failed/lost response may already have changed the server draft.
                // Keep the last accepted display but require a successful review before confirm.
                if(!closeAfter)restore();
                feedback(error.name==='AbortError'?'Máy chủ phản hồi quá lâu. Chưa áp dụng thay đổi; vui lòng thử lại.':error.message);
            } finally {
                clearTimeout(timeout);busy=false;form.removeAttribute('aria-busy');
                controls.forEach(([node, disabled]) => {
                    if (node.isConnected) {
                        node.disabled = disabled;
                    }
                });

                form.querySelectorAll('[data-remove-applied]').forEach(node => {
                    node.disabled = false;
                });

                updateActionButtons();
            }
        }
        note?.addEventListener('input', () => {
            if (busy) return;

            clearTimeout(timer);

            synchronized = false;
            updateActionButtons();

            timer = setTimeout(() => {
                review({...applied}, false);
            }, 300);
        });
        form.addEventListener('submit', event => {
        if (busy || dialog?.open) {
            event.preventDefault();
            return;
        }

        const submitter = event.submitter;

        const isConfirm = submitter?.hasAttribute('data-confirm-booking');
        const isLogin = submitter?.matches(
            'button[name="action"][value="login"]'
        );

        // Xác nhận thanh toán chỉ khi báo giá đã đồng bộ
        if (isConfirm && !synchronized) {
            event.preventDefault();
            review({...applied}, false);
            return;
        }

        // Giữ nguyên dữ liệu dịch vụ đã áp dụng
        restore();

        if (!form.reportValidity()) {
            event.preventDefault();
            return;
        }

        clearTimeout(timer);

        // Khóa các nút sau khi form được gửi.
        // Dùng setTimeout để trình duyệt kịp ghi nhận submitter,
        // đặc biệt là name/value của nút đăng nhập.
        setTimeout(() => {
            form.querySelectorAll(
                'button[type="submit"], button[name="action"], [data-confirm-booking]'
            ).forEach(button => {
                button.disabled = true;
            });
        }, 0);
    });
        renderSelected();
        updateActionButtons();
    }
    const waiting=document.querySelector('[data-booking-wait]');
    if(!waiting) return;
    let epoch=Date.parse(waiting.dataset.now), start=performance.now(), state=waiting.dataset.state;
    const deadline=Date.parse(waiting.dataset.expires.replace(' ','T')+'+07:00');
    const counter=waiting.querySelector('[data-countdown]'), message=waiting.querySelector('[data-wait-message]');
    const labels = {CHO_THANH_TOAN:'Sân đang được tạm giữ', DA_HUY:'Đã hủy giữ sân', HET_HAN:'Đã hết thời gian giữ sân', DA_XAC_NHAN:'Booking đã được xác nhận', HOAN_THANH:'Booking đã hoàn thành', CHO_XAC_NHAN:'Booking chưa được giữ sân'};
    let checking=false;
    let pollTimer=null, tickTimer=null;

    const TERMINAL_STATES=['DA_HUY','HET_HAN','DA_XAC_NHAN','HOAN_THANH'];
    function isTerminal(s) {
        return TERMINAL_STATES.includes(s);
    }

    function stopPolling() {
        if (pollTimer !== null) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function stopAllTimers() {
        stopPolling();
        if (tickTimer !== null) {
            clearInterval(tickTimer);
            tickTimer = null;
        }
    }

    async function refresh() {
        if(checking || isTerminal(state)) {
            stopPolling();
            return;
        }
        checking=true;
        try {
            const response=await fetch(waiting.dataset.statusUrl,{headers:{Accept:'application/json'},cache:'no-store'});
            if(!response.ok) throw new Error();
            const data=await response.json();
            if (data.now) epoch=Date.parse(data.now);
            start=performance.now();
            if (data.state) state=data.state;
            tick();
            if (isTerminal(state)) {
                stopAllTimers();
            }
        } catch {
            if (message && !isTerminal(state)) {
                message.textContent='Chưa cập nhật được trạng thái. Thời hạn giữ sân không được gia hạn.';
            }
        } finally {
            checking=false;
        }
    }

    function tick() {
        const seconds=Number.isFinite(deadline)?Math.max(0,Math.ceil((deadline-epoch-(performance.now()-start))/1000)):0;
        const isConfirmed=(state==='DA_XAC_NHAN' || state==='HOAN_THANH');
        const isEnded=(state==='DA_HUY' || state==='HET_HAN');
        const isWaiting=(state==='CHO_THANH_TOAN' && seconds>0);

        if (counter) {
            counter.textContent = isWaiting
                ? String(Math.floor(seconds/60)).padStart(2,'0')+':'+String(seconds%60).padStart(2,'0')
                : '00:00';
        }

        if (message) {
            if (isConfirmed) {
                message.textContent=labels[state] || 'Booking đã được xác nhận';
            } else if (isEnded) {
                message.textContent=labels[state] || 'Đã hết thời gian giữ sân';
            } else if (seconds===0) {
                message.textContent='Thời gian giữ sân đã hết. Đang cập nhật trạng thái…';
            } else {
                message.textContent=labels[state] || 'Sân đang được tạm giữ';
            }
        }

        // Cập nhật hiển thị giao diện: ẩn/hiện pending, success, expired
        const pendingEls=waiting.querySelectorAll('[data-payment-pending]');
        pendingEls.forEach(el => { el.hidden = !isWaiting; });

        const bottomBar=waiting.querySelector('.stryke-bottom-bar') || document.querySelector('.stryke-bottom-bar');
        if (bottomBar) bottomBar.hidden = !isWaiting;

        const successEl=waiting.querySelector('[data-payment-success]');
        if (successEl) successEl.hidden = !isConfirmed;

        const expiredEl=waiting.querySelector('[data-payment-expired]');
        if (expiredEl) {
            expiredEl.hidden = !(isEnded || (!isConfirmed && seconds===0));
        }

        if (cancelButtonForState()) {
            cancelButtonForState().setAttribute('aria-label', isWaiting ? 'Hủy giữ sân và về lịch sân' : 'Về lịch sân');
            if (isConfirmed) {
                cancelButtonForState().disabled = true;
            }
        }

        if (isTerminal(state)) {
            stopAllTimers();
        } else if (seconds===0) {
            if (tickTimer !== null) {
                clearInterval(tickTimer);
                tickTimer = null;
            }
            refresh();
        }
    }

    function cancelButtonForState() { return cancelForm?.querySelector('button'); }

    tick();
    if (!isTerminal(state)) {
        tickTimer=setInterval(tick,1000);
        pollTimer=setInterval(refresh,5000);
    }
    document.addEventListener('visibilitychange',()=>{
        if(!document.hidden && !isTerminal(state)) refresh();
    });
})();


