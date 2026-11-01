<?php
$bodyClass = 'page-catalog';
$listPath = '/admin/san';
$listLabel = 'sân';
$statusLabels = ['HOAT_DONG' => 'Hoạt động', 'BAO_TRI' => 'Bảo trì', 'TAM_NGUNG' => 'Tạm ngừng'];
$filterFields = [
    'q' => ['label' => 'Tên sân'],
    'ma_co_so' => ['label' => 'Cơ sở', 'options' => array_column(array_map(static fn($f) => $f + ['label' => $f['ten_co_so'] . ' — ' . $f['ten_cong_ty']], $facilities), 'label', 'ma_co_so')],
    'ma_loai_san' => ['label' => 'Loại sân', 'options' => array_column(array_map(static fn($t) => $t + ['label' => $t['ten_bo_mon'] . ' — ' . $t['ten_loai_san']], $types), 'label', 'ma_loai_san')],
    'trang_thai' => ['label' => 'Trạng thái', 'options' => $statusLabels],
];
?>
<section class="container catalog-page catalog-workspace admin-list">
    <header class="catalog-heading">
        <div><p class="eyebrow">STRYKE / Sân thể thao</p><h1>Quản lý sân</h1></div>
        <a class="btn btn--primary" href="<?= e(url('/admin/san/create')) ?>">Thêm sân</a>
    </header>
    <?php if (!empty($notice)): ?><p class="alert alert--success" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php require __DIR__ . '/../partials/list-toolbar.php'; ?>
    <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách sân">
        <table class="catalog-table facility-table">
            <thead><tr><th class="list-stt" scope="col">STT</th><th scope="col">Tên sân</th><th scope="col">Cơ sở</th><th scope="col">Bộ môn</th><th scope="col">Loại sân</th><th scope="col">Trạng thái</th><th scope="col">Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($items as $index => $row): ?>
                <tr>
                    <td class="list-stt"><?= e(($page - 1) * $filters['per_page'] + $index + 1) ?></td>
                    <th scope="row"><?= e($row['ten_san']) ?></th>
                    <td><a class="admin-table-link" href="<?= e(url('/admin/co-so/show') . '&id=' . $row['ma_co_so']) ?>"><?= e($row['ten_co_so']) ?></a></td>
                    <td><?= e($row['ten_bo_mon']) ?></td>
                    <td><a class="admin-table-link" href="<?= e(url('/admin/loai-san/show') . '&id=' . $row['ma_loai_san']) ?>"><?= e($row['ten_loai_san']) ?></a></td>
                    <td><span class="catalog-status" data-status="<?= e($row['trang_thai']) ?>"><?= e(['HOAT_DONG' => 'Hoạt động', 'BAO_TRI' => 'Bảo trì', 'TAM_NGUNG' => 'Tạm ngừng'][$row['trang_thai']]) ?></span></td>
                    <td><?php $recordId = $row['ma_san']; require __DIR__ . '/../partials/list-actions.php'; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="7">Không tìm thấy sân phù hợp. Hãy thay đổi bộ lọc hoặc thêm sân.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
</section>
