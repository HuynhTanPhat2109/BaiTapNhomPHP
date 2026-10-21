<?php $bodyClass = 'page-catalog'; ?>
<section class="container catalog-page">
    <nav class="catalog-nav" aria-label="Quản lý danh mục">
        <a href="<?= e(url('/admin')) ?>">Quản trị</a>
        <a href="<?= e(url('/admin/bo-mon')) ?>">Bộ môn</a>
        <a href="<?= e(url('/admin/loai-san')) ?>">Loại sân</a>
    </nav>
    <header class="catalog-heading"><div><p class="eyebrow">STRYKE / Bộ môn thể thao</p><h1>Xác nhận xóa bộ môn</h1></div></header>
    <?php if (!empty($formError)): ?><p class="alert alert--danger" role="alert"><?= e($formError) ?></p><?php endif; ?>
    <div class="catalog-delete">
        <p>Bạn muốn xóa <strong><?= e($item['ten_bo_mon']) ?></strong> (mã <?= e($item['ma_bo_mon']) ?>)?</p>
        <p>Loại sân đang tham chiếu: <strong><?= e($related['loai_san']) ?></strong>.</p>
        <?php $blocked = $related['loai_san'] > 0; ?>
        <?php if ($blocked): ?><p class="alert alert--danger" role="alert">Không thể xóa dữ liệu đang được tham chiếu, kể cả khi bản ghi liên quan đã ngừng.</p><?php else: ?><p>Thao tác này không thể hoàn tác.</p><?php endif; ?>
        <form method="post" action="<?= e(url('/admin/bo-mon/delete')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($item['ma_bo_mon']) ?>">
            <div class="catalog-actions">
                <button class="btn btn--primary" type="submit" <?= $blocked ? 'disabled' : '' ?>>Xác nhận xóa</button>
                <a class="btn btn--outline" href="<?= e(url('/admin/bo-mon')) ?>">Hủy</a>
            </div>
        </form>
    </div>
</section>

