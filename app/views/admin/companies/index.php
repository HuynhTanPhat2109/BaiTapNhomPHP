<?php $bodyClass = 'page-catalog'; $companies = $companies ?? []; ?>
<section class="container account-page catalog-page admin-list">
    <header class="catalog-heading"><div>
        <p class="eyebrow">Quản trị tập đoàn / Công ty nhượng quyền</p>
        <h1 class="account-h1">Quản lý công ty nhượng quyền</h1>
        <p>Danh sách công ty nhượng quyền trong hệ thống.</p>
    </div><a class="btn btn--primary" href="<?= e(url('/admin/companies/create')) ?>">Thêm công ty</a></header>

    <?php if (!$companies): ?>
        <p role="status">Chưa có công ty nhượng quyền nào.</p>
    <?php else: ?>
        <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách công ty">
            <table class="catalog-table">
                <thead>
                    <tr>
                        <th scope="col">Tên công ty</th>
                        <th scope="col">Mã số thuế</th>
                        <th scope="col">Người đại diện</th>
                        <th scope="col">Số điện thoại</th>
                        <th scope="col">Ngày tham gia</th>
                        <th scope="col">Trạng thái</th>
                        <th scope="col">Thao tác</th>
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
                            <td><?= e($company['ten_cong_ty'] ?? '') ?></td>
                            <td><?= e($company['ma_so_thue'] ?? '') ?></td>
                            <td><?= e($company['nguoi_dai_dien'] ?? '') ?></td>
                            <td><?= e($company['so_dien_thoai'] ?? '') ?></td>
                            <td><?= e($company['ngay_tham_gia'] ?? '') ?></td>
                            <td><span class="catalog-status" data-status="<?= $active ? 'HOAT_DONG' : 'NGUNG' ?>"><?php if ($active): ?>Hoạt động<?php elseif ($inactive): ?>Ngừng hoạt động<?php else: ?>Không xác định<?php endif; ?></span></td>
                            <td><div class="catalog-actions">
                                <a class="list-action list-action--edit" href="<?= e(url('/admin/companies/edit') . '&id=' . rawurlencode((string) $id)) ?>">Chỉnh sửa</a>
                                <?php if ($active || $inactive): ?>
                                    <form method="post" action="<?= e(url('/admin/companies/status') . '&id=' . rawurlencode((string) $id)) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="trang_thai" value="<?= $active ? 'NGUNG_HOAT_DONG' : 'HOAT_DONG' ?>">
                                        <button class="list-action list-action--stop" type="submit"><?= $active ? 'Ngừng hoạt động' : 'Kích hoạt' ?></button>
                                    </form>
                                <?php endif; ?>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
