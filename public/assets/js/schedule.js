'use strict';
(() => {
    const root = document.getElementById('court-schedule');
    if (!root) return;

    /* -------------------------------------------------------
       Date auto-submit
    ------------------------------------------------------- */
    const dateForm = document.getElementById('schedule-date-form');
    const dateInput = document.getElementById('play-date');
    if (dateForm && dateInput) {
        dateInput.addEventListener('change', () => {
            if (dateInput.value) dateForm.submit();
        });
    }

    /* -------------------------------------------------------
       DOM references
    ------------------------------------------------------- */
    const cells     = [...root.querySelectorAll('.time-cell')];
    const titleEl   = document.getElementById('selection-title');
    const previewEl = document.getElementById('selection-preview');
    const feedbackEl = document.getElementById('selection-feedback');
    const listEl    = document.getElementById('selected-courts');
    const clearBtn  = document.getElementById('clear-selection');
    const durationEl = document.getElementById('selection-duration');

    /* -------------------------------------------------------
       Monotonic clock synced with server time
    ------------------------------------------------------- */
    let epoch   = Date.parse(root.dataset.now);
    let started = performance.now();
    let lastSync = started, synchronized = true;
    const stateLabels = {free:'Trống', booked:'Đã đặt', held:'Đang giữ', unavailable:'Khóa'};
    const cellStates = new Map(cells.map(cell => [cell,
        Object.keys(stateLabels).find(state => cell.classList.contains('is-'+state)) || 'unavailable']));
    function fresh() { return synchronized && performance.now()-lastSync < 15000; }
    function setState(cell, state) {
        const changed = cellStates.get(cell) !== state;
        cellStates.set(cell, state);
        if (changed) {
            Object.keys(stateLabels).forEach(value => cell.classList.toggle('is-'+value, value===state));
        }
        cell.disabled = state !== 'free';
        const label = (cell.dataset.courtName || cell.closest('tr').dataset.name) + ' · '
            + cell.dataset.start + '–' + cell.dataset.end + ' · ' + stateLabels[state];
        cell.title = label;
        cell.setAttribute('aria-label', label);
        return changed;
    }

    /* -------------------------------------------------------
       Selection state: Map<string, ItemObject>
    ------------------------------------------------------- */
    const selected = new Map();
    const nextForm = document.getElementById('booking-next');
    let quoteTimer, quoteRequest, quoteRevision = 0;
    function updateQuote(items) {
        if (!nextForm) return;
        clearTimeout(quoteTimer);
        quoteRequest?.abort();
        const revision = ++quoteRevision;
        const button = nextForm.querySelector('button');
        const price = document.getElementById('selection-price');
        button.disabled = true;
        const selection = JSON.stringify({facility:nextForm.dataset.facility,date:root.dataset.date,
            slots:items.map(item => ({court:item.courtId,start:item.start,end:item.end}))});
        nextForm.elements.selection.value = selection;
        price.textContent = '—';
        if (!items.length) return;
        quoteTimer = setTimeout(async () => {
            const controller = new AbortController(); quoteRequest = controller;
            try {
                const response = await fetch(nextForm.dataset.quoteUrl, {method:'POST',body:new FormData(nextForm),signal:controller.signal,headers:{Accept:'application/json'}});
                const data = await response.json();
                if (revision !== quoteRevision) return;
                if (!response.ok) throw new Error(data.error || 'Không thể tính giá. Vui lòng tải lại lịch.');
                price.textContent = Number(data.total).toLocaleString('vi-VN',{maximumFractionDigits:2}) + ' ₫';
                button.disabled = !fresh();
            } catch (error) {
                if (error.name !== 'AbortError' && revision === quoteRevision && feedbackEl) feedbackEl.textContent = error.message || 'Chưa kết nối được máy chủ.';
            }
        },250);
    }
    nextForm?.addEventListener('submit', event => {
        expire();
        const button = nextForm.querySelector('button');
        if (button.disabled || !fresh()) { event.preventDefault(); syncSchedule(); return; }
        button.disabled = true; button.textContent = 'Đang kiểm tra…';
    });

    /** Stable key: courtId|date|start|end */
    function keyOf(cell) {
        var courtId = cell.dataset.court || cell.closest('tr').dataset.court;
        return [courtId, root.dataset.date, cell.dataset.start, cell.dataset.end].join('|');
    }

    /* -------------------------------------------------------
       Duration formatting
    ------------------------------------------------------- */

    /** Total duration – always shows "X giờ YY phút" */
    function formatTotal(minutes) {
        var h = Math.floor(minutes / 60), m = minutes % 60;
        if (m === 0) return minutes === 0 ? '0 giờ 00 phút' : h + ' giờ';
        return h + ' giờ ' + m + ' phút';
    }

    /** Group duration – omits "0 giờ" */
    function formatGroup(minutes) {
        var h = Math.floor(minutes / 60), m = minutes % 60;
        if (h === 0) return m + ' phút';
        if (m === 0) return h + ' giờ';
        return h + ' giờ ' + m + ' phút';
    }

    /* -------------------------------------------------------
       paint() – re-render all selection UI
    ------------------------------------------------------- */
    function paint() {
        // 1. Toggle cell visuals
        cells.forEach(function (cell) {
            var on = selected.has(keyOf(cell));
            cell.classList.toggle('is-selected', on);
            cell.setAttribute('aria-pressed', String(on));
        });

        // 2. Sorted item list
        var items = Array.from(selected.values()).sort(function (a, b) {
            return a.courtId.localeCompare(b.courtId) || a.start.localeCompare(b.start);
        });
        updateQuote(items);

        // 3. Clear button
        clearBtn.disabled = !items.length;

        // 4. Total duration
        var totalMinutes = items.length * 30;
        durationEl.textContent = formatTotal(totalMinutes);

        // 5. Heading title
        if (titleEl) {
            titleEl.textContent = items.length
                ? items.length + ' khung giờ đã chọn'
                : 'Chưa chọn khung giờ';
        }

        // 6. Group consecutive slots per court
        var groups = [];
        items.forEach(function (item) {
            var last = groups.length ? groups[groups.length - 1] : null;
            if (last && last.courtId === item.courtId && last.end === item.start) {
                last.end = item.end;
                last.items.push(item);
            } else {
                groups.push({
                    key: item.key, courtId: item.courtId,
                    courtName: item.courtName, sport: item.sport,
                    start: item.start, end: item.end,
                    items: [item]
                });
            }
        });

        // 7. Empty state
        var emptyEl = listEl.querySelector('.selection-empty');
        if (emptyEl) emptyEl.hidden = !!items.length;

        // 8. Preview text (aria-live region)
        if (previewEl) {
            previewEl.textContent = items.length
                ? items.length + ' khung giờ đã chọn'
                : 'Chọn các ô trống trên lịch để xem chi tiết.';
        }

        // 9. Remove old rows, create new
        var oldRows = listEl.querySelectorAll('.selected-court-row');
        for (var i = 0; i < oldRows.length; i++) oldRows[i].remove();

        groups.forEach(function (group) {
            var row = document.createElement('article');
            row.className = 'selected-court-row';

            // Court name + sport
            var nameWrap = document.createElement('div');
            nameWrap.className = 'selected-court-name';
            var nameStrong = document.createElement('strong');
            nameStrong.textContent = group.courtName;
            var nameSport = document.createElement('small');
            nameSport.textContent = group.sport;
            nameWrap.appendChild(nameStrong);
            nameWrap.appendChild(nameSport);

            // Time range
            var timeEl = document.createElement('span');
            timeEl.className = 'selected-time';
            timeEl.textContent = group.start + '\u2013' + group.end;

            // Duration
            var durEl = document.createElement('small');
            durEl.className = 'selected-duration';
            durEl.textContent = formatGroup(group.items.length * 30);

            // Remove button
            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'selection-remove';
            removeBtn.textContent = 'Bỏ chọn';
            removeBtn.setAttribute('aria-label',
                'Bỏ chọn ' + group.courtName + ' ' + group.start + '\u2013' + group.end);
            removeBtn.addEventListener('click', (function (g) {
                return function () {
                    g.items.forEach(function (it) { selected.delete(it.key); });
                    paint();
                };
            })(group));

            row.appendChild(nameWrap);
            row.appendChild(timeEl);
            row.appendChild(durEl);
            row.appendChild(removeBtn);
            listEl.appendChild(row);
        });

        // 10. Feedback status
        if (feedbackEl) feedbackEl.textContent = items.length
            ? items.length + ' ô đang xem trước \u00b7 Chưa giữ sân'
            : 'Chưa giữ sân';
    }

    /* -------------------------------------------------------
       expire() – disable past slots (Asia/Ho_Chi_Minh)
    ------------------------------------------------------- */
    function expire() {
        var now = epoch + (performance.now() - started);
        var changed = false;

        cells.forEach(function (cell) {
            var cellTime = Date.parse(
                root.dataset.date + 'T' + cell.dataset.start + ':00+07:00'
            );
            if (cellTime <= now) {
                changed = setState(cell, 'unavailable') || changed;
            }
        });

        // Remove selected cells that became disabled
        selected.forEach(function (item, key) {
            if (item.cell.disabled) {
                selected.delete(key);
                changed = true;
            }
        });

        if (changed) paint();
        if (!fresh() && nextForm) nextForm.querySelector('button').disabled = true;
    }

    /* One in-flight request, pause in background, no HTML replacement or selection reset. */
    let syncTimer, syncRequest, syncRevision = 0, retryDelay = 5000;
    async function syncSchedule() {
        if (!root.dataset.endpoint || document.hidden || syncRequest) return;
        clearTimeout(syncTimer);
        const revision = ++syncRevision;
        const controller = new AbortController();
        syncRequest = controller;
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const response = await fetch(root.dataset.endpoint + '&date=' + encodeURIComponent(root.dataset.date) + '&status=1', {
                headers:{Accept:'application/json'}, cache:'no-store', signal:controller.signal
            });
            const data = await response.json();
            if (revision !== syncRevision) return;
            const endpoint = new URL(root.dataset.endpoint, location.href);
            if (!response.ok || data.date !== root.dataset.date || String(data.facility)!==endpoint.searchParams.get('id')
                || !Number.isFinite(Date.parse(data.now)) || !Array.isArray(data.slots) || !Array.isArray(data.courts)) throw new Error('Invalid schedule response');
            const states = new Map();
            data.courts.forEach(court => {
                if (!Array.isArray(court.states) || court.states.length !== data.slots.length) throw new Error('Invalid court states');
                data.slots.forEach((slot,index) => {
                    if (!Array.isArray(slot) || slot.length!==2 || !Object.hasOwn(stateLabels,court.states[index])) throw new Error('Invalid slot state');
                    states.set([court.id,data.date,slot[0],slot[1]].join('|'),court.states[index]);
                });
            });
            epoch = Date.parse(data.now); started = performance.now(); lastSync = started;
            synchronized = true; retryDelay = 5000;
            let removed = false;
            cells.forEach(cell => {
                const past = Date.parse(root.dataset.date+'T'+cell.dataset.start+':00+07:00') <= epoch;
                setState(cell, past ? 'unavailable' : (states.get(keyOf(cell)) || 'unavailable'));
                if (cell.disabled && selected.delete(keyOf(cell))) removed = true;
            });
            if (removed) {
                paint();
                if (feedbackEl) feedbackEl.textContent='Một số ô vừa hết giờ hoặc không còn trống đã được bỏ chọn. Các ô còn hợp lệ được giữ nguyên.';
            } else if (selected.size) updateQuote(Array.from(selected.values()));
            else if (feedbackEl) feedbackEl.textContent='Chưa giữ sân';
        } catch (error) {
            if (revision !== syncRevision) return;
            synchronized = false;
            if (nextForm) nextForm.querySelector('button').disabled = true;
            if (feedbackEl) feedbackEl.textContent='Chưa cập nhật được lịch. Đang kết nối lại; lựa chọn của bạn được giữ để kiểm tra lại.';
            retryDelay = Math.min(30000,retryDelay*2);
        } finally {
            clearTimeout(timeout);
            if (revision === syncRevision) {
                syncRequest = null;
                if (!document.hidden) syncTimer = setTimeout(syncSchedule,retryDelay);
            }
        }
    }
    function resumeSync() {
        synchronized = false;
        expire();
        syncSchedule();
    }

    /* -------------------------------------------------------
       Cell click handler
    ------------------------------------------------------- */
    cells.forEach(function (cell) {
        cell.addEventListener('click', function () {
            expire();
            if (!fresh()) { syncSchedule(); return; }
            if (cell.disabled) return;

            var key = keyOf(cell);
            if (selected.has(key)) {
                selected.delete(key);
            } else {
                var tr = cell.closest('tr');
                selected.set(key, {
                    key:       key,
                    cell:      cell,
                    courtId:   String(cell.dataset.court || tr.dataset.court),
                    courtName: cell.dataset.courtName || tr.dataset.name,
                    sport:     (tr.querySelector('small') || {}).textContent
                                 ? tr.querySelector('small').textContent.trim() : '',
                    start:     cell.dataset.start,
                    end:       cell.dataset.end
                });
            }
            paint();
        });
    });

    /* -------------------------------------------------------
       Clear all selections
    ------------------------------------------------------- */
    clearBtn.addEventListener('click', function () {
        selected.clear();
        paint();
    });

    /* -------------------------------------------------------
       Scroll shadow
    ------------------------------------------------------- */
    var viewport = root.querySelector('.booking-grid-scroll');
    if (viewport) {
        viewport.addEventListener('scroll', function () {
            viewport.classList.toggle('has-horizontal-offset', viewport.scrollLeft > 0);
        }, { passive: true });
    }

    /* -------------------------------------------------------
       Slot count CSS variable
    ------------------------------------------------------- */
    root.style.setProperty('--slot-count', String(Number(root.dataset.slotCount) || 36));

    /* -------------------------------------------------------
       Zoom controls
    ------------------------------------------------------- */
    var zoom = 0;
    var levels = ['zoom-fit', 'zoom-large', 'zoom-largest', 'zoom-detail'];
    var zoomLabels = ['Fit', '125%', '150%', '200%'];
    root.classList.add(levels[zoom]);

    var zoomOut = root.querySelector('[data-zoom="-1"]');
    var zoomIn  = root.querySelector('[data-zoom="1"]');
    var zoomLevel = document.getElementById('zoom-level');
    if (zoomOut) zoomOut.disabled = true;

    root.querySelectorAll('[data-zoom]').forEach(function (button) {
        button.addEventListener('click', function () {
            root.classList.remove(levels[zoom]);
            zoom = Math.max(0, Math.min(3, zoom + Number(button.dataset.zoom)));
            root.classList.add(levels[zoom]);
            if (zoomLevel) zoomLevel.textContent = zoomLabels[zoom];
            if (zoomOut) zoomOut.disabled = zoom === 0;
            if (zoomIn)  zoomIn.disabled  = zoom === 3;
        });
    });

    /* -------------------------------------------------------
       Init
    ------------------------------------------------------- */
    paint();
    expire();
    setInterval(expire, 1000);
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            clearTimeout(syncTimer); synchronized = false;
            syncRevision++; syncRequest?.abort(); syncRequest = null;
        } else resumeSync();
    });
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) resumeSync();
    });
    window.addEventListener('online', resumeSync);
    syncSchedule();
})();
