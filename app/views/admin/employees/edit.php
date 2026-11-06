<?php $bodyClass = 'page-catalog';
$errors = $errors ?? [];
$old = $old ?? [];
$options = $options ?? [];
$companies = $options['companies'] ?? [];
$facilities = $options['facilities'] ?? [];
$employee = $employee ?? [];
$value = static function (array $old, array $employee, string $field, string $default = ''): string {
    if (array_key_exists($field, $old)) return is_scalar($old[$field]) ? (string) $old[$field] : '';
    return isset($employee[$field]) && is_scalar($employee[$field]) ? (string) $employee[$field] : $default;
};
$id = $employee['ma_nhan_vien'] ?? '';
?>
<section class="container account-page catalog-page catalog-workspace workspace-page">
    <header class="account-page__intro catalog-heading module-heading"><div><p class="eyebrow">Quản trị tập đoàn / Nhân viên</p><h1 class="account-h1">Sửa nhân viên</h1><p class="module-subtitle">Thông tin nhân sự và phân công. Vai trò tài khoản hiện tại được giữ nguyên.</p></div><a class="btn btn--outline workspace-back admin-back-link" href="<?= e(admin_context_url('/admin/employees')) ?>">Quay lại danh sách</a></header>
    <form class="catalog-form module-form workspace-form" method="post" action="<?= e(admin_context_url('/admin/employees/update')) ?>">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($id) ?>">
                <section class="module-form-card">
        <h2 class="admin-form-heading"><span class="module-step" aria-hidden="true">1</span>Thông tin nhân sự</h2>
        <div class="module-fields">
        <div class="form-group"><label for="ma_cong_ty">Công ty</label><select class="form-control" id="ma_cong_ty" name="ma_cong_ty" required<?= field_error_attributes('ma_cong_ty', $errors) ?>><option value="">Chọn công ty</option><?php foreach ($companies as $company): ?><?php $companyId = (string) ($company['ma_cong_ty'] ?? ''); ?><option value="<?= e($companyId) ?>" <?= $value($old, $employee, 'ma_cong_ty') === $companyId ? 'selected' : '' ?>><?= e($company['ten_cong_ty'] ?? ('Công ty #' . $companyId)) ?> (<?= e($companyId) ?>)</option><?php endforeach; ?></select><?= field_error('ma_cong_ty', $errors) ?></div>
        <div class="form-group"><label for="ho_ten">Họ tên</label><input class="form-control" id="ho_ten" name="ho_ten" required maxlength="150" value="<?= e($value($old, $employee, 'ho_ten')) ?>"<?= field_error_attributes('ho_ten', $errors) ?>><?= field_error('ho_ten', $errors) ?></div>
        <div class="form-group"><label for="ngay_sinh">Ngày sinh</label><input class="form-control" id="ngay_sinh" name="ngay_sinh" type="date" value="<?= e($value($old, $employee, 'ngay_sinh')) ?>"<?= field_error_attributes('ngay_sinh', $errors) ?>><?= field_error('ngay_sinh', $errors) ?></div>
        <?php $gender = $value($old, $employee, 'gioi_tinh'); ?><div class="form-group"><label for="gioi_tinh">Giới tính</label><select class="form-control" id="gioi_tinh" name="gioi_tinh"<?= field_error_attributes('gioi_tinh', $errors) ?>><option value="">Không chọn</option><?php foreach (['NAM' => 'Nam', 'NU' => 'Nữ', 'KHAC' => 'Khác'] as $key => $label): ?><option value="<?= e($key) ?>" <?= $gender === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= field_error('gioi_tinh', $errors) ?></div>
        <div class="form-group"><label for="so_dien_thoai">Số điện thoại</label><input class="form-control" id="so_dien_thoai" name="so_dien_thoai" type="tel" maxlength="20" value="<?= e($value($old, $employee, 'so_dien_thoai')) ?>"<?= field_error_attributes('so_dien_thoai', $errors) ?>><?= field_error('so_dien_thoai', $errors) ?></div>
        <div class="form-group"><label for="email">Email</label><input class="form-control" id="email" name="email" type="email" maxlength="150" value="<?= e($value($old, $employee, 'email')) ?>"<?= field_error_attributes('email', $errors) ?>><?= field_error('email', $errors) ?></div>
        <div class="form-group"><label for="dia_chi">Địa chỉ</label><input class="form-control" id="dia_chi" name="dia_chi" maxlength="255" value="<?= e($value($old, $employee, 'dia_chi')) ?>"<?= field_error_attributes('dia_chi', $errors) ?>><?= field_error('dia_chi', $errors) ?></div>
                </div></section>
        <section class="module-form-card">
        <h2 class="admin-form-heading"><span class="module-step" aria-hidden="true">2</span>Phân công &amp; trạng thái</h2>
        <div class="module-fields">
        <div class="form-group"><label for="ma_co_so">Cơ sở</label><select class="form-control" id="ma_co_so" name="ma_co_so"<?= ($employee['ten_vai_tro'] ?? '') === 'QUAN_LY_CONG_TY' ? ' disabled' : '' ?><?= field_error_attributes('ma_co_so', $errors) ?>><option value="">Chưa gán cơ sở</option><?php foreach ($facilities as $facility): ?><option value="<?= e($facility['ma_co_so']) ?>" <?= $value($old, $employee, 'ma_co_so') === (string) $facility['ma_co_so'] ? 'selected' : '' ?>><?= e($facility['ten_cong_ty'] . ' — ' . $facility['ten_co_so']) ?></option><?php endforeach; ?></select><?= field_error('ma_co_so', $errors) ?><p class="form-hint">Cơ sở phải thuộc công ty đã chọn; không thay đổi vai trò tài khoản.</p></div>
        <div class="form-group"><label for="chuc_vu">Chức vụ</label><input class="form-control" id="chuc_vu" name="chuc_vu" maxlength="100" value="<?= e($value($old, $employee, 'chuc_vu')) ?>"<?= field_error_attributes('chuc_vu', $errors) ?>><?= field_error('chuc_vu', $errors) ?></div>
        <div class="form-group"><label for="ngay_vao_lam">Ngày vào làm</label><input class="form-control" id="ngay_vao_lam" name="ngay_vao_lam" type="date" value="<?= e($value($old, $employee, 'ngay_vao_lam')) ?>"<?= field_error_attributes('ngay_vao_lam', $errors) ?>><?= field_error('ngay_vao_lam', $errors) ?></div>
        <?php $status = $value($old, $employee, 'trang_thai', 'DANG_LAM'); ?><div class="form-group"><label for="trang_thai">Trạng thái</label><select class="form-control" id="trang_thai" name="trang_thai" required<?= field_error_attributes('trang_thai', $errors) ?>><option value="DANG_LAM" <?= $status === 'DANG_LAM' ? 'selected' : '' ?>>Đang làm</option><option value="DA_NGHI" <?= $status === 'DA_NGHI' ? 'selected' : '' ?>>Đã nghỉ</option></select><?= field_error('trang_thai', $errors) ?></div>
        </div></section>
        <div class="catalog-actions"><button class="btn btn--primary" type="submit">Lưu thay đổi</button>
        <a class="btn btn--outline admin-back-link" href="<?= e(admin_context_url('/admin/employees')) ?>">Quay lại danh sách</a></div>
    </form>
    <?php $workingStatus = $employee['trang_thai'] ?? ''; ?>

    <?php if (in_array($workingStatus, ['DANG_LAM', 'DA_NGHI'], true)): ?>
        <form
            class="employee-status-form"
            method="post"
            action="<?= e(admin_context_url('/admin/employees/status')) ?>"
            data-confirm="<?= e(
                ($workingStatus === 'DANG_LAM'
                    ? 'Cho nghỉ nhân viên '
                    : 'Kích hoạt lại nhân viên ')
                . ($employee['ho_ten'] ?? '') . '?'
            ) ?>"
        >
            <?= csrf_field() ?>

            <input type="hidden" name="id" value="<?= e($id) ?>">
            <input
                type="hidden"
                name="trang_thai"
                value="<?= $workingStatus === 'DANG_LAM' ? 'DA_NGHI' : 'DANG_LAM' ?>"
            >

            <button
                class="employee-status-btn <?= $workingStatus === 'DANG_LAM'
                    ? 'employee-status-btn--stop'
                    : 'employee-status-btn--activate' ?>"
                type="submit"
            >
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 3v9m-5-7a8 8 0 1 0 10 0"/>
                </svg>

                <span>
                    <?= $workingStatus === 'DANG_LAM'
                        ? 'Cho nghỉ'
                        : 'Kích hoạt lại' ?>
                </span>
            </button>
        </form>
    <?php endif; ?>
    <?php require __DIR__ . '/account-provision.php'; ?>
</section>
