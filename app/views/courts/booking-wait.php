
<?php
use App\Services\BookingPricingService as Money;

$states = [
    'CHO_THANH_TOAN' => 'Sân đang được tạm giữ',
    'DA_HUY' => 'Đã hủy giữ sân',
    'HET_HAN' => 'Đã hết thời gian giữ sân',
    'DA_XAC_NHAN' => 'Booking đã được xác nhận',
    'HOAN_THANH' => 'Booking đã hoàn thành',
    'CHO_XAC_NHAN' => 'Booking chưa được giữ sân'
];
?>

<?php
$cssBookingPath = dirname(__DIR__, 3) . '/public/assets/css/booking.css';
$cssVietqrPath = dirname(__DIR__, 3) . '/public/assets/css/vietqr.css';
$jsBookingPath = dirname(__DIR__, 3) . '/public/assets/js/booking.js';
$jsVietqrPath = dirname(__DIR__, 3) . '/public/assets/js/vietqr.js';
?>
<link rel="stylesheet" href="<?= e(base_path().'/assets/css/booking.css?v=' . (file_exists($cssBookingPath) ? filemtime($cssBookingPath) : time())) ?>">
<link rel="stylesheet" href="<?= e(base_path().'/assets/css/vietqr.css?v=' . (file_exists($cssVietqrPath) ? filemtime($cssVietqrPath) : time())) ?>">
<script src="<?= e(base_path().'/assets/js/booking.js?v=' . (file_exists($jsBookingPath) ? filemtime($jsBookingPath) : time())) ?>" defer></script>
<script src="<?= e(base_path().'/assets/js/vietqr.js?v=' . (file_exists($jsVietqrPath) ? filemtime($jsVietqrPath) : time())) ?>" defer></script>

