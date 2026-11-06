<?php
$bodyClass = 'page-catalog';
$listPath = '/admin/customers/show';
$listLabel = 'lịch sử đặt sân của khách hàng';
$formatDate = require dirname(__DIR__) . '/partials/format-date.php';
$dateTime = static fn($value) => $value ? date('d/m/Y H:i', strtotime($value)) : '—';
$statuses = ['CHO_THANH_TOAN' => 'Chờ thanh toán', 'CHO_XAC_NHAN' => 'Chờ xác nhận',
    'DA_XAC_NHAN' => 'Đã xác nhận', 'HOAN_THANH' => 'Hoàn thành', 'DA_HUY' => 'Đã hủy', 'HET_HAN' => 'Hết hạn'];
?>
<section class="container catalog-page catalog-workspace detail-page workspace-page">
    <header class="detail-heading">
        <a class="btn btn--outline detail-back admin-back-link" href="<?= e(url('/admin/customers')) ?>">Quay lại danh sách</a>
        <div class="detail-title"><h1>Khách hàng #<?= e($customer['ma_khach_hang']) ?></h1></div>
    </header>
    <div class="detail-grid">
        <section class="detail-card" aria-labelledby="customer-information">
            <h2 id="customer-information">Thông tin khách hàng</h2>
            <dl class="detail-fields">
                <div><dt>Mã khách hàng</dt><dd>#<?= e($customer['ma_khach_hang']) ?></dd></div>
                <div><dt>Họ và tên</dt><dd><?= e($customer['ho_ten']) ?></dd></div>
                <div><dt>Số điện thoại</dt><dd><?= e($customer['so_dien_thoai']) ?></dd></div>
                <div><dt>Email</dt><dd><?= e($customer['email'] ?: 'Chưa cập nhật') ?></dd></div>
            </dl>
        </section>
        <section class="detail-card" aria-labelledby="customer-statistics">
            <h2 id="customer-statistics">Thống kê trong phạm vi quản lý</h2>
            <dl class="detail-fields">
                <div><dt>Tổng lượt đặt sân</dt><dd><?= e($customer['booking_count']) ?></dd></div>
                <div><dt>Đã xác nhận</dt><dd><?= e($customer['confirmed_count']) ?></dd></div>
                <div><dt>Chờ xác nhận</dt><dd><?= e($customer['awaiting_count']) ?></dd></div>
                <div><dt>Lần đặt sân gần nhất</dt><dd><?= e($dateTime($customer['last_booking_at'])) ?></dd></div>
            </dl>
        </section>
        <section class="detail-card detail-card--wide" aria-labelledby="customer-bookings">
            <h2 id="customer-bookings">Lịch sử đặt sân</h2>
            <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Lịch sử đặt sân của khách hàng">
                <table class="catalog-table workspace-table">
                    <thead><tr><th scope="col">Mã booking</th><th scope="col">Cơ sở</th><th scope="col">Sân</th><th scope="col">Ngày chơi</th><th scope="col">Khung giờ</th><th scope="col">Tổng tiền</th><th scope="col">Trạng thái</th></tr></thead>
                    <tbody><?php foreach ($items as $booking): ?>
                        <tr>
                            <th scope="row">#<?= e($booking['ma_dat_san']) ?></th>
                            <td data-label="Cơ sở"><?= e($booking['ten_co_so']) ?></td>
                            <td data-label="Sân"><?php foreach ($booking['lines'] as $line): ?><div><?= e($line['ten_san_snapshot']) ?></div><?php endforeach; ?><?= !$booking['lines'] ? 'Chưa có chi tiết' : '' ?></td>
                            <td data-label="Ngày chơi"><?php foreach ($booking['lines'] as $line): ?><div><?= e($formatDate($line['ngay_choi'])) ?></div><?php endforeach; ?><?= !$booking['lines'] ? '—' : '' ?></td>
                            <td data-label="Khung giờ"><?php foreach ($booking['lines'] as $line): ?><div><?= e(substr($line['gio_bat_dau'], 0, 5) . '–' . substr($line['gio_ket_thuc'], 0, 5)) ?></div><?php endforeach; ?><?= !$booking['lines'] ? '—' : '' ?></td>
                            <td class="workspace-number" data-label="Tổng tiền"><?= e(App\Services\DashboardService::money((string) $booking['tong_tien_tam_tinh'])) ?></td>
                            <td data-label="Trạng thái"><span class="catalog-status" data-status="<?= e($booking['trang_thai_dat_san']) ?>"><?= e($statuses[$booking['trang_thai_dat_san']] ?? 'Không xác định') ?></span></td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table>
            </div>
            <?php require dirname(__DIR__) . '/partials/pagination.php'; ?>
        </section>
    </div>
</section>
