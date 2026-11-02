<?php
$bodyClass = 'page-catalog';
$listLabel = 'dịch vụ';
$formatMoney = require dirname(__DIR__) . '/partials/format-money.php';
$filterFields = [
    'q' => ['label' => 'Tên dịch vụ'],
    'ma_co_so' => ['label' => 'Cơ sở', 'options' => array_column(array_map(static fn($f) => $f + ['label' => $f['ten_co_so'] . ' — ' . $f['ten_cong_ty']], $facilities), 'label', 'ma_co_so')],
    'loai_dich_vu' => ['label' => 'Loại dịch vụ', 'options' => App\Services\DichVuService::TYPES],
    'trang_thai' => ['label' => 'Trạng thái', 'options' => App\Services\DichVuService::STATES],
    'don_vi_tinh' => ['label' => 'Đơn vị tính', 'options' => array_combine($units, $units)],
];
$home = substr($listPath, 0, -strlen('/dich-vu'));
?>
<section class="container catalog-page catalog-workspace admin-list service-module data-list<?= $listPath === '/admin/dich-vu' ? ' data-list--admin' : '' ?> workspace-page">
    <p class="service-breadcrumb"><a class="admin-table-link" href="<?= e(url($home)) ?>">Khu vực của bạn</a> / Dịch vụ</p>
    <header class="catalog-heading module-heading">
        <div class="module-heading__copy"><?php if ($listPath === '/admin/dich-vu'): ?><span class="module-heading__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="m12 3 8 5v8l-8 5-8-5V8l8-5Zm-8 5 8 5 8-5M12 13v8M8 5.5l8 5"/></svg></span><?php endif; ?><div><p class="eyebrow">STRYKE / Dịch vụ theo cơ sở</p><h1>Quản lý dịch vụ</h1><p class="form-hint"><?= $canWrite ? 'Quản lý giá, đơn vị tính và số lượng trong phạm vi được giao.' : 'Chỉ xem dịch vụ, đơn giá và tồn tại cơ sở được phân công.' ?></p></div></div>
        <?php if ($canWrite): ?><a class="btn btn--primary" href="<?= e(url($listPath . '/create')) ?>"><?php if ($listPath === '/admin/dich-vu'): ?><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg><?php else: ?>+ <?php endif; ?>Thêm dịch vụ</a><?php endif; ?>
    </header>
    <?php if ($notice): ?><p class="alert alert--success" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php require dirname(__DIR__) . '/partials/list-toolbar.php'; ?>
    <?php $tableLabel = 'Danh sách dịch vụ'; $tableTotal = $total; require dirname(__DIR__) . '/partials/list-summary.php'; ?>
    <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách dịch vụ">
        <table class="catalog-table service-table">
            <colgroup><col class="service-col-stt"><col class="service-col-name"><col class="service-col-facility"><col class="service-col-unit"><col class="service-col-money"><col class="service-col-stock"><col class="service-col-status"><col class="service-col-actions"></colgroup>
            <thead><tr><th class="list-stt" scope="col">STT</th><th class="cell-text" scope="col">Dịch vụ / Loại dịch vụ</th><th class="cell-text" scope="col">Cơ sở</th><th scope="col">Đơn vị tính</th><th class="service-money" scope="col">Đơn giá</th><th class="service-stock-cell" scope="col">Tồn kho</th><th scope="col">Trạng thái</th><th scope="col">Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($items as $index => $row): ?>
                <tr>
                    <td class="list-stt" data-label="STT"><?= e(($page - 1) * $filters['per_page'] + $index + 1) ?></td>
                    <th scope="row"><?= e($row['ten_dich_vu']) ?><small class="workspace-secondary"><span class="service-type" data-type="<?= e($row['loai_dich_vu']) ?>"><?= e(App\Services\DichVuService::TYPES[$row['loai_dich_vu']] ?? 'Không xác định') ?></span></small></th>
                    <td class="data-list-facility cell-text" data-label="Cơ sở"><?= e($row['ten_co_so']) ?><small class="service-secondary"><?= e($row['ten_cong_ty']) ?></small></td>
                    <td data-label="Đơn vị tính"><?= e($row['don_vi_tinh']) ?></td>
                    <td class="service-money" data-label="Đơn giá"><data value="<?= e($row['don_gia']) ?>"><?= e($formatMoney($row['don_gia']) . '/' . $row['don_vi_tinh']) ?></data></td>
                    <td class="service-stock-cell" data-label="Tồn kho">
                        <?= $row['so_luong_ton'] === null ? '<span class="service-stock" title="Dịch vụ không theo dõi số lượng tồn kho">Không theo dõi</span>' : e((int) $row['so_luong_ton']) ?>
                    </td>
                    <td data-label="Trạng thái"><span class="catalog-status" data-status="<?= e($row['trang_thai']) ?>"><?= e(App\Services\DichVuService::STATES[$row['trang_thai']] ?? 'Không xác định') ?></span>
                        <?php if ($row['trang_thai_co_so'] !== 'HOAT_DONG' || $row['trang_thai_cong_ty'] !== 'HOAT_DONG'): ?><small class="service-secondary">Cơ sở/công ty đã ngừng</small><?php endif; ?></td>
                    <td data-label="Thao tác"><?php $recordId = $row['ma_dich_vu']; require dirname(__DIR__) . '/partials/list-actions.php'; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="8">Không tìm thấy dịch vụ phù hợp trong phạm vi được phép.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php require dirname(__DIR__) . '/partials/pagination.php'; ?>
    <p class="form-hint service-note">Dịch vụ cho thuê: số lượng quản lý chưa phản ánh dụng cụ đang thuê/đã trả hoặc số sẵn sàng cho thuê. Trang này không bán hàng, trừ tồn hoặc hoàn tồn.</p>
</section>
