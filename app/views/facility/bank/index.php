<?php
$bodyClass = 'page-catalog';
$facilityId = (int) $facility['ma_co_so'];
$canManage = in_array($role, ['QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO'], true);
?>
<section class="container catalog-page catalog-workspace admin-list workspace-page bank-page<?= $canManage ? '' : ' bank-page--readonly' ?>">
    <header class="catalog-heading module-heading"><div>
        <p class="eyebrow">STRYKE / <?= e($facility['ten_co_so']) ?></p><h1>Tài khoản ngân hàng nhận tiền</h1>
        <p class="module-subtitle">Quản lý tài khoản thụ hưởng và tài khoản nhận thanh toán mặc định của cơ sở.</p>
    </div><?php if ($canManage): ?><a class="btn btn--primary" href="<?= e(url('/facility/bank/create') . '&facility_id=' . $facilityId) ?>"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Thêm tài khoản</a><?php endif; ?></header>
    <?php if (!empty($notice)): ?><p class="alert alert--success" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert alert--danger" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?php if (!empty($facilities)): ?>
    <form class="bank-facility-picker" method="get" action="<?= e(base_path() . '/index.php') ?>">
        <input type="hidden" name="route" value="/facility/bank">
        <div class="form-group"><label for="bank-facility">Cơ sở nhận tiền</label><select class="form-control" id="bank-facility" name="facility_id">
            <?php foreach ($facilities as $option): ?><option value="<?= (int) $option['ma_co_so'] ?>"<?= (int) $option['ma_co_so'] === $facilityId ? ' selected' : '' ?>><?= e($option['ten_co_so']) ?></option><?php endforeach; ?>
        </select></div><button class="btn btn--outline" type="submit">Xem tài khoản</button>
    </form>
    <?php endif; ?>
    <?php if (!$canManage): ?><p class="bank-readonly-note">Nhân viên chỉ có quyền xem thông tin tài khoản ngân hàng của cơ sở.</p><?php endif; ?>
    <?php $tableLabel = 'Tài khoản ngân hàng'; $tableTotal = count($accounts); require dirname(__DIR__, 2) . '/admin/partials/list-summary.php'; ?>
    <?php if (!$accounts): ?><div class="workspace-empty" role="status"><h2>Chưa có tài khoản ngân hàng</h2><p><?= $canManage ? 'Cơ sở này chưa cấu hình tài khoản nhận tiền. Sử dụng nút Thêm tài khoản để bổ sung.' : 'Cơ sở này chưa cấu hình tài khoản nhận tiền. Vui lòng liên hệ quản lý cơ sở.' ?></p></div>
    <?php else: ?>
    <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách tài khoản ngân hàng"><table class="catalog-table workspace-table">
        <colgroup><col class="workspace-col-stt"><col class="bank-col-name"><col><col class="bank-col-default"><col class="bank-col-status"><?php if ($canManage): ?><col class="bank-col-actions"><?php endif; ?></colgroup>
        <thead><tr><th class="list-stt" scope="col">STT</th><th scope="col">Ngân hàng</th><th scope="col">Tài khoản thụ hưởng</th><th scope="col">Mặc định</th><th scope="col">Trạng thái</th><?php if ($canManage): ?><th scope="col">Thao tác</th><?php endif; ?></tr></thead>
        <tbody><?php foreach ($accounts as $index => $acc): ?><tr>
            <td class="list-stt" data-label="STT"><?= $index + 1 ?></td>
            <th scope="row"><?= e($acc['ten_ngan_hang']) ?></th>
            <td class="bank-beneficiary" data-label="Tài khoản thụ hưởng"><strong><?= e($acc['so_tai_khoan']) ?></strong><small class="workspace-secondary"><?= e($acc['ten_chu_tai_khoan']) ?></small></td>
            <td class="bank-default" data-label="Mặc định"><?php if ($acc['la_mac_dinh'] == 1): ?><span class="catalog-status" data-status="HOAT_DONG">Nhận tiền chính</span><?php else: ?><span class="workspace-secondary">Chưa đặt mặc định</span><?php endif; ?></td>
            <td data-label="Trạng thái"><span class="catalog-status" data-status="<?= e($acc['trang_thai']) ?>"><?= $acc['trang_thai'] === 'HOAT_DONG' ? 'Hoạt động' : 'Ngừng' ?></span></td>
            <?php if ($canManage): ?>
            <td data-label="Thao tác">
                <?php ob_start(); ?>
                <a class="list-action list-action--edit" href="<?= e(url('/facility/bank/edit') . '&id=' . $acc['ma_tk_ngan_hang']) ?>"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m16 3 5 5M3 21l5-1L21 7a2.1 2.1 0 0 0-5-3L3 17v4Z"/></svg>Sửa</a>
                <?php if ($acc['la_mac_dinh'] != 1 && $acc['trang_thai'] === 'HOAT_DONG'): ?>
                <form method="post" action="<?= e(url('/facility/bank/set-default')) ?>" data-confirm="Đặt tài khoản này làm mặc định nhận thanh toán?">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($acc['ma_tk_ngan_hang']) ?>"><input type="hidden" name="facility_id" value="<?= e($facilityId) ?>">
                    <button type="submit" class="list-action"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>Đặt mặc định</button>
                </form><?php endif; ?>
                <form method="post" action="<?= e(url('/facility/bank/toggle-status')) ?>" data-confirm="Xác nhận thay đổi trạng thái tài khoản ngân hàng này?">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($acc['ma_tk_ngan_hang']) ?>"><input type="hidden" name="facility_id" value="<?= e($facilityId) ?>">
                    <button type="submit" class="list-action list-action--<?= $acc['trang_thai'] === 'HOAT_DONG' ? 'stop' : 'activate' ?>"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v9m-5-7a8 8 0 1 0 10 0"/></svg><?= $acc['trang_thai'] === 'HOAT_DONG' ? 'Tạm ngừng' : 'Kích hoạt' ?></button>
                </form>
                <?php $rowMenuContent = ob_get_clean(); $rowMenuId = 'bank-actions-' . $acc['ma_tk_ngan_hang']; $rowMenuLabel = $acc['ten_ngan_hang']; $rowMenuHasForms = true; $rowMenuUsePopover = false; require dirname(__DIR__, 2) . '/admin/partials/row-menu.php'; ?>
            </td>
            <?php endif; ?>
        </tr><?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
</section>
