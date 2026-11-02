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
<section class="container catalog-page catalog-workspace admin-list workspace-page">
    <nav class="catalog-nav" aria-label="Quản lý danh mục">
        <a href="<?= e(url('/admin')) ?>">Quản trị</a>
        <a href="<?= e(url('/admin/bo-mon')) ?>">Bộ môn</a>
        <a href="<?= e(url('/admin/loai-san')) ?>">Loại sân</a>
    </nav>
    <header class="catalog-heading module-heading">
        <div><p class="eyebrow">STRYKE / Danh mục</p><h1>Bộ môn thể thao</h1><p class="module-subtitle">Danh mục bộ môn và các loại sân liên kết.</p></div>
        <a class="btn btn--primary" href="<?= e(url('/admin/bo-mon/create')) ?>">Thêm bộ môn</a>
    </header>
    <?php if (!empty($notice)): ?><p class="alert alert--success" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php require __DIR__ . '/../partials/list-toolbar.php'; ?>
    <?php $tableLabel = 'Bộ môn thể thao'; $tableTotal = $total; require __DIR__ . '/../partials/list-summary.php'; ?>
    <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách bộ môn">
        <table class="catalog-table workspace-table">
            <colgroup><col class="workspace-col-stt"><col class="workspace-col-name"><col><col class="workspace-col-number"><col class="workspace-col-status"><col class="workspace-col-actions"></colgroup>
            <thead><tr><th class="list-stt" scope="col">STT</th><th scope="col">Tên bộ môn</th><th scope="col">Mô tả</th><th class="workspace-number" scope="col">Số loại sân</th><th class="workspace-number" scope="col">Trạng thái</th><th class="workspace-number" scope="col">Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($items as $index => $row): ?>
                <tr>
                    <td class="list-stt" data-label="STT"><?= e(($page - 1) * $filters['per_page'] + $index + 1) ?></td>
                    <th scope="row"><?= e($row['ten_bo_mon']) ?></th>
                    <td data-label="Mô tả"><?= e($row['mo_ta'] ?? '—') ?></td>
                    <td class="workspace-number" data-label="Số loại sân"><?= e($row['so_loai_san']) ?></td>
                    <td class="workspace-number" data-label="Trạng thái"><span class="catalog-status" data-status="<?= e($row['trang_thai']) ?>"><?= $row['trang_thai'] === 'HOAT_DONG' ? 'Hoạt động' : 'Ngừng' ?></span></td>
                    <td class="workspace-number" data-label="Thao tác"><?php $recordId = $row['ma_bo_mon']; require __DIR__ . '/../partials/list-actions.php'; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="6">Không tìm thấy bộ môn phù hợp. Hãy thay đổi bộ lọc hoặc thêm bộ môn.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
</section>

