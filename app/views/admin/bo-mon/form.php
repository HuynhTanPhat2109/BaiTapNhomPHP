<?php $bodyClass = 'page-catalog'; ?>
<section class="container catalog-page catalog-workspace">
    <nav class="catalog-nav" aria-label="Quản lý danh mục">
        <a href="<?= e(url('/admin')) ?>">Quản trị</a>
        <a href="<?= e(url('/admin/bo-mon')) ?>">Bộ môn</a>
        <a href="<?= e(url('/admin/loai-san')) ?>">Loại sân</a>
    </nav>
    <header class="catalog-heading"><div><p class="eyebrow">STRYKE / Bộ môn thể thao</p><h1><?= e($title) ?></h1></div></header>
    <form class="catalog-form" method="post" action="<?= e(url('/admin/bo-mon/' . ($item ? 'update' : 'store'))) ?>" novalidate>
        <?= csrf_field() ?>
        <?php if ($item): ?><input type="hidden" name="id" value="<?= e($item['ma_bo_mon']) ?>"><?php endif; ?>
        <h2 class="admin-form-heading">Thông tin bộ môn</h2>
        <div class="form-group">
            <label for="ten_bo_mon">Tên bộ môn</label>
            <input class="form-control" id="ten_bo_mon" name="ten_bo_mon" maxlength="100" required<?= field_error_attributes('ten_bo_mon', $errors) ?> value="<?= e($old['ten_bo_mon'] ?? '') ?>">
            <?= field_error('ten_bo_mon', $errors) ?>
        </div>
        <div class="form-group">
            <label for="mo_ta">Mô tả <span class="optional">(tùy chọn)</span></label>
            <textarea class="form-control" id="mo_ta" name="mo_ta" rows="4" maxlength="255"<?= field_error_attributes('mo_ta', $errors) ?>><?= e($old['mo_ta'] ?? '') ?></textarea>
            <?= field_error('mo_ta', $errors) ?>
        </div>
        <div class="form-group">
            <label for="trang_thai">Trạng thái</label>
            <select class="form-control" id="trang_thai" name="trang_thai" required<?= field_error_attributes('trang_thai', $errors) ?>>
                <option value="HOAT_DONG" <?= ($old['trang_thai'] ?? 'HOAT_DONG') === 'HOAT_DONG' ? 'selected' : '' ?>>Hoạt động</option>
                <option value="NGUNG" <?= ($old['trang_thai'] ?? '') === 'NGUNG' ? 'selected' : '' ?>>Ngừng</option>
            </select>
            <?= field_error('trang_thai', $errors) ?>
        </div>
        <div class="catalog-actions">
            <button class="btn btn--primary" type="submit">Lưu bộ môn</button>
            <a class="btn btn--outline" href="<?= e(url('/admin/bo-mon')) ?>">Hủy</a>
        </div>
    </form>
</section>

