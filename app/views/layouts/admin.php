<?php
$adminUsername = is_string($_SESSION['username'] ?? null) ? $_SESSION['username'] : 'Admin';
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#13251B">
    <title><?= e($title ?? 'Quản trị') ?> — STRYKE</title>
    <link rel="stylesheet" href="<?= e(base_path() . '/assets/css/app.css') ?>">
    <?php if (($bodyClass ?? '') === 'page-catalog'): ?>
        <link rel="stylesheet" href="<?= e(base_path() . '/assets/css/catalog.css') ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e(base_path() . '/assets/css/admin.css?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/css/admin.css')) ?>">
    <script src="<?= e(base_path() . '/assets/js/app.js') ?>" defer></script>
    <script src="<?= e(base_path() . '/assets/js/admin.js') ?>" defer></script>
</head>
<body class="page-admin <?= e($bodyClass ?? '') ?>">
<a class="skip-link" href="#main-content">Bỏ qua điều hướng</a>
<div class="admin-shell">
    <?php require dirname(__DIR__) . '/admin/partials/sidebar.php'; ?>
    <button class="admin-backdrop" id="admin-backdrop" type="button" aria-label="Đóng menu quản trị" tabindex="-1" hidden></button>
    <div class="admin-main" id="admin-main">
        <header class="admin-topbar">
            <button class="admin-menu-toggle" id="admin-menu-toggle" type="button" aria-controls="admin-sidebar" aria-expanded="false" aria-label="Mở menu quản trị" hidden>
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <div class="admin-topbar__heading">
                <p class="admin-topbar__label">Không gian quản trị</p>
                <p class="admin-topbar__title"><?= e($title ?? 'Tổng quan') ?></p>
            </div>
            <details class="account-menu" id="account-menu">
                <summary class="account-menu__trigger">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c.4-3.7 2.9-5.5 7-5.5s6.6 1.8 7 5.5"/></svg>
                    <span class="account-menu__name" title="<?= e($adminUsername) ?>"><?= e($adminUsername) ?></span>
                    <svg class="account-menu__chevron" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m3 6 5 5 5-5"/></svg>
                </summary>
                <div class="account-menu__panel">
                    <a href="<?= e(url('/account')) ?>">Thông tin cá nhân</a>
                    <a href="<?= e(url('/account/settings')) ?>">Cài đặt tài khoản</a>
                    <form method="post" action="<?= e(url('/logout')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit">Đăng xuất</button>
                    </form>
                </div>
            </details>
        </header>
        <main id="main-content" tabindex="-1">
            <?= $content /* Trusted templates escape dynamic values. */ ?>
        </main>
        <footer class="admin-footer">STRYKE<span class="brand-dot">.</span> <span>Quản trị tập đoàn</span></footer>
    </div>
</div>
</body>
</html>
