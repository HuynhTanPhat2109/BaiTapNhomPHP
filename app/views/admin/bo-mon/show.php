<?php $bodyClass = 'page-catalog'; ?>
<section class="container catalog-page">
    <nav class="catalog-nav" aria-label="Quản lý danh mục">
        <a href="<?= e(url('/admin')) ?>">Quản trị</a>
        <a href="<?= e(url('/admin/bo-mon')) ?>">Bộ môn</a>
        <a href="<?= e(url('/admin/loai-san')) ?>">Loại sân</a>
    </nav>
    <header class="catalog-heading">
        <div><p class="eyebrow">STRYKE / Chi tiết bộ môn</p><h1><?= e($item['ten_bo_mon']) ?></h1></div>
        <div class="catalog-actions">
            <a class="btn btn--primary" href="<?= e(url('/admin/bo-mon/edit') . '&id=' . $item['ma_bo_mon']) ?>">Sửa</a>
            <a class="btn btn--outline" href="<?= e(url('/admin/bo-mon/delete') . '&id=' . $item['ma_bo_mon']) ?>">Xóa</a>
        </div>
    </header>
    <dl class="catalog-details">
        <div><dt>Mã bộ môn</dt><dd><?= e($item['ma_bo_mon']) ?></dd></div>
        <div><dt>Mô tả</dt><dd class="catalog-description"><?= e($item['mo_ta'] ?? 'Chưa có mô tả') ?></dd></div>
        <div><dt>Trạng thái</dt><dd><?= $item['trang_thai'] === 'HOAT_DONG' ? 'Hoạt động' : 'Ngừng' ?></dd></div>
        <div><dt>Loại sân liên quan</dt><dd><?= e($related['loai_san']) ?> (<?= e($related['loai_san_dang_dung']) ?> hoạt động)</dd></div>
    </dl>
    <?php if ($types): ?>
        <h2>Loại sân thuộc bộ môn</h2>
        <ul class="catalog-related">
            <?php foreach ($types as $type): ?>
                <li><a href="<?= e(url('/admin/loai-san/show') . '&id=' . $type['ma_loai_san']) ?>"><?= e($type['ten_loai_san']) ?></a> — <?= $type['trang_thai'] === 'HOAT_DONG' ? 'Hoạt động' : 'Ngừng' ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <a class="btn btn--outline" href="<?= e(url('/admin/bo-mon')) ?>">Quay lại danh sách</a>
</section>

