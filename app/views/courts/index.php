<section class="courts-page courts-directory">
    <form method="get" action="<?= e(base_path() . '/index.php') ?>" id="court-filters" class="directory-search">
        <input type="hidden" name="route" value="/courts">
        <div class="search-input-wrap">
            <svg class="search-input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><!--!Font Awesome Free v7.3.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M480 272C480 317.9 465.1 360.3 440 394.7L566.6 521.4C579.1 533.9 579.1 554.2 566.6 566.7C554.1 579.2 533.8 579.2 521.3 566.7L394.7 440C360.3 465.1 317.9 480 272 480C157.1 480 64 386.9 64 272C64 157.1 157.1 64 272 64C386.9 64 480 157.1 480 272zM272 416C351.5 416 416 351.5 416 272C416 192.5 351.5 128 272 128C192.5 128 128 192.5 128 272C128 351.5 192.5 416 272 416z"/></svg>
            <input
                id="court-search"
                name="q"
                value="<?= e($filters['q']) ?>"
                maxlength="200"
                aria-label="Tìm tên cơ sở"
                placeholder="Tìm tên sân thể thao…">
        </div>
        <button class="btn btn--primary">Tìm kiếm</button>
        <button
            class="btn filter-icon-btn"
            type="button"
            data-open-filter
            aria-controls="filter-drawer"
            aria-haspopup="dialog"
            aria-label="Bộ lọc nâng cao"
            title="Bộ lọc nâng cao">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><!--!Font Awesome Free v7.3.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M96 128C78.3 128 64 142.3 64 160C64 177.7 78.3 192 96 192L182.7 192C195 220.3 223.2 240 256 240C288.8 240 317 220.3 329.3 192L544 192C561.7 192 576 177.7 576 160C576 142.3 561.7 128 544 128L329.3 128C317 99.7 288.8 80 256 80C223.2 80 195 99.7 182.7 128L96 128zM96 288C78.3 288 64 302.3 64 320C64 337.7 78.3 352 96 352L342.7 352C355 380.3 383.2 400 416 400C448.8 400 477 380.3 489.3 352L544 352C561.7 352 576 337.7 576 320C576 302.3 561.7 288 544 288L489.3 288C477 259.7 448.8 240 416 240C383.2 240 355 259.7 342.7 288L96 288zM96 448C78.3 448 64 462.3 64 480C64 497.7 78.3 512 96 512L150.7 512C163 540.3 191.2 560 224 560C256.8 560 285 540.3 297.3 512L544 512C561.7 512 576 497.7 576 480C576 462.3 561.7 448 544 448L297.3 448C285 419.7 256.8 400 224 400C191.2 400 163 419.7 150.7 448L96 448z"/></svg>
        </button>   
    </form>
    <dialog id="filter-drawer" class="court-drawer drawer-right" aria-labelledby="filter-title">
        <header class="drawer-header"><h2 id="filter-title">Bộ lọc nâng cao</h2><button type="button" class="drawer-close" data-close-drawer aria-label="Đóng bộ lọc"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><!--!Font Awesome Free v7.3.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M183.1 137.4C170.6 124.9 150.3 124.9 137.8 137.4C125.3 149.9 125.3 170.2 137.8 182.7L275.2 320L137.9 457.4C125.4 469.9 125.4 490.2 137.9 502.7C150.4 515.2 170.7 515.2 183.2 502.7L320.5 365.3L457.9 502.6C470.4 515.1 490.7 515.1 503.2 502.6C515.7 490.1 515.7 469.8 503.2 457.3L365.8 320L503.1 182.6C515.6 170.1 515.6 149.8 503.1 137.3C490.6 124.8 470.3 124.8 457.8 137.3L320.5 274.7L183.1 137.4z"/></svg></button></header>
        <div class="drawer-body">
            <label>Bộ môn<select form="court-filters" name="ma_bo_mon"><option value="">Tất cả bộ môn</option><?php foreach ($sports as $sport): ?><option value="<?= e($sport['ma_bo_mon']) ?>" <?= $filters['ma_bo_mon'] === (string) $sport['ma_bo_mon'] ? 'selected' : '' ?>><?= e($sport['ten_bo_mon']) ?></option><?php endforeach; ?></select></label>
            <label>Tỉnh/Thành phố<select form="court-filters" name="ma_tinh" id="browse-province"><option value="">Tất cả tỉnh/thành phố</option><?php foreach ($provinces as $p): ?><option value="<?= e($p['Code']) ?>" <?= $filters['ma_tinh'] === $p['Code'] ? 'selected' : '' ?>><?= e($p['FullName']) ?></option><?php endforeach; ?></select></label>
            <label>Phường/Xã<select form="court-filters" name="ma_phuong_xa" id="browse-ward"><option value="">Tất cả phường/xã</option><?php foreach ($provinces as $p): ?><optgroup label="<?= e($p['FullName']) ?>" data-province="<?= e($p['Code']) ?>"><?php foreach ($p['Wards'] as $w): ?><option value="<?= e($w['Code']) ?>" <?= $filters['ma_phuong_xa'] === $w['Code'] ? 'selected' : '' ?>><?= e($w['FullName']) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label>
        </div>
        <footer class="drawer-footer"><a href="<?= e(url('/courts')) ?>">Xóa bộ lọc</a><button class="btn btn--primary" form="court-filters">Áp dụng / Tìm kiếm</button></footer>
    </dialog>
    <div class="directory-grid">
        <?php foreach ($items as $facility): ?>
            <article class="court-card directory-card">
                <?php require __DIR__ . '/photo.php'; ?>
                <div class="directory-card-info">
                    <?php require __DIR__ . '/logo.php'; ?>
                    <div class="directory-card-copy"><h2><a class="facility-detail-link" href="<?= e(url('/courts/show') . '&id=' . $facility['ma_co_so']) ?>" data-panel-url="<?= e(url('/courts/panel') . '&id=' . $facility['ma_co_so']) ?>" aria-haspopup="dialog"><?= e($facility['ten_co_so']) ?></a></h2><p title="<?= e($facility['dia_chi']) ?>"><?= e($facility['dia_chi']) ?></p><p class="directory-hours"><?= e(substr($facility['gio_mo_cua'], 0, 5)) ?> — <?= e(substr($facility['gio_dong_cua'], 0, 5)) ?></p></div>
                    <a class="btn btn--primary directory-book booking-choice-link" href="<?= e(url('/courts/schedule') . '&id=' . $facility['ma_co_so']) ?>" data-schedule-url="<?= e(url('/courts/schedule') . '&id=' . $facility['ma_co_so']) ?>">ĐẶT LỊCH</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php if (!$items): ?><div class="courts-empty"><h2>Chưa tìm thấy cơ sở phù hợp</h2><p>Thử tên khác hoặc bỏ bớt bộ lọc.</p></div><?php endif; ?>
    <dialog id="detail-drawer" class="court-drawer drawer-right facility-drawer" aria-label="Chi tiết cơ sở" data-initial-facility="<?= e($openedFacility['ma_co_so'] ?? '') ?>">
        <button type="button" class="facility-dismiss" data-close-drawer aria-label="Đóng chi tiết cơ sở"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><!--!Font Awesome Free v7.3.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M169.4 297.4C156.9 309.9 156.9 330.2 169.4 342.7L361.4 534.7C373.9 547.2 394.2 547.2 406.7 534.7C419.2 522.2 419.2 501.9 406.7 489.4L237.3 320L406.6 150.6C419.1 138.1 419.1 117.8 406.6 105.3C394.1 92.8 373.8 92.8 361.3 105.3L169.3 297.3z"/></svg></button>
        <div id="facility-detail-content" aria-live="polite">
            <?php if (!empty($openedFacility)): $facility = $openedFacility; $courts = $openedCourts; require __DIR__ . '/detail-panel.php'; endif; ?>
        </div>
    </dialog>
    <?php require __DIR__ . '/booking-modal.php'; ?>
    <noscript><p>Bật JavaScript để sử dụng drawer chi tiết và bộ lọc.</p></noscript>
</section>