<section class="booking-page booking-wait"
         data-booking-navigation
         data-back-url="<?= e($bookingBackUrl) ?>"
         data-booking-wait
         data-booking-id="<?= e($booking['ma_dat_san']) ?>"
         data-expire-url="<?= e(url('/booking/expire')) ?>"
         data-receipt-submitted="<?= !empty($receiptSubmitted) ? '1' : '0' ?>"
         data-payment-status="<?= e($payment['trang_thai'] ?? '') ?>"
         data-now="<?= e($now->format(DATE_ATOM)) ?>"
         data-expires="<?= e($booking['het_han_giu_san_luc']) ?>"
         data-state="<?= e($booking['trang_thai_dat_san']) ?>"
         data-status-url="<?= e(url('/booking/wait') . '&id=' . $booking['ma_dat_san'] . '&status=1') ?>">

    <?php
    $isReconcileState = !empty($isReconcile) || ($payment && ($payment['trang_thai'] ?? '') === 'CAN_DOI_SOAT');
    $isConfirmedBooking = in_array($booking['trang_thai_dat_san'], ['DA_XAC_NHAN', 'HOAN_THANH'], true) && !$isReconcileState;
    $isReceiptWait = (!empty($receiptSubmitted) || $booking['trang_thai_dat_san'] === 'CHO_XAC_NHAN') && !$isConfirmedBooking && !$isReconcileState && !in_array($booking['trang_thai_dat_san'], ['HET_HAN','DA_HUY'], true) && $booking['het_han_giu_san_luc'] > $now->format('Y-m-d H:i:s');
    $isExpiredOrCanceled = in_array($booking['trang_thai_dat_san'], ['HET_HAN', 'DA_HUY'], true) && !$isConfirmedBooking && !$isReceiptWait && !$isReconcileState;
    ?>

    <header class="booking-heading">
        <div>
            <p class="booking-eyebrow" data-booking-eyebrow><?= $isReconcileState ? 'CẦN ĐỐI SOÁT' : ($isConfirmedBooking ? 'THÀNH CÔNG' : ($isExpiredOrCanceled ? 'ĐẶT SÂN' : 'THANH TOÁN')) ?></p>
            <h1 data-booking-title>
                <?php
                if ($isReconcileState) {
                    echo 'Giao dịch cần đối soát thanh toán';
                } elseif ($isConfirmedBooking) {
                    echo 'Đặt sân &amp; Thanh toán thành công';
                } elseif ($isReceiptWait) {
                    echo 'Chờ xác nhận thanh toán';
                } elseif ($isExpiredOrCanceled) {
                    echo 'Đã hết thời gian giữ sân';
                } else {
                    echo 'Chờ thanh toán';
                }
                ?>
            </h1>
            <p class="booking-subtle">
                Mã đặt sân #<?= e($booking['ma_dat_san']) ?>
            </p>
        </div>
    </header>

    <?php if (!empty($receiptNotice)): ?>
        <div class="stryke-toast-notice" style="background: rgba(200, 243, 107, 0.12); border: 1px solid rgba(200, 243, 107, 0.35); color: #c8f36b; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 1.15rem;">✓</span>
            <span><?= e($receiptNotice) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($receiptImage)): ?><p class="booking-receipt-link"><a class="booking-text-button" href="<?= e($receiptImage) ?>" target="_blank" rel="noopener">Xem lại minh chứng đã gửi ↗</a></p><?php endif; ?>

    <?php if (!empty($paymentError) && !$isConfirmedBooking && !$isReconcileState): ?>
        <div class="stryke-toast-error" style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #fca5a5; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 1.15rem;">⚠️</span>
            <span><?= e($paymentError) ?></span>
        </div>
    <?php endif; ?>

    <div class="booking-navigation-error"
         data-cancel-error
         hidden
         role="alert">
        <p data-cancel-message></p>
        <button type="button"
                class="booking-text-button"
                data-retry-cancel>
            Thử hủy lại
        </button>
    </div>

    <!-- 1. THANH ĐẾM NGƯỢC GIỮ CHỖ (COUNTDOWN BANNER) -->
    <div class="stryke-countdown-banner" <?= ($isConfirmedBooking || $isReceiptWait || $isExpiredOrCanceled || $isReconcileState) ? 'hidden' : '' ?>>
        <p class="stryke-countdown-label" data-wait-message role="status">
            <?= ($booking['trang_thai_dat_san'] === 'CHO_THANH_TOAN')
                ? 'Đơn của bạn còn được giữ chỗ trong'
                : e($states[$booking['trang_thai_dat_san']] ?? 'Trạng thái booking đã thay đổi') ?>
        </p>
        <div class="stryke-countdown-time" data-countdown aria-label="Thời gian giữ sân còn lại">
            —
        </div>
    </div>

    <!-- TH3: KHỐI CẦN ĐỐI SOÁT THANH TOÁN (CAN_DOI_SOAT) - LIÊN HỆ ZALO VỚI CHỦ SÂN -->
    <div class="stryke-reconcile-box"
         data-payment-reconcile
         <?= !$isReconcileState ? 'hidden' : '' ?>>

        <div class="stryke-reconcile-icon-badge">
            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
        </div>

        <h2 class="stryke-reconcile-heading">
            GIAO DỊCH CẦN ĐỐI SOÁT
        </h2>
        <p class="stryke-reconcile-subheading">
            (Khoản thanh toán cần đối chiếu lại thông tin với chủ sân trước khi kích hoạt)
        </p>

        <!-- Thẻ lý do từ nhân viên -->
        <div class="stryke-reconcile-reason-card">
            <div class="stryke-reconcile-reason-header">
                <span>⚠️</span>
                <span>Lý do cần đối soát từ nhân viên:</span>
            </div>
            <p class="stryke-reconcile-reason-content" data-reconcile-reason>
                <?= !empty($reconcileReason) ? e($reconcileReason) : 'Hệ thống chưa khớp được số tiền hoặc mã giao dịch ngân hàng của bạn. Vui lòng liên hệ chủ sân để đối chiếu nhanh.' ?>
            </p>
        </div>

        <!-- Khung tóm tắt thông tin giao dịch -->
        <div class="stryke-receipt-summary-card">
            <div class="stryke-summary-grid">
                <div class="stryke-summary-item">
                    <span class="stryke-item-label">Mã đặt sân</span>
                    <strong class="stryke-item-value">#<?= e($booking['ma_dat_san']) ?></strong>
                </div>
                <div class="stryke-summary-item">
                    <span class="stryke-item-label">Số tiền cần thanh toán</span>
                    <strong class="stryke-item-value accent-lime">
                        <?= e(number_format((float) ($payment['so_tien'] ?? $booking['tong_tien_tam_tinh']), 0, ',', '.')) ?> đ
                    </strong>
                </div>
                <div class="stryke-summary-item">
                    <span class="stryke-item-label">Mã tham chiếu CK</span>
                    <code class="stryke-item-code"><?= e($payment['ma_tham_chieu'] ?? '') ?></code>
                </div>
                <div class="stryke-summary-item">
                    <span class="stryke-item-label">Trạng thái</span>
                    <span class="stryke-item-value" style="color: #fbbf24; font-weight: 700;">Cần đối soát (CAN_DOI_SOAT)</span>
                </div>
            </div>

            <!-- Khung ảnh biên lai thu nhỏ nếu có -->
            <?php if (!empty($receiptImage)): ?>
                <div class="stryke-receipt-thumbnail-section">
                    <div class="stryke-receipt-thumb-header">
                        <span class="stryke-thumb-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <polyline points="21 15 16 10 5 21"></polyline>
                            </svg>
                            Ảnh biên lai bạn đã gửi
                        </span>
                        <a href="<?= e($receiptImage) ?>" target="_blank" rel="noopener" class="stryke-thumb-link">
                            Phóng to ảnh ↗
                        </a>
                    </div>
                    
                    <div class="stryke-receipt-thumb-container">
                        <a href="<?= e($receiptImage) ?>" target="_blank" rel="noopener" title="Bấm vào để xem ảnh kích thước gốc" class="stryke-thumb-link-wrapper">
                            <img src="<?= e($receiptImage) ?>" alt="Biên lai chuyển khoản" class="stryke-receipt-thumb-img">
                            <span class="stryke-thumb-zoom-tag">🔍 Bấm để xem ảnh lớn</span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Thẻ thông tin liên hệ chủ sân & Hướng dẫn đối chiếu qua Zalo -->
        <div class="stryke-reconcile-contact-card">
            <div class="stryke-reconcile-facility-header">
                <div class="stryke-reconcile-facility-name">
                    <span>🏟</span>
                    <span><?= e($facility['ten_co_so'] ?? 'Cơ sở thể thao STRYKE') ?></span>
                </div>
                <?php if (!empty($facility['so_dien_thoai'])): ?>
                    <div class="stryke-reconcile-facility-phone">
                        SĐT / Zalo chủ sân: <strong><?= e($facility['so_dien_thoai']) ?></strong>
                    </div>
                <?php endif; ?>
            </div>

            <div class="stryke-reconcile-guide-title">
                📝 Nội dung cần cung cấp khi nhắn tin Zalo:
            </div>
            <ol class="stryke-reconcile-guide-list">
                <li>
                    <strong>Mã đặt sân:</strong> <code>#<?= e($booking['ma_dat_san']) ?></code>
                    <button type="button" class="stryke-btn-copy-memo" data-copy-text="<?= e($booking['ma_dat_san']) ?>" style="margin-left: 6px;">Sao chép mã</button>
                </li>
                <?php if (!empty($payment['ma_tham_chieu'])): ?>
                    <li>
                        <strong>Mã tham chiếu CK:</strong> <code><?= e($payment['ma_tham_chieu']) ?></code>
                        <button type="button" class="stryke-btn-copy-memo" data-copy-text="<?= e($payment['ma_tham_chieu']) ?>" style="margin-left: 6px;">Sao chép mã</button>
                    </li>
                <?php endif; ?>
                <li>
                    <strong>Ảnh biên lai / Sao kê:</strong> Gửi kèm ảnh chụp màn hình ứng dụng ngân hàng hiển thị giao dịch chuyển khoản thành công.
                </li>
            </ol>

            <div class="stryke-reconcile-contact-buttons">
                <?php
                $facilityPhone = $facility['so_dien_thoai'] ?? '';
                $cleanPhone = preg_replace('/[^0-9]/', '', $facilityPhone);
                ?>
                <?php if (!empty($cleanPhone)): ?>
                    <a href="https://zalo.me/<?= e($cleanPhone) ?>" target="_blank" rel="noopener" class="stryke-btn-zalo">
                        <svg width="22" height="22" viewBox="0 0 48 48" fill="none">
                            <path d="M24 4C12.954 4 4 12.06 4 22c0 5.617 2.923 10.61 7.514 13.98-.316 2.37-1.488 6.643-1.632 7.185-.246.924.341 1.096.726.83 2.82-1.95 6.78-4.743 7.85-5.508C20.016 38.835 21.97 39 24 39c11.046 0 20-8.06 20-18S35.046 4 24 4z" fill="#fff"/>
                            <path d="M14 26.5l5.5-8.5H14v-2h8.5v2.5L17 27h6v2H14v-2.5zm11.5-10.5h2.5v13H25.5v-13zm5 0h2.5v10.5h4.5v2.5h-7v-13z" fill="#0068ff"/>
                        </svg>
                        <span>Nhắn tin Zalo với chủ sân (<?= e($facilityPhone) ?>) ↗</span>
                    </a>
                    <a href="tel:<?= e($cleanPhone) ?>" class="stryke-btn-hotline">
                        <span>📞 Gọi Hotline chủ sân: <strong><?= e($facilityPhone) ?></strong></span>
                    </a>
                <?php else: ?>
                    <p class="stryke-reconcile-no-phone">
                        Cơ sở chưa cung cấp số điện thoại liên hệ trực tuyến. Vui lòng liên hệ trực tiếp nhân viên quầy tại cơ sở để được hỗ trợ đối soát.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <p class="stryke-receipt-reassure">
            💡 Gửi ảnh hoặc đối soát không gia hạn giữ sân. Khoản tiền chuyển muộn cần được cơ sở xử lý riêng, không tự động giữ lại khung giờ đã hết hạn.
        </p>

        <!-- Nhóm nút điều hướng -->
        <div class="stryke-receipt-actions">
            <a href="<?= e(url('/account')) ?>" class="stryke-btn-primary-action">
                Xem lịch đặt tại Tài khoản của tôi ↗
            </a>
            <a href="<?= e(url('/')) ?>" class="stryke-btn-secondary-action">
                Về trang chủ
            </a>
        </div>
    </div>

    <!-- TH1: KHỐI THANH TOÁN THÀNH CÔNG (ĐANG CHỜ XÁC NHẬN) - THIẾT KẾ ĐỒNG BỘ STRYKE DARK THEME -->
    <div class="stryke-receipt-submitted-box"
         data-receipt-waiting
         <?= !$isReceiptWait ? 'hidden' : '' ?>>
        
        <!-- Icon thành công phát sáng phong cách STRYKE -->
        <div class="stryke-success-icon-badge">
            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>

        <h2 class="stryke-success-heading">
            ĐÃ GỬI MINH CHỨNG
        </h2>
        <p class="stryke-success-subheading">
            Chờ xác nhận thanh toán
        </p>

        <!-- Thanh thông báo trạng thái nổi bật -->
        <div class="stryke-receipt-status-banner">
            <span class="stryke-receipt-status-dot"></span>
            <span>Hệ thống đã tiếp nhận ảnh biên lai chuyển khoản. Nhân viên lễ tân đang kiểm tra biến động số dư tài khoản ngân hàng và sẽ kích hoạt lịch đặt ngay khi khớp lệnh.</span>
        </div>

        <!-- Khung tóm tắt thông tin đơn đặt & thanh toán -->
        <div class="stryke-receipt-summary-card">
            <div class="stryke-summary-grid">
                <div class="stryke-summary-item">
                    <span class="stryke-item-label">Mã đặt sân</span>
                    <strong class="stryke-item-value">#<?= e($booking['ma_dat_san']) ?></strong>
                </div>
                <div class="stryke-summary-item">
                    <span class="stryke-item-label">Số tiền cần thanh toán</span>
                    <strong class="stryke-item-value accent-lime">
                        <?= e(number_format((float) ($payment['so_tien'] ?? $booking['tong_tien_tam_tinh']), 0, ',', '.')) ?> đ
                    </strong>
                </div>
                <div class="stryke-summary-item">
                    <span class="stryke-item-label">Mã tham chiếu CK</span>
                    <code class="stryke-item-code"><?= e($payment['ma_tham_chieu'] ?? '') ?></code>
                </div>
                <div class="stryke-summary-item">
                    <span class="stryke-item-label">Phương thức</span>
                    <span class="stryke-item-value">Chuyển khoản VietQR</span>
                </div>
            </div>

            <!-- Khung ảnh biên lai thu nhỏ - KHẮC PHỤC TRIỆT ĐỂ LỖI PHÌNH TO MÀN HÌNH -->
            <?php if (!empty($receiptImage)): ?>
                <div class="stryke-receipt-thumbnail-section">
                    <div class="stryke-receipt-thumb-header">
                        <span class="stryke-thumb-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <polyline points="21 15 16 10 5 21"></polyline>
                            </svg>
                            Ảnh biên lai đã gửi
                        </span>
                        <a href="<?= e($receiptImage) ?>" target="_blank" rel="noopener" class="stryke-thumb-link">
                            Phóng to ảnh ↗
                        </a>
                    </div>
                    
                    <div class="stryke-receipt-thumb-container">
                        <a href="<?= e($receiptImage) ?>" target="_blank" rel="noopener" title="Bấm vào để xem ảnh kích thước gốc" class="stryke-thumb-link-wrapper">
                            <img src="<?= e($receiptImage) ?>" alt="Biên lai chuyển khoản" class="stryke-receipt-thumb-img">
                            <span class="stryke-thumb-zoom-tag">🔍 Bấm để xem ảnh lớn</span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <p class="stryke-receipt-reassure">
            💡 Bạn có thể theo dõi tiến trình xử lý tại trang tài khoản cá nhân. Đơn đặt sân sẽ chuyển sang <strong>ĐÃ XÁC NHẬN</strong> ngay khi nhân viên hoàn tất đối soát.
        </p>

        <!-- Nhóm nút điều hướng -->
        <div class="stryke-receipt-actions">
            <a href="<?= e(url('/account')) ?>" class="stryke-btn-primary-action">
                Xem chi tiết lịch đặt sân của tôi ↗
            </a>
            <a href="<?= e(url('/')) ?>" class="stryke-btn-secondary-action">
                Về trang chủ
            </a>
        </div>
    </div>

    <!-- KHUNG LƯỚI NỘI DUNG 2 CỘT (DESKTOP & MOBILE RESPONSIVE) -->
    <div class="stryke-checkout-container"
         data-payment-pending
         <?= ($booking['trang_thai_dat_san'] !== 'CHO_THANH_TOAN' || !empty($receiptSubmitted) || $isReconcileState) ? 'hidden' : '' ?>>

        <div class="stryke-checkout-grid">
            <!-- ================= CỘT TRÁI: PHƯƠNG THỨC THANH TOÁN & TẢI ẢNH ================= -->
            <div class="stryke-col-left">
                <h2 class="stryke-section-heading">Phương thức thanh toán</h2>

                <?php if ($bankAccount): ?>
                    <!-- Card tài khoản ngân hàng & QR thu nhỏ -->
                    <div class="stryke-bank-card">
                        <!-- Thumbnail QR có thể click phóng to -->
                        <div class="stryke-bank-qr-thumb" data-open-qr title="Bấm vào để phóng to mã QR">
                            <img src="<?= e(url('/payment/qr') . '&booking_id=' . (int) $booking['ma_dat_san'] . '&ref=' . e($payment['ma_tham_chieu'] ?? '')) ?>" alt="VietQR Thumbnail">
                            <span class="stryke-qr-zoom-badge">🔍 Phóng to</span>
                        </div>

                        <!-- Thông tin tài khoản -->
                        <div class="stryke-bank-details">
                            <div class="stryke-bank-acc-name"><?= e($bankAccount['ten_chu_tai_khoan']) ?></div>
                            <div class="stryke-bank-acc-num">Số tài khoản: <span><?= e($bankAccount['so_tai_khoan']) ?></span></div>
                            <div class="stryke-bank-name"><?= e($bankAccount['ten_ngan_hang']) ?></div>
                        </div>

                        <!-- Nút Sao chép & Tải ảnh -->
                        <div class="stryke-bank-actions">
                            <button type="button" class="stryke-action-btn" data-copy-text="<?= e($bankAccount['so_tai_khoan']) ?>" title="Sao chép số tài khoản">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                            </button>
                            <a href="<?= e(url('/payment/qr') . '&booking_id=' . (int) $booking['ma_dat_san'] . '&ref=' . e($payment['ma_tham_chieu'] ?? '')) ?>" download="VietQR_DonHang_<?= e($booking['ma_dat_san']) ?>.png" target="_blank" class="stryke-action-btn" title="Tải ảnh mã QR">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                            </a>
                        </div>
                    </div>

                    <!-- Hộp cảnh báo chuyển khoản -->
                    <div class="stryke-alert-notice">
                        <span class="stryke-alert-icon">⚠️</span>
                        <div class="stryke-alert-text">
                            Vui lòng chuyển khoản <strong><?= e(number_format((float) ($payment['so_tien'] ?? $booking['tong_tien_tam_tinh']), 0, ',', '.')) ?> đ</strong> đúng nội dung. Sau khi chuyển khoản, gửi ảnh minh chứng để nhân viên kiểm tra tiền thực nhận.
                            <div class="stryke-alert-memo">
                                <span>Nội dung chuyển khoản: <strong class="stryke-memo-code"><?= e($payment['ma_tham_chieu'] ?? '') ?></strong></span>
                                <button type="button" class="stryke-btn-copy-memo" data-copy-text="<?= e($payment['ma_tham_chieu'] ?? '') ?>">Sao chép mã</button>
                            </div>
                        </div>
                    </div>

                    <!-- Khu vực tải ảnh chuyển khoản (Upload Proof) - TH1: Bắt buộc gửi ảnh để xác nhận -->
                    <form id="form-submit-receipt" action="<?= e(url('/booking/receipt')) ?>" method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= e($booking['ma_dat_san']) ?>">
                        <div class="stryke-upload-group">
                            <label class="stryke-upload-label" for="stryke-proof-input">
                                Hình ảnh biên lai thanh toán <span class="stryke-required">* (Bắt buộc gửi ảnh)</span>
                            </label>
                            <div class="stryke-upload-dropzone" data-upload-dropzone>
                                <input type="file" id="stryke-proof-input" name="bien_lai" accept="image/png,image/jpeg,image/jpg,image/webp" class="stryke-file-input" required>
                                
                                <!-- Trạng thái chưa chọn file -->
                                <div class="stryke-upload-placeholder" data-upload-placeholder>
                                    <div class="stryke-upload-icon-wrap">
                                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#008848" stroke-width="2">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                            <polyline points="21 15 16 10 5 21"></polyline>
                                            <line x1="19" y1="8" x2="19" y2="14" stroke="#008848" stroke-width="2.5"></line>
                                            <line x1="16" y1="11" x2="22" y2="11" stroke="#008848" stroke-width="2.5"></line>
                                        </svg>
                                    </div>
                                    <span class="stryke-upload-text">Tải ảnh biên lai chuyển khoản</span>
                                    <span class="stryke-upload-hint">PNG, JPG, WebP (Bắt buộc gửi ảnh để xác nhận)</span>
                                </div>

                                <!-- Trạng thái đã chọn ảnh (Xem trước preview) -->
                                <div class="stryke-upload-preview" data-upload-preview hidden>
                                    <img src="" alt="Ảnh xác nhận thanh toán" class="stryke-preview-img" data-preview-img>
                                    <div class="stryke-preview-info">
                                        <span class="stryke-preview-name" data-preview-name></span>
                                        <span class="stryke-preview-size" data-preview-size></span>
                                        <button type="button" class="stryke-btn-remove-file" data-remove-file>Xóa ảnh &amp; Chọn lại</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                <?php else: ?>
                    <div class="stryke-bank-missing" style="background:#fff;border:1px solid #e5e7eb;padding:24px;border-radius:12px;text-align:center;">
                        <h3 style="color:#d97706;margin:0 0 8px;">Chưa sẵn sàng thanh toán</h3>
                        <p style="color:#6b7280;margin:0;font-size:13px;" role="status"><?= e($paymentError ?? ($payment ? 'Cơ sở chưa cấu hình tài khoản ngân hàng hoạt động. Vui lòng liên hệ cơ sở.' : 'Chưa tạo được yêu cầu thanh toán. Bạn có thể thử lại khi booking còn hạn.')) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ================= CỘT PHẢI: THÔNG TIN LỊCH ĐẶT ================= -->
            <div class="stryke-col-right">
                <div class="stryke-order-summary-card">
                    <div class="stryke-summary-header" data-toggle-summary title="Bấm để ẩn/hiện chi tiết">
                        <h3>Thông tin lịch đặt</h3>
                        <span class="stryke-chevron">▲</span>
                    </div>

                    <div class="stryke-summary-content" data-summary-content>
                        <!-- Tên khách hàng -->
                        <div class="stryke-summary-row">
                            <div class="stryke-row-icon">👤</div>
                            <div class="stryke-row-text">
                                <span class="stryke-row-label">Tên</span>
                                <strong class="stryke-row-val"><?= e($customer['ho_ten'] ?? $user['ten_khach_hang'] ?? $user['ho_ten'] ?? $user['ten_dang_nhap'] ?? 'Khách hàng') ?></strong>
                            </div>
                        </div>

                        <!-- Số điện thoại -->
                        <div class="stryke-summary-row">
                            <div class="stryke-row-icon">📞</div>
                            <div class="stryke-row-text">
                                <span class="stryke-row-label">SĐT</span>
                                <strong class="stryke-row-val"><?= e($customer['so_dien_thoai'] ?? $user['so_dien_thoai_khach'] ?? $user['so_dien_thoai'] ?? '—') ?></strong>
                            </div>
                        </div>

                        <!-- Mã đơn -->
                        <div class="stryke-summary-row">
                            <div class="stryke-row-icon">🏆</div>
                            <div class="stryke-row-text">
                                <span class="stryke-row-label">Mã đơn</span>
                                <strong class="stryke-row-val">#<?= e($booking['ma_dat_san']) ?></strong>
                            </div>
                        </div>

                        <!-- Chi tiết đơn -->
                        <div class="stryke-summary-row">
                            <div class="stryke-row-icon">📅</div>
                            <div class="stryke-row-text">
                                <span class="stryke-row-label">Chi tiết đơn</span>
                                <?php foreach ($booking['lines'] as $line): ?>
                                    <strong class="stryke-row-val">
                                        <?= e((new DateTimeImmutable($line['ngay_choi']))->format('d/m/Y')) ?>
                                    </strong>
                                    <span class="stryke-row-sub">
                                        - <?= e($line['ten_san_snapshot']) ?>: <?= e(substr($line['gio_bat_dau'], 0, 5) . ' – ' . substr($line['gio_ket_thuc'], 0, 5)) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Tổng đơn -->
                        <div class="stryke-summary-row">
                            <div class="stryke-row-icon">📷</div>
                            <div class="stryke-row-text">
                                <span class="stryke-row-label">Tổng đơn</span>
                                <strong class="stryke-row-val"><?= e(Money::display($booking['tong_tien_tam_tinh'])) ?></strong>
                            </div>
                        </div>

                        <!-- Cần thanh toán -->
                        <div class="stryke-summary-row">
                            <div class="stryke-row-icon">💵</div>
                            <div class="stryke-row-text">
                                <span class="stryke-row-label">Cần thanh toán</span>
                                <strong class="stryke-row-val green"><?= e(Money::display($payment['so_tien'] ?? $booking['tong_tien_tam_tinh'])) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- KHỐI HIỂN THỊ KHI HẾT THỜI GIAN GIỮ SÂN (Ở CHÍNH GIỮA) - TH2 -->
    <?php if (!$isReconcileState): ?>
        <div class="stryke-expired-center-box"
             data-payment-expired
             <?= !$isExpiredOrCanceled ? 'hidden' : '' ?>>
            <div class="stryke-expired-icon-wrap">⏰</div>
            <h3 class="stryke-expired-title">Đã hết thời gian giữ sân</h3>
            <p class="stryke-expired-desc">
                Thời hạn giữ chỗ 6 phút cho đơn đặt sân #<?= e($booking['ma_dat_san']) ?> đã kết thúc.<br>
                Đơn hàng đã được tự động <strong>HỦY</strong> và khung giờ này đã được giải phóng để người khác có thể đặt.
            </p>
            <div class="stryke-expired-btns">
                <a href="<?= e(url('/')) ?>" class="stryke-btn-go-home">
                    Quay lại trang chủ
                </a>
                <a href="<?= e($bookingBackUrl ?? url('/courts')) ?>" class="stryke-btn-reselect">
                    Chọn lại khung giờ
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- THANH ĐIỀU HƯỚNG CỐ ĐỊNH Ở ĐÁY (STICKY BOTTOM BAR) -->
    <div class="stryke-bottom-bar"
         data-payment-pending
         <?= ($booking['trang_thai_dat_san'] !== 'CHO_THANH_TOAN' || !empty($receiptSubmitted) || $isReconcileState) ? 'hidden' : '' ?>>
        <div class="stryke-bottom-inner">
            <div class="stryke-bottom-total-row">
                <span class="stryke-bottom-label">Thanh toán: <span class="stryke-bottom-desc">Tiền sân</span></span>
                <div class="stryke-bottom-amount">
                    <span class="stryke-total-label">Tổng : </span>
                    <strong class="stryke-total-val"><?= e(Money::display($payment['so_tien'] ?? $booking['tong_tien_tam_tinh'])) ?></strong>
                </div>
            </div>
            <form id="booking-payment-retry" action="<?= e(url('/booking/payment')) ?>" method="post" hidden>
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($booking['ma_dat_san']) ?>">
            </form>
            <button type="submit" form="form-submit-receipt" class="stryke-btn-continue" data-submit-payment <?= $payment && $payment['trang_thai'] !== 'CHO_THANH_TOAN' ? 'disabled' : '' ?>>
                Xác nhận đã chuyển khoản &amp; Gửi ảnh biên lai ↗
            </button>
        </div>
    </div>

    <!-- MODAL PHÓNG TO MÃ VIETQR (KHI BẤM VÀO THUMBNAIL) -->
    <?php if ($bankAccount): ?>
        <div class="stryke-qr-modal" id="stryke-qr-modal" hidden>
            <div class="stryke-qr-modal-overlay" data-close-qr></div>
            <div class="stryke-qr-modal-dialog">
                <button type="button" class="stryke-modal-close" data-close-qr aria-label="Đóng">&times;</button>
                <h3>Mã VietQR thanh toán</h3>
                <p>Mở ứng dụng Mobile Banking bất kỳ để quét mã</p>
                <div class="stryke-modal-qr-frame">
                    <img src="<?= e(url('/payment/qr') . '&booking_id=' . (int) $booking['ma_dat_san'] . '&ref=' . e($payment['ma_tham_chieu'] ?? '')) ?>" alt="Mã VietQR đầy đủ">
                </div>
                <div class="stryke-modal-account-info">
                    <strong><?= e($bankAccount['ten_ngan_hang']) ?></strong> &bull; <span><?= e($bankAccount['so_tai_khoan']) ?></span>
                    <div class="stryke-modal-memo">Nội dung CK: <code><?= e($payment['ma_tham_chieu'] ?? '') ?></code></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- THÔNG BÁO XÁC NHẬN THÀNH CÔNG -->
    <div class="stryke-success-banner"
         data-payment-success
         <?= ($isReconcileState || ($booking['trang_thai_dat_san'] !== 'DA_XAC_NHAN' && $booking['trang_thai_dat_san'] !== 'HOAN_THANH')) ? 'hidden' : '' ?>>
        <div class="stryke-success-icon">✓</div>
        <h3>Thanh toán &amp; Xác nhận thành công!</h3>
        <p>Đơn đặt sân #<?= e($booking['ma_dat_san']) ?> đã được xác nhận. Chúc bạn có những trận đấu thể thao tuyệt vời!</p>
        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 14px;">
            <a href="<?= e(url('/invoices/show') . '&booking_id=' . (int) $booking['ma_dat_san']) ?>" class="stryke-btn-view-acc" style="background: #22c55e; color: #000; font-weight: 700;">
                🧾 Xem hóa đơn thanh toán ↗
            </a>
            <a href="<?= e(url('/account')) ?>" class="stryke-btn-view-acc">Xem chi tiết lịch đặt sân</a>
        </div>
    </div>

</section>
