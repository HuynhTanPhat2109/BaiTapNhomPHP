<?php $bodyClass = 'page-catalog'; $companies = $companies ?? []; $formatDate = require dirname(__DIR__) . '/partials/format-date.php';
?>
<section class="container account-page catalog-page admin-list catalog-workspace workspace-page">
    <header class="catalog-heading module-heading"><div>
        <p class="eyebrow">Quản trị tập đoàn / Công ty nhượng quyền</p>
        <h1 class="account-h1">Quản lý công ty nhượng quyền</h1>
        <p>Danh sách công ty nhượng quyền trong hệ thống.</p>
    </div><a class="btn btn--primary" href="<?= e(url('/admin/companies/create')) ?>">Thêm công ty</a></header>


    <?php $tableLabel = 'Công ty nhượng quyền'; $tableTotal = count($companies); require dirname(__DIR__) . '/partials/list-summary.php'; ?>
    <?php if (!$companies): ?><div class="workspace-empty" role="status"><h2>Chưa có công ty</h2><p>Thêm công ty nhượng quyền để bắt đầu quản lý thông tin hợp tác.</p></div>
    <?php else: ?>
    <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách công ty">
        <table class="catalog-table workspace-table">
            <colgroup><col class="workspace-col-name"><col><col><col class="workspace-col-status"><col class="workspace-col-actions"></colgroup>
            <thead><tr><th scope="col">Công ty</th><th scope="col">Người đại diện</th><th scope="col">Liên hệ</th><th class="workspace-number" scope="col">Trạng thái</th><th class="workspace-number" scope="col">Thao tác</th></tr></thead>
            <tbody><?php foreach ($companies as $company): ?>
                <?php $id = $company['ma_cong_ty'] ?? ''; $active = ($company['trang_thai'] ?? '') === 'HOAT_DONG'; $inactive = ($company['trang_thai'] ?? '') === 'NGUNG_HOAT_DONG'; ?>
                <tr>
                    <th scope="row"><?= e($company['ten_cong_ty'] ?? '') ?><small class="workspace-secondary">Mã số thuế: <?= e($company['ma_so_thue'] ?? '') ?></small></th>
                    <td data-label="Người đại diện"><?= e($company['nguoi_dai_dien'] ?? '') ?><small class="workspace-secondary">Tham gia <?= e($formatDate($company['ngay_tham_gia'] ?? null)) ?></small></td>
                    <td data-label="Liên hệ"><?= e($company['so_dien_thoai'] ?? '') ?></td>
                    <td class="workspace-number" data-label="Trạng thái"><span class="catalog-status" data-status="<?= $active ? 'HOAT_DONG' : 'NGUNG' ?>"><?= $active ? 'Hoạt động' : ($inactive ? 'Ngừng hoạt động' : 'Không xác định') ?></span></td>
                    <td data-label="Thao tác">
                        <?php ob_start(); ?>
                        <a class="list-action list-action--edit" href="<?= e(url('/admin/companies/edit') . '&id=' . rawurlencode((string) $id)) ?>"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m16 3 5 5M3 21l5-1L21 7a2.1 2.1 0 0 0-5-3L3 17v4Z"/></svg>Sửa</a>
                        <?php if ($active || $inactive): ?>
                            <form method="post" action="<?= e(url('/admin/companies/status') . '&id=' . rawurlencode((string) $id)) ?>" data-confirm="<?= e(($active ? 'Ngừng hoạt động' : 'Kích hoạt') . ' công ty ' . ($company['ten_cong_ty'] ?? '') . '?') ?>">
                                <?= csrf_field() ?><input type="hidden" name="trang_thai" value="<?= $active ? 'NGUNG_HOAT_DONG' : 'HOAT_DONG' ?>">
                                <button class="list-action list-action--<?= $active ? 'stop' : 'activate' ?>" type="submit"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v9m-5-7a8 8 0 1 0 10 0"/></svg><?= $active ? 'Ngừng' : 'Kích hoạt' ?></button>
                            </form>
                        <?php endif; ?>
                        <?php $rowMenuContent = ob_get_clean(); $rowMenuId = 'company-actions-' . $id; $rowMenuLabel = $company['ten_cong_ty'] ?? ''; $rowMenuHasForms = true; require dirname(__DIR__) . '/partials/row-menu.php'; ?>
                    </td>
                </tr>
            <?php endforeach; ?></tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
