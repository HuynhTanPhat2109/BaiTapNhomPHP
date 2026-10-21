<?php $companies = $companies ?? []; ?>
<section class="container account-page">
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
            <table>
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
                            <td>
                                <?php if ($active): ?>Hoạt động
                                <?php elseif ($inactive): ?>Ngừng hoạt động
                                <?php else: ?>Không xác định
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="btn btn--small" href="<?= e(url('/admin/companies/edit') . '&id=' . rawurlencode((string) $id)) ?>">Chỉnh sửa</a>
                                <?php if ($active || $inactive): ?>
                                    <form method="post" action="<?= e(url('/admin/companies/status') . '&id=' . rawurlencode((string) $id)) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="trang_thai" value="<?= $active ? 'NGUNG_HOAT_DONG' : 'HOAT_DONG' ?>">
                                        <button class="btn btn--small" type="submit"><?= $active ? 'Ngừng hoạt động' : 'Kích hoạt' ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>