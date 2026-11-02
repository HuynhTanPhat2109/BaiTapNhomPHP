<?php
$bodyClass = 'page-catalog page-services';
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
<section class="container catalog-page catalog-workspace admin-list service-module">
    <p class="service-breadcrumb"><a class="admin-table-link" href="<?= e(url($home)) ?>">Khu vực của bạn</a> / Dịch vụ</p>
    <header class="catalog-heading">
        <div><p class="eyebrow">STRYKE / Dịch vụ theo cơ sở</p><h1>Quản lý dịch vụ</h1><p class="form-hint"><?= $canWrite ? 'Quản lý giá, đơn vị tính và số lượng trong phạm vi được giao.' : 'Chỉ xem dịch vụ, đơn giá và tồn tại cơ sở được phân công.' ?></p></div>
        <?php if ($canWrite): ?><a class="btn btn--primary" href="<?= e(url($listPath . '/create')) ?>">+ Thêm dịch vụ</a><?php endif; ?>
    </header>
    <?php if ($notice): ?><p class="alert alert--success" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php require dirname(__DIR__) . '/partials/list-toolbar.php'; ?>
    <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách dịch vụ" style="background: var(--surface, #fff); border: 1px solid var(--border, #e0e0e0); border-radius: 12px; overflow-x: auto; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04); margin-bottom: 20px;">
        <table class="catalog-table service-table" style="width: 100%; min-width: 960px; border-collapse: collapse;">
            <thead><tr><th class="list-stt" scope="col">STT</th><th scope="col">Tên dịch vụ</th><th scope="col">Cơ sở</th><th scope="col">Loại dịch vụ</th><th scope="col">Đơn vị tính</th><th class="service-money" scope="col">Đơn giá</th><th class="service-stock-cell" scope="col">Tồn kho</th><th scope="col">Trạng thái</th><th class="service-actions-col" scope="col" style="text-align: right; padding-right: 20px;">Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($items as $index => $row): ?>
                <tr>
                    <td class="list-stt"><?= e(($page - 1) * $filters['per_page'] + $index + 1) ?></td>
                    <th scope="row"><?= e($row['ten_dich_vu']) ?></th>
                    <td><?= e($row['ten_co_so']) ?><small class="service-secondary"><?= e($row['ten_cong_ty']) ?></small></td>
                    <td><span class="service-type" data-type="<?= e($row['loai_dich_vu']) ?>"><?= e(App\Services\DichVuService::TYPES[$row['loai_dich_vu']] ?? 'Không xác định') ?></span></td>
                    <td><?= e($row['don_vi_tinh']) ?></td>
                    <td class="service-money"><?= e($formatMoney($row['don_gia'])) ?><small class="service-secondary">/<?= e($row['don_vi_tinh']) ?></small></td>
                    <td class="service-stock-cell">
                        <?php if ($row['so_luong_ton'] === null): ?><span class="service-stock">Không theo dõi tồn</span>
                        <?php elseif ($row['loai_dich_vu'] === 'CHO_THUE'): ?><span class="service-stock">Số quản lý: <?= e($row['so_luong_ton']) ?></span>
                        <?php elseif ((int) $row['so_luong_ton'] === 0): ?><span class="service-stock service-stock--empty">Hết hàng · 0</span>
                        <?php else: ?><span class="service-stock service-stock--available">Còn hàng · <?= e($row['so_luong_ton']) ?></span><?php endif; ?>
                    </td>
                    <td><span class="catalog-status" data-status="<?= e($row['trang_thai']) ?>"><?= e(App\Services\DichVuService::STATES[$row['trang_thai']] ?? 'Không xác định') ?></span>
                        <?php if ($row['trang_thai_co_so'] !== 'HOAT_DONG' || $row['trang_thai_cong_ty'] !== 'HOAT_DONG'): ?><small class="service-secondary">Cơ sở/công ty đã ngừng</small><?php endif; ?></td>
                    <td class="service-actions-col" style="text-align: right; padding-right: 20px; white-space: nowrap;"><?php $recordId = $row['ma_dich_vu']; require dirname(__DIR__) . '/partials/list-actions.php'; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="9">Không tìm thấy dịch vụ phù hợp trong phạm vi được phép.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php require dirname(__DIR__) . '/partials/pagination.php'; ?>
    <p class="form-hint service-note">Dịch vụ cho thuê: số lượng quản lý chưa phản ánh dụng cụ đang thuê/đã trả hoặc số sẵn sàng cho thuê. Trang này không bán hàng, trừ tồn hoặc hoàn tồn.</p>
</section>
