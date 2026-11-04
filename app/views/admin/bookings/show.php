<?php
$bodyClass = 'page-catalog';
$bookingSt = $booking['trang_thai_dat_san'];
$paymentSt = $booking['trang_thai_thanh_toan'] ?? 'CHO_THANH_TOAN';

$bookingStatusLabels = [
    'CHO_THANH_TOAN' => 'Chờ thanh toán',
    'CHO_XAC_NHAN' => 'Chờ xác nhận',
    'DA_XAC_NHAN' => 'Đã xác nhận',
    'HOAN_THANH' => 'Hoàn thành',
    'HET_HAN' => 'Hết hạn',
    'DA_HUY' => 'Đã hủy',
];

$paymentStatusLabels = [
    'CHO_THANH_TOAN' => 'Chờ thanh toán',
    'CHO_XAC_NHAN' => 'Chờ xác nhận',
    'THANH_CONG' => 'Thành công',
    'CAN_DOI_SOAT' => 'Cần đối soát',
    'HET_HAN' => 'Hết hạn',
    'HUY' => 'Đã hủy',
];

$effectivePaymentSt = ($bookingSt === 'CHO_XAC_NHAN' && $paymentSt === 'CHO_THANH_TOAN')
    ? 'CHO_XAC_NHAN'
    : $paymentSt;

