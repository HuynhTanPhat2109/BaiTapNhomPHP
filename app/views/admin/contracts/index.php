<?php $listPath = '/admin/contracts'; $bodyClass = 'page-catalog'; $contracts = $contracts ?? []; $formatDate = require dirname(__DIR__) . '/partials/format-date.php';
$formatMoney = require dirname(__DIR__) . '/partials/format-money.php';
?>
<section class="container account-page catalog-page admin-list catalog-workspace workspace-page">
    <header class="catalog-heading module-heading"><div>
        <p class="eyebrow">Quản trị tập đoàn / Hợp đồng nhượng quyền</p>
        <h1 class="account-h1">Quản lý hợp đồng nhượng quyền</h1>
        <p>Danh sách hợp đồng nhượng quyền trong hệ thống.</p>
    </div><a class="btn btn--primary" href="<?= e(admin_context_url('/admin/contracts/create')) ?>">Thêm hợp đồng</a></header>


    <?php require dirname(__DIR__) . '/partials/management-toolbar.php'; ?>
    <?php $tableLabel = 'Hợp đồng nhượng quyền'; $total ??= count($contracts); $tableTotal = $total; require dirname(__DIR__) . '/partials/list-summary.php'; ?>
    <?php if (!$contracts): ?><div class="workspace-empty" role="status"><h2>Không tìm thấy hợp đồng phù hợp</h2><p>Các hợp đồng đã tạo sẽ hiển thị tại đây cùng thời hạn và trạng thái.</p></div>
    <?php else: ?>
    <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách hợp đồng">
        <table class="catalog-table workspace-table">
            <colgroup><col><col><col><col><col class="workspace-col-status"><col class="workspace-col-actions"></colgroup>
            <thead><tr><th scope="col">Hợp đồng</th><th scope="col">Công ty</th><th scope="col">Thời hạn</th><th scope="col">Phí nhượng quyền</th><th class="workspace-number" scope="col">Trạng thái</th><th class="workspace-number" scope="col">Thao tác</th></tr></thead>
            <tbody><?php foreach ($contracts as $contract): ?>
                <?php $id = $contract['ma_hop_dong'] ?? ''; $status = $contract['trang_thai'] ?? ''; ?>
                <tr>
                    <th scope="row"><?= e($contract['so_hop_dong'] ?? '') ?><small class="workspace-secondary">Ký ngày <?= e($formatDate($contract['ngay_ky'] ?? null)) ?></small></th>
                    <td data-label="Công ty"><?= e($contract['ten_cong_ty'] ?? '') ?></td>
                    <td data-label="Thời hạn"><?= e($formatDate($contract['ngay_bat_dau'] ?? null)) ?><small class="workspace-secondary">Đến <?= e($formatDate($contract['ngay_ket_thuc'] ?? null)) ?></small></td>
                    <td data-label="Phí nhượng quyền"><strong><?= e($formatMoney($contract['phi_nhuong_quyen'] ?? null)) ?></strong><small class="workspace-secondary">Tỷ lệ phí: <?= e($contract['ty_le_phi'] ?? '') ?>%</small></td>
                    <td class="workspace-number" data-label="Trạng thái"><span class="catalog-status" data-status="<?= e($status) ?>"><?= e(['HIEU_LUC' => 'Hiệu lực', 'HET_HAN' => 'Hết hạn', 'CHAM_DUT' => 'Chấm dứt'][$status] ?? 'Không xác định') ?></span></td>
                    <td data-label="Thao tác">
                        <?php ob_start(); ?>
                        <form class="contract-actions" method="post" action="<?= e(admin_context_url('/admin/contracts/status') . '&id=' . rawurlencode((string) $id)) ?>" data-confirm="Xác nhận cập nhật trạng thái hợp đồng này?">
                            <?= csrf_field() ?>
                            <select class="contract-actions__select" id="trang_thai_<?= e((string) $id) ?>" name="trang_thai" aria-label="Trạng thái hợp đồng" required>
                                <option value="HIEU_LUC" <?= $status === 'HIEU_LUC' ? 'selected' : '' ?>>Hiệu lực</option>
                                <option value="HET_HAN" <?= $status === 'HET_HAN' ? 'selected' : '' ?>>Hết hạn</option>
                                <option value="CHAM_DUT" <?= $status === 'CHAM_DUT' ? 'selected' : '' ?>>Chấm dứt</option>
                            </select>
                            <div class="contract-actions__buttons">
                                <a class="list-action list-action--edit" href="<?= e(admin_context_url('/admin/contracts/edit') . '&id=' . rawurlencode((string) $id)) ?>"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m16 3 5 5M3 21l5-1L21 7a2.1 2.1 0 0 0-5-3L3 17v4Z"/></svg>Sửa</a>
                                <button class="list-action list-action--activate" type="submit"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>Cập nhật</button>
                            </div>
                        </form>
                        <?php $rowMenuContent = ob_get_clean(); $rowMenuId = 'contract-actions-' . $id; $rowMenuLabel = $contract['so_hop_dong'] ?? ''; $rowMenuHasForms = true; $rowMenuUsePopover = false; require dirname(__DIR__) . '/partials/row-menu.php'; ?>
                    </td>
                </tr>
            <?php endforeach; ?></tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php require dirname(__DIR__) . '/partials/pagination.php'; ?>
</section>
