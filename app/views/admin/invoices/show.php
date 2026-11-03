<?php
$bodyClass = 'page-catalog';
$statusLabels = [
    'DA_LAP' => 'ĐÃ THANH TOÁN',
    'HUY' => 'ĐÃ HỦY',
];
$isDaLap = ($invoice['trang_thai'] ?? '') === 'DA_LAP';
?>
<link rel="stylesheet" href="<?= e(base_path() . '/assets/css/invoice.css?v=' . filemtime(dirname(__DIR__, 4) . '/public/assets/css/invoice.css')) ?>">
<script src="<?= e(base_path() . '/assets/js/invoice.js?v=' . filemtime(dirname(__DIR__, 4) . '/public/assets/js/invoice.js')) ?>" defer></script>

<?php
$roleRoot = match ($scope['role'] ?? '') {
    'QUAN_LY_CONG_TY' => ['/company', 'Công ty'],
    'QUAN_LY_CO_SO' => ['/facility', 'Cơ sở'],
    'NHAN_VIEN' => ['/staff', 'Cơ sở'],
    default => ['/admin', 'Quản trị'],
};
?>
<section class="container catalog-page catalog-workspace detail-page">
    <nav class="catalog-nav" aria-label="Điều hướng">
        <a href="<?= e(url($roleRoot[0])) ?>"><?= e($roleRoot[1]) ?></a>
        <span aria-hidden="true">/</span>
        <a href="<?= e(url('/admin/invoices')) ?>">Quản lý hóa đơn</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Hóa đơn #<?= e($invoice['so_hoa_don'] ?: $invoice['ma_hoa_don']) ?></span>
    </nav>

    <!-- THANH TIÊU ĐỀ TRANG VÀ NÚT HÀNH ĐỘNG -->
    <header class="catalog-heading" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 24px;">
        <div>
            <p class="eyebrow">HÓA ĐƠN THANH TOÁN / STRYKE</p>
            <h1>Hóa đơn #<?= e($invoice['so_hoa_don'] ?: ('HD' . str_pad($invoice['ma_hoa_don'], 6, '0', STR_PAD_LEFT))) ?></h1>
            <p style="color: #666; font-size: 0.9rem; margin-top: 4px;">
                Ngày lập: <strong><?= e(date('d/m/Y H:i:s', strtotime($invoice['ngay_lap']))) ?></strong>
                &bull; Mã đặt sân liên kết:
                <a href="<?= e(url('/admin/bookings/show') . '&id=' . $invoice['ma_dat_san']) ?>" style="color: #1a73e8; font-weight: 600;">
                    #<?= (int) $invoice['ma_dat_san'] ?> ↗
                </a>
            </p>
        </div>
        
        <!-- NÚT IN HÓA ĐƠN VÀ ĐIỀU HƯỚNG -->
        <div class="invoice-actions" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <a class="btn btn--primary" href="<?= e(url('/admin/invoices/print') . '&id=' . $invoice['ma_hoa_don']) ?>" target="_blank" rel="noopener" style="padding: 10px 20px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; box-shadow: 0 4px 12px rgba(178, 236, 114, 0.4);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                <span>🖨 Mở trang in (Tab mới) ↗</span>
            </a>
            <button type="button" class="btn btn--outline" data-action="print" style="padding: 10px 16px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                <span>In trực tiếp</span>
            </button>
            <a class="btn btn--outline" href="<?= e(url('/admin/invoices')) ?>" style="padding: 10px 16px; font-weight: 600;">
                Quay lại danh sách
            </a>
        </div>
    </header>

    <?php if (!empty($notice)): ?>
        <p class="alert alert--success" role="status" style="margin-bottom: 20px;"><?= e($notice) ?></p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p class="alert alert--danger" role="alert" style="margin-bottom: 20px;"><?= e($error) ?></p>
    <?php endif; ?>

    <!-- KHÔNG GIAN HIỂN THỊ TỜ BILL POS CAFE -->
    <div class="pos-bill-workspace">

        <!-- ===================================================================
             TỜ BILL HÓA ĐƠN CHUẨN QUÁN CAFE / MÁY IN NHIỆT 80MM (POS RECEIPT)
             =================================================================== -->
        <div class="pos-receipt-paper" id="invoice-paper">
            
            <!-- HEADER CƠ SỞ -->
            <div class="pos-receipt-header">
                <div class="pos-brand-name">STRYKE<span>.</span></div>
                <div class="pos-brand-slogan">SÂN THỂ THAO CAO CẤP</div>
                <div class="pos-facility-info" style="font-weight: 700;"><?= e($facility['ten_co_so'] ?? $invoice['ten_co_so']) ?></div>
                <div class="pos-facility-info"><?= e($facility['dia_chi'] ?? 'Hệ thống cơ sở STRYKE') ?></div>
                <?php if (!empty($facility['so_dien_thoai'])): ?>
                    <div class="pos-facility-info">Hotline: <strong><?= e($facility['so_dien_thoai']) ?></strong></div>
                <?php endif; ?>
            </div>

            <hr class="pos-divider-dash">

            <!-- TIÊU ĐỀ PHIẾU TÍNH TIỀN -->
            <div class="pos-bill-title">PHIẾU THANH TOÁN</div>
            <div class="pos-bill-subtitle">HÓA ĐƠN DỊCH VỤ / RECEIPT</div>

            <!-- THÔNG TIN PHIẾU -->
            <div class="pos-meta-row">
                <span>Số phiếu:</span>
                <strong><?= e($invoice['so_hoa_don'] ?: ('HD' . str_pad($invoice['ma_hoa_don'], 6, '0', STR_PAD_LEFT))) ?></strong>
            </div>
            <div class="pos-meta-row">
                <span>Ngày in:</span>
                <span><?= e(date('d/m/Y H:i:s', strtotime($invoice['ngay_lap']))) ?></span>
            </div>
            <div class="pos-meta-row">
                <span>Mã đơn đặt:</span>
                <strong>#<?= (int) $invoice['ma_dat_san'] ?></strong>
            </div>
            <div class="pos-meta-row">
                <span>Thu ngân:</span>
                <span><?= e($invoice['ten_nhan_vien'] ?: 'Hệ thống STRYKE') ?></span>
            </div>
            <div class="pos-meta-row">
                <span>Khách hàng:</span>
                <strong><?= e($invoice['ten_khach_hang']) ?></strong>
            </div>
            <div class="pos-meta-row">
                <span>Điện thoại:</span>
                <span><?= e($invoice['sdt_khach_hang']) ?></span>
            </div>

            <!-- BẢNG MÓN / LỊCH SÂN & DỊCH VỤ -->
            <table class="pos-items-table">
                <thead>
                    <tr>
                        <th style="width: 50%;">TÊN MÓN / DỊCH VỤ</th>
                        <th style="width: 12%; text-align: center;">SL</th>
                        <th style="width: 18%; text-align: right;">ĐƠN GIÁ</th>
                        <th style="width: 20%; text-align: right;">T.TIỀN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($courts)): ?>
                        <?php foreach ($courts as $c): ?>
                            <tr>
                                <td>
                                    <div class="pos-item-name"><?= e($c['ten_san']) ?></div>
                                    <div class="pos-item-sub"><?= e($c['ten_bo_mon']) ?> &bull; <?= e(date('d/m', strtotime($c['ngay_choi']))) ?> (<?= e(substr($c['gio_bat_dau'], 0, 5)) ?>-<?= e(substr($c['gio_ket_thuc'], 0, 5)) ?>)</div>
                                </td>
                                <td style="text-align: center; font-weight: 600;">1</td>
                                <td style="text-align: right;"><?= number_format((float) $c['gia_tien'], 0, ',', '.') ?></td>
                                <td style="text-align: right; font-weight: 700;"><?= number_format((float) $c['gia_tien'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td>
                                <div class="pos-item-name">Tiền thuê sân theo đơn</div>
                            </td>
                            <td style="text-align: center; font-weight: 600;">1</td>
                            <td style="text-align: right;"><?= number_format((float) $invoice['tien_san'], 0, ',', '.') ?></td>
                            <td style="text-align: right; font-weight: 700;"><?= number_format((float) $invoice['tien_san'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php if (!empty($services)): ?>
                        <?php foreach ($services as $s): ?>
                            <tr>
                                <td>
                                    <div class="pos-item-name"><?= e($s['ten_dich_vu']) ?></div>
                                    <div class="pos-item-sub">ĐVT: <?= e($s['don_vi_tinh']) ?></div>
                                </td>
                                <td style="text-align: center; font-weight: 600;"><?= (int) $s['so_luong'] ?></td>
                                <td style="text-align: right;"><?= number_format((float) $s['don_gia'], 0, ',', '.') ?></td>
                                <td style="text-align: right; font-weight: 700;"><?= number_format((float) $s['thanh_tien'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- KHỐI TỔNG TIỀN VÀ PHƯƠNG THỨC -->
            <div class="pos-total-row">
                <span>Tiền sân:</span>
                <span><?= number_format((float) $invoice['tien_san'], 0, ',', '.') ?> đ</span>
            </div>
            <div class="pos-total-row">
                <span>Tiền dịch vụ:</span>
                <span><?= number_format((float) $invoice['tien_dich_vu'], 0, ',', '.') ?> đ</span>
            </div>

            <!-- TỔNG CỘNG TIỀN -->
            <div class="pos-grand-total">
                <span>TỔNG CỘNG:</span>
                <span class="pos-grand-amount"><?= number_format((float) $invoice['tong_tien'], 0, ',', '.') ?> đ</span>
            </div>

            <div class="pos-meta-row">
                <span>Thanh toán:</span>
                <strong><?= e($invoice['phuong_thuc_thanh_toan'] ?: 'Chuyển khoản VietQR') ?></strong>
            </div>
            <?php if (!empty($invoice['ma_giao_dich'])): ?>
                <div class="pos-meta-row">
                    <span>Mã GD:</span>
                    <strong><?= e($invoice['ma_giao_dich']) ?></strong>
                </div>
            <?php endif; ?>
            <div class="pos-meta-row" style="margin-top: 4px;">
                <span>Trạng thái:</span>
                <strong style="color: #15803d;">✓ ĐÃ THANH TOÁN THÀNH CÔNG</strong>
            </div>

            <hr class="pos-divider-dash">

            <!-- LỜI CẢM ƠN & THÔNG TIN QUÁN CAFE / SÂN -->
            <div class="pos-footer-box">
                <div class="pos-thank-you">Cảm ơn Quý khách &amp; Hẹn gặp lại!</div>
                <div>Wifi: <strong>STRYKE_FREE</strong> &bull; Pass: <strong>stryke888</strong></div>
                <div style="font-size: 11px; color: #64748b; margin-top: 6px;">Hotline phản hồi dịch vụ: <?= e($facility['so_dien_thoai'] ?? '1900 8888') ?></div>
                <div class="pos-barcode-simulation">||| | ||||| || |||| |||| |||</div>
                <div style="font-size: 10px; color: #94a3b8;"><?= e($invoice['so_hoa_don']) ?></div>
            </div>

        </div>

        <!-- ===================================================================
             CHỨNG TỪ BIÊN LAI CỦA KHÁCH: DÙNG NÚT MỞ TAB MỚI, KHÔNG CHÈN ẢNH TRÁNH TRÀN MÀN HÌNH
             =================================================================== -->
        <?php if (!empty($receiptImage)): ?>
            <div style="margin-top: 20px; text-align: center;">
                <a href="<?= e(base_path() . '/' . $receiptImage) ?>" target="_blank" rel="noopener" class="btn btn--outline" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; font-size: 13px; font-weight: 600; text-decoration: none; border-color: #cbd5e1; background: #ffffff; color: #1e293b; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                    <span>Xem ảnh biên lai khách chuyển khoản (Mở tab mới) ↗</span>
                </a>
            </div>
        <?php endif; ?>

    </div>
</section>
