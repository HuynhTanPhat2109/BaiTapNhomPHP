<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#101c14">
    <title><?= e($title ?? 'Tài khoản') ?> — STRYKE</title>
    <link rel="stylesheet" href="<?= e(base_path() . '/assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= e(base_path() . '/assets/css/auth.css') ?>">
    <script src="<?= e(base_path() . '/assets/js/app.js') ?>" defer></script>
</head>
<body class="page-auth auth-standalone">
    <a class="skip-link" href="#main-content">Đến biểu mẫu</a>
    <main id="main-content" tabindex="-1">
        <a class="auth-home-link" href="<?= e(url('/')) ?>"><span aria-hidden="true">←</span> STRYKE<span class="brand-dot">.</span> <span class="auth-home-label">Về trang chủ</span></a>
        <?= $content /* Trusted templates escape all dynamic fields. */ ?>
    </main>
</body>
</html>
