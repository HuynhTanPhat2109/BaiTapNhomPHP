<?php
$bodyClass = 'page-catalog';
$listPath = '/admin/bang-gia';
$listLabel = 'bảng giá';
$formatPrice = require __DIR__ . '/format-price.php';
$dayLabels = ['NGAY_THUONG' => 'Ngày thường (T2–T6)', 'CUOI_TUAN' => 'Cuối tuần (T7–CN)'];
$statusLabels = ['DANG_AP_DUNG' => 'Đang áp dụng', 'NGUNG' => 'Ngừng áp dụng'];
$filterFields = [
    'q' => ['label' => 'Tên bảng giá'],
    'ma_co_so' => ['label' => 'Cơ sở', 'options' => array_column(array_map(static fn($f) => $f + ['label' => $f['ten_co_so'] . ' — ' . $f['ten_cong_ty']], $facilities), 'label', 'ma_co_so')],
    'ma_loai_san' => ['label' => 'Loại sân', 'options' => array_column(array_map(static fn($t) => $t + ['label' => $t['ten_bo_mon'] . ' — ' . $t['ten_loai_san']], $types), 'label', 'ma_loai_san')],
    'loai_ngay' => ['label' => 'Loại ngày', 'options' => $dayLabels],
    'trang_thai' => ['label' => 'Trạng thái', 'options' => $statusLabels],
];
?>
<section class="container catalog-page admin-list pricing-list">
    <header class="catalog-heading">
        <div><p class="eyebrow">STRYKE / Giá sân thể thao</p><h1>Quản lý bảng giá</h1></div>
        <a class="btn btn--primary" href="<?= e(url('/admin/bang-gia/create')) ?>">Thêm bảng giá</a>
    </header>
    <?php if ($notice): ?><p class="alert alert--success" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php require __DIR__ . '/../partials/list-toolbar.php'; ?>
    <p class="form-hint">Đơn giá tính theo VNĐ/giờ. Thời hạn áp dụng tính cả ngày bắt đầu và ngày kết thúc.</p>
    <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách bảng giá">
        <table class="catalog-table pricing-table">
            <colgroup><col class="pricing-col-stt"><col class="pricing-col-name"><col class="pricing-col-facility"><col class="pricing-col-type"><col class="pricing-col-day"><col class="pricing-col-dates"><col class="pricing-col-hours"><col class="pricing-col-money"><col class="pricing-col-status"><col class="pricing-col-actions"></colgroup>
            <thead><tr><th class="list-stt" scope="col">STT</th><th scope="col">Tên bảng giá</th><th scope="col">Cơ sở</th><th scope="col">Bộ môn / Loại sân</th><th scope="col">Loại ngày</th><th scope="col">Thời hạn áp dụng</th><th scope="col">Khung giờ</th><th scope="col">Đơn giá</th><th scope="col">Trạng thái</th><th scope="col">Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($items as $index => $row): ?>
                <tr>
                    <td class="list-stt"><?= e(($page - 1) * $filters['per_page'] + $index + 1) ?></td>
                    <th scope="row"><span class="pricing-copy" title="<?= e($row['ten_bang_gia']) ?>"><?= e($row['ten_bang_gia']) ?></span></th>
                    <td><a class="admin-table-link pricing-copy pricing-facility" title="<?= e($row['ten_co_so']) ?>" href="<?= e(url('/admin/co-so/show') . '&id=' . $row['ma_co_so']) ?>"><?= e($row['ten_co_so']) ?></a></td>
                    <td><a class="admin-table-link pricing-type" title="<?= e($row['ten_bo_mon'] . ' / ' . $row['ten_loai_san']) ?>" href="<?= e(url('/admin/loai-san/show') . '&id=' . $row['ma_loai_san']) ?>"><small><?= e($row['ten_bo_mon']) ?></small><span class="pricing-copy"><?= e($row['ten_loai_san']) ?></span></a></td>
                    <td><span><?= $row['loai_ngay'] === 'CUOI_TUAN' ? 'Cuối tuần' : 'Ngày thường' ?></span><small class="pricing-secondary"><?= $row['loai_ngay'] === 'CUOI_TUAN' ? '(T7–CN)' : '(T2–T6)' ?></small></td>
                    <td><time datetime="<?= e($row['ngay_bat_dau']) ?>"><?= e($row['ngay_bat_dau']) ?></time><small class="pricing-secondary"><?= $row['ngay_ket_thuc'] === null ? 'Không thời hạn' : 'đến ' . e($row['ngay_ket_thuc']) ?></small></td>
                    <td class="pricing-hours"><?= e(substr($row['gio_bat_dau'], 0, 5)) ?>–<?= e(substr($row['gio_ket_thuc'], 0, 5)) ?></td>
                    <td><data class="pricing-money" title="<?= e($formatPrice($row['don_gia'])) ?>" value="<?= e($row['don_gia']) ?>"><?= e($formatPrice($row['don_gia'])) ?></data></td>
                    <td><span class="catalog-status" data-status="<?= e($row['trang_thai']) ?>"><?= e($statusLabels[$row['trang_thai']]) ?></span></td>
                    <td><?php $recordId = $row['ma_bang_gia']; require __DIR__ . '/../partials/list-actions.php'; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="10">Không có bảng giá phù hợp. Hãy thay đổi bộ lọc hoặc thêm bảng giá.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
</section>
