<?php
$bodyClass = 'page-catalog';
$listPath = '/admin/bo-mon';
$listLabel = 'bộ môn';
$statusLabels = ['HOAT_DONG' => 'Hoạt động', 'NGUNG' => 'Ngừng'];
$filterFields = [
    'q' => ['label' => 'Tên bộ môn'],
    'trang_thai' => ['label' => 'Trạng thái', 'options' => $statusLabels],
];
?>
<section class="container catalog-page catalog-workspace admin-list">
    <nav class="catalog-nav" aria-label="Quản lý danh mục">
        <a href="<?= e(url('/admin')) ?>">Quản trị</a>
        <a href="<?= e(url('/admin/bo-mon')) ?>">Bộ môn</a>
        <a href="<?= e(url('/admin/loai-san')) ?>">Loại sân</a>
    </nav>
    <header class="catalog-heading">
        <div><p class="eyebrow">STRYKE / Danh mục</p><h1>Bộ môn thể thao</h1></div>
        <a class="btn btn--primary" href="<?= e(url('/admin/bo-mon/create')) ?>">Thêm bộ môn</a>
    </header>
    <?php if (!empty($notice)): ?><p class="alert alert--success" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php require __DIR__ . '/../partials/list-toolbar.php'; ?>
    <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách bộ môn">
        <table class="catalog-table">
            <thead><tr><th class="list-stt" scope="col">STT</th><th scope="col">Tên bộ môn</th><th scope="col">Mô tả</th><th scope="col">Số loại sân</th><th scope="col">Trạng thái</th><th scope="col">Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($items as $index => $row): ?>
                <tr>
                    <td class="list-stt"><?= e(($page - 1) * $filters['per_page'] + $index + 1) ?></td>
                    <th scope="row"><?= e($row['ten_bo_mon']) ?></th>
                    <td><?= e($row['mo_ta'] ?? '—') ?></td>
                    <td><?= e($row['so_loai_san']) ?></td>
                    <td><span class="catalog-status" data-status="<?= e($row['trang_thai']) ?>"><?= $row['trang_thai'] === 'HOAT_DONG' ? 'Hoạt động' : 'Ngừng' ?></span></td>
                    <td><?php $recordId = $row['ma_bo_mon']; require __DIR__ . '/../partials/list-actions.php'; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="6">Không tìm thấy bộ môn phù hợp. Hãy thay đổi bộ lọc hoặc thêm bộ môn.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
</section>

