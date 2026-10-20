<?php
$roleLabels = [
    'QUAN_TRI_TAP_DOAN' => 'Quản trị tập đoàn',
    'QUAN_LY_CONG_TY' => 'Quản lý công ty',
    'QUAN_LY_CO_SO' => 'Quản lý cơ sở',
    'NHAN_VIEN' => 'Nhân viên',
    'KHACH_HANG' => 'Khách hàng',
];
$destinations = [
    'QUAN_TRI_TAP_DOAN' => '/admin',
    'QUAN_LY_CONG_TY' => '/company',
    'QUAN_LY_CO_SO' => '/facility',
    'NHAN_VIEN' => '/staff',
    'KHACH_HANG' => '/customer',
];
$roleKey = $user['ten_vai_tro'] ?? '';
$displayName = $user['ten_khach_hang'] ?? $user['ten_nhan_vien'] ?? 'Quản trị viên';
$initial = mb_strtoupper(mb_substr($displayName, 0, 1, 'UTF-8'), 'UTF-8');
?>
<section class="container inner-page">
    <header class="page-heading"><p class="eyebrow">Không gian của bạn</p><h1>Tài khoản STRYKE.</h1><p>Thông tin tài khoản và khu vực truy cập của bạn.</p></header>
    <div class="profile">
        <div class="profile-identity"><div class="profile-avatar" aria-hidden="true"><?= e($initial) ?></div><h2><?= e($displayName) ?></h2><p><?= e($roleLabels[$roleKey] ?? $roleKey) ?></p></div>
        <div>
            <dl class="profile-details">
                <div class="detail-row"><dt>Tên đăng nhập</dt><dd><?= e($user['ten_dang_nhap']) ?></dd></div>
                <div class="detail-row"><dt>Họ và tên</dt><dd><?= e($displayName) ?></dd></div>
                <div class="detail-row"><dt>Vai trò</dt><dd><?= e($roleLabels[$roleKey] ?? $roleKey) ?></dd></div>
            </dl>
            <?php if (isset($destinations[$roleKey])): ?><a class="btn btn--primary" href="<?= e(url($destinations[$roleKey])) ?>">Vào khu vực của bạn <span aria-hidden="true">↗</span></a><?php endif; ?>
        </div>
    </div>
</section>