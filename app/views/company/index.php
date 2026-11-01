<?php $bodyClass = 'page-catalog';
$facilities = $facilities ?? [];
$contracts = $contracts ?? [];
$company = $company ?? [];
$value = static fn ($item): string => $item === null || $item === '' ? '—' : e($item);
?>
<section class="container account-page catalog-page">
    <header class="account-page__intro catalog-heading">
        <p class="eyebrow">STRYKE / Công ty</p>
        <h1 class="account-h1"><?= e($company['ten_cong_ty'] ?? '') ?></h1>
    </header>

    <section aria-labelledby="company-info-heading">
        <h2 id="company-info-heading">Thông tin công ty</h2>
        <div class="account-card"><dl class="catalog-details">
            <div><dt>Mã số thuế</dt><dd><?= $value($company['ma_so_thue'] ?? null) ?></dd></div>
            <div><dt>Người đại diện</dt><dd><?= $value($company['nguoi_dai_dien'] ?? null) ?></dd></div>
            <div><dt>Địa chỉ</dt><dd><?= $value($company['dia_chi'] ?? null) ?></dd></div>
            <div><dt>Số điện thoại</dt><dd><?= $value($company['so_dien_thoai'] ?? null) ?></dd></div>
            <div><dt>Email</dt><dd><?= $value($company['email'] ?? null) ?></dd></div>
            <div><dt>Trạng thái</dt><dd><span class="catalog-status" data-status="<?= ($company['trang_thai'] ?? '') === 'HOAT_DONG' ? 'HOAT_DONG' : 'NGUNG' ?>"><?= ($company['trang_thai'] ?? '') === 'HOAT_DONG' ? 'Hoạt động' : 'Ngừng hoạt động' ?></span></dd></div>
        </dl></div>
    </section>

    <section class="account-card" aria-labelledby="employee-heading">
        <h2 id="employee-heading">Nhân viên</h2>
        <p>Quản lý nhân viên thuộc công ty của bạn.</p>
        <p><a class="btn btn--primary" href="<?= e(url('/company/employees')) ?>">Quản lý nhân viên</a>
            <a class="btn btn--outline" href="<?= e(url('/company/employees/create')) ?>">Thêm nhân viên</a></p>
    </section>

    <section aria-labelledby="facilities-heading">
        <header class="account-page__intro catalog-heading"><div><p class="eyebrow">Cơ sở</p><h2 id="facilities-heading">Danh sách cơ sở</h2>
            <p>Các cơ sở thuộc công ty.</p></div>
        </header>
        <?php if (!$facilities): ?>
            <p role="status">Công ty hiện chưa có cơ sở.</p>
        <?php else: ?>
            <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách cơ sở"><table class="catalog-table">
                <thead><tr><th scope="col">Tên cơ sở</th><th scope="col">Địa chỉ</th><th scope="col">Số điện thoại</th><th scope="col">Email</th><th scope="col">Giờ mở cửa</th><th scope="col">Giờ đóng cửa</th><th scope="col">Trạng thái</th></tr></thead>
                <tbody><?php foreach ($facilities as $facility): ?>
                    <tr>
                        <td><?= $value($facility['ten_co_so'] ?? null) ?></td>
                        <td><?= $value($facility['dia_chi'] ?? null) ?></td>
                        <td><?= $value($facility['so_dien_thoai'] ?? null) ?></td>
                        <td><?= $value($facility['email'] ?? null) ?></td>
                        <td><?= $value($facility['gio_mo_cua'] ?? null) ?></td>
                        <td><?= $value($facility['gio_dong_cua'] ?? null) ?></td>
                        <td><span class="catalog-status" data-status="<?= e($facility['trang_thai'] ?? '') ?>"><?= ($facility['trang_thai'] ?? '') === 'HOAT_DONG' ? 'Hoạt động' : 'Tạm ngưng' ?></span></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>

    <section aria-labelledby="contracts-heading">
        <header class="account-page__intro catalog-heading"><div><p class="eyebrow">Hợp đồng nhượng quyền</p><h2 id="contracts-heading">Danh sách hợp đồng</h2>
            <p>Các hợp đồng nhượng quyền thuộc công ty.</p></div>
        </header>
        <?php if (!$contracts): ?>
            <p role="status">Công ty hiện chưa có hợp đồng nhượng quyền.</p>
        <?php else: ?>
            <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách hợp đồng"><table class="catalog-table">
                <thead><tr><th scope="col">Số hợp đồng</th><th scope="col">Ngày ký</th><th scope="col">Ngày bắt đầu</th><th scope="col">Ngày kết thúc</th><th scope="col">Phí nhượng quyền</th><th scope="col">Tỷ lệ phí</th><th scope="col">Trạng thái</th></tr></thead>
                <tbody><?php foreach ($contracts as $contract): ?>
                    <?php $status = $contract['trang_thai'] ?? ''; ?>
                    <tr>
                        <td><?= $value($contract['so_hop_dong'] ?? null) ?></td>
                        <td><?= $value($contract['ngay_ky'] ?? null) ?></td>
                        <td><?= $value($contract['ngay_bat_dau'] ?? null) ?></td>
                        <td><?= $value($contract['ngay_ket_thuc'] ?? null) ?></td>
                        <td><?= $value($contract['phi_nhuong_quyen'] ?? null) ?></td>
                        <td><?= $value($contract['ty_le_phi'] ?? null) ?>%</td>
                        <td><span class="catalog-status" data-status="<?= $status === 'HIEU_LUC' ? 'HOAT_DONG' : 'NGUNG' ?>"><?= $status === 'HIEU_LUC' ? 'Hiệu lực' : ($status === 'HET_HAN' ? 'Hết hạn' : ($status === 'CHAM_DUT' ? 'Chấm dứt' : 'Không xác định')) ?></span></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>
</section>
