<?php
$roleLabels = [
    'QUAN_TRI_TAP_DOAN' => 'Quản trị tập đoàn', 'QUAN_LY_CONG_TY' => 'Quản lý công ty',
    'QUAN_LY_CO_SO' => 'Quản lý cơ sở', 'NHAN_VIEN' => 'Nhân viên', 'KHACH_HANG' => 'Khách hàng',
];
$destinations = [
    'QUAN_TRI_TAP_DOAN' => '/admin', 'QUAN_LY_CONG_TY' => '/company',
    'QUAN_LY_CO_SO' => '/facility', 'NHAN_VIEN' => '/staff', 'KHACH_HANG' => '/customer',
];
$roleKey = $user['ten_vai_tro'] ?? '';
$isCustomer = $roleKey === 'KHACH_HANG';
$displayName = $user['ten_khach_hang'] ?? $user['ten_nhan_vien'] ?? $user['ten_dang_nhap'] ?? 'Thành viên STRYKE';
$initial = mb_strtoupper(mb_substr($displayName, 0, 1, 'UTF-8'), 'UTF-8');
$profileErrors = $profileErrors ?? [];
$profile = $profileOld ?? [
    'name' => $user['ten_khach_hang'] ?? '',
    'phone' => $user['so_dien_thoai_khach'] ?? '',
    'email' => $user['email_khach'] ?? '',
    'birth_date' => $user['ngay_sinh_khach'] ?? '',
    'gender' => $user['gioi_tinh_khach'] ?? '',
];
?>
<section class="container account-page">
    <div class="account-page__intro">
        <p class="eyebrow">STRYKE / Tài khoản / Thông tin cá nhân</p>
        <h1 class="account-h1">Thông tin cá nhân.</h1>
        <p><?= $isCustomer ? 'Giữ thông tin liên hệ chính xác để các hoạt động trên sân luôn thuận tiện.' : 'Thông tin tài khoản và khu vực làm việc theo vai trò của bạn.' ?></p>
    </div>
    <?php if (!empty($notice)): ?><div class="alert alert--success account-notice" role="status"><?= e($notice) ?></div><?php endif; ?>
    <div class="account-layout">
        <aside class="account-sidebar" aria-label="Tổng quan tài khoản">
            <div class="account-identity">
                <div class="profile-avatar" aria-hidden="true"><?= e($initial) ?></div>
                <div class="account-identity__info">
                    <h2><?= e($displayName) ?></h2>
                    <span class="account-role"><?= e($roleLabels[$roleKey] ?? $roleKey) ?></span>
                </div>
            </div>
            <dl class="account-facts">
                <div><dt>Tên đăng nhập</dt><dd><?= e($user['ten_dang_nhap'] ?? '') ?></dd></div>
                <div><dt>Trạng thái</dt><dd>Đang hoạt động</dd></div>
                <?php if (!empty($user['ngay_tao_tai_khoan'])): ?>
                    <div><dt>Tham gia</dt><dd><?= e(date('d/m/Y', strtotime($user['ngay_tao_tai_khoan']))) ?></dd></div>
                <?php endif; ?>
            </dl>
            <?php if (isset($destinations[$roleKey])): ?>
                <a class="account-area-link" href="<?= e(url($destinations[$roleKey])) ?>">Khu vực của bạn <span aria-hidden="true">↗</span></a>
            <?php endif; ?>
        </aside>
        <div class="account-content">
            <section class="account-card" id="profile" aria-labelledby="profile-title">
                <div class="account-card__heading"><div><h2 id="profile-title"><?= $isCustomer ? 'Cập nhật thông tin' : 'Thông tin tài khoản' ?></h2></div>
                </div>
                <?php if ($isCustomer): ?>
                    <p class="account-card__description">Thông tin liên hệ của bạn được dùng cho việc đặt sân sau này.</p>
                    <form method="post" action="<?= e(url('/account')) ?>" novalidate>
                        <?= csrf_field() ?>
                        <div class="account-form-grid">
                            <div class="form-group account-field--wide">
                                <label for="profile-name">Họ và tên</label>
                                <input class="form-control" id="profile-name" name="name" maxlength="150" autocomplete="name" required<?= field_error_attributes('name', $profileErrors) ?> value="<?= e($profile['name']) ?>">
                                <?= field_error('name', $profileErrors) ?>
                            </div>
                            <div class="form-group">
                                <label for="profile-phone">Số điện thoại</label>
                                <input class="form-control" id="profile-phone" type="tel" name="phone" inputmode="numeric" pattern="0[0-9]{9}" minlength="10" maxlength="10" autocomplete="tel" required<?= field_error_attributes('phone', $profileErrors) ?> value="<?= e($profile['phone']) ?>">
                                <?= field_error('phone', $profileErrors) ?>
                            </div>
                            <div class="form-group">
                                <label for="profile-email">Email <span class="optional">(tùy chọn)</span></label>
                                <input class="form-control" id="profile-email" type="email" name="email" maxlength="150" autocomplete="email"<?= field_error_attributes('email', $profileErrors) ?> value="<?= e($profile['email']) ?>">
                                <?= field_error('email', $profileErrors) ?>
                            </div>
                            <div class="form-group">
                                <label for="profile-birth-date">Ngày sinh <span class="optional">(tùy chọn)</span></label>
                                <input class="form-control" id="profile-birth-date" type="date" name="birth_date" max="<?= e(date('Y-m-d')) ?>"<?= field_error_attributes('birth_date', $profileErrors) ?> value="<?= e($profile['birth_date']) ?>">
                                <?= field_error('birth_date', $profileErrors) ?>
                            </div>
                            <div class="form-group">
                                <label for="profile-gender">Giới tính <span class="optional">(tùy chọn)</span></label>
                                <select class="form-control" id="profile-gender" name="gender"<?= field_error_attributes('gender', $profileErrors) ?>>
                                    <option value="">Chưa chọn</option>
                                    <option value="NAM" <?= $profile['gender'] === 'NAM' ? 'selected' : '' ?>>Nam</option>
                                    <option value="NU" <?= $profile['gender'] === 'NU' ? 'selected' : '' ?>>Nữ</option>
                                    <option value="KHAC" <?= $profile['gender'] === 'KHAC' ? 'selected' : '' ?>>Khác</option>
                                </select>
                                <?= field_error('gender', $profileErrors) ?>
                            </div>
                        </div>
                        <button class="btn btn--primary account-save" type="submit">Lưu thay đổi</button>
                    </form>
                <?php elseif ($roleKey === 'QUAN_TRI_TAP_DOAN'): ?>
                    <p class="account-card__description">Tài khoản quản trị: <strong><?= e($user['ten_dang_nhap']) ?></strong>. Chọn Tổng quan trên Sidebar để quay lại Dashboard.</p>
                <?php else: ?>
                    <p class="account-card__description">Họ và tên: <strong><?= e($displayName) ?></strong>. Thông tin nhân sự được quản lý tại khu vực dành cho nhân viên.</p>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>
