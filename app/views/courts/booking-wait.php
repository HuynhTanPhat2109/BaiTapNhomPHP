
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

<link rel="stylesheet" href="<?= e(base_path().'/assets/css/booking.css') ?>">
<link rel="stylesheet" href="<?= e(base_path().'/assets/css/vietqr.css') ?>">
<script src="<?= e(base_path().'/assets/js/booking.js') ?>" defer></script>
<script src="<?= e(base_path().'/assets/js/vietqr.js') ?>" defer></script>

<section class="booking-page booking-wait"
         data-booking-navigation
         data-back-url="<?= e($bookingBackUrl) ?>"
         data-booking-wait
         data-now="<?= e($now->format(DATE_ATOM)) ?>"
         data-expires="<?= e($booking['het_han_giu_san_luc']) ?>"
         data-state="<?= e($booking['trang_thai_dat_san']) ?>"
         data-status-url="<?= e(url('/booking/wait') . '&id=' . $booking['ma_dat_san'] . '&status=1') ?>">

    <header class="booking-heading">
        <div>
            <p class="booking-eyebrow">THANH TOÁN</p>
            <h1>Chờ thanh toán</h1>
            <p class="booking-subtle">
                Mã đặt sân #<?= e($booking['ma_dat_san']) ?>
            </p>
        </div>
    </header>

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
    <div class="stryke-countdown-banner">
        <p class="stryke-countdown-label" data-wait-message role="status">
            <?= ($booking['trang_thai_dat_san'] === 'CHO_THANH_TOAN')
                ? 'Đơn của bạn còn được giữ chỗ trong'
                : e($states[$booking['trang_thai_dat_san']] ?? 'Trạng thái booking đã thay đổi') ?>
        </p>
        <div class="stryke-countdown-time" data-countdown aria-label="Thời gian giữ sân còn lại">
            —
        </div>
    </div>

    <!-- KHUNG LƯỚI NỘI DUNG 2 CỘT (DESKTOP & MOBILE RESPONSIVE) -->
    <div class="stryke-checkout-container"
         data-payment-pending
         <?= $booking['trang_thai_dat_san'] !== 'CHO_THANH_TOAN' ? 'hidden' : '' ?>>

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
                            Vui lòng chuyển khoản <strong><?= e(number_format((float) ($payment['so_tien'] ?? $booking['tong_tien_tam_tinh']), 0, ',', '.')) ?> đ</strong> đúng nội dung. Chức năng xác minh giao dịch đang được tích hợp.
                            <div class="stryke-alert-memo">
                                <span>Nội dung chuyển khoản: <strong class="stryke-memo-code"><?= e($payment['ma_tham_chieu'] ?? '') ?></strong></span>
                                <button type="button" class="stryke-btn-copy-memo" data-copy-text="<?= e($payment['ma_tham_chieu'] ?? '') ?>">Sao chép mã</button>
                            </div>
                        </div>
                    </div>

                    <!-- Khu vực tải ảnh chuyển khoản (Upload Proof) -->
                    <div class="stryke-upload-group">
                        <label class="stryke-upload-label" for="stryke-proof-input">
                            Hình ảnh thanh toán <span class="stryke-required">*</span>
                        </label>
                        <div class="stryke-upload-dropzone" data-upload-dropzone>
                            <input type="file" id="stryke-proof-input" accept="image/png,image/jpeg,image/jpg" class="stryke-file-input">
                            
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
                                <span class="stryke-upload-text">Tải ảnh chuyển khoản</span>
                                <span class="stryke-upload-hint">PNG, JPG (tối đa 5MB)</span>
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

    <!-- KHỐI HIỂN THỊ KHI HẾT THỜI GIAN GIỮ SÂN (Ở CHÍNH GIỮA) -->
    <div class="stryke-expired-center-box"
         data-payment-expired
         <?= ($booking['trang_thai_dat_san'] !== 'HET_HAN' && $booking['trang_thai_dat_san'] !== 'DA_HUY') ? 'hidden' : '' ?>>
        <div class="stryke-expired-icon-wrap">⏰</div>
        <h3 class="stryke-expired-title">Đã hết thời gian giữ sân</h3>
        <p class="stryke-expired-desc">
            Thời hạn giữ chỗ 6 phút cho đơn đặt sân #<?= e($booking['ma_dat_san']) ?> đã kết thúc.<br>
            Khung giờ này đã được giải phóng để người khác có thể đặt.
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

    <!-- THANH ĐIỀU HƯỚNG CỐ ĐỊNH Ở ĐÁY (STICKY BOTTOM BAR) -->
    <div class="stryke-bottom-bar"
         data-payment-pending
         <?= $booking['trang_thai_dat_san'] !== 'CHO_THANH_TOAN' ? 'hidden' : '' ?>>
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
            <button type="<?= !$payment || $paymentError ? 'submit' : 'button' ?>" form="booking-payment-retry" class="stryke-btn-continue" <?= !$payment || $paymentError ? '' : 'data-submit-payment' ?> <?= $payment && $payment['trang_thai'] !== 'CHO_THANH_TOAN' ? 'disabled' : '' ?>>
                <?= !$payment || $paymentError ? 'Thử lại yêu cầu thanh toán' : 'Tiếp tục thanh toán' ?>
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
         <?= ($booking['trang_thai_dat_san'] !== 'DA_XAC_NHAN' && $booking['trang_thai_dat_san'] !== 'HOAN_THANH') ? 'hidden' : '' ?>>
        <div class="stryke-success-icon">✓</div>
        <h3>Thanh toán &amp; Xác nhận thành công!</h3>
        <p>Đơn đặt sân #<?= e($booking['ma_dat_san']) ?> đã được xác nhận. Chúc bạn có những trận đấu thể thao tuyệt vời!</p>
        <a href="<?= e(url('/account')) ?>" class="stryke-btn-view-acc">Xem chi tiết lịch đặt sân</a>
    </div>

</section>
