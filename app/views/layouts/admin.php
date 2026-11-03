<?php
$adminUsername = is_string($_SESSION['username'] ?? null) ? $_SESSION['username'] : 'Admin';
$workspaceRole = $currentUser['ten_vai_tro'] ?? $data['user']['ten_vai_tro'] ?? $data['role'] ?? null;
$currentUser ??= ['ten_vai_tro' => $workspaceRole];
$workspaceRoleLabel = ['QUAN_TRI_TAP_DOAN' => 'Quản trị tập đoàn', 'QUAN_LY_CONG_TY' => 'Quản lý công ty', 'QUAN_LY_CO_SO' => 'Quản lý cơ sở', 'NHAN_VIEN' => 'Nhân viên'][$workspaceRole] ?? 'Tài khoản';
$workspaceHome = ['QUAN_TRI_TAP_DOAN' => '/admin', 'QUAN_LY_CONG_TY' => '/company', 'QUAN_LY_CO_SO' => '/facility', 'NHAN_VIEN' => '/staff'][$workspaceRole] ?? '/account';
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#13251B">
    <title><?= e($title ?? 'Quản trị') ?> — STRYKE</title>
    <link rel="stylesheet" href="<?= e(base_path() . '/assets/css/app.css?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_path() . '/assets/css/catalog.css?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/css/catalog.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_path() . '/assets/css/admin.css?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/css/admin.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_path() . '/assets/css/admin-catalog.css?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/css/admin-catalog.css')) ?>">
    <script src="<?= e(base_path() . '/assets/js/app.js?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/js/app.js')) ?>" defer></script>
    <script src="<?= e(base_path() . '/assets/js/admin.js?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/js/admin.js')) ?>" defer></script>
</head>
<body class="page-admin page-catalog <?= e($bodyClass ?? '') ?>">
<a class="skip-link" href="#main-content">Bỏ qua điều hướng</a>
<div class="admin-shell">
    <?php require dirname(__DIR__) . '/admin/partials/sidebar.php'; ?>
    <button class="admin-backdrop" id="admin-backdrop" type="button" aria-label="Đóng menu quản trị" tabindex="-1" hidden></button>
    <div class="admin-main" id="admin-main">
        <header class="admin-topbar">
            <button class="admin-menu-toggle" id="admin-menu-toggle" type="button" aria-controls="admin-sidebar" aria-expanded="false" aria-label="Mở menu quản trị" hidden>
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <button class="admin-collapse-toggle" data-sidebar-collapse type="button" aria-controls="admin-sidebar" aria-expanded="true" aria-label="Thu gọn Sidebar" hidden><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M9 4v16m6-11-3 3 3 3"/></svg></button>
            <div class="admin-topbar__heading">
                <p class="admin-topbar__label"><?= e($workspaceRoleLabel) ?></p>
                <p class="admin-topbar__title">STRYKE Workspace</p>
            </div>
            <details class="account-menu" id="account-menu">
                <summary class="account-menu__trigger">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c.4-3.7 2.9-5.5 7-5.5s6.6 1.8 7 5.5"/></svg>
                    <span class="account-menu__name" title="<?= e($adminUsername) ?>"><?= e($adminUsername) ?></span>
                    <svg class="account-menu__chevron" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m3 6 5 5 5-5"/></svg>
                </summary>
                <div class="account-menu__panel">
                    <a href="<?= e(url('/account')) ?>">Thông tin cá nhân</a>
                    <?php if (($role ?? '') === 'QUAN_LY_CO_SO'): ?>
                        <a href="<?= e(url('/facility')) ?>">Khu vực cơ sở</a>
                    <?php elseif (($role ?? '') === 'NHAN_VIEN'): ?>
                        <a href="<?= e(url('/staff')) ?>">Khu vực nhân viên</a>
                    <?php elseif (($role ?? '') === 'QUAN_LY_CONG_TY'): ?>
                        <a href="<?= e(url('/company')) ?>">Khu vực công ty</a>
                    <?php endif; ?>
                    <a href="<?= e(url('/account/settings')) ?>">Cài đặt tài khoản</a>
                    <form method="post" action="<?= e(url('/logout')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit">Đăng xuất</button>
                    </form>
                </div>
            </details>
        </header>
        <main id="main-content" tabindex="-1">
            <?php
            $workspaceSection = null;
            foreach ($menu as $navigationItems) {
                foreach ($navigationItems as $navigationItem) {
                    if ($navigationItem['route'] !== null && ($template === $navigationItem['template'] || str_starts_with($template, $navigationItem['template'] . '/'))) $workspaceSection = $navigationItem;
                }
            }
            $workspaceLeaf = $data['servicePage'] ?? basename($template);
            $workspaceLeafLabel = ['create' => 'Thêm mới', 'edit' => 'Chỉnh sửa', 'show' => 'Chi tiết', 'delete' => 'Xác nhận xóa', 'account' => 'Thông tin cá nhân', 'settings' => 'Cài đặt tài khoản'][$workspaceLeaf] ?? null;
            if ($workspaceLeaf === 'form') $workspaceLeafLabel = empty($data['item']) ? 'Thêm mới' : 'Chỉnh sửa';
            ?>
            <nav class="workspace-breadcrumb" aria-label="Đường dẫn">
                <a href="<?= e(url($workspaceHome)) ?>"><?= e($workspaceRoleLabel) ?></a>
                <?php if (str_starts_with($template, 'facility/bank/')): ?><span aria-hidden="true">/</span><a href="<?= e(url('/facility/bank') . '&facility_id=' . (int) $data['facility']['ma_co_so']) ?>">Ngân hàng cơ sở</a><?php endif; ?>
                <?php if ($workspaceSection && $workspaceSection['route'] !== $workspaceHome): ?><span aria-hidden="true">/</span><a href="<?= e(admin_context_url($workspaceSection['route'])) ?>"><?= e($workspaceSection['label']) ?></a><?php endif; ?>
                <?php if ($workspaceLeafLabel): ?><span aria-hidden="true">/</span><span aria-current="page"><?= e($workspaceLeafLabel) ?></span><?php endif; ?>
            </nav>
            <div class="admin-workspace catalog-workspace admin-catalog"><?= $content /* Trusted templates escape dynamic values. */ ?></div>
        </main>
        <footer class="admin-footer">STRYKE<span class="brand-dot">.</span> <span><?= e($workspaceRoleLabel) ?></span></footer>
    </div>
</div>
<dialog class="workspace-confirm" id="workspace-confirm" aria-labelledby="workspace-confirm-title" aria-describedby="workspace-confirm-description">
    <span class="workspace-confirm__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="m12 3 10 18H2L12 3Z"/><path d="M12 9v5m0 3h.01"/></svg></span>
    <h2 id="workspace-confirm-title">Xác nhận thao tác</h2>
    <p id="workspace-confirm-description"></p>
    <div class="catalog-actions"><button class="btn btn--outline" type="button" data-confirm-cancel autofocus>Hủy</button><button class="btn btn--primary" type="button" data-confirm-accept>Xác nhận</button></div>
</dialog>
</body>
</html>
