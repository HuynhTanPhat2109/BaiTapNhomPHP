<?php
$isAuthPage = ($bodyClass ?? '') === 'page-auth';
$navAuthenticated = Core\Session::accountId() !== null;
$navUsername = is_string($_SESSION['username'] ?? null) ? $_SESSION['username'] : 'Tài khoản';
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#101411">
    <meta name="description" content="STRYKE — Hệ thống sân thể thao đa bộ môn tại Việt Nam, dành cho các môn thể thao có nhu cầu đặt sân.">
    <title><?= e($title ?? 'Quản lý sân thể thao') ?> — STRYKE</title>
    <link rel="stylesheet" href="<?= e(base_path() . '/assets/css/app.css') ?>">
    <script src="<?= e(base_path() . '/assets/js/app.js') ?>" defer></script>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<a class="skip-link" href="#main-content">Bỏ qua điều hướng</a>
<header class="navbar">
    <nav class="container navbar__inner" aria-label="Điều hướng chính">
        <a class="navbar__brand" href="<?= e(url('/')) ?>" aria-label="STRYKE — Trang chủ">
            <svg class="brand-mark" viewBox="0 0 36 36" fill="none" aria-hidden="true"><rect x="3" y="5" width="30" height="26" rx="1"/><path d="M18 5v26M3 11h7v14H3m30-14h-7v14h7"/><circle cx="18" cy="18" r="5"/></svg>
            <span>STRYKE<span class="brand-dot">.</span></span>
        </a>
        <button class="navbar__toggle" type="button" id="nav-toggle" aria-controls="nav-menu" aria-expanded="false" aria-label="Mở menu" hidden><span></span><span></span></button>
        <div class="navbar__links" id="nav-menu">
            <a href="<?= e(url('/')) ?>">Trang chủ</a>
            <a href="<?= e(url('/') . '#about') ?>">Về STRYKE</a>
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
