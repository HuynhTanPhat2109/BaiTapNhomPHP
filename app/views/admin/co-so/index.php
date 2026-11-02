<?php
$bodyClass = 'page-catalog';
$listPath = '/admin/co-so';
$listLabel = 'cơ sở';
$statusLabels = ['HOAT_DONG' => 'Hoạt động', 'TAM_NGUNG' => 'Tạm ngừng'];
$filterFields = [
    'q' => ['label' => 'Tên cơ sở'],
    'ma_cong_ty' => ['label' => 'Công ty', 'options' => array_column($companies, 'ten_cong_ty', 'ma_cong_ty')],
    'trang_thai' => ['label' => 'Trạng thái', 'options' => $statusLabels],
];
?>
<section class="container catalog-page catalog-workspace admin-list workspace-page">
    <header class="catalog-heading module-heading">
        <div><p class="eyebrow">STRYKE / Cơ sở thể thao</p><h1>Quản lý cơ sở</h1><p class="module-subtitle">Thông tin cơ sở, liên hệ và giờ hoạt động.</p></div>
        <a class="btn btn--primary" href="<?= e(url('/admin/co-so/create')) ?>">Thêm cơ sở</a>
    </header>
    <?php if (!empty($notice)): ?><p class="alert alert--success" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php require __DIR__ . '/../partials/list-toolbar.php'; ?>
    <?php $tableLabel = 'Cơ sở thể thao'; $tableTotal = $total; require __DIR__ . '/../partials/list-summary.php'; ?>
    <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách cơ sở">
        <table class="catalog-table workspace-table">
            <colgroup><col class="workspace-col-stt"><col class="workspace-col-name"><col><col><col class="workspace-col-number"><col class="workspace-col-status"><col class="workspace-col-actions"></colgroup>
            <thead><tr><th class="list-stt" scope="col">STT</th><th scope="col">Tên cơ sở</th><th scope="col">Địa chỉ</th><th class="workspace-number" scope="col">Giờ hoạt động</th><th class="workspace-number" scope="col">Số sân</th><th class="workspace-number" scope="col">Trạng thái</th><th class="workspace-number" scope="col">Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($items as $index => $row): ?>
                <tr>
                    <td class="list-stt" data-label="STT"><?= e(($page - 1) * $filters['per_page'] + $index + 1) ?></td>
                    <th scope="row"><?= e($row['ten_co_so']) ?><small class="workspace-secondary"><?= e($row['ten_cong_ty']) ?></small></th>
                    <td data-label="Địa chỉ"><?= e($row['dia_chi']) ?></td>
                    <td class="workspace-number" data-label="Giờ hoạt động"><?= e($row['gio_mo_cua']) ?> – <?= e($row['gio_dong_cua']) ?></td>
                    <td class="workspace-number" data-label="Số sân"><?= e($row['so_san']) ?></td>
                    <td class="workspace-number" data-label="Trạng thái"><span class="catalog-status" data-status="<?= e($row['trang_thai']) ?>"><?= $row['trang_thai'] === 'HOAT_DONG' ? 'Hoạt động' : 'Tạm ngừng' ?></span>
                        <?php if ($row['trang_thai_cong_ty'] !== 'HOAT_DONG'): ?><small>Công ty đã ngừng</small><?php endif; ?></td>
                    <td class="workspace-number" data-label="Thao tác"><?php $recordId = $row['ma_co_so']; require __DIR__ . '/../partials/list-actions.php'; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="7">Không tìm thấy cơ sở phù hợp. Hãy thay đổi bộ lọc hoặc thêm cơ sở.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
</section>
