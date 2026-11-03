<?php $bodyClass = 'page-catalog'; ?>
<section class="container catalog-page catalog-workspace workspace-page catalog-compact-form">
    <nav class="catalog-nav" aria-label="Quản lý danh mục">
        <a href="<?= e(admin_context_url('/admin')) ?>">Quản trị</a>
        <a href="<?= e(admin_context_url('/admin/bo-mon')) ?>">Bộ môn</a>
        <a href="<?= e(admin_context_url('/admin/loai-san')) ?>">Loại sân</a>
    </nav>
    <header class="catalog-heading module-heading"><div><p class="eyebrow">STRYKE / Loại sân</p><h1><?= e($title) ?></h1><p class="module-subtitle">Phân loại sân theo từng bộ môn thể thao.</p></div><a class="btn btn--outline workspace-back admin-back-link" href="<?= e(admin_context_url('/admin/loai-san')) ?>">Quay lại danh sách</a></header>
    <?php if (!$sports): ?><p class="alert alert--danger" role="alert">Chưa có bộ môn đang hoạt động. Hãy thêm hoặc kích hoạt bộ môn trước.</p><?php endif; ?>
    <?php if (($item['trang_thai_bo_mon'] ?? '') === 'NGUNG'): ?><p class="alert" role="status">Bộ môn hiện tại đã ngừng. Loại sân chỉ được lưu ở trạng thái Ngừng hoặc chuyển sang bộ môn đang hoạt động nếu chưa được sử dụng.</p><?php endif; ?>
    <form class="catalog-form module-form workspace-form" method="post" action="<?= e(admin_context_url('/admin/loai-san/' . ($item ? 'update' : 'store'))) ?>" novalidate>
        <?= csrf_field() ?>
        <?php if ($item): ?><input type="hidden" name="id" value="<?= e($item['ma_loai_san']) ?>"><?php endif; ?>
        <section class="module-form-card">
        <h2 class="admin-form-heading">Thông tin loại sân</h2>
        <div class="module-fields">
        <div class="form-group module-field--wide">
            <label for="ma_bo_mon">Bộ môn</label>
            <select class="form-control" id="ma_bo_mon" name="ma_bo_mon" required<?= field_error_attributes('ma_bo_mon', $errors) ?>>
                <option value="">Chọn bộ môn</option>
                <?php foreach ($sports as $sport): ?>
                    <option value="<?= e($sport['ma_bo_mon']) ?>" <?= (string) ($old['ma_bo_mon'] ?? '') === (string) $sport['ma_bo_mon'] ? 'selected' : '' ?>><?= e($sport['ten_bo_mon']) ?><?= $sport['trang_thai'] === 'NGUNG' ? ' (Đã ngừng)' : '' ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error('ma_bo_mon', $errors) ?>
        </div>
        <div class="form-group">
            <label for="ten_loai_san">Tên loại sân</label>
            <input class="form-control" id="ten_loai_san" name="ten_loai_san" maxlength="150" required<?= field_error_attributes('ten_loai_san', $errors) ?> value="<?= e($old['ten_loai_san'] ?? '') ?>">
            <?= field_error('ten_loai_san', $errors) ?>
        </div>
        <div class="form-group">
            <label for="trang_thai">Trạng thái</label>
            <select class="form-control" id="trang_thai" name="trang_thai" required<?= field_error_attributes('trang_thai', $errors) ?>>
                <option value="HOAT_DONG" <?= ($old['trang_thai'] ?? 'HOAT_DONG') === 'HOAT_DONG' ? 'selected' : '' ?>>Hoạt động</option>
                <option value="NGUNG" <?= ($old['trang_thai'] ?? '') === 'NGUNG' ? 'selected' : '' ?>>Ngừng</option>
            </select>
            <?= field_error('trang_thai', $errors) ?>
        </div>
        <div class="form-group module-field--wide">
            <label for="mo_ta">Mô tả <span class="optional">(tùy chọn)</span></label>
            <textarea class="form-control" id="mo_ta" name="mo_ta" rows="4" maxlength="255"<?= field_error_attributes('mo_ta', $errors) ?>><?= e($old['mo_ta'] ?? '') ?></textarea>
            <?= field_error('mo_ta', $errors) ?>
        </div>
        </div>
        <div class="catalog-actions compact-form-actions">
            <button class="btn btn--primary" type="submit" <?= !$sports ? 'disabled' : '' ?>><?= $item ? 'Lưu thay đổi' : 'Thêm loại sân' ?></button>
            <a class="btn btn--outline" href="<?= e(admin_context_url('/admin/loai-san')) ?>">Hủy</a>
        </div>
        </section>
    </form>
</section>

