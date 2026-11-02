<?php
$recordQuery = '&id=' . rawurlencode((string) $recordId);
$serviceActions = str_ends_with($listPath, '/dich-vu');
$buttons = [['view', 'Chi tiết', url($listPath . '/show') . $recordQuery]];
if (!$serviceActions || $canWrite) $buttons[] = ['edit', 'Sửa', url($listPath . '/edit') . $recordQuery];
if ($serviceActions) {
    if ($canWrite) $buttons[] = [$row['trang_thai'] === 'DANG_CUNG_CAP' ? 'stop' : 'activate', $row['trang_thai'] === 'DANG_CUNG_CAP' ? 'Ngừng cung cấp' : 'Kích hoạt', url($listPath . '/show') . $recordQuery . '&confirm=1#status-confirmation'];
} elseif ($listPath === '/admin/bang-gia') {
    $activePrice = $row['trang_thai'] === 'DANG_AP_DUNG';
    $buttons[] = [$activePrice ? 'stop' : 'activate', $activePrice ? 'Ngừng áp dụng' : 'Kích hoạt',
        url($listPath . '/show') . $recordQuery . '&confirm=1#status-confirmation'];
} else {
    $buttons[] = ['delete', 'Xóa', url($listPath . '/delete') . $recordQuery];
}
$icons = [
    'view' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
    'edit' => '<path d="m16 3 5 5M3 21l5-1L21 7a2.1 2.1 0 0 0-5-3L3 17v4Z"/>',
    'delete' => '<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/>',
    'stop' => '<circle cx="12" cy="12" r="9"/><path d="M9 8v8M15 8v8"/>',
    'activate' => '<circle cx="12" cy="12" r="9"/><path d="m9 12 2 2 4-4"/>',
];
$rowMenuId = 'row-actions-' . str_replace('/', '-', trim($listPath, '/')) . '-' . $recordId;
$rowMenuLabel = $row['ten_bang_gia'] ?? $row['ten_dich_vu'] ?? $row['ten_bo_mon'] ?? $row['ten_loai_san'] ?? $row['ten_co_so'] ?? $row['ten_san'] ?? 'bản ghi';
$rowMenuHasForms = false;
$rowMenuUsePopover = $serviceActions || $listPath === '/admin/bang-gia';
ob_start();
?>
    <?php foreach ($buttons as [$kind, $label, $href]): ?>
        <a class="list-action list-action--<?= e($kind) ?>" href="<?= e($href) ?>" title="<?= e($label) ?>"<?= $rowMenuUsePopover ? ' role="menuitem"' : '' ?>>
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><?= $icons[$kind] /* Fixed SVG paths only. */ ?></svg><span><?= e(!$rowMenuUsePopover && $kind === 'view' ? 'Xem' : $label) ?></span>
        </a>
    <?php endforeach; ?>
<?php $rowMenuContent = ob_get_clean(); ?>
<div class="list-actions<?= $rowMenuUsePopover ? '' : ' list-actions--direct' ?>"><?php require __DIR__ . '/row-menu.php'; ?></div>
