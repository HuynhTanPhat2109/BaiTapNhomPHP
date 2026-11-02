<?php
$bodyClass = 'page-catalog';
$facilities = $facilities ?? []; $contracts = $contracts ?? []; $company = $company ?? [];
$value = static fn($item): string => $item === null || $item === '' ? '—' : e($item);
$formatDate = require dirname(__DIR__) . '/admin/partials/format-date.php';
$formatMoney = require dirname(__DIR__) . '/admin/partials/format-money.php';
?>
<section class="container catalog-page catalog-workspace workspace-page">
    <header class="catalog-heading module-heading"><div><p class="eyebrow">STRYKE / Không gian công ty</p><h1><?= e($company['ten_cong_ty'] ?? '') ?></h1><p class="module-subtitle">Thông tin hợp tác, cơ sở và nhân sự thuộc công ty của bạn.</p></div></header>
    <div class="admin-stats">
        <section class="admin-stat"><h2>Cơ sở trực thuộc</h2><p class="admin-stat__value"><?= e(count($facilities)) ?></p><p>Cơ sở trong dữ liệu công ty</p></section>
        <section class="admin-stat"><h2>Hợp đồng nhượng quyền</h2><p class="admin-stat__value"><?= e(count($contracts)) ?></p><p>Hợp đồng thuộc công ty</p></section>
    </div>
    <section class="detail-card workspace-block" aria-labelledby="company-info-heading">
        <h2 id="company-info-heading">Thông tin công ty</h2>
        <dl class="detail-fields">
            <div><dt>Mã số thuế</dt><dd><?= $value($company['ma_so_thue'] ?? null) ?></dd></div>
            <div><dt>Người đại diện</dt><dd><?= $value($company['nguoi_dai_dien'] ?? null) ?></dd></div>
            <div class="detail-field--wide"><dt>Địa chỉ</dt><dd><?= $value($company['dia_chi'] ?? null) ?></dd></div>
            <div><dt>Số điện thoại</dt><dd><?= $value($company['so_dien_thoai'] ?? null) ?></dd></div>
            <div><dt>Email</dt><dd><?= $value($company['email'] ?? null) ?></dd></div>
            <div><dt>Trạng thái</dt><dd><span class="catalog-status" data-status="<?= ($company['trang_thai'] ?? '') === 'HOAT_DONG' ? 'HOAT_DONG' : 'NGUNG' ?>"><?= ($company['trang_thai'] ?? '') === 'HOAT_DONG' ? 'Hoạt động' : 'Ngừng hoạt động' ?></span></dd></div>
        </dl>
    </section>
    <section class="account-card admin-quick workspace-block" aria-labelledby="company-team-heading">
        <h2 id="company-team-heading">Nhân sự &amp; dịch vụ</h2><p>Truy cập các chức năng trong phạm vi công ty.</p>
        <div class="admin-quick__links">
            <a class="admin-quick-link" href="<?= e(url('/company/employees')) ?>"><span><strong>Quản lý nhân viên</strong><small>Hồ sơ và trạng thái làm việc</small></span><span aria-hidden="true">↗</span></a>
            <a class="admin-quick-link" href="<?= e(url('/company/employees/create')) ?>"><span><strong>Thêm nhân viên</strong><small>Thông tin và phân công nhân sự</small></span><span aria-hidden="true">↗</span></a>
            <a class="admin-quick-link" href="<?= e(url('/company/dich-vu')) ?>"><span><strong>Quản lý dịch vụ</strong><small>Dịch vụ tại các cơ sở trực thuộc</small></span><span aria-hidden="true">↗</span></a>
        </div>
    </section>
    <section class="workspace-block" aria-labelledby="company-facilities-heading">
        <?php $tableLabel = 'Cơ sở trực thuộc'; $tableTotal = count($facilities); require dirname(__DIR__) . '/admin/partials/list-summary.php'; ?>
        <?php if (!$facilities): ?><div class="workspace-empty" role="status"><h2 id="company-facilities-heading">Chưa có cơ sở</h2><p>Công ty hiện chưa có cơ sở trong dữ liệu được phân quyền.</p></div>
        <?php else: ?>
        <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách cơ sở"><table class="catalog-table workspace-table">
            <colgroup><col><col><col><col class="workspace-col-status"></colgroup>
            <thead><tr><th scope="col" id="company-facilities-heading">Cơ sở</th><th scope="col">Liên hệ</th><th scope="col">Giờ hoạt động</th><th class="workspace-number" scope="col">Trạng thái</th></tr></thead>
            <tbody><?php foreach ($facilities as $facility): ?><tr>
                <th scope="row"><?= $value($facility['ten_co_so'] ?? null) ?><small class="workspace-secondary"><?= $value($facility['dia_chi'] ?? null) ?></small></th>
                <td data-label="Liên hệ"><?= $value($facility['so_dien_thoai'] ?? null) ?><small class="workspace-secondary"><?= $value($facility['email'] ?? null) ?></small></td>
                <td data-label="Giờ hoạt động"><?= $value($facility['gio_mo_cua'] ?? null) ?> – <?= $value($facility['gio_dong_cua'] ?? null) ?></td>
                <td class="workspace-number" data-label="Trạng thái"><span class="catalog-status" data-status="<?= e($facility['trang_thai'] ?? '') ?>"><?= ($facility['trang_thai'] ?? '') === 'HOAT_DONG' ? 'Hoạt động' : 'Tạm ngưng' ?></span></td>
            </tr><?php endforeach; ?></tbody>
        </table></div><?php endif; ?>
    </section>
    <section class="workspace-block" aria-labelledby="company-contracts-heading">
        <?php $tableLabel = 'Hợp đồng nhượng quyền'; $tableTotal = count($contracts); require dirname(__DIR__) . '/admin/partials/list-summary.php'; ?>
        <?php if (!$contracts): ?><div class="workspace-empty" role="status"><h2 id="company-contracts-heading">Chưa có hợp đồng</h2><p>Công ty hiện chưa có hợp đồng nhượng quyền.</p></div>
        <?php else: ?>
        <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách hợp đồng"><table class="catalog-table workspace-table">
            <colgroup><col><col><col><col class="workspace-col-status"></colgroup>
            <thead><tr><th scope="col" id="company-contracts-heading">Hợp đồng</th><th scope="col">Thời hạn</th><th scope="col">Điều khoản phí</th><th class="workspace-number" scope="col">Trạng thái</th></tr></thead>
            <tbody><?php foreach ($contracts as $contract): ?><?php $status = $contract['trang_thai'] ?? ''; ?><tr>
                <th scope="row"><?= $value($contract['so_hop_dong'] ?? null) ?><small class="workspace-secondary">Ký ngày <?= e($formatDate($contract['ngay_ky'] ?? null)) ?></small></th>
                <td data-label="Thời hạn"><?= e($formatDate($contract['ngay_bat_dau'] ?? null)) ?><small class="workspace-secondary">Đến <?= e($formatDate($contract['ngay_ket_thuc'] ?? null)) ?></small></td>
                <td data-label="Điều khoản phí"><?= e($formatMoney($contract['phi_nhuong_quyen'] ?? null)) ?><small class="workspace-secondary">Tỷ lệ phí: <?= $value($contract['ty_le_phi'] ?? null) ?>%</small></td>
                <td class="workspace-number" data-label="Trạng thái"><span class="catalog-status" data-status="<?= e($status) ?>"><?= e(['HIEU_LUC' => 'Hiệu lực', 'HET_HAN' => 'Hết hạn', 'CHAM_DUT' => 'Chấm dứt'][$status] ?? 'Không xác định') ?></span></td>
            </tr><?php endforeach; ?></tbody>
        </table></div><?php endif; ?>
    </section>
</section>
