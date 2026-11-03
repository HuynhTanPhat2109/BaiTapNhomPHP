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
  const navigation = sidebar.querySelector('.admin-sidebar__nav');
  const scrollKey = `stryke.sidebar.scroll:${location.pathname}:${sidebar.dataset.scrollScope}`;
  const currentScrollKey = () => scrollKey + (document.body.classList.contains('sidebar-compact') ? ':compact' : '');
  const saveScroll = () => {
    if (!navigation) return;
    try { sessionStorage.setItem(currentScrollKey(), String(navigation.scrollTop)); } catch {}
  };
  const revealActive = () => {
    const active = navigation?.querySelector('[aria-current="page"]');
    if (!active) return;
    const viewport = navigation.getBoundingClientRect();
    const item = active.getBoundingClientRect();
    if (item.top < viewport.top) navigation.scrollTop += item.top - viewport.top - 8;
    else if (item.bottom > viewport.bottom) navigation.scrollTop += item.bottom - viewport.bottom + 8;
  };
  const restoreScroll = () => {
    if (!navigation) return;
    try {
      const saved = Number(sessionStorage.getItem(currentScrollKey()));
      if (Number.isFinite(saved) && saved >= 0) navigation.scrollTop = saved;
    } catch {}
    revealActive();
  };
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
      revealActive();
      requestAnimationFrame(() => {
        if (opened) close.focus({ preventScroll: true });
      });
    } else if (restoreFocus) {
      main.inert = false;
      toggle.focus({ preventScroll: true });
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
    if (mobile.matches && sidebar.contains(document.activeElement)) toggle.focus({ preventScroll: true });
  };
  toggle.addEventListener('click', () => setOpen(!opened, opened));
  collapse?.addEventListener('click', () => {
    saveScroll();
    compact = !compact;
    try { localStorage.setItem('stryke.sidebar.compact', compact ? '1' : '0'); } catch {}
    resize();
    restoreScroll();
  });
  close.addEventListener('click', () => setOpen(false, true));
  backdrop.addEventListener('click', () => setOpen(false, true));
  sidebar.addEventListener('click', event => {
    if (event.target.closest('a[href]')) saveScroll();
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
  navigation?.addEventListener('scroll', saveScroll, { passive: true });
  window.addEventListener('pagehide', saveScroll);
  mobile.addEventListener('change', () => { saveScroll(); resize(); restoreScroll(); });
  window.addEventListener('pageshow', () => { resize(); restoreScroll(); });
  resize();
  restoreScroll();
})();

// Localized display only. The existing field name submits a decimal string, never a float.
(() => {
  'use strict';
  const grouped = /^\d{1,3}(?:\.\d{3})+$/;
  const decode = (value, rawInput) => {
    if (value === '') return '';
    if (rawInput === 'server') return /^\d+(?:\.\d*)?$/.test(value) ? value : null;
    if (rawInput && /^\d+(?:\.\d*)?$/.test(value) && !grouped.test(value)) return value;
    if (!/^[\d.]+(?:,\d*)?$/.test(value)) return null;
    const [whole, fraction] = value.split(',');
    if (rawInput && !/^\d+$/.test(whole) && !grouped.test(whole)) return null;
    return whole.replaceAll('.', '') + (fraction === undefined ? '' : '.' + fraction);
  };
  const display = (raw, editing) => {
    if (!/^\d+(?:\.\d*)?$/.test(raw)) return raw;
    const [whole, fraction] = raw.split('.');
    return whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
      + (fraction !== undefined && (editing || !/^0*$/.test(fraction)) ? ',' + fraction : '');
  };
  document.querySelectorAll('[data-money-input]').forEach(input => {
    const name = input.name;
    const nativeNumber = input.type === 'number' ? input.cloneNode() : null;
    const hidden = document.createElement('input');
    hidden.type = 'hidden'; hidden.name = name;
    input.removeAttribute('name'); input.type = 'text'; input.inputMode = 'decimal';
    input.removeAttribute('maxlength');
    input.insertAdjacentElement('afterend', hidden);
    let lastDisplay = null;
    const sync = (editing, rawInput = false) => {
      const value = input.value;
      const caret = input.selectionStart ?? value.length;
      let raw = value === lastDisplay ? hidden.value : decode(value, rawInput);
      if (raw === null) raw = value; // Preserve invalid/negative input for the existing validation.
      if (!editing && /^\d+\.$/.test(raw)) raw = raw.slice(0, -1);
      hidden.value = raw;
      if (nativeNumber) {
        nativeNumber.value = raw;
        input.setCustomValidity(nativeNumber.validity.valid ? '' : nativeNumber.validationMessage);
      }
      const formatted = display(raw, editing);
      lastDisplay = formatted;
      const decimal = raw.includes('.') ? (value.includes(',') ? value.indexOf(',') : value.lastIndexOf('.')) : -1;
      const tokens = (value.slice(0, caret).match(/\d/g) || []).length + (decimal >= 0 && caret > decimal ? 1 : 0);
      if (formatted === value) return;
      input.value = formatted;
      if (editing && document.activeElement === input) {
        let position = 0, seen = 0;
        while (position < formatted.length && seen < tokens) {
          if (/[\d,]/.test(formatted[position])) seen++;
          position++;
        }
        input.setSelectionRange(position, position);
      }
    };
    sync(false, 'server');
    input.addEventListener('beforeinput', event => {
      if (event.inputType !== 'insertText' || event.data !== '.') return;
      event.preventDefault();
      input.setRangeText(',', input.selectionStart, input.selectionEnd, 'end');
      input.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertText', data: ',' }));
    });
    input.addEventListener('keydown', event => {
      const start = input.selectionStart;
      if (start !== input.selectionEnd || event.ctrlKey || event.metaKey || event.altKey) return;
      if (event.key === 'Backspace' && input.value[start - 1] === '.') input.setSelectionRange(start - 1, start - 1);
      if (event.key === 'Delete' && input.value[start] === '.') input.setSelectionRange(start + 1, start + 1);
    });
    input.addEventListener('input', event => {
      if (!event.isComposing) sync(true, event.inputType === 'insertFromPaste' || event.inputType === 'insertReplacementText' || (event.data?.length ?? 0) > 1);
    });
    input.addEventListener('compositionend', () => sync(true));
    input.addEventListener('blur', () => sync(false));
    input.form?.addEventListener('submit', () => sync(false));
    input.form?.addEventListener('formdata', event => { sync(false); event.formData.set(name, hidden.value); });
    input.form?.addEventListener('reset', () => requestAnimationFrame(() => { lastDisplay = null; sync(false, 'server'); }));
  });
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
