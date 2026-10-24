<?php $bodyClass = 'page-catalog'; $formatPrice = require __DIR__ . '/format-price.php'; ?>
<section class="container catalog-page">
    <header class="catalog-heading"><div><p class="eyebrow">STRYKE / Bảng giá</p><h1><?= e($title) ?></h1></div></header>
    <?php if (!$facilities || !$types): ?><p class="alert alert--danger" role="alert">Cần có Cơ sở/Công ty và Loại sân/Bộ môn hoạt động để thêm bảng giá. Không yêu cầu đã có Sân tương ứng.</p><?php endif; ?>
    <?php if ($item && ($item['trang_thai_co_so'] !== 'HOAT_DONG' || $item['trang_thai_cong_ty'] !== 'HOAT_DONG' || $item['trang_thai_loai_san'] !== 'HOAT_DONG' || $item['trang_thai_bo_mon'] !== 'HOAT_DONG')): ?><p class="alert" role="status">Dữ liệu cha đã ngừng. Không thể kích hoạt giá; có thể ngừng áp dụng tại trang chi tiết hoặc chọn lại liên kết hoạt động khi sửa.</p><?php endif; ?>
    <form class="catalog-form" method="post" action="<?= e(url('/admin/bang-gia/' . ($item ? 'update' : 'store'))) ?>" novalidate>
        <?= csrf_field() ?>
        <?php if ($item): ?><input type="hidden" name="id" value="<?= e($item['ma_bang_gia']) ?>"><?php endif; ?>
        <div class="form-group">
            <label for="ten_bang_gia">Tên bảng giá</label>
            <input class="form-control" id="ten_bang_gia" name="ten_bang_gia" maxlength="150" required<?= field_error_attributes('ten_bang_gia', $errors) ?> value="<?= e($old['ten_bang_gia'] ?? '') ?>">
            <?= field_error('ten_bang_gia', $errors) ?>
        </div>
        <div class="form-group">
            <label for="ma_co_so">Cơ sở</label>
            <select class="form-control" id="ma_co_so" name="ma_co_so" required<?= field_error_attributes('ma_co_so', $errors) ?>>
                <option value="">Chọn cơ sở</option>
                <?php foreach ($facilities as $facility): ?><option value="<?= e($facility['ma_co_so']) ?>" <?= (string) ($old['ma_co_so'] ?? '') === (string) $facility['ma_co_so'] ? 'selected' : '' ?>><?= e($facility['ten_co_so'] . ' — ' . $facility['ten_cong_ty']) ?><?= $facility['trang_thai'] !== 'HOAT_DONG' || $facility['trang_thai_cong_ty'] !== 'HOAT_DONG' ? ' (Đã ngừng)' : '' ?></option><?php endforeach; ?>
            </select>
            <?= field_error('ma_co_so', $errors) ?>
        </div>
        <div class="form-group">
            <label for="ma_loai_san">Bộ môn — Loại sân</label>
            <select class="form-control" id="ma_loai_san" name="ma_loai_san" required<?= field_error_attributes('ma_loai_san', $errors) ?>>
                <option value="">Chọn loại sân</option>
                <?php foreach ($types as $type): ?><option value="<?= e($type['ma_loai_san']) ?>" <?= (string) ($old['ma_loai_san'] ?? '') === (string) $type['ma_loai_san'] ? 'selected' : '' ?>><?= e($type['ten_bo_mon'] . ' — ' . $type['ten_loai_san']) ?><?= $type['trang_thai'] !== 'HOAT_DONG' || $type['trang_thai_bo_mon'] !== 'HOAT_DONG' ? ' (Đã ngừng)' : '' ?></option><?php endforeach; ?>
            </select>
            <?= field_error('ma_loai_san', $errors) ?>
        </div>
        <div class="account-form-grid">
            <div class="form-group">
                <label for="loai_ngay">Loại ngày</label>
                <select class="form-control" id="loai_ngay" name="loai_ngay" required<?= field_error_attributes('loai_ngay', $errors) ?>>
                    <?php foreach (['NGAY_THUONG' => 'Ngày thường (T2–T6)', 'CUOI_TUAN' => 'Cuối tuần (T7–CN)'] as $value => $label): ?><option value="<?= e($value) ?>" <?= ($old['loai_ngay'] ?? 'NGAY_THUONG') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
                <?= field_error('loai_ngay', $errors) ?>
            </div>
            <div class="form-group">
                <label for="don_gia">Đơn giá (VNĐ/giờ)</label>
                <input class="form-control" id="don_gia" name="don_gia" inputmode="decimal" maxlength="13" placeholder="Ví dụ: 100000 hoặc 100000.50" required<?= field_error_attributes('don_gia', $errors) ?> value="<?= e($old['don_gia'] ?? '') ?>">
                <?= field_error('don_gia', $errors) ?>
                <?php if (($old['don_gia'] ?? '') !== ''): ?><small class="form-hint">Hiển thị: <?= e($formatPrice($old['don_gia'])) ?></small><?php endif; ?>
            </div>
            <?php foreach (['gio_bat_dau' => 'Giờ bắt đầu', 'gio_ket_thuc' => 'Giờ kết thúc'] as $field => $label): ?>
                <div class="form-group">
                    <label for="<?= e($field) ?>"><?= e($label) ?></label>
                    <input class="form-control" type="time" id="<?= e($field) ?>" name="<?= e($field) ?>" step="1800" required<?= field_error_attributes($field, $errors) ?> value="<?= e($old[$field] ?? '') ?>">
                    <?= field_error($field, $errors) ?>
                </div>
            <?php endforeach; ?>
            <?php foreach (['ngay_bat_dau' => 'Ngày bắt đầu', 'ngay_ket_thuc' => 'Ngày kết thúc (tùy chọn)'] as $field => $label): ?>
                <div class="form-group">
                    <label for="<?= e($field) ?>"><?= e($label) ?></label>
                    <input class="form-control" type="date" id="<?= e($field) ?>" name="<?= e($field) ?>" <?= $field === 'ngay_bat_dau' ? 'required' : '' ?> min="1000-01-01" max="9999-12-31"<?= field_error_attributes($field, $errors) ?> value="<?= e($old[$field] ?? '') ?>">
                    <?= field_error($field, $errors) ?>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="form-hint">Cho phép ngày bắt đầu trong quá khứ. Để trống ngày kết thúc nếu không thời hạn. Giờ theo bước 30 phút, trong cùng ngày và giờ hoạt động của cơ sở.</p>
        <div class="form-group">
            <?php if ($item): ?>
                <input type="hidden" name="trang_thai" value="<?= e($item['trang_thai']) ?>">
                <p>Trạng thái: <span class="catalog-status"><?= $item['trang_thai'] === 'DANG_AP_DUNG' ? 'Đang áp dụng' : 'Ngừng áp dụng' ?></span>. <a href="<?= e(url('/admin/bang-gia/show') . '&id=' . $item['ma_bang_gia']) ?>">Ngừng/Kích hoạt tại trang chi tiết</a>.</p>
            <?php else: ?>
                <label for="trang_thai">Trạng thái ban đầu</label>
                <select class="form-control" id="trang_thai" name="trang_thai" required<?= field_error_attributes('trang_thai', $errors) ?>>
                    <?php foreach (['DANG_AP_DUNG' => 'Đang áp dụng', 'NGUNG' => 'Ngừng áp dụng'] as $value => $label): ?><option value="<?= e($value) ?>" <?= ($old['trang_thai'] ?? 'DANG_AP_DUNG') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            <?php endif; ?>
            <?= field_error('trang_thai', $errors) ?>
        </div>
        <div class="catalog-actions"><button class="btn btn--primary" type="submit" <?= !$facilities || !$types ? 'disabled' : '' ?>>Lưu bảng giá</button><a class="btn btn--outline" href="<?= e(url('/admin/bang-gia')) ?>">Hủy</a></div>
    </form>
</section>
