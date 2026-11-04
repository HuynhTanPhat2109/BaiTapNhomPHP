<?php
$id = (int) $row['ma_dat_san'];
$values = (int) ($oldInput['id'] ?? 0) === $id ? $oldInput : [];
$labels = ['confirm' => 'Xác nhận thanh toán', 'cancel' => 'Từ chối / Hủy booking',
    'reconcile' => $actions['late'] ? 'Ghi nhận tiền chuyển muộn' : 'Đối soát giao dịch'];
?>
<dialog class="booking-action-dialog" id="booking-dialog-<?= $id ?>" aria-labelledby="booking-dialog-title-<?= $id ?>" data-booking-dialog>
    <header><div><p class="eyebrow">ĐƠN ĐẶT SÂN #<?= $id ?></p><h2 id="booking-dialog-title-<?= $id ?>">Thao tác booking</h2></div><button type="button" class="btn btn--outline" data-booking-close aria-label="Đóng">×</button></header>
    <dl class="booking-action-summary">
        <div><dt>Khách hàng</dt><dd><?= e($row['ten_khach_hang']) ?></dd></div>
        <div><dt>Cơ sở / Sân</dt><dd><?= e($row['ten_co_so']) ?> · <?= e($row['cac_san'] ?: '—') ?></dd></div>
        <div><dt>Khoảng lịch chơi</dt><dd><?= $row['ngay_choi_dau'] ? e(date('d/m/Y', strtotime($row['ngay_choi_dau']))) : '—' ?><?php if (!empty($row['ngay_choi_cuoi']) && $row['ngay_choi_cuoi'] !== $row['ngay_choi_dau']): ?>–<?= e(date('d/m/Y', strtotime($row['ngay_choi_cuoi']))) ?><?php endif; ?> · <?= e(substr($row['gio_bat_dau_som'] ?? '', 0, 5)) ?>–<?= e(substr($row['gio_ket_thuc_muon'] ?? '', 0, 5)) ?></dd></div>
        <div><dt>Cần thanh toán</dt><dd><?= e(App\Services\DashboardService::money((string) ($row['so_tien_thanh_toan'] ?? $row['tong_tien_tam_tinh']))) ?></dd></div>
        <div><dt>Mã chuyển khoản</dt><dd><?= e($row['ma_tham_chieu'] ?: 'Chưa tạo') ?></dd></div>
        <div><dt>Thanh toán / Thực nhận</dt><dd><?= e($paymentStatusLabels[$paymentSt] ?? $paymentSt) ?> · <?= e(App\Services\DashboardService::money((string) ($row['so_tien_thuc_nhan'] ?? '0'))) ?></dd></div>
    </dl>
    <?php foreach ($labels as $action => $label): if (!$actions[$action]) continue; ?>
    <form method="post" action="<?= e(url('/admin/bookings/' . $action)) ?>" data-booking-form="<?= e($action) ?>" data-confirm="<?= e($label) ?> cho booking #<?= $id ?>? Kiểm tra giao dịch thực tế trước khi tiếp tục." hidden>
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="from_list" value="1"><input type="hidden" name="booking_action" value="<?= e($action) ?>">
        <?php foreach ($filters as $key => $value): ?><input type="hidden" name="list[<?= e($key) ?>]" value="<?= e($value) ?>"><?php endforeach; ?>
        <h3><?= e($label) ?></h3>
        <?php if ($action === 'cancel'): ?>
        <p>Hủy đơn đang chờ và giải phóng sân. Thao tác này không thực hiện hoàn tiền.</p>
        <div class="form-group"><label for="cancel-reason-<?= $id ?>">Lý do từ chối / hủy *</label><textarea class="form-control" id="cancel-reason-<?= $id ?>" name="ly_do_huy" required maxlength="500" rows="3"><?= e($values['ly_do_huy'] ?? '') ?></textarea></div>
        <?php else: ?>
        <?php if ($action === 'reconcile'): ?><p><?= $actions['late'] ? 'Chỉ ghi nhận khoản tiền cần xử lý. Không xác nhận giữ sân hoặc tự động hoàn tiền.' : 'Ghi nhận giao dịch bất thường để đối soát; không xác nhận thanh toán hoặc xuất tồn.' ?></p><?php endif; ?>
        <div class="booking-action-fields">
            <div class="form-group"><label for="amount-<?= $action ?>-<?= $id ?>">Số tiền thực nhận (đ)<?= $action === 'confirm' || $actions['late'] ? ' *' : '' ?></label><input class="form-control" id="amount-<?= $action ?>-<?= $id ?>" name="so_tien_thuc_nhan" type="number" min="<?= $action === 'reconcile' && $actions['late'] ? '0.01' : '0' ?>" step="0.01" value="<?= e($values['so_tien_thuc_nhan'] ?? ($row['so_tien_thuc_nhan'] ?? '')) ?>" <?= $action === 'confirm' || $actions['late'] ? 'required' : '' ?>></div>
            <div class="form-group"><label for="txn-<?= $action ?>-<?= $id ?>">Mã giao dịch ngân hàng<?= $action === 'confirm' ? ' *' : '' ?></label><input class="form-control" id="txn-<?= $action ?>-<?= $id ?>" name="ma_giao_dich" value="<?= e($values['ma_giao_dich'] ?? $row['ma_giao_dich'] ?? '') ?>" maxlength="150" <?= $action === 'confirm' ? 'required' : '' ?>></div>
        </div>
        <?php if ($action === 'confirm'): ?><div class="form-group"><label for="paid-at-<?= $id ?>">Thời gian thanh toán</label><input class="form-control" id="paid-at-<?= $id ?>" name="ngay_thanh_toan" type="datetime-local" value="<?= e($values['ngay_thanh_toan'] ?? '') ?>"></div><?php endif; ?>
        <div class="form-group"><label for="note-<?= $action ?>-<?= $id ?>"><?= $action === 'reconcile' ? 'Lý do đối soát *' : 'Ghi chú xác minh' ?></label><textarea class="form-control" id="note-<?= $action ?>-<?= $id ?>" name="<?= $action === 'reconcile' ? 'ly_do_doi_soat' : 'ghi_chu' ?>" maxlength="500" rows="3" <?= $action === 'reconcile' ? 'required' : '' ?>><?= e($values[$action === 'reconcile' ? 'ly_do_doi_soat' : 'ghi_chu'] ?? '') ?></textarea></div>
        <?php endif; ?>
        <footer><button type="button" class="btn btn--outline" data-booking-close>Đóng</button><button class="btn <?= $action === 'cancel' ? 'btn--outline' : 'btn--primary' ?>" type="submit"><?= e($label) ?></button></footer>
    </form>
    <?php endforeach; ?>
</dialog>
