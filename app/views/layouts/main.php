<?php
// Internal pages share the workspace shell; customer templates keep their current layout.
if ((in_array($template, ['auth/role', 'auth/account', 'auth/settings', 'services/page'], true)
        && in_array($data['user']['ten_vai_tro'] ?? null, ['QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO', 'NHAN_VIEN'], true))
    || (str_starts_with($template, 'facility/bank/')
        && in_array($data['role'] ?? null, ['QUAN_TRI_TAP_DOAN', 'QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO'], true))) {
    require __DIR__ . '/admin.php';
    return;
}
$isAuthPage = ($bodyClass ?? '') === 'page-auth';
$isAdminArea = str_starts_with($template ?? '', 'admin/');
$navAuthenticated = Core\Session::accountId() !== null;
$navUsername = is_string($_SESSION['username'] ?? null) ? $_SESSION['username'] : 'Tài khoản';
$navUser = $data['user'] ?? null;
if ($navUser === null && $navAuthenticated && isset($GLOBALS['app']) && $GLOBALS['app'] instanceof Core\Application) {
    $navUser = $GLOBALS['app']->auth()->user();
}
$navRole = $navUser['ten_vai_tro'] ?? null;
$accountArea = null;
if (in_array($template, ['auth/account', 'auth/settings', 'services/page', 'auth/role', 'facility/bank/index', 'facility/bank/form'], true)) {
    $accountArea = [
        'QUAN_LY_CONG_TY' => ['/company', 'Quản lý công ty'],
        'QUAN_LY_CO_SO' => ['/facility', 'Quản lý cơ sở'],
        'NHAN_VIEN' => ['/staff', 'Nhân viên'],
    ][$navRole] ?? null;
}
$publicHeader = !$isAdminArea && $accountArea === null;
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#101411">
    <meta name="description" content="STRYKE — Hệ thống sân thể thao đa bộ môn tại Việt Nam, dành cho các môn thể thao có nhu cầu đặt sân.">
    <title><?= e($title ?? 'Quản lý sân thể thao') ?> — STRYKE</title>
    <link rel="stylesheet" href="<?= e(base_path() . '/assets/css/app.css?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/css/app.css')) ?>">
    <?php if (($bodyClass ?? '') === 'page-catalog'): ?>
        <link rel="stylesheet"
              href="<?= e(base_path() . '/assets/css/catalog.css?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/css/catalog.css')) ?>">
    <?php endif; ?>
    <script src="<?= e(base_path() . '/assets/js/app.js') ?>" defer></script>
    <?php if ($publicHeader): ?>
        <link rel="stylesheet" href="<?= e(base_path() . '/assets/css/public-header.css?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/css/public-header.css')) ?>">
        <script src="<?= e(base_path() . '/assets/js/public-header.js') ?>" defer></script>
    <?php endif; ?>
    <?php if ($publicHeader && $template === 'home'): ?>
        <link rel="stylesheet"
            href="<?= e(base_path() . '/assets/css/public-page-transition.css?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/css/public-page-transition.css')) ?>">
    <?php endif; ?>
    <?php if ($template === 'services/page'): ?><link rel="stylesheet" href="<?= e(base_path() . '/assets/css/admin.css?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/css/admin.css')) ?>"><?php endif; ?>
    <?php if (str_starts_with($template, 'courts/')): ?>
        <link rel="stylesheet" href="<?= e(base_path() . '/assets/css/courts.css') ?>">
        <script src="<?= e(base_path() . '/assets/js/courts.js') ?>" defer></script>
    <?php endif; ?>
