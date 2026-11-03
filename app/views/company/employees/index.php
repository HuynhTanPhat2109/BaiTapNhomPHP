<?php $listPath = '/company/employees'; $bodyClass = 'page-catalog'; $employees = $employees ?? []; $formatDate = require dirname(__DIR__, 2) . '/admin/partials/format-date.php';
?>
<section class="container account-page catalog-page admin-list catalog-workspace workspace-page">
    <header class="account-page__intro catalog-heading module-heading"><div><p class="eyebrow">Công ty / Nhân viên</p><h1 class="account-h1">Quản lý nhân viên</h1><p>Danh sách nhân viên thuộc công ty của bạn.</p></div>
    <a class="btn btn--primary" href="<?= e(admin_context_url('/company/employees/create')) ?>">Thêm nhân viên</a></header>

    <?php require dirname(__DIR__, 2) . '/admin/partials/management-toolbar.php'; ?>
    <?php $tableLabel = 'Nhân viên'; $total ??= count($employees); $tableTotal = $total; require dirname(__DIR__, 2) . '/admin/partials/list-summary.php'; ?>
    <?php if (!$employees): ?><div class="workspace-empty" role="status"><h2>Không tìm thấy nhân viên phù hợp</h2><p>Các hồ sơ nhân viên trong phạm vi của bạn sẽ được hiển thị tại đây.</p></div>
    <?php else: ?>
    <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách nhân viên"><table class="catalog-table workspace-table">
        <colgroup><col class="workspace-col-name"><col><col><col><col class="workspace-col-status"><col class="workspace-col-actions"></colgroup>
        <thead><tr><th scope="col">Nhân viên</th><th scope="col">Cơ sở</th><th scope="col">Liên hệ</th><th scope="col">Ngày vào làm</th><th class="workspace-number" scope="col">Trạng thái</th><th class="workspace-number" scope="col">Thao tác</th></tr></thead>
        <tbody><?php foreach ($employees as $employee): ?>
            <?php $id = (string) ($employee['ma_nhan_vien'] ?? ''); $status = $employee['trang_thai'] ?? ''; ?>
            <tr>
                <th scope="row"><?= e($employee['ho_ten'] ?? '') ?><small class="workspace-secondary"><?= e($employee['chuc_vu'] ?? '') ?></small></th>
                <td data-label="Cơ sở"><?= ($employee['ma_co_so'] ?? null) === null ? 'Chưa gán' : e($employee['ma_co_so']) ?></td>
                <td data-label="Liên hệ"><?= e($employee['email'] ?? '') ?><small class="workspace-secondary"><?= e($employee['so_dien_thoai'] ?? '') ?></small></td>
                <td data-label="Ngày vào làm"><?= e($formatDate($employee['ngay_vao_lam'] ?? null)) ?></td>
                <td class="workspace-number" data-label="Trạng thái"><span class="catalog-status" data-status="<?= $status === 'DANG_LAM' ? 'HOAT_DONG' : 'NGUNG' ?>"><?= $status === 'DANG_LAM' ? 'Đang làm' : ($status === 'DA_NGHI' ? 'Đã nghỉ' : 'Không xác định') ?></span></td>
                <td data-label="Thao tác">
                    <?php ob_start(); ?>
                    <a class="list-action list-action--edit" href="<?= e(admin_context_url('/company/employees/edit') . '&id=' . rawurlencode($id)) ?>"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m16 3 5 5M3 21l5-1L21 7a2.1 2.1 0 0 0-5-3L3 17v4Z"/></svg>Sửa</a>
                    <?php if (in_array($status, ['DANG_LAM', 'DA_NGHI'], true)): ?>
                        <form method="post" action="<?= e(admin_context_url('/company/employees/status')) ?>" data-confirm="<?= e(($status === 'DANG_LAM' ? 'Cho nghỉ nhân viên ' : 'Kích hoạt lại nhân viên ') . ($employee['ho_ten'] ?? '') . '?') ?>">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($id) ?>"><input type="hidden" name="trang_thai" value="<?= $status === 'DANG_LAM' ? 'DA_NGHI' : 'DANG_LAM' ?>">
                            <button class="list-action list-action--<?= $status === 'DANG_LAM' ? 'stop' : 'activate' ?>" type="submit"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v9m-5-7a8 8 0 1 0 10 0"/></svg><?= $status === 'DANG_LAM' ? 'Cho nghỉ' : 'Kích hoạt lại' ?></button>
                        </form>
                    <?php endif; ?>
                    <?php $rowMenuContent = ob_get_clean(); $rowMenuId = 'company-employee-actions-' . $id; $rowMenuLabel = $employee['ho_ten'] ?? ''; $rowMenuHasForms = true; require dirname(__DIR__, 2) . '/admin/partials/row-menu.php'; ?>
                </td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
    <?php require dirname(__DIR__, 2) . '/admin/partials/pagination.php'; ?>
</section>
