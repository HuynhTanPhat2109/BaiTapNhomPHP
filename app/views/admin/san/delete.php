<?php $bodyClass = 'page-catalog'; ?>
<section class="container catalog-page catalog-workspace workspace-page">
    <header class="catalog-heading module-heading"><div><p class="eyebrow">STRYKE / Sân thể thao</p><h1>Xác nhận xóa sân</h1><p class="module-subtitle">Sân thể thao và liên kết cơ sở, loại sân.</p></div><a class="btn btn--outline workspace-back admin-back-link" href="<?= e(admin_context_url('/admin/san')) ?>">Quay lại danh sách</a></header>
    <?php if (!empty($formError)): ?><p class="alert alert--danger" role="alert"><?= e($formError) ?></p><?php endif; ?>
    <?php $blocked = $related['chi_tiet_dat_san'] > 0; ?>
    <div class="catalog-delete workspace-danger-card">
        <p>Bạn muốn xóa <strong><?= e($item['ten_san']) ?></strong> tại <?= e($item['ten_co_so']) ?>?</p>
        <p>Chi tiết đặt sân đang tham chiếu: <strong><?= e($related['chi_tiet_dat_san']) ?></strong>.</p>
        <?php if ($blocked): ?><p class="alert alert--danger" role="alert">Không thể xóa sân có lịch sử tham chiếu, kể cả booking đã hủy hoặc hoàn thành.</p><?php else: ?><p>Thao tác này không thể hoàn tác.</p><?php endif; ?>
        <form method="post" action="<?= e(admin_context_url('/admin/san/delete')) ?>">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($item['ma_san']) ?>">
            <div class="catalog-actions"><button class="btn btn--primary" type="submit" <?= $blocked ? 'disabled' : '' ?>>Xác nhận xóa</button><a class="btn btn--outline" href="<?= e(admin_context_url('/admin/san')) ?>">Hủy</a></div>
        </form>
    </div>
</section>