$nowStr = $now->format('Y-m-d H:i:s');
$isReconcile = ($effectivePaymentSt === 'CAN_DOI_SOAT' || $paymentSt === 'CAN_DOI_SOAT');
$actions = App\Services\BookingManagementService::actions($booking, $now);
$isExpired = $bookingSt === 'HET_HAN' || (!empty($booking['het_han_giu_san_luc']) && $booking['het_han_giu_san_luc'] <= $nowStr);
$isConfirmed = in_array($bookingSt, ['DA_XAC_NHAN', 'HOAN_THANH'], true);
$roleRoot = match ($scope['role'] ?? '') {
    'QUAN_LY_CONG_TY' => ['/company', 'Công ty'],
    'QUAN_LY_CO_SO' => ['/facility', 'Cơ sở'],
    'NHAN_VIEN' => ['/staff', 'Cơ sở'],
    default => ['/admin', 'Quản trị'],
};
?>
<section class="container catalog-page catalog-workspace detail-page booking-admin-detail">
    <nav class="catalog-nav" aria-label="Điều hướng">
        <a href="<?= e(url($roleRoot[0])) ?>"><?= e($roleRoot[1]) ?></a>
        <span aria-hidden="true">/</span>
        <a href="<?= e(url('/admin/bookings')) ?>">Quản lý booking</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Đơn #<?= e($booking['ma_dat_san']) ?></span>
    </nav>

    <header class="catalog-heading" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 24px;">
        <div>
            <p class="eyebrow">CHI TIẾT ĐẶT SÂN / #<?= e($booking['ma_dat_san']) ?></p>
            <h1>Đơn đặt sân #<?= e($booking['ma_dat_san']) ?></h1>
            <p style="color: #666; font-size: 0.9rem; margin-top: 4px;">
                Tạo lúc: <?= e(date('d/m/Y H:i:s', strtotime($booking['ngay_tao']))) ?>
                <?php if ($isReconcile): ?>
                    &bull; <strong style="color: #c5221f;">Giao dịch cần đối soát; kiểm tra thời hạn giữ chỗ</strong>
                <?php elseif (!empty($booking['het_han_giu_san_luc'])): ?>
                    &bull; Hết hạn giữ chỗ: <strong style="<?= $isExpired ? 'color: #c5221f;' : 'color: #b06000;' ?>"><?= e(date('d/m/Y H:i:s', strtotime($booking['het_han_giu_san_luc']))) ?></strong>
                <?php endif; ?>
            </p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <span class="catalog-status" style="font-size: 0.95rem; padding: 6px 14px; border-radius: 6px; <?= $isConfirmed ? 'background: #e6f4ea; color: #137333;' : ($isExpired ? 'background: #f1f3f4; color: #5f6368;' : 'background: #fef7e0; color: #b06000; border: 1px solid #f9ab00;') ?>">
                Booking: <?= e($bookingStatusLabels[$bookingSt] ?? $bookingSt) ?>
            </span>
            <span class="catalog-status" style="font-size: 0.95rem; padding: 6px 14px; border-radius: 6px; <?= $effectivePaymentSt === 'THANH_CONG' ? 'background: #e6f4ea; color: #137333;' : ($effectivePaymentSt === 'CAN_DOI_SOAT' ? 'background: #fce8e6; color: #c5221f; font-weight: bold; border: 1px solid #ea4335;' : 'background: #fef7e0; color: #b06000; border: 1px solid #f9ab00;') ?>">
                Thanh toán: <?= e($paymentStatusLabels[$effectivePaymentSt] ?? $effectivePaymentSt) ?>
            </span>
        </div>
    </header>

    <?php if (!empty($notice)): ?>
        <p class="alert alert--success" role="status" style="margin-bottom: 20px; font-size: 0.95rem;"><?= e($notice) ?></p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p class="alert alert--danger" role="alert" style="margin-bottom: 20px; font-size: 0.95rem;"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if (!empty($validationErrors)): ?>
        <div class="alert alert--danger" role="alert" style="margin-bottom: 20px;">
            <strong style="display: block; margin-bottom: 6px;">Vui lòng kiểm tra lại các thông tin sau:</strong>
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($validationErrors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="detail-grid" style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; margin-bottom: 30px; min-width: 0; max-width: 100%;">
        
        <!-- KHỐI 1: THÔNG TIN KHÁCH HÀNG -->
        <section class="detail-card" style="background: var(--surface, #fff); border: 1px solid var(--border, #e0e0e0); border-radius: 8px; padding: 22px; min-width: 0; overflow: hidden;">
            <h2 style="font-size: 1.15rem; margin-top: 0; margin-bottom: 16px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                Thông tin khách hàng
            </h2>
            <dl class="detail-fields" style="display: grid; grid-template-columns: 140px minmax(0, 1fr); gap: 12px 16px; margin: 0; min-width: 0;">
                <dt style="color: #666; font-size: 0.9rem;">Họ và tên:</dt>
                <dd style="margin: 0; font-weight: 600; min-width: 0; overflow-wrap: break-word;"><?= e($booking['ten_khach_hang']) ?></dd>

                <dt style="color: #666; font-size: 0.9rem;">Số điện thoại:</dt>
                <dd style="margin: 0; min-width: 0;"><a href="tel:<?= e($booking['sdt_khach_hang']) ?>" style="color: #1a73e8; font-weight: 600;"><?= e($booking['sdt_khach_hang']) ?></a></dd>

                <dt style="color: #666; font-size: 0.9rem;">Email:</dt>
                <dd style="margin: 0; min-width: 0; overflow-wrap: break-word;"><?= e($booking['email_khach_hang'] ?: '—') ?></dd>

                <dt style="color: #666; font-size: 0.9rem;">Giới tính / NS:</dt>
                <dd style="margin: 0; min-width: 0;"><?= e($booking['gioi_tinh_khach'] ?? '—') ?> / <?= e($booking['ngay_sinh_khach'] ? date('d/m/Y', strtotime($booking['ngay_sinh_khach'])) : '—') ?></dd>

                <dt style="color: #666; font-size: 0.9rem;">Ghi chú khách:</dt>
                <dd style="margin: 0; color: #444; font-style: italic; min-width: 0; overflow-wrap: break-word;"><?= e($booking['ghi_chu'] ?: 'Không có ghi chú') ?></dd>
            </dl>
        </section>

        <!-- KHỐI 2: THÔNG TIN THANH TOÁN & NGÂN HÀNG NHẬN TIỀN CỦA CƠ SỞ -->
        <section class="detail-card" style="background: var(--surface, #fff); border: 1px solid var(--border, #e0e0e0); border-radius: 8px; padding: 22px; min-width: 0; overflow: hidden;">
            <h2 style="font-size: 1.15rem; margin-top: 0; margin-bottom: 16px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                Thanh toán &amp; Tài khoản nhận tiền
            </h2>
            <dl class="detail-fields" style="display: grid; grid-template-columns: 160px minmax(0, 1fr); gap: 12px 16px; margin: 0 0 16px 0; min-width: 0;">
                <dt style="color: #666; font-size: 0.9rem;">Số tiền cần thanh toán:</dt>
                <dd style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #0f5132; min-width: 0;">
                    <?= number_format((float) ($booking['so_tien_can_thanh_toan'] ?? $booking['tong_tien_tam_tinh']), 0, ',', '.') ?> đ
                </dd>

                <dt style="color: #666; font-size: 0.9rem;">Mã tham chiếu CK:</dt>
                <dd style="margin: 0; min-width: 0;">
                    <code style="background: #eef2f5; padding: 4px 8px; border-radius: 4px; font-weight: 700; color: #1a73e8; font-size: 1rem;">
                        <?= e($booking['ma_tham_chieu'] ?? 'Chưa tạo') ?>
                    </code>
                </dd>

                <dt style="color: #666; font-size: 0.9rem;">Phương thức:</dt>
                <dd style="margin: 0; min-width: 0;">Chuyển khoản VietQR</dd>

                <dt style="color: #666; font-size: 0.9rem;">Mã giao dịch NH:</dt>
                <dd style="margin: 0; font-family: monospace; font-weight: 600; min-width: 0; overflow-wrap: break-word;"><?= e($booking['ma_giao_dich'] ?: '—') ?></dd>

                <dt style="color: #666; font-size: 0.9rem;">Số tiền thực nhận:</dt>
                <dd style="margin: 0; font-weight: 600; color: #137333; min-width: 0;">
                    <?= !empty($booking['so_tien_thuc_nhan']) ? number_format((float) $booking['so_tien_thuc_nhan'], 0, ',', '.') . ' đ' : '—' ?>
                </dd>

                <dt style="color: #666; font-size: 0.9rem;">Thời gian thanh toán:</dt>
                <dd style="margin: 0; min-width: 0;"><?= !empty($booking['ngay_thanh_toan']) ? e(date('d/m/Y H:i:s', strtotime($booking['ngay_thanh_toan']))) : '—' ?></dd>

                <?php if (!empty($receiptImage)): ?>
                    <dt style="color: #666; font-size: 0.9rem;">Ảnh biên lai khách gửi:</dt>
                    <dd style="margin: 0; min-width: 0; max-width: 100%; overflow: hidden;">
                        <!-- Thẻ biên lai thu nhỏ, chống tràn khung hình tuyệt đối (Pixel cứng cho Safari/WebKit) -->
                        <div class="admin-receipt-preview" style="width: 240px; max-width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; background: #0f172a; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin: 4px 0;">
                            <a href="<?= e(base_path() . '/' . $receiptImage) ?>" target="_blank" rel="noopener" title="Bấm vào để xem ảnh đầy đủ" style="display: block; width: 240px; height: 140px; max-width: 100%; overflow: hidden; position: relative; background: #0f172a; text-decoration: none;">
                                <img src="<?= e(base_path() . '/' . $receiptImage) ?>" alt="Biên lai chuyển khoản" width="240" height="140" style="width: 240px !important; height: 140px !important; max-width: 240px !important; max-height: 140px !important; object-fit: contain !important; display: block; margin: 0 auto; border: 0;">
                                <span class="admin-receipt-zoom-tag" style="position: absolute; bottom: 6px; right: 6px; background: rgba(0,0,0,0.85); color: #c8f36b !important; font-size: 11px; padding: 2px 7px; border-radius: 4px; pointer-events: none; font-weight: 600; text-decoration: none !important;">🔍 Phóng to</span>
                            </a>
                            <div class="admin-receipt-footer" style="padding: 6px 10px; display: flex; align-items: center; justify-content: space-between; font-size: 0.8rem; background: #f8fafc; border-top: 1px solid #e2e8f0; width: 240px; max-width: 100%; box-sizing: border-box;">
                                <span style="color: #64748b; font-weight: 600;">Biên lai đính kèm</span>
                                <a href="<?= e(base_path() . '/' . $receiptImage) ?>" target="_blank" rel="noopener" style="color: #1a73e8 !important; font-weight: 600; text-decoration: none; font-size: 0.78rem;">Mở tab mới ↗</a>
                            </div>
                        </div>
                    </dd>
                <?php endif; ?>

                <?php if (!empty($booking['ghi_chu_thanh_toan'])): ?>
                    <dt style="color: #666; font-size: 0.9rem;">Ghi chú đối soát:</dt>
                    <dd style="margin: 0; color: #c5221f; font-weight: 600; min-width: 0; overflow-wrap: break-word;"><?= e($booking['ghi_chu_thanh_toan']) ?></dd>
                <?php endif; ?>
            </dl>

            <!-- HỘP ĐỐI CHIẾU TÀI KHOẢN NGÂN HÀNG CỦA CƠ SỞ -->
            <div style="background: #f8fbf9; border: 1px dashed #34a853; border-radius: 6px; padding: 12px 14px;">
                <div style="font-size: 0.85rem; font-weight: 700; color: #137333; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3"/></svg>
                    Tài khoản thụ hưởng của cơ sở (Mở App NH để đối chiếu)
                </div>
                <?php if ($bankAccount): ?>
                    <div style="font-size: 0.88rem; line-height: 1.5; color: #2d3748;">
                        <div>Ngân hàng: <strong><?= e($bankAccount['ten_ngan_hang']) ?></strong> (BIN: <?= e($bankAccount['ma_bin']) ?>)</div>
                        <div>Số tài khoản: <strong style="font-size: 1rem; color: #1a73e8;"><?= e($bankAccount['so_tai_khoan']) ?></strong></div>
                        <div>Chủ tài khoản: <strong><?= e($bankAccount['ten_chu_tai_khoan']) ?></strong></div>
                    </div>
                <?php else: ?>
                    <span style="font-size: 0.85rem; color: #c5221f;">Cơ sở này chưa cấu hình tài khoản ngân hàng mặc định!</span>
                <?php endif; ?>
            </div>
        </section>

    </div>

    <!-- KHỐI 3: DANH SÁCH SÂN ĐÃ ĐẶT -->
    <section class="detail-card" style="background: var(--surface, #fff); border: 1px solid var(--border, #e0e0e0); border-radius: 8px; padding: 22px; margin-bottom: 24px;">
        <h2 style="font-size: 1.15rem; margin-top: 0; margin-bottom: 16px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; display: flex; align-items: center; gap: 8px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
            Chi tiết sân đặt (<?= count($courts) ?> khung giờ)
        </h2>
        <div style="overflow-x: auto;">
            <table class="catalog-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8faf9; border-bottom: 1px solid #e0e0e0;">
                        <th style="padding: 10px 12px;">Sân</th>
                        <th style="padding: 10px 12px;">Bộ môn &amp; Loại sân</th>
                        <th style="padding: 10px 12px;">Ngày chơi</th>
                        <th style="padding: 10px 12px;">Khung giờ</th>
                        <th style="padding: 10px 12px; text-align: right;">Đơn giá</th>
                        <th style="padding: 10px 12px; text-align: right;">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courts as $line): ?>
                        <tr style="border-bottom: 1px solid #f0f0f0;">
                            <td style="padding: 10px 12px; font-weight: 600;"><?= e($line['ten_san_snapshot']) ?></td>
                            <td style="padding: 10px 12px; color: #555;"><?= e($line['ten_bo_mon']) ?> &bull; <?= e($line['ten_loai_san']) ?></td>
                            <td style="padding: 10px 12px; font-weight: 600;"><?= e(date('d/m/Y', strtotime($line['ngay_choi']))) ?></td>
                            <td style="padding: 10px 12px;"><?= e(substr($line['gio_bat_dau'], 0, 5)) ?> – <?= e(substr($line['gio_ket_thuc'], 0, 5)) ?></td>
                            <td style="padding: 10px 12px; text-align: right; color: #666;"><?= number_format((float) $line['don_gia'], 0, ',', '.') ?> đ</td>
                            <td style="padding: 10px 12px; text-align: right; font-weight: 600;"><?= number_format((float) $line['thanh_tien'], 0, ',', '.') ?> đ</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="border-top: 2px solid #e0e0e0; font-weight: 700;">
                        <td colspan="5" style="padding: 12px; text-align: right;">Tổng tiền sân:</td>
                        <td style="padding: 12px; text-align: right; color: #0f5132;"><?= number_format((float) $booking['tong_tien_san'], 0, ',', '.') ?> đ</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    <!-- KHỐI 4: DANH SÁCH DỊCH VỤ ĐÍNH KÈM -->
    <section class="detail-card" style="background: var(--surface, #fff); border: 1px solid var(--border, #e0e0e0); border-radius: 8px; padding: 22px; margin-bottom: 24px;">
        <h2 style="font-size: 1.15rem; margin-top: 0; margin-bottom: 16px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; display: flex; align-items: center; gap: 8px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
            Dịch vụ đính kèm (<?= count($services) ?>)
        </h2>
        <?php if ($services): ?>
            <div style="overflow-x: auto;">
                <table class="catalog-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8faf9; border-bottom: 1px solid #e0e0e0;">
                            <th style="padding: 10px 12px;">Tên dịch vụ</th>
                            <th style="padding: 10px 12px;">Đơn vị tính</th>
                            <th style="padding: 10px 12px; text-align: center;">Số lượng</th>
                            <th style="padding: 10px 12px; text-align: right;">Đơn giá</th>
                            <th style="padding: 10px 12px; text-align: right;">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $srv): ?>
                            <tr style="border-bottom: 1px solid #f0f0f0;">
                                <td style="padding: 10px 12px; font-weight: 600;"><?= e($srv['ten_dich_vu_snapshot']) ?></td>
                                <td style="padding: 10px 12px; color: #666;"><?= e($srv['don_vi_tinh_snapshot']) ?></td>
                                <td style="padding: 10px 12px; text-align: center; font-weight: 600;">&times; <?= e($srv['so_luong']) ?></td>
                                <td style="padding: 10px 12px; text-align: right; color: #666;"><?= number_format((float) $srv['don_gia'], 0, ',', '.') ?> đ</td>
                                <td style="padding: 10px 12px; text-align: right; font-weight: 600;"><?= number_format((float) $srv['thanh_tien'], 0, ',', '.') ?> đ</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="border-top: 2px solid #e0e0e0; font-weight: 700;">
                            <td colspan="4" style="padding: 12px; text-align: right;">Tổng tiền dịch vụ:</td>
                            <td style="padding: 12px; text-align: right; color: #0f5132;"><?= number_format((float) $booking['tong_tien_dich_vu'], 0, ',', '.') ?> đ</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <p style="color: #777; margin: 0; font-style: italic;">Khách hàng không chọn thêm dịch vụ nào.</p>
        <?php endif; ?>
    </section>

    <section class="booking-operations" id="booking-operations" aria-label="Thanh toán và đối soát">
        <?php if ($isConfirmed): ?>
            <section class="booking-operation-card booking-operation-card--success">
                <header><h2>Thanh toán đã xác nhận</h2><p>Đơn đặt sân đã được xác nhận thanh toán thành công.</p></header>
                <dl class="booking-operation-facts">
                    <div><dt>Mã giao dịch ngân hàng</dt><dd><?= e($booking['ma_giao_dich'] ?: '—') ?></dd></div>
                    <div><dt>Số tiền thực nhận</dt><dd><?= number_format((float) $booking['so_tien_thuc_nhan'], 0, ',', '.') ?> đ</dd></div>
                    <?php if (!empty($booking['ten_nhan_vien_xac_nhan'])): ?><div><dt>Nhân viên xác nhận</dt><dd><?= e($booking['ten_nhan_vien_xac_nhan']) ?> (<?= e($booking['chuc_vu_nhan_vien'] ?? 'Nhân viên') ?>)</dd></div><?php endif; ?>
                </dl>
                <footer class="booking-operation-footer">
                    <?php if (!empty($receiptImage)): ?><a class="btn btn--outline" href="<?= e(base_path() . '/' . $receiptImage) ?>" target="_blank" rel="noopener">Xem ảnh biên lai khách gửi ↗</a><?php endif; ?>
                    <a class="btn btn--primary" href="<?= e(url('/admin/invoices/show') . '&booking_id=' . (int) $booking['ma_dat_san']) ?>">Phiếu hóa đơn thanh toán (PR4) ↗</a>
                </footer>
            </section>
        <?php elseif ($actions['confirm']): ?>
            <?php if ($isReconcile): ?><aside class="booking-operation-notice">
                <h2>Đơn đang cần đối soát</h2>
                <p><strong>Lý do:</strong> <?= e(\App\Services\ReceiptImage::stripReceiptTag($booking['ghi_chu_thanh_toan'] ?? 'Chưa có ghi chú')) ?></p>
                <p>Tổng tiền cần thanh toán: <strong><?= number_format((float) ($booking['so_tien_can_thanh_toan'] ?? $booking['tong_tien_tam_tinh']), 0, ',', '.') ?> đ</strong>
                <?php if (!empty($booking['so_tien_thuc_nhan'])): ?> · Đã ghi nhận: <strong><?= number_format((float) $booking['so_tien_thuc_nhan'], 0, ',', '.') ?> đ</strong><?php endif; ?></p>
                <?php if (!empty($booking['ma_giao_dich'])): ?><p>Mã giao dịch trước đó: <?= e($booking['ma_giao_dich']) ?></p><?php endif; ?>
            </aside><?php endif; ?>
            <div class="booking-operation-grid">
                <form class="booking-operation-card" method="post" action="<?= e(url('/admin/bookings/confirm')) ?>" data-confirm="<?= $isReconcile ? 'Xác nhận giao dịch THÀNH CÔNG và kích hoạt lịch đặt sân chính thức cho khách?' : 'Bạn xác nhận ĐÃ KIỂM TRA TÀI KHOẢN NGÂN HÀNG và số tiền nhận được hoàn toàn chính xác?' ?>">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($booking['ma_dat_san']) ?>">
                    <header><h2><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/><circle cx="12" cy="12" r="10"/></svg>Xác nhận thanh toán</h2>
                        <p><?= $isReconcile ? 'Khách hàng đã bổ sung đủ tiền hoặc giao dịch đã đối soát khớp. Xác nhận để giữ sân chính thức và lập hóa đơn.' : 'Đối chiếu giao dịch trên ứng dụng ngân hàng của cơ sở. Chỉ xác nhận khi đã nhận đủ tiền và khớp thông tin chuyển khoản.' ?></p>
                    </header>
                    <div class="booking-operation-fields">
                        <div class="form-group"><label for="so_tien_thuc_nhan">Số tiền thực nhận (đ) <span aria-hidden="true">*</span></label>
                            <input class="form-control" id="so_tien_thuc_nhan" name="so_tien_thuc_nhan" type="number" step="1000" required value="<?= e($oldInput['so_tien_thuc_nhan'] ?? (string) (int) ($booking['so_tien_can_thanh_toan'] ?? $booking['tong_tien_tam_tinh'])) ?>">
                            <small>Phải khớp chính xác số tiền cần thanh toán.</small></div>
                        <div class="form-group"><label for="ma_giao_dich">Mã giao dịch ngân hàng <span aria-hidden="true">*</span></label>
                            <input class="form-control" id="ma_giao_dich" name="ma_giao_dich" maxlength="150" required placeholder="VD: FT26110298765432" value="<?= e($oldInput['ma_giao_dich'] ?? ($isReconcile ? ($booking['ma_giao_dich'] ?? '') : '')) ?>"></div>
                        <div class="form-group"><label for="ngay_thanh_toan">Thời điểm tiền về ngân hàng</label>
                            <input class="form-control" id="ngay_thanh_toan" name="ngay_thanh_toan" type="datetime-local" value="<?= e($oldInput['ngay_thanh_toan'] ?? $now->format('Y-m-d\TH:i')) ?>"></div>
                        <div class="form-group"><label for="ghi_chu">Ghi chú <?= $isReconcile ? 'đối soát hoàn tất' : '(tùy chọn)' ?></label>
                            <input class="form-control" id="ghi_chu" name="ghi_chu" maxlength="500" value="<?= e($oldInput['ghi_chu'] ?? '') ?>" placeholder="Ghi chú đối soát nội bộ..."></div>
                    </div>
                    <footer class="booking-operation-footer"><button class="btn btn--primary" type="submit">Xác nhận thành công &amp; Giữ sân chính thức</button></footer>
                </form>
                <?php if ($isReconcile): ?>
                    <form class="booking-operation-card booking-operation-card--warning" method="post" action="<?= e(url('/admin/bookings/cancel')) ?>" data-confirm="CẢNH BÁO: Bạn chắc chắn muốn HỦY đơn đặt sân này? Khung giờ sân sẽ được giải phóng về trạng thái TRỐNG ngay lập tức!">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($booking['ma_dat_san']) ?>">
                        <header><h2><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m8 8 8 8m0-8-8 8"/></svg>Hủy đặt sân</h2>
                            <p>Dùng khi đối soát không thành công hoặc hủy theo yêu cầu. Đơn chuyển thành <strong>Đã hủy</strong> và khung giờ sân được giải phóng về <strong>Trống</strong>.</p></header>
                        <div class="booking-operation-fields"><div class="form-group"><label for="ly_do_huy">Lý do hủy đặt sân <span aria-hidden="true">*</span></label>
                            <textarea class="form-control" id="ly_do_huy" name="ly_do_huy" rows="5" required aria-describedby="cancel-help" placeholder="Đối soát không thành công, giao dịch không hợp lệ..."><?= e($oldInput['ly_do_huy'] ?? '') ?></textarea>
                            <small id="cancel-help">Lý do hủy sẽ được lưu vào hệ thống và giải phóng sân.</small></div></div>
                        <footer class="booking-operation-footer"><button class="btn btn--outline" type="submit">Hủy đặt sân &amp; Trả về sân trống</button></footer>
                    </form>
                <?php else: ?>
                    <form class="booking-operation-card booking-operation-card--warning" method="post" action="<?= e(url('/admin/bookings/reconcile')) ?>" data-confirm="Chuyển trạng thái thanh toán sang CẦN ĐỐI SOÁT? Đơn đặt sân sẽ KHÔNG được xác nhận.">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($booking['ma_dat_san']) ?>">
                        <header><h2><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 10 18H2L12 3Zm0 6v5m0 3h.01"/></svg>Cần đối soát</h2><p>Dùng khi chuyển thiếu/thừa tiền, sai nội dung hoặc giao dịch cần xử lý riêng. Thao tác này không xác nhận giữ sân và không trừ tồn kho.</p></header>
                        <div class="booking-operation-fields">
                            <div class="form-group"><label for="rec_so_tien">Số tiền thực tế nhận được (đ)</label><input class="form-control" id="rec_so_tien" name="so_tien_thuc_nhan" type="number" step="1000" placeholder="Số tiền thực tế trong tài khoản"></div>
                            <div class="form-group"><label for="rec_ma_gd">Mã giao dịch ngân hàng (nếu có)</label><input class="form-control" id="rec_ma_gd" name="ma_giao_dich" maxlength="150" placeholder="Mã giao dịch ngân hàng..."></div>
                            <div class="form-group"><label for="ly_do_doi_soat">Lý do cần đối soát <span aria-hidden="true">*</span></label><textarea class="form-control" id="ly_do_doi_soat" name="ly_do_doi_soat" rows="4" required placeholder="Khách chuyển thiếu tiền, sai nội dung..."></textarea></div>
                        </div>
                        <footer class="booking-operation-footer"><button class="btn btn--outline" type="submit">Đánh dấu Cần đối soát</button></footer>
                    </form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <aside class="booking-operation-notice" role="status"><h2><?= $isExpired ? 'Đã hết thời gian giữ chỗ' : ($bookingSt === 'DA_HUY' ? 'Đơn đặt sân đã bị hủy' : 'Đơn không trong trạng thái chờ duyệt') ?></h2>
                <p>Hệ thống không cho phép xác nhận giữ sân cho đơn đã hết hạn hoặc đã hủy để tránh tranh chấp trùng lịch với khách hàng khác.</p></aside>
            <?php if ($actions['reconcile']): ?>
                <form class="booking-operation-card booking-operation-card--warning booking-operation-card--late" method="post" action="<?= e(url('/admin/bookings/reconcile')) ?>" data-confirm="Ghi nhận khoản tiền chuyển muộn vào Cần đối soát? Thao tác này không giữ lại sân hoặc thực hiện hoàn tiền.">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($booking['ma_dat_san']) ?>">
                    <header><h2>Khách hàng đã chuyển tiền muộn?</h2><p>Ghi nhận khoản tiền vào Cần đối soát để xử lý riêng; thao tác này không xác nhận lại sân hoặc thực hiện hoàn tiền.</p></header>
                    <div class="booking-operation-fields">
                        <div class="form-group"><label for="late-amount">Số tiền thực nhận (đ) <span aria-hidden="true">*</span></label><input class="form-control" id="late-amount" name="so_tien_thuc_nhan" type="number" step="1000" required placeholder="Số tiền đã nhận"></div>
                        <div class="form-group"><label for="late-transaction">Mã giao dịch ngân hàng</label><input class="form-control" id="late-transaction" name="ma_giao_dich" placeholder="Mã giao dịch ngân hàng..."></div>
                        <div class="form-group booking-operation-field--wide"><label for="late-reason">Lý do đối soát <span aria-hidden="true">*</span></label><input class="form-control" id="late-reason" name="ly_do_doi_soat" required placeholder="Khách chuyển tiền muộn..."></div>
                    </div>
                    <footer class="booking-operation-footer"><button class="btn btn--outline" type="submit">Ghi nhận tiền chuyển muộn</button></footer>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</section>
