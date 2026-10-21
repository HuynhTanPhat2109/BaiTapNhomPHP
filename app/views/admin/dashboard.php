<?php $bodyClass = 'page-catalog'; ?>
<section class="container catalog-page">
    <header class="catalog-heading">
        <div>
            <p class="eyebrow">STRYKE / Quản trị tập đoàn</p>
            <h1>Quản trị danh mục</h1>
            <p class="account-card__description">Xin chào, <strong><?= e($user['ten_dang_nhap']) ?></strong>. Quản lý bộ môn và loại sân trong hệ thống STRYKE.</p>
        </div>
    </header>
    <div class="account-form-grid catalog-dashboard">
        <section class="account-card" aria-labelledby="sports-title">
            <div class="account-card__heading"><h2 id="sports-title">Bộ môn thể thao</h2></div>
            <p class="account-card__description">Thêm, sửa, xem chi tiết và quản lý trạng thái các bộ môn.</p>
            <a class="btn btn--primary" href="<?= e(url('/admin/bo-mon')) ?>">Quản lý bộ môn</a>
        </section>
        <section class="account-card" aria-labelledby="types-title">
            <div class="account-card__heading"><h2 id="types-title">Loại sân</h2></div>
            <p class="account-card__description">Quản lý loại sân theo bộ môn và kiểm tra các liên kết dữ liệu.</p>
            <a class="btn btn--primary" href="<?= e(url('/admin/loai-san')) ?>">Quản lý loại sân</a>
        </section>
    </div>
</section>