</head>
<body class="<?= e($bodyClass ?? '') ?><?= $template === 'services/page' ? ' page-admin page-services' : '' ?>">
<a class="skip-link" href="#main-content">Bỏ qua điều hướng</a>
<header class="navbar<?= $publicHeader ? ' stryke-header' : '' ?>">
    <nav class="container navbar__inner" aria-label="Điều hướng chính">
        <a class="navbar__brand" href="<?= e(url($accountArea[0] ?? ($isAdminArea ? '/admin' : '/'))) ?>" aria-label="STRYKE — Trang chủ">
            <svg class="brand-mark" viewBox="0 0 36 36" fill="none" aria-hidden="true"><rect x="3" y="5" width="30" height="26" rx="1"/><path d="M18 5v26M3 11h7v14H3m30-14h-7v14h7"/><circle cx="18" cy="18" r="5"/></svg>
            <span>STRYKE<span class="brand-dot">.</span></span>
        </a>
        <button class="navbar__toggle" type="button" id="nav-toggle" aria-controls="nav-menu" aria-expanded="false" aria-label="Mở menu" hidden><span></span><span></span></button>
        <div class="navbar__links" id="nav-menu">
            <?php if ($publicHeader): ?><div class="stryke-header__menu-content"><div class="stryke-header__primary"><?php endif; ?>
            <?php if ($accountArea !== null): ?>
                <a href="<?= e(url($accountArea[0])) ?>"><?= e($accountArea[1]) ?></a>
                <?php if (in_array($navRole, ['QUAN_LY_CO_SO', 'NHAN_VIEN', 'QUAN_LY_CONG_TY'], true)): ?>
                    <a href="<?= e(url('/admin/bookings')) ?>">Duyệt booking</a>
                    <a href="<?= e(url('/admin/invoices')) ?>">Hóa đơn</a>
                <?php endif; ?>
                <a href="<?= e(url('/account')) ?>">Thông tin cá nhân</a>
            <?php elseif ($isAdminArea): ?>
                <a href="<?= e(url('/admin')) ?>">Tổng quan</a>
                <a href="<?= e(url('/admin/bo-mon')) ?>">Bộ môn</a>
                <a href="<?= e(url('/admin/loai-san')) ?>">Loại sân</a>
            <?php else: ?>
                <a href="<?= e(url('/')) ?>"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m3 10 9-7 9 7M5 9v12h5v-7h4v7h5V9"/></svg><span>Trang chủ</span></a>
                <a href="<?= e(url('/courts')) ?>"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18m-14 4h2m6 0h2"/></svg><span>Đặt sân</span></a>
                <?php if (!$navAuthenticated || ($app->auth()->user()['ten_vai_tro'] ?? '') === 'KHACH_HANG'): ?>
                    <a href="<?= e(url('/booking/history')) ?>"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 11a9 9 0 1 1 2.6 7.4M3 5v6h6m3-4v5l3 2"/></svg><span>Lịch sử đặt sân</span></a>
                <?php endif; ?>
                <a href="<?= e(url('/') . '#about') ?>"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v.01"/></svg><span>Về STRYKE</span></a>
            <?php endif; ?>
            <?php if ($publicHeader): ?></div><div class="stryke-header__account"><?php endif; ?>
            <span class="nav-divider" aria-hidden="true"></span>
            <?php if ($navAuthenticated): ?>
                <details class="account-menu" id="account-menu">
                    <summary class="account-menu__trigger">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c.4-3.7 2.9-5.5 7-5.5s6.6 1.8 7 5.5"/></svg>
                        <span class="account-menu__name" title="<?= e($navUsername) ?>"><?= e($navUsername) ?></span>
                        <svg class="account-menu__chevron" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m3 6 5 5 5-5"/></svg>
                    </summary>
                    <div class="account-menu__panel">
                        <a href="<?= e(url('/account')) ?>">Thông tin cá nhân</a>
                        <?php if ($navRole === 'KHACH_HANG'): ?>
                            <a href="<?= e(url('/booking/history')) ?>">Lịch sử đặt sân</a>
                        <?php elseif (in_array($navRole, ['QUAN_LY_CO_SO', 'NHAN_VIEN'], true)): ?>
                            <a href="<?= e(url('/admin/bookings')) ?>">Quản lý đặt sân</a>
                            <a href="<?= e(url('/admin/invoices')) ?>">Hóa đơn cơ sở</a>
                        <?php elseif ($navRole === 'QUAN_LY_CONG_TY'): ?>
                            <a href="<?= e(url('/admin/bookings')) ?>">Quản lý đặt sân</a>
                            <a href="<?= e(url('/admin/invoices')) ?>">Hóa đơn công ty</a>
                        <?php elseif ($navRole === 'QUAN_TRI_TAP_DOAN'): ?>
                            <a href="<?= e(url('/admin')) ?>">Bảng quản trị</a>
                            <a href="<?= e(url('/admin/bookings')) ?>">Quản lý booking</a>
                            <a href="<?= e(url('/admin/invoices')) ?>">Quản lý hóa đơn</a>
                        <?php endif; ?>
                        <a href="<?= e(url('/account/settings')) ?>">Cài đặt tài khoản</a>
                        <form method="post" action="<?= e(url('/logout')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit">Đăng xuất</button>
                        </form>
                    </div>
                </details>
            <?php else: ?>
                <a href="<?= e(url('/login')) ?>" <?= $isAuthPage ? 'data-auth-link' : '' ?>>Đăng nhập</a>
                <a class="btn btn--primary btn--small" href="<?= e(url('/register')) ?>" <?= $isAuthPage ? 'data-auth-link' : '' ?>>Đăng ký <span aria-hidden="true">↗</span></a>
            <?php endif; ?>
            <?php if ($publicHeader): ?></div></div><?php endif; ?>
        </div>
    </nav>
</header>
<main id="main-content" tabindex="-1">
    <?= $content /* Trusted local templates; dynamic values are escaped in each view. */ ?>
</main>
<footer class="footer">
    <div class="container footer__inner">
        <p><strong>STRYKE.</strong> <span>Gặp nhau trên sân.</span></p>
        <p>&copy; <?= e(date('Y')) ?> STRYKE <span class="footer__separator">/</span> Thể thao kết nối chúng ta.</p>
    </div>
</footer>
</body>
</html>
