<section class="container inner-page role-page">
    <header class="page-heading"><p class="eyebrow">STRYKE / Khu vực của bạn</p><h1><?= e($title) ?></h1><p>Xin chào, <strong><?= e($user['ten_dang_nhap']) ?></strong>.</p></header>
    <?php if (($user['ten_vai_tro'] ?? '') === 'QUAN_LY_CONG_TY'): ?>
        <section aria-labelledby="company-management-heading">
            <h2 id="company-management-heading">Chức năng quản lý</h2>
            <div class="hero__actions">
                <a class="btn btn--primary" href="<?= e(url('/company/employees')) ?>">Quản lý nhân viên</a>
                <a class="btn btn--outline" href="<?= e(url('/company/employees/create')) ?>">Thêm nhân viên</a>
            </div>
        </section>
    <?php else: ?>
        <p class="role-notice">Bạn đã đăng nhập vào khu vực dành cho vai trò của mình. Các chức năng nghiệp vụ sẽ được bổ sung ở giai đoạn tiếp theo.</p>
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
