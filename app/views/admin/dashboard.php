<?php
$bodyClass = 'page-catalog';
$dashboardLinks = (require dirname(__DIR__, 3) . '/config/admin-menu.php')['QUAN_TRI_TAP_DOAN']['QUẢN LÝ HỆ THỐNG'];
?>
<section class="container catalog-page catalog-workspace workspace-page">
    <header class="admin-dashboard__heading module-heading"><div>
        <p class="eyebrow">STRYKE / Quản trị tập đoàn</p>
        <h1>Tổng quan quản trị</h1>
        <p>Xin chào, <strong><?= e($user['ten_dang_nhap']) ?></strong>. Chào mừng bạn trở lại STRYKE.</p>
    </div></header>
    <div class="admin-stats">
        <section class="admin-stat" aria-labelledby="sports-title">
            <span class="admin-stat__icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c-5 5-5 13 0 18M12 3c5 5 5 13 0 18"/></svg></span>
            <h2 id="sports-title">Tổng số bộ môn</h2>
            <p class="admin-stat__value" data-stat="bo_mon"><?= e($stats['bo_mon']) ?></p>
            <p>Bao gồm hoạt động và đã ngừng</p>
        </section>
        <section class="admin-stat" aria-labelledby="types-title">
            <span class="admin-stat__icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 10 5-10 5L2 8zM2 12l10 5 10-5M2 16l10 5 10-5"/></svg></span>
            <h2 id="types-title">Tổng số loại sân</h2>
            <p class="admin-stat__value" data-stat="loai_san"><?= e($stats['loai_san']) ?></p>
            <p>Bao gồm hoạt động và đã ngừng</p>
        </section>
    </div>
    <section class="account-card admin-quick" aria-labelledby="quick-title">
        <h2 id="quick-title">Truy cập nhanh</h2>
        <p>Mở các chức năng quản trị đang có trong hệ thống.</p>
        <div class="admin-quick__links">
            <?php foreach ($dashboardLinks as $link): ?>
            <?php if ($link['route'] === null) continue; ?>
            <a class="admin-quick-link" href="<?= e(url($link['route'])) ?>">
                <span><strong><?= e($link['label']) ?></strong><small>Mở khu vực quản lý</small></span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
</section>
