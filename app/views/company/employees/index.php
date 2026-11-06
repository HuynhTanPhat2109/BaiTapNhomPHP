<?php $basePath ??= '/company'; $isFacility = $basePath === '/facility'; $listPath = $basePath . '/employees'; $bodyClass = 'page-catalog'; $employees = $employees ?? []; $formatDate = require dirname(__DIR__, 2) . '/admin/partials/format-date.php';
?>
<section class="container account-page catalog-page admin-list catalog-workspace workspace-page">
    <header class="account-page__intro catalog-heading module-heading"><div><p class="eyebrow"><?= $isFacility ? 'Cơ sở' : 'Công ty' ?> / Nhân viên</p><h1 class="account-h1">Quản lý nhân viên</h1><p>Danh sách nhân viên thuộc <?= $isFacility ? 'cơ sở được phân công' : 'công ty của bạn' ?>.</p></div>
    <a class="btn btn--primary" href="<?= e(admin_context_url($basePath . '/employees/create')) ?>">Thêm nhân viên</a></header>

    <?php require dirname(__DIR__, 2) . '/admin/partials/management-toolbar.php'; ?>
    <?php $tableLabel = 'Nhân viên'; $total ??= count($employees); $tableTotal = $total; require dirname(__DIR__, 2) . '/admin/partials/list-summary.php'; ?>
    <?php if (!$employees): ?><div class="workspace-empty" role="status"><h2>Không tìm thấy nhân viên phù hợp</h2><p>Các hồ sơ nhân viên trong phạm vi của bạn sẽ được hiển thị tại đây.</p></div>
    <?php else: ?>
    <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách nhân viên"><table class="catalog-table workspace-table">
        <colgroup><col class="workspace-col-name"><col><col><col><col><col class="workspace-col-status"><col class="workspace-col-actions"></colgroup>
        <thead><tr><th scope="col">Nhân viên</th><th scope="col">Công ty</th><th scope="col">Cơ sở</th><th scope="col">Liên hệ</th><th scope="col">Ngày vào làm</th><th class="workspace-number" scope="col">Trạng thái</th><th class="workspace-number" scope="col">Thao tác</th></tr></thead>
        <tbody><?php foreach ($employees as $employee): ?>
            <?php $id = (string) ($employee['ma_nhan_vien'] ?? ''); $status = $employee['trang_thai'] ?? '';
            $canManage = App\Services\EmployeeService::canManage($actorRole ?? ($isFacility ? 'QUAN_LY_CO_SO' : 'QUAN_LY_CONG_TY'), $employee['ten_vai_tro'] ?? null); ?>
            <tr>
                <th scope="row"><a class="admin-table-link" href="<?= e(admin_context_url($basePath . '/employees/show') . '&id=' . rawurlencode($id)) ?>"><?= e($employee['ho_ten'] ?? '') ?></a><small class="workspace-secondary"><?= e($employee['ten_dang_nhap'] ?? 'Chưa cấp tài khoản') ?></small><small class="workspace-secondary"><?= e($employee['chuc_vu'] ?? '') ?></small></th>
                <td data-label="Công ty"><?= e($employee['ten_cong_ty'] ?? 'Không còn thông tin công ty') ?></td>
                <td data-label="Cơ sở"><?= e(($employee['ma_co_so'] ?? null) === null ? 'Chưa phân công' : ($employee['ten_co_so'] ?? 'Không còn thông tin cơ sở')) ?></td>
                <td data-label="Liên hệ"><?= e($employee['email'] ?? '') ?><small class="workspace-secondary"><?= e($employee['so_dien_thoai'] ?? '') ?></small></td>
                <td data-label="Ngày vào làm"><?= e($formatDate($employee['ngay_vao_lam'] ?? null)) ?></td>
                <td class="workspace-number" data-label="Trạng thái"><span class="catalog-status" data-status="<?= $status === 'DANG_LAM' ? 'HOAT_DONG' : 'NGUNG' ?>"><?= $status === 'DANG_LAM' ? 'Đang làm' : ($status === 'DA_NGHI' ? 'Đã nghỉ' : 'Không xác định') ?></span></td>
                <td data-label="Thao tác">
                    <?php ob_start(); ?>
                    <a class="list-action list-action--view" href="<?= e(admin_context_url($basePath . '/employees/show') . '&id=' . rawurlencode($id)) ?>"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>Xem chi tiết</a>
                    <?php if ($canManage): ?>
                    <a class="list-action list-action--edit" href="<?= e(admin_context_url($basePath . '/employees/edit') . '&id=' . rawurlencode($id)) ?>"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m16 3 5 5M3 21l5-1L21 7a2.1 2.1 0 0 0-5-3L3 17v4Z"/></svg>Sửa</a>
                    <?php endif; ?>
                    <?php $rowMenuContent = ob_get_clean(); $rowMenuId = 'company-employee-actions-' . $id; $rowMenuLabel = $employee['ho_ten'] ?? ''; $rowMenuHasForms = false; require dirname(__DIR__, 2) . '/admin/partials/row-menu.php'; ?>
                </td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
    <?php require dirname(__DIR__, 2) . '/admin/partials/pagination.php'; ?>
</section>
