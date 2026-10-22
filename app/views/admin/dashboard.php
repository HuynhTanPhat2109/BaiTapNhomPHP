<?php $bodyClass = 'page-catalog'; ?>
<section class="container catalog-page">
    <header class="admin-dashboard__heading">
        <p class="eyebrow">STRYKE / Quản trị tập đoàn</p>
        <h1>Tổng quan quản trị</h1>
        <p>Xin chào, <strong><?= e($user['ten_dang_nhap']) ?></strong>. Chào mừng bạn trở lại STRYKE.</p>
    </header>
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
        <p>Quản lý danh mục thể thao của STRYKE.</p>
        <div class="admin-quick__links">
            <a class="admin-quick-link" href="<?= e(url('/admin/bo-mon')) ?>">
                <span><strong>Quản lý Bộ môn</strong><small>Thêm, sửa và quản lý trạng thái bộ môn</small></span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
            </a>
            <a class="admin-quick-link" href="<?= e(url('/admin/loai-san')) ?>">
                <span><strong>Quản lý Loại sân</strong><small>Quản lý loại sân và liên kết bộ môn</small></span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
            </a>
        </div>
    </section>
</section>
