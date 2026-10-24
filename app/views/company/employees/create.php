<?php
$errors = $errors ?? [];
$old = $old ?? [];
$options = $options ?? [];
$facilities = $options['facilities'] ?? [];
$value = static function (array $data, string $field, string $default = ''): string {
    if (!array_key_exists($field, $data)) return $default;
    return is_scalar($data[$field]) ? (string) $data[$field] : '';
};
$status = $value($old, 'trang_thai', 'DANG_LAM');
$gender = $value($old, 'gioi_tinh');
$accountChecked = !empty($old['cap_tai_khoan']);
?>
<section class="container account-page">
    <div class="account-page__intro"><p class="eyebrow">Công ty / Nhân viên</p><h1 class="account-h1">Thêm nhân viên</h1></div>
    <form method="post" action="<?= e(url('/company/employees')) ?>">
        <?= csrf_field() ?>
        <div class="form-group"><label for="ho_ten">Họ tên</label><input class="form-control" id="ho_ten" name="ho_ten" required maxlength="150" value="<?= e($value($old, 'ho_ten')) ?>"<?= field_error_attributes('ho_ten', $errors) ?>><?= field_error('ho_ten', $errors) ?></div>
        <div class="form-group"><label for="ngay_sinh">Ngày sinh</label><input class="form-control" id="ngay_sinh" name="ngay_sinh" type="date" value="<?= e($value($old, 'ngay_sinh')) ?>"<?= field_error_attributes('ngay_sinh', $errors) ?>><?= field_error('ngay_sinh', $errors) ?></div>
        <div class="form-group"><label for="gioi_tinh">Giới tính</label><select class="form-control" id="gioi_tinh" name="gioi_tinh"<?= field_error_attributes('gioi_tinh', $errors) ?>><option value="">Không chọn</option><?php foreach (['NAM' => 'Nam', 'NU' => 'Nữ', 'KHAC' => 'Khác'] as $key => $label): ?><option value="<?= e($key) ?>" <?= $gender === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= field_error('gioi_tinh', $errors) ?></div>
        <div class="form-group"><label for="so_dien_thoai">Số điện thoại</label><input class="form-control" id="so_dien_thoai" name="so_dien_thoai" type="tel" maxlength="20" value="<?= e($value($old, 'so_dien_thoai')) ?>"<?= field_error_attributes('so_dien_thoai', $errors) ?>><?= field_error('so_dien_thoai', $errors) ?></div>
        <div class="form-group"><label for="email">Email</label><input class="form-control" id="email" name="email" type="email" maxlength="150" value="<?= e($value($old, 'email')) ?>"<?= field_error_attributes('email', $errors) ?>><?= field_error('email', $errors) ?></div>
        <div class="form-group"><label for="dia_chi">Địa chỉ</label><input class="form-control" id="dia_chi" name="dia_chi" maxlength="255" value="<?= e($value($old, 'dia_chi')) ?>"<?= field_error_attributes('dia_chi', $errors) ?>><?= field_error('dia_chi', $errors) ?></div>
        <div class="form-group"><label for="ma_co_so">Cơ sở</label><select class="form-control" id="ma_co_so" name="ma_co_so"<?= field_error_attributes('ma_co_so', $errors) ?>><option value="">Chưa gán cơ sở</option><?php foreach ($facilities as $facility): ?><?php $facilityId = (string) ($facility['ma_co_so'] ?? ''); ?><option value="<?= e($facilityId) ?>" <?= $value($old, 'ma_co_so') === $facilityId ? 'selected' : '' ?>><?= e($facility['ten_co_so'] ?? ('Cơ sở #' . $facilityId)) ?> (<?= e($facilityId) ?>)</option><?php endforeach; ?></select><?= field_error('ma_co_so', $errors) ?></div>
        <div class="form-group"><label for="chuc_vu">Chức vụ</label><input class="form-control" id="chuc_vu" name="chuc_vu" maxlength="100" value="<?= e($value($old, 'chuc_vu')) ?>"<?= field_error_attributes('chuc_vu', $errors) ?>><?= field_error('chuc_vu', $errors) ?></div>
        <div class="form-group"><label for="ngay_vao_lam">Ngày vào làm</label><input class="form-control" id="ngay_vao_lam" name="ngay_vao_lam" type="date" value="<?= e($value($old, 'ngay_vao_lam')) ?>"<?= field_error_attributes('ngay_vao_lam', $errors) ?>><?= field_error('ngay_vao_lam', $errors) ?></div>
        <div class="form-group"><label for="trang_thai">Trạng thái</label><select class="form-control" id="trang_thai" name="trang_thai" required<?= field_error_attributes('trang_thai', $errors) ?>><option value="DANG_LAM" <?= $status === 'DANG_LAM' ? 'selected' : '' ?>>Đang làm</option><option value="DA_NGHI" <?= $status === 'DA_NGHI' ? 'selected' : '' ?>>Đã nghỉ</option></select><?= field_error('trang_thai', $errors) ?></div>
        <h2>Tài khoản</h2>
        <div class="form-group"><label><input type="checkbox" name="cap_tai_khoan" value="1" <?= $accountChecked ? 'checked' : '' ?>> Cấp tài khoản cho nhân viên</label><?= field_error('account', $errors) ?></div>
        <p class="form-hint">Nếu cấp tài khoản, cần chọn cơ sở và vai trò tài khoản phải là Quản lý cơ sở hoặc Nhân viên.</p>
        <div class="form-group"><label for="username">Tên đăng nhập</label><input class="form-control" id="username" name="username" maxlength="15" value="<?= e($value($old, 'username')) ?>"<?= field_error_attributes('username', $errors) ?>><?= field_error('username', $errors) ?></div>
        <div class="form-group"><label for="password">Mật khẩu</label><input class="form-control" id="password" name="password" type="password" value=""<?= field_error_attributes('password', $errors) ?>><?= field_error('password', $errors) ?></div>
        <div class="form-group"><label for="role">Vai trò tài khoản</label><select class="form-control" id="role" name="role"<?= field_error_attributes('role', $errors) ?>><option value="">Chọn vai trò</option><?php foreach (['QUAN_LY_CO_SO' => 'Quản lý cơ sở', 'NHAN_VIEN' => 'Nhân viên'] as $key => $label): ?><option value="<?= e($key) ?>" <?= $value($old, 'role') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= field_error('role', $errors) ?></div>
        <button class="btn btn--primary" type="submit">Lưu nhân viên</button><a class="btn btn--small" href="<?= e(url('/company/employees')) ?>">Quay lại</a>
    </form>
</section>
