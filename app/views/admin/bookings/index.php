<?php
$bodyClass = 'page-catalog';
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
$roleRoot = match ($scope['role'] ?? '') {
    'QUAN_LY_CONG_TY' => ['/company', 'Công ty'],
    'QUAN_LY_CO_SO' => ['/facility', 'Cơ sở'],
    'NHAN_VIEN' => ['/staff', 'Cơ sở'],
    default => ['/admin', 'Quản trị'],
};
?>
<section class="container catalog-page catalog-workspace admin-list booking-admin-list">
    <nav class="catalog-nav" aria-label="Điều hướng quản lý booking">
        <a href="<?= e(url($roleRoot[0])) ?>"><?= e($roleRoot[1]) ?></a>
        <span aria-hidden="true">/</span>
        <a href="<?= e(url('/admin/bookings')) ?>" aria-current="page">Quản lý booking</a>
    </nav>

    <header class="catalog-heading">
        <div>
            <p class="eyebrow">STRYKE / VẬN HÀNH</p>
            <h1>Quản lý đơn đặt sân</h1>
        </div>
        <div>
            <span class="catalog-status" data-status="HOAT_DONG" style="font-size: 0.95rem; padding: 8px 14px;">
                Tổng cộng: <strong><?= number_format($total) ?></strong> đơn
            </span>
        </div>
    </header>

    <?php if (!empty($notice)): ?>
        <p class="alert alert--success" role="status" style="margin-bottom: 20px;"><?= e($notice) ?></p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p class="alert alert--danger" role="alert" style="margin-bottom: 20px;"><?= e($error) ?></p>
    <?php endif; ?>

    <!-- THANH CÔNG CỤ TÌM KIẾM VÀ BỘ LỌC ĐA TIÊU CHÍ -->
    <form class="list-toolbar" method="get" action="<?= e(base_path() . '/index.php') ?>" aria-label="Tìm kiếm và lọc booking" style="display: flex; flex-wrap: wrap; gap: 14px; background: var(--surface, #fff); padding: 18px; border: 1px solid var(--border, #e0e0e0); border-radius: 8px; margin-bottom: 24px;">
        <input type="hidden" name="route" value="/admin/bookings">

        <!-- 1. Tìm kiếm từ khóa -->
        <div class="form-group" style="flex: 1 1 220px; min-width: 180px;">
            <label for="filter-q" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 5px; color: var(--muted, #666);">Mã đơn / Mã chuyển khoản / Khách</label>
            <input class="form-control" id="filter-q" type="search" name="q" maxlength="100" placeholder="VD: 15, PAY2611..., Nguyễn..." value="<?= e($filters['q']) ?>">
        </div>

        <!-- 2. Loại ngày -->
        <div class="form-group" style="flex: 0 1 140px; min-width: 130px;">
            <label for="filter-date-type" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 5px; color: var(--muted, #666);">Loại ngày lọc</label>
            <select class="form-control" id="filter-date-type" name="date_type">
                <option value="ngay_choi" <?= ($filters['date_type'] ?? '') === 'ngay_choi' ? 'selected' : '' ?>>Ngày chơi</option>
                <option value="ngay_tao" <?= ($filters['date_type'] ?? '') === 'ngay_tao' ? 'selected' : '' ?>>Ngày đặt đơn</option>
            </select>
        </div>

        <!-- 3. Từ ngày -->
        <div class="form-group" style="flex: 0 1 140px; min-width: 130px;">
            <label for="filter-date-from" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 5px; color: var(--muted, #666);">Từ ngày</label>
            <input class="form-control" id="filter-date-from" type="date" name="date_from" value="<?= e($filters['date_from']) ?>">
        </div>

        <!-- 4. Đến ngày -->
        <div class="form-group" style="flex: 0 1 140px; min-width: 130px;">
            <label for="filter-date-to" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 5px; color: var(--muted, #666);">Đến ngày</label>
            <input class="form-control" id="filter-date-to" type="date" name="date_to" value="<?= e($filters['date_to']) ?>">
        </div>

        <!-- 5. Cơ sở (nếu được phép chọn nhiều cơ sở) -->
        <?php if ($scope['facility'] === null): ?>
            <div class="form-group" style="flex: 1 1 180px; min-width: 160px;">
                <label for="filter-facility" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 5px; color: var(--muted, #666);">Cơ sở thể thao</label>
                <select class="form-control" id="filter-facility" name="facility_id">
                    <option value="">Tất cả cơ sở</option>
                    <?php foreach ($facilities as $fac): ?>
                        <option value="<?= e($fac['ma_co_so']) ?>" <?= ((string) $filters['facility_id']) === (string) $fac['ma_co_so'] ? 'selected' : '' ?>>
                            <?= e($fac['ten_co_so']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <!-- 6. Sân cụ thể (nếu có danh sách sân) -->
        <?php if (!empty($courts)): ?>
            <div class="form-group" style="flex: 1 1 150px; min-width: 130px;">
                <label for="filter-court" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 5px; color: var(--muted, #666);">Sân cụ thể</label>
                <select class="form-control" id="filter-court" name="court_id">
                    <option value="">Tất cả sân</option>
                    <?php foreach ($courts as $c): ?>
                        <option value="<?= e($c['ma_san']) ?>" <?= ((string) $filters['court_id']) === (string) $c['ma_san'] ? 'selected' : '' ?>>
                            <?= e($c['ten_san']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <!-- 7. Trạng thái Booking -->
        <div class="form-group" style="flex: 0 1 150px; min-width: 130px;">
            <label for="filter-booking-status" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 5px; color: var(--muted, #666);">TT Booking</label>
            <select class="form-control" id="filter-booking-status" name="booking_status">
                <option value="">Tất cả trạng thái</option>
                <?php foreach ($bookingStatusLabels as $val => $lbl): ?>
                    <option value="<?= e($val) ?>" <?= $filters['booking_status'] === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- 8. Trạng thái Thanh toán -->
        <div class="form-group" style="flex: 0 1 150px; min-width: 130px;">
            <label for="filter-payment-status" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 5px; color: var(--muted, #666);">TT Thanh toán</label>
            <select class="form-control" id="filter-payment-status" name="payment_status">
                <option value="">Tất cả trạng thái</option>
                <?php foreach ($paymentStatusLabels as $val => $lbl): ?>
                    <option value="<?= e($val) ?>" <?= $filters['payment_status'] === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- 9. Số lượng hiển thị -->
        <div class="form-group" style="flex: 0 1 100px;">
            <label for="filter-per-page" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 5px; color: var(--muted, #666);">Hiển thị</label>
            <select class="form-control" id="filter-per-page" name="per_page">
                <?php foreach ([10, 20, 50] as $sz): ?>
                    <option value="<?= $sz ?>" <?= $per_page === $sz ? 'selected' : '' ?>><?= $sz ?> / trang</option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Nút hành động -->
        <div class="list-toolbar__actions" style="display: flex; align-items: flex-end; gap: 8px;">
            <button class="btn btn--primary" type="submit" style="padding: 9px 18px;">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" style="width: 16px; height: 16px;"><circle cx="10.5" cy="10.5" r="6.5" stroke="currentColor" stroke-width="2"/><path d="m16 16 5 5" stroke="currentColor" stroke-width="2"/></svg>
                <span>Tìm kiếm</span>
            </button>
            <a class="btn btn--outline" href="<?= e(url('/admin/bookings')) ?>" style="padding: 9px 14px;">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" style="width: 16px; height: 16px;"><path d="M4 4v6h6M4.5 10a8 8 0 1 1-.2 4" stroke="currentColor" stroke-width="2"/></svg>
                <span>Đặt lại</span>
            </a>
        </div>
    </form>

    <!-- BẢNG DANH SÁCH BOOKING -->
    <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách đơn đặt sân" style="background: var(--surface, #fff); border: 1px solid var(--border, #e0e0e0); border-radius: 8px; overflow-x: auto;">
        <table class="catalog-table" style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border, #e0e0e0); background: #f8faf9;">
                    <th class="list-stt" scope="col" style="padding: 12px 14px;">STT</th>
                    <th scope="col" style="padding: 12px 14px;">Mã đơn / Mã CK</th>
                    <th scope="col" style="padding: 12px 14px;">Khách hàng</th>
                    <th scope="col" style="padding: 12px 14px;">Cơ sở & Sân</th>
                    <th scope="col" style="padding: 12px 14px;">Lịch chơi</th>
                    <th scope="col" style="padding: 12px 14px;">Tổng tiền</th>
                    <th scope="col" style="padding: 12px 14px;">Trạng thái Booking</th>
                    <th scope="col" style="padding: 12px 14px;">Thanh toán</th>
                    <th scope="col" style="padding: 12px 14px; text-align: center;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $index => $row): ?>
                <?php
                $bookingSt = $row['trang_thai_dat_san'];
                $paymentSt = $row['trang_thai_thanh_toan'] ?? 'CHO_THANH_TOAN';
                $stt = ($page - 1) * $per_page + $index + 1;

                // Logic hiển thị thực tế: Nếu khách đã gửi ảnh biên lai và đơn chuyển sang CHO_XAC_NHAN,
                // thì trạng thái thanh toán hiển thị là CHO_XAC_NHAN (Chờ xác nhận), không để "Chờ thanh toán"
                $effectivePaymentSt = ($bookingSt === 'CHO_XAC_NHAN' && $paymentSt === 'CHO_THANH_TOAN')
                    ? 'CHO_XAC_NHAN'
                    : $paymentSt;

                // Màu trạng thái booking
                $bookingColor = match ($bookingSt) {
                    'DA_XAC_NHAN', 'HOAN_THANH' => 'background: #e6f4ea; color: #137333;',
                    'CHO_THANH_TOAN' => 'background: #f1f3f4; color: #5f6368;',
                    'CHO_XAC_NHAN' => 'background: #fef7e0; color: #b06000; border: 1px solid #f9ab00; font-weight: 600;',
                    'HET_HAN' => 'background: #f1f3f4; color: #5f6368;',
                    'DA_HUY' => 'background: #fce8e6; color: #c5221f;',
                    default => 'background: #edf0ee; color: #52635a;',
                };

                // Màu trạng thái thanh toán
                $paymentColor = match ($effectivePaymentSt) {
                    'THANH_CONG' => 'background: #e6f4ea; color: #137333;',
                    'CAN_DOI_SOAT' => 'background: #fce8e6; color: #c5221f; font-weight: bold; border: 1px solid #ea4335;',
                    'CHO_XAC_NHAN' => 'background: #fef7e0; color: #b06000; border: 1px solid #f9ab00; font-weight: 600;',
                    'CHO_THANH_TOAN' => 'background: #f1f3f4; color: #5f6368;',
                    default => 'background: #f1f3f4; color: #5f6368;',
                };
                ?>
                <tr style="border-bottom: 1px solid var(--border, #eee);">
                    <td class="list-stt" style="padding: 12px 14px;"><?= $stt ?></td>
                    <td style="padding: 12px 14px;">
                        <a href="<?= e(url('/admin/bookings/show') . '&id=' . $row['ma_dat_san']) ?>" style="font-weight: 700; color: #1a73e8; text-decoration: none;">
                            #<?= e($row['ma_dat_san']) ?>
                        </a>
                        <?php if (!empty($row['ma_tham_chieu'])): ?>
                            <div style="font-size: 0.8rem; color: #555; font-family: monospace; margin-top: 2px;">
                                <?= e($row['ma_tham_chieu']) ?>
                            </div>
                        <?php endif; ?>
                        <div style="font-size: 0.75rem; color: #888; margin-top: 2px;">
                            <?= e(date('d/m/Y H:i', strtotime($row['ngay_tao']))) ?>
                        </div>
                    </td>
                    <td style="padding: 12px 14px;">
                        <strong style="display: block; font-size: 0.95rem;"><?= e($row['ten_khach_hang']) ?></strong>
                        <span style="font-size: 0.85rem; color: #666;"><?= e($row['sdt_khach_hang']) ?></span>
                    </td>
                    <td style="padding: 12px 14px;">
                        <span style="display: block; font-weight: 600; color: #222;"><?= e($row['ten_co_so']) ?></span>
                        <small style="color: #666;"><?= e($row['cac_san'] ?: 'Chưa chọn sân') ?></small>
                    </td>
                    <td style="padding: 12px 14px;">
                        <?php if ($row['ngay_choi_dau']): ?>
                            <div style="font-weight: 600; color: #2d3748;">
                                <?= e(date('d/m/Y', strtotime($row['ngay_choi_dau']))) ?>
                            </div>
                            <small style="color: #555;">
                                <?= e(substr($row['gio_bat_dau_som'], 0, 5)) ?> – <?= e(substr($row['gio_ket_thuc_muon'], 0, 5)) ?>
                            </small>
                        <?php else: ?>
                            <span style="color: #999;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px 14px; font-weight: 700; color: #0f5132;">
                        <?= number_format((float) $row['tong_tien_tam_tinh'], 0, ',', '.') ?> đ
                    </td>
                    <td style="padding: 12px 14px;">
                        <span style="display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: 600; <?= $bookingColor ?>">
                            <?= e($bookingStatusLabels[$bookingSt] ?? $bookingSt) ?>
                        </span>
                        <?php if ($bookingSt === 'CHO_THANH_TOAN' && !empty($row['het_han_giu_san_luc'])): ?>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px 14px;">
                        <span style="display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: 600; <?= $paymentColor ?>">
                            <?= e($paymentStatusLabels[$effectivePaymentSt] ?? $effectivePaymentSt) ?>
                        </span>
                    </td>
                    <td style="padding: 12px 14px; text-align: center;">
                        <?php
                        $actions = App\Services\BookingManagementService::actions($row);
                        $bookingIcons = [
                            'view' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
                            'confirm' => '<circle cx="12" cy="12" r="9"/><path d="m7 12 3 3 7-7"/>',
                            'cancel' => '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/>',
                            'reconcile' => '<path d="M14 3H5v18h14V8l-5-5Zm0 0v5h5M8 12h8m-8 4h4"/>',
                        ];
                        ob_start(); ?>
                        <a class="list-action list-action--view" role="menuitem" href="<?= e(url('/admin/bookings/show?id=' . $row['ma_dat_san'])) ?>"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><?= $bookingIcons['view'] ?></svg><span>Xem chi tiết</span></a>
                        <?php if ($actions['view_reconciliation']): ?><a class="list-action list-action--reconcile" role="menuitem" href="<?= e(url('/admin/bookings/show?id=' . $row['ma_dat_san']) . '#booking-operations') ?>"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><?= $bookingIcons['reconcile'] ?></svg><span>Xem đối soát</span></a><?php endif; ?>
                        <?php foreach (['confirm' => 'Xác nhận thanh toán', 'cancel' => 'Từ chối / Hủy', 'reconcile' => $actions['late'] ? 'Ghi nhận tiền chuyển muộn' : 'Đối soát giao dịch'] as $action => $label): if (!$scope['can_confirm'] || !$actions[$action]) continue; ?>
                        <button class="list-action list-action--<?= e($action) ?>" role="menuitem" type="button" data-booking-action="<?= e($action) ?>" data-booking-target="booking-dialog-<?= (int) $row['ma_dat_san'] ?>"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><?= $bookingIcons[$action] ?></svg><span><?= e($label) ?></span></button>
                        <?php endforeach; ?>
                        <?php $rowMenuContent = ob_get_clean(); $rowMenuId = 'booking-menu-' . $row['ma_dat_san']; $rowMenuLabel = 'booking #' . $row['ma_dat_san']; $rowMenuHasForms = false; $rowMenuUsePopover = true; require dirname(__DIR__) . '/partials/row-menu.php'; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$items): ?>
                <tr>
                    <td colspan="9" style="padding: 30px; text-align: center; color: #666;">
                        Không tìm thấy đơn đặt sân nào phù hợp với bộ lọc hiện tại.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php require __DIR__ . '/receipt-viewer.php'; ?>
    <?php foreach ($items as $row):
        $actions = App\Services\BookingManagementService::actions($row);
        $paymentSt = $row['trang_thai_thanh_toan'] ?? '';
        if ($scope['can_confirm'] && ($actions['confirm'] || $actions['cancel'] || $actions['reconcile'])) require __DIR__ . '/action-dialog.php';
    endforeach; ?>
    <?php if (!empty($validationErrors)): ?><div class="alert alert--danger" role="alert"><ul><?php foreach ($validationErrors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul><button type="button" class="btn btn--outline" data-booking-action="<?= e($oldInput['booking_action'] ?? '') ?>" data-booking-target="booking-dialog-<?= (int) ($oldInput['id'] ?? 0) ?>">Mở lại thông tin vừa nhập</button></div><?php endif; ?>
    <script src="<?= e(base_path() . '/assets/js/booking-actions.js?v=' . filemtime(dirname(__DIR__, 4) . '/public/assets/js/booking-actions.js')) ?>" defer></script>
    <!-- PHÂN TRANG -->
    <?php if ($pages > 1): ?>
        <nav class="catalog-pagination" aria-label="Phân trang đơn đặt sân" style="margin-top: 24px; display: flex; justify-content: center; gap: 8px;">
            <?php for ($p = 1; $p <= $pages; $p++): ?>
                <?php
                $pageParams = array_merge($_GET, ['page' => $p, 'route' => '/admin/bookings']);
                $pageUrl = base_path() . '/index.php?' . http_build_query($pageParams);
                ?>
                <a class="btn <?= $p === $page ? 'btn--primary' : 'btn--outline' ?>" href="<?= e($pageUrl) ?>" style="padding: 6px 12px;">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</section>
