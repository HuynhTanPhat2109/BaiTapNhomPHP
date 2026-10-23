<?php $bodyClass = 'page-catalog'; ?>
<section class="container catalog-page">
    <header class="catalog-heading"><div><p class="eyebrow">STRYKE / Sân thể thao</p><h1><?= e($title) ?></h1></div></header>
    <?php if (!$facilities): ?><p class="alert alert--danger" role="alert">Chưa có cơ sở và công ty đang hoạt động. Kiểm tra <a href="<?= e(url('/admin/co-so')) ?>">Quản lý Cơ sở</a> trước khi thêm sân.</p><?php endif; ?>
    <?php if (!$types): ?><p class="alert alert--danger" role="alert">Chưa có loại sân và bộ môn đang hoạt động. Kiểm tra <a href="<?= e(url('/admin/loai-san')) ?>">Quản lý Loại sân</a>.</p><?php endif; ?>
    <?php if ($item && (($item['trang_thai_co_so'] ?? '') !== 'HOAT_DONG' || ($item['trang_thai_cong_ty'] ?? '') !== 'HOAT_DONG'
        || ($item['trang_thai_loai_san'] ?? '') !== 'HOAT_DONG' || ($item['trang_thai_bo_mon'] ?? '') !== 'HOAT_DONG')): ?>
        <p class="alert" role="status">Dữ liệu cha hiện có trạng thái ngừng. Chỉ có thể giữ sân ở trạng thái Tạm ngừng hoặc chuyển sang liên kết hoạt động khi chưa có lịch sử tham chiếu.</p>
    <?php endif; ?>
    <form class="catalog-form" method="post" action="<?= e(url('/admin/san/' . ($item ? 'update' : 'store'))) ?>" novalidate>
        <?= csrf_field() ?>
        <?php if ($item): ?><input type="hidden" name="id" value="<?= e($item['ma_san']) ?>"><?php endif; ?>
        <div class="form-group">
            <label for="ma_co_so">Cơ sở thể thao</label>
            <select class="form-control" id="ma_co_so" name="ma_co_so" required<?= field_error_attributes('ma_co_so', $errors) ?>>
                <option value="">Chọn cơ sở</option>
                <?php foreach ($facilities as $facility): ?>
                    <option value="<?= e($facility['ma_co_so']) ?>" <?= (string) ($old['ma_co_so'] ?? '') === (string) $facility['ma_co_so'] ? 'selected' : '' ?>><?= e($facility['ten_co_so'] . ' — ' . $facility['ten_cong_ty']) ?><?= $facility['trang_thai'] !== 'HOAT_DONG' || $facility['trang_thai_cong_ty'] !== 'HOAT_DONG' ? ' (Đã ngừng)' : '' ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error('ma_co_so', $errors) ?>
        </div>
        <div class="form-group">
            <label for="ma_loai_san">Bộ môn — Loại sân</label>
            <select class="form-control" id="ma_loai_san" name="ma_loai_san" required<?= field_error_attributes('ma_loai_san', $errors) ?>>
                <option value="">Chọn loại sân</option>
                <?php foreach ($types as $type): ?>
                    <option value="<?= e($type['ma_loai_san']) ?>" <?= (string) ($old['ma_loai_san'] ?? '') === (string) $type['ma_loai_san'] ? 'selected' : '' ?>><?= e($type['ten_bo_mon'] . ' — ' . $type['ten_loai_san']) ?><?= $type['trang_thai'] !== 'HOAT_DONG' || $type['trang_thai_bo_mon'] !== 'HOAT_DONG' ? ' (Đã ngừng)' : '' ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error('ma_loai_san', $errors) ?>
        </div>
        <?php foreach (['ten_san' => ['Tên sân', true], 'vi_tri' => ['Vị trí (tùy chọn)', false]] as $field => [$label, $required]): ?>
            <div class="form-group">
                <label for="<?= e($field) ?>"><?= e($label) ?></label>
                <input class="form-control" id="<?= e($field) ?>" name="<?= e($field) ?>" maxlength="150" <?= $required ? 'required' : '' ?><?= field_error_attributes($field, $errors) ?> value="<?= e($old[$field] ?? '') ?>">
                <?= field_error($field, $errors) ?>
            </div>
        <?php endforeach; ?>
        <div class="form-group">
            <label for="mo_ta">Mô tả (tùy chọn)</label>
            <textarea class="form-control" id="mo_ta" name="mo_ta" rows="4"<?= field_error_attributes('mo_ta', $errors) ?>><?= e($old['mo_ta'] ?? '') ?></textarea>
            <?= field_error('mo_ta', $errors) ?>
        </div>
        <div class="form-group">
            <label for="trang_thai">Trạng thái</label>
            <select class="form-control" id="trang_thai" name="trang_thai" required<?= field_error_attributes('trang_thai', $errors) ?>>
                <?php foreach (['HOAT_DONG' => 'Hoạt động', 'BAO_TRI' => 'Bảo trì', 'TAM_NGUNG' => 'Tạm ngừng'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($old['trang_thai'] ?? 'HOAT_DONG') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error('trang_thai', $errors) ?>
        </div>
        <div class="catalog-actions"><button class="btn btn--primary" type="submit" <?= !$facilities || !$types ? 'disabled' : '' ?>>Lưu sân</button><a class="btn btn--outline" href="<?= e(url('/admin/san')) ?>">Hủy</a></div>
    </form>
</section>
