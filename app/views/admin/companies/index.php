<?php $companies = $companies ?? []; ?>
<section class="container account-page catalog-workspace admin-list">
    <div class="account-page__intro">
        <p class="eyebrow">Quản trị tập đoàn / Công ty nhượng quyền</p>
        <h1 class="account-h1">Quản lý công ty nhượng quyền</h1>
        <p>Danh sách công ty nhượng quyền trong hệ thống.</p>
    </div>
    <p><a class="btn btn--primary" href="<?= e(url('/admin/companies/create')) ?>">Thêm công ty</a></p>

    <?php if (!$companies): ?>
        <p role="status">Chưa có công ty nhượng quyền nào.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="company-table">
                <thead>
                    <tr>
                        <th scope="col">Tên công ty</th>
                        <th scope="col">Mã số thuế</th>
                        <th scope="col">Người đại diện</th>
                        <th scope="col">Số điện thoại</th>
                        <th scope="col">Ngày tham gia</th>
                        <th scope="col">Trạng thái</th>
                        <th class="company-actions-cell" scope="col">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($companies as $company): ?>
                        <?php
                        $id = $company['ma_cong_ty'] ?? '';
                        $active = ($company['trang_thai'] ?? '') === 'HOAT_DONG';
                        $inactive = ($company['trang_thai'] ?? '') === 'NGUNG_HOAT_DONG';
                        ?>
                        <tr>
                            <td class="admin-table-name"><?= e($company['ten_cong_ty'] ?? '') ?></td>
                            <td class="list-nowrap"><?= e($company['ma_so_thue'] ?? '') ?></td>
                            <td><?= e($company['nguoi_dai_dien'] ?? '') ?></td>
                            <td class="list-nowrap"><?= e($company['so_dien_thoai'] ?? '') ?></td>
                            <td class="list-nowrap"><?= e($company['ngay_tham_gia'] ?? '') ?></td>
                            <td>
                                <span class="catalog-status" data-status="<?= e($company['trang_thai'] ?? '') ?>">
                                    <?php if ($active): ?>Hoạt động
                                    <?php elseif ($inactive): ?>Ngừng hoạt động
                                    <?php else: ?>Không xác định
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td class="company-actions-cell">
                                <div class="list-actions company-actions">
                                    <a class="list-action" href="<?= e(url('/admin/companies/edit') . '&id=' . rawurlencode((string) $id)) ?>">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m16 3 5 5M3 21l5-1L21 7a2.1 2.1 0 0 0-5-3L3 17v4Z"/></svg><span>Chỉnh sửa</span>
                                    </a>
                                    <?php if ($active || $inactive): ?>
                                        <form method="post" action="<?= e(url('/admin/companies/status') . '&id=' . rawurlencode((string) $id)) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="trang_thai" value="<?= $active ? 'NGUNG_HOAT_DONG' : 'HOAT_DONG' ?>">
                                            <button class="list-action list-action--<?= $active ? 'stop' : 'activate' ?>" type="submit" title="<?= $active ? 'Ngừng hoạt động' : 'Kích hoạt công ty' ?>">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="<?= $active ? 'M9 8v8M15 8v8' : 'm9 12 2 2 4-4' ?>"/></svg><span><?= $active ? 'Ngừng' : 'Kích hoạt' ?></span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
