
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
<script src="<?= e(base_path().'/assets/js/booking.js') ?>" defer></script>

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

    <div class="booking-hold-status">
        <div class="booking-hold-copy">
            <p data-wait-message role="status">
                <?= e($states[$booking['trang_thai_dat_san']]
                    ?? 'Trạng thái booking đã thay đổi') ?>
            </p>

            <span>Thời gian giữ sân còn lại</span>
        </div>

        <div class="booking-countdown-wrap">
            <strong class="booking-countdown"
                    data-countdown
                    aria-label="Thời gian giữ sân còn lại">
                —
            </strong>
            <small>phút : giây</small>
        </div>
    </div>

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

    <section class="booking-section booking-wait-details">
        <h2>Thông tin lịch đặt</h2>

        <div class="booking-wait-facility">
            <h3><?= e($booking['facility']['ten_co_so'] ?? '') ?></h3>
            <p class="booking-subtle">
                <?= e($booking['facility']['dia_chi'] ?? '') ?>
            </p>
        </div>

        <div class="booking-lines">
            <?php foreach ($booking['lines'] as $line): ?>
                <article class="booking-line">
                    <div class="booking-line-main">
                        <h3><?= e($line['ten_san_snapshot']) ?></h3>
                        <span class="booking-sport">
                            <?= e($line['ten_bo_mon']) ?>
                        </span>

                        <p class="booking-line-time">
                            <?= e(substr($line['gio_bat_dau'], 0, 5)
                                . ' – ' .
                                substr($line['gio_ket_thuc'], 0, 5)) ?>
                        </p>
                    </div>

                    <time datetime="<?= e($line['ngay_choi']) ?>">
                        <?= e((new DateTimeImmutable($line['ngay_choi']))->format('d/m/Y')) ?>
                    </time>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="booking-wait-total">
        <span>Tổng thanh toán</span>
        <strong class="booking-total">
            <?= e(Money::display($booking['tong_tien_tam_tinh'])) ?>
        </strong>
    </div>

    <div class="booking-payment-placeholder"
         data-payment-pending
         <?= $booking['trang_thai_dat_san'] !== 'CHO_THANH_TOAN'
            ? 'hidden'
            : '' ?>>
        <p>Thanh toán trực tuyến đang được tích hợp.</p>
        <span>Thông tin đặt sân được tạm giữ trong thời gian hiển thị phía trên.</span>
    </div>

    <p class="booking-wait-note">
        Nếu muốn chọn lại lịch, sử dụng mũi tên quay lại ở đầu trang.
        Hệ thống sẽ hủy giữ sân hiện tại.
    </p>
</section>
