<?php $bodyClass = 'page-catalog'; ?>
<section class="container catalog-page">
    <nav class="catalog-nav" aria-label="Quản lý danh mục">
        <a href="<?= e(url('/admin')) ?>">Quản trị</a>
        <a href="<?= e(url('/admin/bo-mon')) ?>">Bộ môn</a>
        <a href="<?= e(url('/admin/loai-san')) ?>">Loại sân</a>
    </nav>
    <header class="catalog-heading">
        <div><p class="eyebrow">STRYKE / Danh mục</p><h1>Bộ môn thể thao</h1></div>
        <a class="btn btn--primary" href="<?= e(url('/admin/bo-mon/create')) ?>">Thêm bộ môn</a>
    </header>
    <?php if (!empty($notice)): ?><p class="alert alert--success" role="status"><?= e($notice) ?></p><?php endif; ?>
    <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách bộ môn">
        <table class="catalog-table">
            <caption>Danh sách bộ môn — <?= e(count($items)) ?> bản ghi</caption>
            <thead><tr><th scope="col">Mã</th><th scope="col">Tên bộ môn</th><th scope="col">Mô tả</th><th scope="col">Số loại sân</th><th scope="col">Trạng thái</th><th scope="col">Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($items as $row): ?>
                <tr>
                    <td><?= e($row['ma_bo_mon']) ?></td>
                    <th scope="row"><?= e($row['ten_bo_mon']) ?></th>
                    <td><?= e($row['mo_ta'] ?? '—') ?></td>
                    <td><?= e($row['so_loai_san']) ?></td>
                    <td><span class="catalog-status"><?= $row['trang_thai'] === 'HOAT_DONG' ? 'Hoạt động' : 'Ngừng' ?></span></td>
                    <td><div class="catalog-actions"><a href="<?= e(url('/admin/bo-mon/show') . '&id=' . $row['ma_bo_mon']) ?>">Chi tiết</a>
                        <a href="<?= e(url('/admin/bo-mon/edit') . '&id=' . $row['ma_bo_mon']) ?>">Sửa</a>
                        <a href="<?= e(url('/admin/bo-mon/delete') . '&id=' . $row['ma_bo_mon']) ?>">Xóa</a></div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="6">Chưa có bộ môn. Chọn “Thêm bộ môn” để bắt đầu.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

