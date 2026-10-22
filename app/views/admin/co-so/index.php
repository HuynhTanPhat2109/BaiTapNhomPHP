<?php $bodyClass = 'page-catalog'; ?>
<section class="container catalog-page">
    <header class="catalog-heading">
        <div><p class="eyebrow">STRYKE / Cơ sở thể thao</p><h1>Quản lý cơ sở</h1></div>
        <a class="btn btn--primary" href="<?= e(url('/admin/co-so/create')) ?>">Thêm cơ sở</a>
    </header>
    <?php if (!empty($notice)): ?><p class="alert alert--success" role="status"><?= e($notice) ?></p><?php endif; ?>
    <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách cơ sở">
        <table class="catalog-table facility-table">
            <caption>Danh sách cơ sở — <?= e(count($items)) ?> bản ghi</caption>
            <thead><tr><th scope="col">Tên cơ sở</th><th scope="col">Công ty</th><th scope="col">Địa chỉ</th><th scope="col">Giờ hoạt động</th><th scope="col">Số sân</th><th scope="col">Trạng thái</th><th scope="col">Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($items as $row): ?>
                <tr>
                    <th scope="row"><?= e($row['ten_co_so']) ?></th>
                    <td><?= e($row['ten_cong_ty']) ?></td><td><?= e($row['dia_chi']) ?></td>
                    <td><?= e($row['gio_mo_cua']) ?> – <?= e($row['gio_dong_cua']) ?></td>
                    <td><?= e($row['so_san']) ?></td>
                    <td><span class="catalog-status"><?= $row['trang_thai'] === 'HOAT_DONG' ? 'Hoạt động' : 'Tạm ngừng' ?></span>
                        <?php if ($row['trang_thai_cong_ty'] !== 'HOAT_DONG'): ?><small>Công ty đã ngừng</small><?php endif; ?></td>
                    <td><div class="catalog-actions">
                        <a href="<?= e(url('/admin/co-so/show') . '&id=' . $row['ma_co_so']) ?>">Chi tiết</a>
                        <a href="<?= e(url('/admin/co-so/edit') . '&id=' . $row['ma_co_so']) ?>">Sửa</a>
                        <a href="<?= e(url('/admin/co-so/delete') . '&id=' . $row['ma_co_so']) ?>">Xóa</a>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="7">Chưa có cơ sở. Chọn “Thêm cơ sở” để bắt đầu.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
