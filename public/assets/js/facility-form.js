'use strict';
(() => {
    const province = document.getElementById('ma_tinh');
    const ward = document.getElementById('ma_phuong_xa');
    if (!province || !ward) return;
    const groups = [...ward.querySelectorAll('optgroup')].map(group => group.cloneNode(true));
    function refresh(selected = '') {
        ward.replaceChildren(new Option('Chọn phường/xã', ''));
        const codes = new Set();
        groups.filter(group => group.dataset.province === province.value).forEach(group => {
            [...group.children].forEach(option => {
                if (codes.has(option.value)) return;
                codes.add(option.value);
                const copy = option.cloneNode(true);
                copy.selected = copy.value === selected;
                ward.append(copy);
            });
        });
        ward.value = selected;
        if (ward.selectedIndex < 0) ward.selectedIndex = 0;
    }
    refresh(ward.value);
    province.addEventListener('change', () => refresh());
    for (const [field, target] of [['anh_dai_dien', 'facility-image-preview'], ['logo_clb', 'facility-logo-preview']]) {
    const input = document.getElementById(field);
    const preview = document.getElementById(target);
    const original = preview.getAttribute('src');
    let reader;
    input.addEventListener('change', () => {
        if (reader) reader.abort();
        const file = input.files[0];
        if (file && file.size <= 3 * 1024 * 1024 && ['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            reader = new FileReader();
            reader.onload = () => { preview.src = reader.result; preview.hidden = false; };
            reader.readAsDataURL(file);
        } else {
            preview.hidden = !original;
            if (original) preview.src = original;
        }
    });
    }
})();
