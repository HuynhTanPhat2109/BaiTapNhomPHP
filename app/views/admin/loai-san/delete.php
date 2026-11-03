<?php $bodyClass = 'page-catalog'; ?>
<section class="container catalog-page catalog-workspace workspace-page">
    <nav class="catalog-nav" aria-label="Quản lý danh mục">
        <a href="<?= e(admin_context_url('/admin')) ?>">Quản trị</a>
        <a href="<?= e(admin_context_url('/admin/bo-mon')) ?>">Bộ môn</a>
        <a href="<?= e(admin_context_url('/admin/loai-san')) ?>">Loại sân</a>
    </nav>
    <header class="catalog-heading module-heading"><div><p class="eyebrow">STRYKE / Loại sân</p><h1>Xác nhận xóa loại sân</h1><p class="module-subtitle">Phân loại sân theo từng bộ môn thể thao.</p></div><a class="btn btn--outline workspace-back admin-back-link" href="<?= e(admin_context_url('/admin/loai-san')) ?>">Quay lại danh sách</a></header>
    <?php if (!empty($formError)): ?><p class="alert alert--danger" role="alert"><?= e($formError) ?></p><?php endif; ?>
    <div class="catalog-delete workspace-danger-card">
        <p>Bạn muốn xóa <strong><?= e($item['ten_loai_san']) ?></strong> (mã <?= e($item['ma_loai_san']) ?>)?</p>
        <p>Sân đang tham chiếu: <strong><?= e($related['san']) ?></strong>. Bảng giá đang tham chiếu: <strong><?= e($related['bang_gia']) ?></strong>.</p>
        <?php $blocked = $related['san'] > 0 || $related['bang_gia'] > 0; ?>
        <?php if ($blocked): ?><p class="alert alert--danger" role="alert">Không thể xóa dữ liệu đang được tham chiếu, kể cả khi bản ghi liên quan đã ngừng.</p><?php else: ?><p>Thao tác này không thể hoàn tác.</p><?php endif; ?>
        <form method="post" action="<?= e(admin_context_url('/admin/loai-san/delete')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($item['ma_loai_san']) ?>">
            <div class="catalog-actions">
                <button class="btn btn--primary" type="submit" <?= $blocked ? 'disabled' : '' ?>>Xác nhận xóa</button>
                <a class="btn btn--outline" href="<?= e(admin_context_url('/admin/loai-san')) ?>">Hủy</a>
            </div>
        </form>
    </div>
</section>

