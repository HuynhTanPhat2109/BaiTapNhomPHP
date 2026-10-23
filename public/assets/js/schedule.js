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
    const epoch   = Date.parse(root.dataset.now);
    const started = performance.now();

    /* -------------------------------------------------------
       Selection state: Map<string, ItemObject>
    ------------------------------------------------------- */
    const selected = new Map();

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
        feedbackEl.textContent = items.length
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
            if (cell.disabled) return;
            var cellTime = Date.parse(
                root.dataset.date + 'T' + cell.dataset.start + ':00+07:00'
            );
            if (cellTime <= now) {
                cell.disabled = true;
                cell.classList.remove('is-free', 'is-selected');
                cell.classList.add('is-unavailable');
                var span = cell.querySelector('span');
                if (span) span.textContent = 'Locked';
                cell.title = cell.dataset.start + '\u2013' + cell.dataset.end + ' \u00b7 Khóa, đã bắt đầu';
                cell.setAttribute('aria-label', cell.title);
                changed = true;
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
    }

    /* -------------------------------------------------------
       Cell click handler
    ------------------------------------------------------- */
    cells.forEach(function (cell) {
        cell.addEventListener('click', function () {
            expire();
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
    document.addEventListener('visibilitychange', expire);
})();
