
document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('court-search');
    const cards = document.querySelectorAll('.directory-grid .directory-card');

    if (!search) return;

    // Chuẩn hóa: bỏ dấu tiếng Việt, không phân biệt chữ hoa/thường
    function normalizeText(text) {
        return text
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/đ/g, 'd')
            .replace(/Đ/g, 'D')
            .toLowerCase()
            .trim();
    }

    search.addEventListener('input', function () {
        const keyword = normalizeText(search.value);

        cards.forEach(function (card) {
            const title = card.querySelector('.directory-card-copy h2');
            if (!title) return;

            const name = normalizeText(title.textContent);

            card.style.display = name.includes(keyword) ? '' : 'none';
        });
    });
});
