<?php
$errors = $errors ?? [];
$old = $old ?? [];
$options = $options ?? [];
$companies = $options['companies'] ?? [];
$employee = $employee ?? [];
$value = static function (array $old, array $employee, string $field, string $default = ''): string {
    if (array_key_exists($field, $old)) return is_scalar($old[$field]) ? (string) $old[$field] : '';
    return isset($employee[$field]) && is_scalar($employee[$field]) ? (string) $employee[$field] : $default;
};
$id = $employee['ma_nhan_vien'] ?? '';
?>
<section class="container account-page">
    <div class="account-page__intro"><p class="eyebrow">Quản trị tập đoàn / Nhân viên</p><h1 class="account-h1">Sửa quản lý công ty</h1></div>
    <form method="post" action="<?= e(url('/admin/employees/update')) ?>">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($id) ?>">
        <div class="form-group"><label for="ma_cong_ty">Công ty</label><select class="form-control" id="ma_cong_ty" name="ma_cong_ty" required<?= field_error_attributes('ma_cong_ty', $errors) ?>><option value="">Chọn công ty</option><?php foreach ($companies as $company): ?><?php $companyId = (string) ($company['ma_cong_ty'] ?? ''); ?><option value="<?= e($companyId) ?>" <?= $value($old, $employee, 'ma_cong_ty') === $companyId ? 'selected' : '' ?>><?= e($company['ten_cong_ty'] ?? ('Công ty #' . $companyId)) ?> (<?= e($companyId) ?>)</option><?php endforeach; ?></select><?= field_error('ma_cong_ty', $errors) ?></div>
        <div class="form-group"><label for="ho_ten">Họ tên</label><input class="form-control" id="ho_ten" name="ho_ten" required maxlength="150" value="<?= e($value($old, $employee, 'ho_ten')) ?>"<?= field_error_attributes('ho_ten', $errors) ?>><?= field_error('ho_ten', $errors) ?></div>
        <div class="form-group"><label for="ngay_sinh">Ngày sinh</label><input class="form-control" id="ngay_sinh" name="ngay_sinh" type="date" value="<?= e($value($old, $employee, 'ngay_sinh')) ?>"<?= field_error_attributes('ngay_sinh', $errors) ?>><?= field_error('ngay_sinh', $errors) ?></div>
        <?php $gender = $value($old, $employee, 'gioi_tinh'); ?><div class="form-group"><label for="gioi_tinh">Giới tính</label><select class="form-control" id="gioi_tinh" name="gioi_tinh"<?= field_error_attributes('gioi_tinh', $errors) ?>><option value="">Không chọn</option><?php foreach (['NAM' => 'Nam', 'NU' => 'Nữ', 'KHAC' => 'Khác'] as $key => $label): ?><option value="<?= e($key) ?>" <?= $gender === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= field_error('gioi_tinh', $errors) ?></div>
        <div class="form-group"><label for="so_dien_thoai">Số điện thoại</label><input class="form-control" id="so_dien_thoai" name="so_dien_thoai" type="tel" maxlength="20" value="<?= e($value($old, $employee, 'so_dien_thoai')) ?>"<?= field_error_attributes('so_dien_thoai', $errors) ?>><?= field_error('so_dien_thoai', $errors) ?></div>
        <div class="form-group"><label for="email">Email</label><input class="form-control" id="email" name="email" type="email" maxlength="150" value="<?= e($value($old, $employee, 'email')) ?>"<?= field_error_attributes('email', $errors) ?>><?= field_error('email', $errors) ?></div>
        <div class="form-group"><label for="dia_chi">Địa chỉ</label><input class="form-control" id="dia_chi" name="dia_chi" maxlength="255" value="<?= e($value($old, $employee, 'dia_chi')) ?>"<?= field_error_attributes('dia_chi', $errors) ?>><?= field_error('dia_chi', $errors) ?></div>
        <div class="form-group"><label for="chuc_vu">Chức vụ</label><input class="form-control" id="chuc_vu" name="chuc_vu" maxlength="100" value="<?= e($value($old, $employee, 'chuc_vu')) ?>"<?= field_error_attributes('chuc_vu', $errors) ?>><?= field_error('chuc_vu', $errors) ?></div>
        <div class="form-group"><label for="ngay_vao_lam">Ngày vào làm</label><input class="form-control" id="ngay_vao_lam" name="ngay_vao_lam" type="date" value="<?= e($value($old, $employee, 'ngay_vao_lam')) ?>"<?= field_error_attributes('ngay_vao_lam', $errors) ?>><?= field_error('ngay_vao_lam', $errors) ?></div>
        <?php $status = $value($old, $employee, 'trang_thai', 'DANG_LAM'); ?><div class="form-group"><label for="trang_thai">Trạng thái</label><select class="form-control" id="trang_thai" name="trang_thai" required<?= field_error_attributes('trang_thai', $errors) ?>><option value="DANG_LAM" <?= $status === 'DANG_LAM' ? 'selected' : '' ?>>Đang làm</option><option value="DA_NGHI" <?= $status === 'DA_NGHI' ? 'selected' : '' ?>>Đã nghỉ</option></select><?= field_error('trang_thai', $errors) ?></div>
        <button class="btn btn--primary" type="submit">Lưu thay đổi</button>
        <a class="btn btn--small" href="<?= e(url('/admin/employees')) ?>">Quay lại</a>
    </form>
</section>
