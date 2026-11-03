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
$hasReceipt = !empty($receiptImage);
$isReconcile = ($effectivePaymentSt === 'CAN_DOI_SOAT' || $paymentSt === 'CAN_DOI_SOAT');
$isExpired = (!$hasReceipt && !$isReconcile && !empty($booking['het_han_giu_san_luc']) && $booking['het_han_giu_san_luc'] <= $nowStr);
$isPending = ((in_array($bookingSt, ['CHO_THANH_TOAN', 'CHO_XAC_NHAN'], true) && !$isExpired) || ($hasReceipt && !in_array($bookingSt, ['DA_XAC_NHAN', 'HOAN_THANH', 'DA_HUY'], true)) || $isReconcile) && !in_array($bookingSt, ['DA_HUY', 'HOAN_THANH'], true);
$isConfirmed = in_array($bookingSt, ['DA_XAC_NHAN', 'HOAN_THANH'], true);
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
                    &bull; <strong style="color: #c5221f;">Đang giữ sân để đối soát với khách hàng</strong>
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

    <!-- BANNER PHƯƠNG THỨC TỰ ĐỘNG ĐANG PHÁT TRIỂN (THEO QUY ĐỊNH) -->
    <div class="alert alert--info" style="margin-bottom: 24px; display: flex; align-items: flex-start; gap: 12px; background: #eef6fc; border-left: 4px solid #1a73e8; padding: 14px 18px; border-radius: 6px; color: #15467e;">
        <svg width="22" height="22" style="width: 22px !important; height: 22px !important; min-width: 22px !important; min-height: 22px !important; max-width: 22px !important; max-height: 22px !important; flex: 0 0 22px !important; flex-shrink: 0 !important; margin-top: 2px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="16" x2="12" y2="12"></line>
            <line x1="12" y1="8" x2="12.01" y2="8"></line>
        </svg>
        <div style="flex: 1; min-width: 0;">
            <strong style="display: block; font-size: 0.95rem; margin-bottom: 4px;">Xác nhận thanh toán tự động — Đang trong quá trình phát triển</strong>
            <span style="font-size: 0.9rem; line-height: 1.5;">Hệ thống đang phát triển tính năng tự động đối soát giao dịch ngân hàng. Trong thời gian này, vui lòng nhân viên hoặc chủ cơ sở kiểm tra biến động số dư tài khoản ngân hàng của cơ sở và nhấn xác nhận thanh toán thủ công bên dưới.</span>
        </div>
    </div>

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

    <!-- KHỐI 5: QUY TRÌNH XÁC NHẬN THỦ CÔNG & ĐỐI SOÁT GIAO DỊCH -->
    <section class="detail-card" style="background: var(--surface, #fff); border: 2px solid <?= $isConfirmed ? '#34a853' : '#1a73e8' ?>; border-radius: 8px; padding: 24px; margin-bottom: 30px;">
        <h2 style="font-size: 1.25rem; margin-top: 0; margin-bottom: 12px; color: #1a202c; display: flex; align-items: center; gap: 8px;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            Xác nhận thanh toán &amp; Đối soát giao dịch thủ công
        </h2>

        <?php if ($isConfirmed): ?>
            <!-- ĐÃ XÁC NHẬN HOÀN TẤT -->
            <div style="background: #e6f4ea; border-left: 4px solid #34a853; padding: 18px 22px; border-radius: 8px; color: #137333;">
                <strong style="font-size: 1.1rem; display: block; margin-bottom: 6px;">✓ Đơn đặt sân đã được xác nhận thanh toán thành công!</strong>
                <p style="margin: 0; font-size: 0.95rem; line-height: 1.6; color: #14532d;">
                    Mã giao dịch ngân hàng: <strong style="font-family: monospace; background: #dcfce7; padding: 2px 8px; border-radius: 4px;"><?= e($booking['ma_giao_dich'] ?: '—') ?></strong> &bull;
                    Số tiền thực nhận: <strong><?= number_format((float) $booking['so_tien_thuc_nhan'], 0, ',', '.') ?> đ</strong><br>
                    <?php if (!empty($booking['ten_nhan_vien_xac_nhan'])): ?>
                        Nhân viên xác nhận: <strong><?= e($booking['ten_nhan_vien_xac_nhan']) ?></strong> (<?= e($booking['chuc_vu_nhan_vien'] ?? 'Nhân viên') ?>)
                    <?php endif; ?>
                </p>

                <!-- KHU VỰC HÀNH ĐỘNG: 2 NÚT RIÊNG BIỆT (KHÔNG BỊ TRÙNG LẶP, KHÔNG TRÀN ẢNH) -->
                <div style="margin-top: 16px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                    <?php if (!empty($receiptImage)): ?>
                        <a class="btn btn--outline" href="<?= e(base_path() . '/' . $receiptImage) ?>" target="_blank" rel="noopener" style="display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; font-weight: 600; text-decoration: none; border-color: #16a34a; color: #15803d; background: #ffffff;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            <span>Xem ảnh biên lai khách gửi ↗</span>
                        </a>
                    <?php endif; ?>

                    <a class="btn btn--primary" href="<?= e(url('/admin/invoices/show') . '&booking_id=' . (int) $booking['ma_dat_san']) ?>" style="display: inline-flex; align-items: center; gap: 6px; padding: 9px 20px; font-weight: 700; text-decoration: none;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3h14v18l-3-2-4 2-4-2-3 2zM8 7h8M8 11h8M8 15h4"/></svg>
                        <span>Phiếu hóa đơn thanh toán (PR4) ↗</span>
                    </a>
                </div>
            </div>

        <?php elseif ($isReconcile): ?>
            <!-- ĐANG TRONG TRẠNG THÁI CẦN ĐỐI SOÁT: CHỈ CÓ 2 THAO TÁC (THÀNH CÔNG HOẶC HỦY ĐẶT SÂN) -->
            <div style="background: #fffbeb; border: 1px solid #fde68a; border-left: 5px solid #f59e0b; border-radius: 8px; padding: 18px 22px; margin-bottom: 24px;">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 8px;">
                    <span style="font-size: 1.05rem; font-weight: 700; color: #b45309; display: flex; align-items: center; gap: 8px;">
                        <span>⚠️</span> ĐƠN ĐANG TRONG TRẠNG THÁI CẦN ĐỐI SOÁT (CAN_DOI_SOAT)
                    </span>
                    <span style="font-size: 0.85rem; background: #fef3c7; color: #92400e; padding: 4px 12px; border-radius: 6px; font-weight: 700; border: 1px solid #fde68a;">
                        🏟 Khung giờ sân đang được giữ cho khách
                    </span>
                </div>
                <p style="margin: 0 0 10px 0; font-size: 0.92rem; color: #78350f; line-height: 1.6;">
                    <strong>Lý do cần đối soát:</strong> <?= e(\App\Services\ReceiptImage::stripReceiptTag($booking['ghi_chu_thanh_toan'] ?? 'Chưa có ghi chú')) ?>
                </p>
                <div style="font-size: 0.88rem; color: #92400e; display: flex; gap: 20px; flex-wrap: wrap;">
                    <span>Tổng tiền cần thanh toán: <strong><?= number_format((float) ($booking['so_tien_can_thanh_toan'] ?? $booking['tong_tien_tam_tinh']), 0, ',', '.') ?> đ</strong></span>
                    <?php if (!empty($booking['so_tien_thuc_nhan']) && (float) $booking['so_tien_thuc_nhan'] > 0): ?>
                        <span>Số tiền đã ghi nhận: <strong><?= number_format((float) $booking['so_tien_thuc_nhan'], 0, ',', '.') ?> đ</strong></span>
                    <?php endif; ?>
                    <?php if (!empty($booking['ma_giao_dich'])): ?>
                        <span>Mã GD trước đó: <code><?= e($booking['ma_giao_dich']) ?></code></span>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
                
                <!-- THAO TÁC 1: XÁC NHẬN THÀNH CÔNG -->
                <form method="post" action="<?= e(url('/admin/bookings/confirm')) ?>" style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 22px; display: flex; flex-direction: column; justify-content: space-between;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($booking['ma_dat_san']) ?>">

                    <div>
                        <h3 style="font-size: 1.1rem; margin-top: 0; margin-bottom: 8px; color: #166534; display: flex; align-items: center; gap: 6px;">
                            <span>✓</span> Xác nhận thành công (Giao dịch thành công)
                        </h3>
                        <p style="font-size: 0.86rem; color: #15803d; margin-top: 0; margin-bottom: 16px; line-height: 1.5;">
                            Khách hàng đã bổ sung đủ tiền hoặc đã đối soát Zalo khớp lệnh thành công. Nhấn để kích hoạt đơn <strong>ĐÃ XÁC NHẬN</strong> và lập hóa đơn thu ngân.
                        </p>

                        <div class="form-group" style="margin-bottom: 14px;">
                            <label for="rec_conf_so_tien" style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 4px; color: #166534;">
                                Số tiền chốt đơn (đ) <span style="color: red;">*</span>
                            </label>
                            <input class="form-control" type="number" step="1000" id="rec_conf_so_tien" name="so_tien_thuc_nhan" required
                                   value="<?= e($oldInput['so_tien_thuc_nhan'] ?? (string) (int) ($booking['so_tien_can_thanh_toan'] ?? $booking['tong_tien_tam_tinh'])) ?>"
                                   style="width: 100%; font-weight: 700; font-size: 1.05rem; color: #166534; background: #ffffff;">
                        </div>

                        <div class="form-group" style="margin-bottom: 14px;">
                            <label for="rec_conf_ma_gd" style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 4px; color: #166534;">
                                Mã giao dịch ngân hàng khớp lệnh <span style="color: red;">*</span>
                            </label>
                            <input class="form-control" type="text" id="rec_conf_ma_gd" name="ma_giao_dich" maxlength="150" required
                                   placeholder="VD: FT26110298765432..."
                                   value="<?= e($oldInput['ma_giao_dich'] ?? ($booking['ma_giao_dich'] ?? '')) ?>"
                                   style="width: 100%; font-family: monospace; background: #ffffff;">
                        </div>

                        <div class="form-group" style="margin-bottom: 14px;">
                            <label for="rec_conf_ngay" style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 4px; color: #166534;">
                                Thời điểm xác nhận tiền về
                            </label>
                            <input class="form-control" type="datetime-local" id="rec_conf_ngay" name="ngay_thanh_toan"
                                   value="<?= e($oldInput['ngay_thanh_toan'] ?? $now->format('Y-m-d\TH:i')) ?>"
                                   style="width: 100%; background: #ffffff;">
                        </div>

                        <div class="form-group" style="margin-bottom: 18px;">
                            <label for="rec_conf_ghi_chu" style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 4px; color: #166534;">
                                Ghi chú đối soát hoàn tất
                            </label>
                            <input class="form-control" type="text" id="rec_conf_ghi_chu" name="ghi_chu" maxlength="500"
                                   placeholder="Đã đối soát xong qua Zalo, nhận đủ tiền..."
                                   value="<?= e($oldInput['ghi_chu'] ?? '') ?>"
                                   style="width: 100%; background: #ffffff;">
                        </div>
                    </div>

                    <button class="btn btn--primary" type="submit" style="width: 100%; padding: 13px; font-weight: 700; font-size: 0.98rem; justify-content: center; background: #16a34a; border-color: #16a34a; color: #ffffff;"
                            onclick="return confirm('Xác nhận giao dịch THÀNH CÔNG và kích hoạt lịch đặt sân chính thức cho khách?');">
                        ✓ Xác nhận thành công &amp; Giữ sân chính thức ↗
                    </button>
                </form>

                <!-- THAO TÁC 2: HỦY ĐẶT SÂN & GIẢI PHÓNG SÂN VỀ TRỐNG -->
                <form method="post" action="<?= e(url('/admin/bookings/cancel')) ?>" style="background: #fff5f5; border: 1px solid #fecaca; border-radius: 8px; padding: 22px; display: flex; flex-direction: column; justify-content: space-between;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($booking['ma_dat_san']) ?>">

                    <div>
                        <h3 style="font-size: 1.1rem; margin-top: 0; margin-bottom: 8px; color: #991b1b; display: flex; align-items: center; gap: 6px;">
                            <span>✕</span> Hủy đặt sân (Giải phóng sân về TRỐNG)
                        </h3>
                        <p style="font-size: 0.86rem; color: #b91c1c; margin-top: 0; margin-bottom: 16px; line-height: 1.5;">
                            Dùng khi đối soát không thành công (khách không chuyển thêm tiền, giao dịch không hợp lệ, hoặc hủy theo yêu cầu). Trạng thái đơn sẽ thành <strong>ĐÃ HỦY</strong> và khung giờ sân sẽ chuyển về <strong>TRỐNG</strong> ngay lập tức để người khác có thể đặt.
                        </p>

                        <div class="form-group" style="margin-bottom: 18px;">
                            <label for="ly_do_huy" style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 4px; color: #991b1b;">
                                Lý do hủy đặt sân <span style="color: red;">*</span>
                            </label>
                            <textarea class="form-control" id="ly_do_huy" name="ly_do_huy" rows="4" required
                                      placeholder="VD: Đối soát qua Zalo thất bại, khách không thanh toán phần tiền còn thiếu; Giao dịch không hợp lệ..."
                                      style="width: 100%; background: #ffffff;"></textarea>
                            <small style="color: #b91c1c; font-size: 0.78rem;">Lý do hủy sẽ được lưu vào hệ thống và giải phóng sân</small>
                        </div>
                    </div>

                    <button class="btn btn--outline" type="submit" style="width: 100%; padding: 13px; font-weight: 700; font-size: 0.98rem; justify-content: center; color: #dc2626; border-color: #ef4444; background: #ffffff;"
                            onclick="return confirm('CẢNH BÁO: Bạn chắc chắn muốn HỦY đơn đặt sân này? Khung giờ sân sẽ được giải phóng về trạng thái TRỐNG ngay lập tức!');">
                        ✕ Hủy đặt sân &amp; Trả về sân trống
                    </button>
                </form>

            </div>

        <?php elseif ($isPending): ?>
            <!-- ĐANG TRONG THỜI HẠN GIỮ SÂN - CHO PHÉP XÁC NHẬN HOẶC ĐỐI SOÁT -->
            <p style="color: #4a5568; font-size: 0.95rem; margin-top: 0; margin-bottom: 20px;">
                Nhân viên vui lòng mở ứng dụng ngân hàng của cơ sở, tìm kiếm biến động số dư có nội dung chứa <strong><?= e($booking['ma_tham_chieu']) ?></strong>.
                Nếu đã nhận đủ số tiền và khớp thông tin, hãy điền mã giao dịch và nhấn nút xác nhận bên dưới.
            </p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
                
                <!-- FORM 1: XÁC NHẬN HỢP LỆ -->
                <form method="post" action="<?= e(url('/admin/bookings/confirm')) ?>" style="background: #f8fafc; border: 1px solid #cbd5e0; border-radius: 8px; padding: 20px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($booking['ma_dat_san']) ?>">

                    <h3 style="font-size: 1.05rem; margin-top: 0; margin-bottom: 14px; color: #0f5132; display: flex; align-items: center; gap: 6px;">
                        <span>✓</span> Xác nhận thanh toán &amp; Booking
                    </h3>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="so_tien_thuc_nhan" style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 4px;">
                            Số tiền thực nhận vào tài khoản (đ) <span style="color: red;">*</span>
                        </label>
                        <input class="form-control" type="number" step="1000" id="so_tien_thuc_nhan" name="so_tien_thuc_nhan" required
                               value="<?= e($oldInput['so_tien_thuc_nhan'] ?? (string) (int) ($booking['so_tien_can_thanh_toan'] ?? $booking['tong_tien_tam_tinh'])) ?>"
                               style="width: 100%; font-weight: 700; font-size: 1.05rem; color: #0f5132;">
                        <small style="color: #666; font-size: 0.78rem;">Phải khớp chính xác số tiền cần thanh toán</small>
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="ma_giao_dich" style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 4px;">
                            Mã giao dịch ngân hàng (FT... / Mã GD) <span style="color: red;">*</span>
                        </label>
                        <input class="form-control" type="text" id="ma_giao_dich" name="ma_giao_dich" maxlength="150" required
                               placeholder="VD: FT26110298765432 hoặc MB123456"
                               value="<?= e($oldInput['ma_giao_dich'] ?? '') ?>"
                               style="width: 100%; font-family: monospace;">
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="ngay_thanh_toan" style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 4px;">
                            Thời điểm tiền về ngân hàng
                        </label>
                        <input class="form-control" type="datetime-local" id="ngay_thanh_toan" name="ngay_thanh_toan"
                               value="<?= e($oldInput['ngay_thanh_toan'] ?? $now->format('Y-m-d\TH:i')) ?>"
                               style="width: 100%;">
                    </div>

                    <div class="form-group" style="margin-bottom: 18px;">
                        <label for="ghi_chu" style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 4px;">
                            Ghi chú (tùy chọn)
                        </label>
                        <input class="form-control" type="text" id="ghi_chu" name="ghi_chu" maxlength="500"
                               placeholder="Ghi chú đối soát nội bộ..."
                               value="<?= e($oldInput['ghi_chu'] ?? '') ?>"
                               style="width: 100%;">
                    </div>

                    <button class="btn btn--primary" type="submit" style="width: 100%; padding: 12px; font-weight: 700; font-size: 0.95rem; justify-content: center;"
                            onclick="return confirm('Bạn xác nhận ĐÃ KIỂM TRA TÀI KHOẢN NGÂN HÀNG và số tiền nhận được hoàn toàn chính xác?');">
                        Xác nhận thanh toán &amp; Giữ sân chính thức ↗
                    </button>
                </form>

                <!-- FORM 2: CHUYỂN SANG CẦN ĐỐI SOÁT (SAI TIỀN / TIỀN MUỘN / NỘI DUNG LỖI) -->
                <form method="post" action="<?= e(url('/admin/bookings/reconcile')) ?>" style="background: #fff8f6; border: 1px solid #fecaca; border-radius: 8px; padding: 20px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($booking['ma_dat_san']) ?>">

                    <h3 style="font-size: 1.05rem; margin-top: 0; margin-bottom: 6px; color: #c5221f; display: flex; align-items: center; gap: 6px;">
                        <span>⚠</span> Chuyển "Cần đối soát" (CAN_DOI_SOAT)
                    </h3>
                    <p style="font-size: 0.83rem; color: #7f1d1d; margin-top: 0; margin-bottom: 14px; line-height: 1.4;">
                        Dùng khi: Khách chuyển thiếu tiền, chuyển thừa tiền, sai nội dung chuyển khoản hoặc giao dịch cần xử lý riêng. <em>(Tuyệt đối không xác nhận giữ sân và không trừ tồn kho).</em>
                    </p>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="rec_so_tien" style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 4px;">
                            Số tiền thực tế nhận được (đ)
                        </label>
                        <input class="form-control" type="number" step="1000" id="rec_so_tien" name="so_tien_thuc_nhan"
                               placeholder="Số tiền thực tế có trong tài khoản"
                               style="width: 100%;">
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="rec_ma_gd" style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 4px;">
                            Mã giao dịch ngân hàng (nếu có)
                        </label>
                        <input class="form-control" type="text" id="rec_ma_gd" name="ma_giao_dich" maxlength="150"
                               placeholder="Mã GD ngân hàng..."
                               style="width: 100%; font-family: monospace;">
                    </div>

                    <div class="form-group" style="margin-bottom: 18px;">
                        <label for="ly_do_doi_soat" style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 4px; color: #b91c1c;">
                            Lý do cần đối soát <span style="color: red;">*</span>
                        </label>
                        <textarea class="form-control" id="ly_do_doi_soat" name="ly_do_doi_soat" rows="3" required
                                  placeholder="VD: Khách chuyển thiếu 50.000đ; Khách chuyển sai mã nội dung; Chuyển tiền sau khi đơn hết hạn..."
                                  style="width: 100%;"></textarea>
                    </div>

                    <button class="btn btn--outline" type="submit" style="width: 100%; padding: 12px; font-weight: 600; color: #c5221f; border-color: #f87171; justify-content: center;"
                            onclick="return confirm('Chuyển trạng thái thanh toán sang CẦN ĐỐI SOÁT? Đơn đặt sân sẽ KHÔNG được xác nhận.');">
                        Đánh dấu Cần đối soát
                    </button>
                </form>

            </div>

        <?php else: ?>
            <!-- ĐÃ HẾT HẠN HOẶC ĐÃ HỦY -->
            <div style="background: #f8fafc; border-left: 4px solid #ef4444; padding: 16px 20px; border-radius: 6px; color: #374151; margin-bottom: 18px;">
                <strong style="color: #dc2626; font-size: 1rem; display: block; margin-bottom: 4px;">
                    Đơn đặt sân này <?= $isExpired ? 'ĐÃ HẾT THỜI GIAN GIỮ CHỖ' : ($bookingSt === 'DA_HUY' ? 'ĐÃ BỊ HỦY' : 'KHÔNG TRONG TRẠNG THÁI CHỜ DUYỆT') ?>.
                </strong>
                <p style="margin: 0; font-size: 0.9rem; line-height: 1.5; color: #4b5563;">
                    Theo quy định nghiệp vụ, hệ thống không cho phép xác nhận giữ sân cho đơn đã hết hạn hoặc đã hủy để tránh tranh chấp trùng lịch với khách hàng khác.
                </p>
            </div>

            <!-- Nếu khách lỡ chuyển tiền muộn khi đơn đã hết hạn, vẫn cung cấp form chuyển CAN_DOI_SOAT để đối soát hoàn tiền -->
            <?php if ($paymentSt !== 'THANH_CONG' && $paymentSt !== 'CAN_DOI_SOAT'): ?>
                <form method="post" action="<?= e(url('/admin/bookings/reconcile')) ?>" style="background: #fff8f6; border: 1px dashed #f87171; border-radius: 8px; padding: 18px; max-width: 500px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($booking['ma_dat_san']) ?>">
                    <h3 style="font-size: 0.95rem; margin-top: 0; margin-bottom: 10px; color: #991b1b;">
                        Khách hàng đã chuyển tiền muộn sau khi đơn hết hạn?
                    </h3>
                    <div class="form-group" style="margin-bottom: 10px;">
                        <input class="form-control" type="number" step="1000" name="so_tien_thuc_nhan" placeholder="Số tiền thực nhận được (đ)" required style="width: 100%;">
                    </div>
                    <div class="form-group" style="margin-bottom: 10px;">
                        <input class="form-control" type="text" name="ma_giao_dich" placeholder="Mã giao dịch ngân hàng..." style="width: 100%; font-family: monospace;">
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <input class="form-control" type="text" name="ly_do_doi_soat" placeholder="Lý do đối soát: Khách chuyển tiền muộn..." required style="width: 100%;">
                    </div>
                    <button class="btn btn--outline" type="submit" style="color: #b91c1c; border-color: #f87171; padding: 8px 16px; font-size: 0.9rem;">
                        Ghi nhận tiền vào Cần đối soát (Hoàn tiền cho khách)
                    </button>
                </form>
            <?php endif; ?>

        <?php endif; ?>
    </section>

</section>
