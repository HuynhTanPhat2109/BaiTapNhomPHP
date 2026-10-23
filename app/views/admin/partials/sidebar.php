<?php
$menu = require dirname(__DIR__, 4) . '/config/admin-menu.php';
$icons = [
    'overview' => 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z',
    'company' => 'M4 21V5l8-3v19M12 9h8v12M2 21h20M7 7h2M7 11h2M7 15h2M15 12h2M15 16h2',
    'contract' => 'M14 3H5v18h14V8zM14 3v5h5M8 12h8M8 16h4m2 1 2 2 4-4',
    'people' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
    'sport' => 'M21 12a9 9 0 1 0-18 0 9 9 0 0 0 18 0M12 3c-5 5-5 13 0 18M12 3c5 5 5 13 0 18M3 12h18',
    'layers' => 'm12 3 10 5-10 5L2 8zM2 12l10 5 10-5M2 16l10 5 10-5',
    'location' => 'M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0M15 10a3 3 0 1 0-6 0 3 3 0 0 0 6 0',
    'court' => 'M3 4h18v16H3zM12 4v16M3 8h5v8H3m18-8h-5v8h5M14 12a2 2 0 1 0-4 0 2 2 0 0 0 4 0',
    'price' => 'M3 3h7l11 11-7 7L3 10zM8 7h.01',
    'service' => 'm12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5z',
    'calendar' => 'M4 5h16v16H4zM8 3v4M16 3v4M4 11h16M8 15h2M14 15h2M8 18h2',
    'payment' => 'M3 5h18v14H3zM3 10h18M7 15h4',
    'invoice' => 'M5 3h14v18l-3-2-4 2-4-2-3 2zM8 7h8M8 11h8M8 15h4',
];
?>
<aside class="admin-sidebar" id="admin-sidebar" aria-label="Điều hướng quản trị">
    <div class="admin-sidebar__brand">
        <a class="navbar__brand" href="<?= e(url('/admin')) ?>" aria-label="STRYKE — Tổng quan quản trị">
            <svg class="brand-mark" viewBox="0 0 36 36" fill="none" aria-hidden="true"><rect x="3" y="5" width="30" height="26" rx="1"/><path d="M18 5v26M3 11h7v14H3m30-14h-7v14h7"/><circle cx="18" cy="18" r="5"/></svg>
            <span>STRYKE<span class="brand-dot">.</span></span>
        </a>
        <button class="admin-sidebar__close" id="admin-sidebar-close" type="button" aria-label="Đóng menu quản trị" hidden>
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6"/></svg>
        </button>
    </div>
    <nav class="admin-sidebar__nav" aria-label="Chức năng quản trị">
        <?php foreach ($menu as $group => $items): ?>
            <section class="admin-menu-group" aria-label="<?= e($group) ?>">
                <h2><?= e($group) ?></h2>
                <?php foreach ($items as $item): ?>
                    <?php $active = $item['route'] !== null && ($template === $item['template'] || str_starts_with($template, $item['template'] . '/')); ?>
                    <?php if ($item['route'] !== null): ?>
                        <a class="admin-menu__item<?= $active ? ' is-active' : '' ?>" href="<?= e(url($item['route'])) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                    <?php else: ?>
                        <span class="admin-menu__item is-disabled" aria-disabled="true">
                    <?php endif; ?>
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="<?= e($icons[$item['icon']] ?? $icons['overview']) ?>"/></svg>
                            <span class="admin-menu__text"><?= e($item['label']) ?><?php if ($item['route'] === null): ?><small>Sắp phát triển</small><?php endif; ?></span>
                    <?php if ($item['route'] !== null): ?></a><?php else: ?></span><?php endif; ?>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </nav>
    <p class="admin-sidebar__footer"><span class="status-dot" aria-hidden="true"></span> Khu vực quản trị STRYKE</p>
</aside>
