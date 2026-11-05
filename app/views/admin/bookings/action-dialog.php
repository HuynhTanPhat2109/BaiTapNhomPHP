<?php
$id = (int) $row['ma_dat_san'];
$values = (int) ($oldInput['id'] ?? 0) === $id ? $oldInput : [];
$labels = ['confirm' => 'Xác nhận thanh toán', 'cancel' => 'Từ chối / Hủy booking',
    'reconcile' => $actions['late'] ? 'Ghi nhận tiền chuyển muộn' : 'Đối soát giao dịch'];
$money = static fn($value) => App\Services\DashboardService::money((string) $value);
$date = static fn($value) => $value ? date('d/m/Y H:i', strtotime($value)) : 'Chưa ghi nhận';
$expected = (string) ($row['so_tien_thanh_toan'] ?? $row['tong_tien_tam_tinh']);
$receipt = $receiptDetails[$id];
?>
<dialog class="booking-action-dialog" id="booking-dialog-<?= $id ?>" aria-labelledby="booking-dialog-title-<?= $id ?>" data-booking-dialog>
    <header><div><p class="eyebrow">ĐƠN ĐẶT SÂN #<?= $id ?></p><h2 id="booking-dialog-title-<?= $id ?>">Thao tác booking</h2></div><button type="button" class="btn btn--outline" data-booking-close aria-label="Đóng">×</button></header>
    <div class="booking-action-overview">
    <section class="booking-action-section"><h3>Thông tin đơn đặt sân</h3><dl class="booking-action-summary">
        <div><dt>Khách hàng</dt><dd><?= e($row['ten_khach_hang']) ?></dd></div>
        <div><dt>Cơ sở / Sân</dt><dd><?= e($row['ten_co_so']) ?> · <?= e($row['cac_san'] ?: '—') ?></dd></div>
        <div><dt>Khoảng lịch chơi</dt><dd><?= $row['ngay_choi_dau'] ? e(date('d/m/Y', strtotime($row['ngay_choi_dau']))) : '—' ?><?php if (!empty($row['ngay_choi_cuoi']) && $row['ngay_choi_cuoi'] !== $row['ngay_choi_dau']): ?>–<?= e(date('d/m/Y', strtotime($row['ngay_choi_cuoi']))) ?><?php endif; ?> · <?= e(substr($row['gio_bat_dau_som'] ?? '', 0, 5)) ?>–<?= e(substr($row['gio_ket_thuc_muon'] ?? '', 0, 5)) ?></dd></div>
        <div><dt>Tạo booking lúc</dt><dd><?= e($date($row['ngay_tao'])) ?></dd></div>
        <?php if ($row['trang_thai_dat_san'] === 'CHO_THANH_TOAN'): ?>
        <div><dt>Hạn giữ sân</dt><dd><?= e($date($row['het_han_giu_san_luc'])) ?></dd></div>
        <?php elseif ($row['trang_thai_dat_san'] === 'CHO_XAC_NHAN'): ?>
        <div><dt>Trạng thái giữ chỗ</dt><dd>Đang giữ lịch – chờ xác nhận thanh toán</dd></div>
        <?php endif; ?>
        <div><dt>Trạng thái booking</dt><dd><?= e($bookingStatusLabels[$row['trang_thai_dat_san']] ?? $row['trang_thai_dat_san']) ?></dd></div>
        <div><dt>Số tiền cần thanh toán</dt><dd class="booking-action-amount"><?= e($money($expected)) ?></dd></div>
        <div><dt>Mã tham chiếu chuyển khoản</dt><dd><?= e($row['ma_tham_chieu'] ?: 'Chưa tạo') ?></dd></div>
    </dl></section>
    <section class="booking-action-section booking-receipt-card"><h3>Ảnh minh chứng chuyển khoản</h3>
        <?php if ($receipt['image']): ?>
        <a href="<?= e(url('/booking/receipt?id=' . $id)) ?>" data-receipt-open target="_blank" rel="noopener"><img src="<?= e(url('/booking/receipt?id=' . $id)) ?>" alt="Minh chứng chuyển khoản của booking #<?= $id ?>" loading="lazy"></a>
        <a class="btn btn--outline" href="<?= e(url('/booking/receipt?id=' . $id)) ?>" data-receipt-open target="_blank" rel="noopener">Xem ảnh lớn</a>
        <p>Gửi lúc: <?= !empty($receipt['image']['sent_at']) ? e($date($receipt['image']['sent_at'])) : 'Chưa ghi nhận thời gian gửi của ảnh cũ' ?></p>
        <p class="catalog-status">Đang chờ nhân viên kiểm tra</p>
        <?php else: ?><p class="booking-receipt-empty">Khách hàng chưa gửi minh chứng chuyển khoản.</p><?php endif; ?>
        <p class="booking-action-note">Ảnh do khách cung cấp. Người xác nhận phải kiểm tra tài khoản nhận tiền và đối chiếu đủ số tiền thực nhận; ảnh không chứng minh ngân hàng đã nhận tiền.</p>
    </section>
    </div>
    <?php if ($values && !empty($validationErrors)): ?><div class="alert alert--danger" role="alert"><ul><?php foreach ($validationErrors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php foreach ($labels as $action => $label): if (!$actions[$action]) continue; ?>
    <form method="post" action="<?= e(url('/admin/bookings/' . $action)) ?>" data-booking-form="<?= e($action) ?>" data-booking-label="<?= e($label) ?>"<?= $action === 'confirm' ? ' data-receipt-payment data-payment-ready="' . ($receipt['can_confirm'] ? '1' : '0') . '"' : '' ?> data-confirm="<?= $action === 'confirm' ? 'Bạn xác nhận đã kiểm tra tài khoản nhận tiền và nhận đủ số tiền của booking này?' : e($label) . ' cho booking #' . $id . '?' ?>" data-confirm-cancel-label="Quay lại" hidden>
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="from_list" value="<?= ($fromList ?? true) ? '1' : '0' ?>"><input type="hidden" name="booking_action" value="<?= e($action) ?>">
        <?php foreach ($filters as $key => $value): ?><input type="hidden" name="list[<?= e($key) ?>]" value="<?= e($value) ?>"><?php endforeach; ?>
        <?php if ($action === 'confirm'): ?>
        <input type="hidden" name="confirmation_mode" value="receipt">
        <input type="hidden" name="receipt_version" value="<?= e($receipt['image']['version'] ?? '') ?>">
        <p class="booking-action-note" role="status"><?= !$receipt['can_confirm'] ? e($receipt['reason'] ?? 'Chưa đủ điều kiện xác nhận.') : 'Chỉ xác nhận sau khi kiểm tra tài khoản nhận tiền và nhận đủ số tiền của booking.' ?></p>
        <?php elseif ($action === 'cancel'): ?>
        <h3><?= e($label) ?></h3>
        <p>Hủy đơn đang chờ và giải phóng sân. Thao tác này không thực hiện hoàn tiền.</p>
        <div class="form-group"><label for="cancel-reason-<?= $id ?>">Lý do từ chối / hủy *</label><textarea class="form-control" id="cancel-reason-<?= $id ?>" name="ly_do_huy" required maxlength="500" rows="3"><?= e($values['ly_do_huy'] ?? '') ?></textarea></div>
        <?php else: ?>
        <?php if ($action === 'reconcile'): ?><p><?= $actions['late'] ? 'Chỉ ghi nhận khoản tiền cần xử lý. Không xác nhận giữ sân hoặc tự động hoàn tiền.' : 'Ghi nhận giao dịch bất thường để đối soát; không xác nhận thanh toán hoặc xuất tồn.' ?></p><?php endif; ?>
        <div class="booking-action-fields">
            <div class="form-group"><label for="amount-<?= $action ?>-<?= $id ?>">Số tiền thực nhận (đ)<?= $actions['late'] ? ' *' : '' ?></label><input class="form-control" id="amount-<?= $action ?>-<?= $id ?>" name="so_tien_thuc_nhan" type="number" min="<?= $actions['late'] ? '0.01' : '0' ?>" step="0.01" value="<?= e($values['so_tien_thuc_nhan'] ?? ($row['so_tien_thuc_nhan'] ?? '')) ?>" <?= $actions['late'] ? 'required' : '' ?>></div>
            <div class="form-group"><label for="txn-<?= $action ?>-<?= $id ?>">Mã giao dịch ngân hàng</label><input class="form-control" id="txn-<?= $action ?>-<?= $id ?>" name="ma_giao_dich" value="<?= e($values['ma_giao_dich'] ?? $row['ma_giao_dich'] ?? '') ?>" maxlength="150"></div>
        </div>
        <div class="form-group"><label for="note-<?= $action ?>-<?= $id ?>">Lý do đối soát *</label><textarea class="form-control" id="note-<?= $action ?>-<?= $id ?>" name="ly_do_doi_soat" maxlength="500" rows="3" required><?= e($values['ly_do_doi_soat'] ?? '') ?></textarea></div>
        <?php endif; ?>
        <footer><button type="button" class="btn btn--outline" data-booking-close>Đóng</button><?php if ($action === 'confirm'): ?><a class="btn btn--outline" href="<?= e(url('/admin/bookings/show?id=' . $id) . '#booking-operations') ?>">Xem đối soát</a><?php endif; ?><button class="btn <?= $action === 'cancel' ? 'btn--outline' : 'btn--primary' ?>" type="submit"<?= $action === 'confirm' && !$receipt['can_confirm'] ? ' disabled' : '' ?>><?= e($label) ?></button></footer>
    </form>
    <?php endforeach; ?>
</dialog>
