<?php $bodyClass = 'page-catalog'; ?>
<section class="container catalog-page catalog-workspace role-page">
    <header class="catalog-heading module-heading"><div><p class="eyebrow">STRYKE / Khu vực của bạn</p><h1><?= e($title) ?></h1><p class="module-subtitle">Xin chào, <strong><?= e($user['ten_dang_nhap']) ?></strong>. Chọn chức năng để bắt đầu làm việc.</p></div></header>
    <?php if (($user['ten_vai_tro'] ?? '') === 'QUAN_LY_CONG_TY'): ?>
        <section aria-labelledby="company-management-heading">
            <h2 id="company-management-heading">Chức năng quản lý</h2>
            <div class="hero__actions">
                <a class="btn btn--primary" href="<?= e(url('/company/employees')) ?>">Quản lý nhân viên</a>
                <a class="btn btn--outline" href="<?= e(url('/company/employees/create')) ?>">Thêm nhân viên</a>
            </div>
        </section>
    <?php else: ?>
        <section class="account-card workspace-welcome"><h2>Không gian làm việc</h2><p>Các chức năng bên dưới sử dụng đúng phạm vi công ty và cơ sở được phân công cho tài khoản của bạn.</p></section>
    <?php endif; ?>
    <?php if (($user['ten_vai_tro'] ?? '') === 'QUAN_TRI_TAP_DOAN'): ?>
        <div class="hero__actions">
            <a class="btn btn--primary"
               href="<?= e(url('/admin/bo-mon')) ?>">Quản lý bộ môn</a>
            <a class="btn btn--outline"
               href="<?= e(url('/admin/loai-san')) ?>">Quản lý loại sân</a>
        </div>
    <?php endif; ?>
    <?php if (in_array($user['ten_vai_tro'] ?? '', ['QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO', 'NHAN_VIEN'], true)): ?>
        <a class="btn btn--primary" href="<?= e(url(App\Services\DichVuService::basePath($user['ten_vai_tro']))) ?>"><?= $user['ten_vai_tro'] === 'NHAN_VIEN' ? 'Xem dịch vụ tại cơ sở' : 'Quản lý dịch vụ' ?></a>
    <?php endif; ?>
    <a class="btn btn--outline" href="<?= e(url('/account')) ?>"><span aria-hidden="true">←</span> Quay lại tài khoản</a>
</section>
