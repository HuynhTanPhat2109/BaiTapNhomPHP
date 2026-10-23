<?php $bodyClass = 'page-catalog'; ?>
<section class="container catalog-page">
    <header class="catalog-heading">
        <div><p class="eyebrow">STRYKE / Sân thể thao</p><h1>Quản lý sân</h1></div>
        <a class="btn btn--primary" href="<?= e(url('/admin/san/create')) ?>">Thêm sân</a>
    </header>
    <?php if (!empty($notice)): ?><p class="alert alert--success" role="status"><?= e($notice) ?></p><?php endif; ?>
    <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách sân">
        <table class="catalog-table facility-table">
            <caption>Danh sách sân — <?= e(count($items)) ?> bản ghi</caption>
            <thead><tr><th scope="col">Tên sân</th><th scope="col">Cơ sở</th><th scope="col">Bộ môn</th><th scope="col">Loại sân</th><th scope="col">Trạng thái</th><th scope="col">Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($items as $row): ?>
                <tr>
                    <th scope="row"><?= e($row['ten_san']) ?></th>
                    <td><a href="<?= e(url('/admin/co-so/show') . '&id=' . $row['ma_co_so']) ?>"><?= e($row['ten_co_so']) ?></a></td>
                    <td><?= e($row['ten_bo_mon']) ?></td>
                    <td><a href="<?= e(url('/admin/loai-san/show') . '&id=' . $row['ma_loai_san']) ?>"><?= e($row['ten_loai_san']) ?></a></td>
                    <td><span class="catalog-status"><?= e(['HOAT_DONG' => 'Hoạt động', 'BAO_TRI' => 'Bảo trì', 'TAM_NGUNG' => 'Tạm ngừng'][$row['trang_thai']]) ?></span></td>
                    <td><div class="catalog-actions">
                        <a href="<?= e(url('/admin/san/show') . '&id=' . $row['ma_san']) ?>">Chi tiết</a>
                        <a href="<?= e(url('/admin/san/edit') . '&id=' . $row['ma_san']) ?>">Sửa</a>
                        <a href="<?= e(url('/admin/san/delete') . '&id=' . $row['ma_san']) ?>">Xóa</a>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="6">Chưa có sân. Chọn “Thêm sân” để bắt đầu.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
