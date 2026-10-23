<?php $bodyClass = 'page-catalog'; ?>
<section class="container catalog-page">
    <header class="catalog-heading"><div><p class="eyebrow">STRYKE / Cơ sở thể thao</p><h1><?= e($title) ?></h1></div></header>
    <?php if (!$companies): ?><p class="alert alert--danger" role="alert">Chưa có công ty đang hoạt động. Kiểm tra <a href="<?= e(url('/admin/companies')) ?>">Quản lý Công ty</a> trước khi thêm cơ sở.</p><?php endif; ?>
    <?php if (($item['trang_thai_cong_ty'] ?? 'HOAT_DONG') !== 'HOAT_DONG'): ?><p class="alert" role="status">Công ty hiện tại đã ngừng. Cơ sở chỉ được giữ ở trạng thái Tạm ngừng hoặc chuyển sang công ty hoạt động khi chưa có dữ liệu tham chiếu.</p><?php endif; ?>
    <form class="catalog-form" method="post" action="<?= e(url('/admin/co-so/' . ($item ? 'update' : 'store'))) ?>" novalidate>
        <?= csrf_field() ?>
        <?php if ($item): ?><input type="hidden" name="id" value="<?= e($item['ma_co_so']) ?>"><?php endif; ?>
        <div class="form-group">
            <label for="ma_cong_ty">Công ty nhượng quyền</label>
            <select class="form-control" id="ma_cong_ty" name="ma_cong_ty" required<?= field_error_attributes('ma_cong_ty', $errors) ?>>
                <option value="">Chọn công ty</option>
                <?php foreach ($companies as $company): ?>
                    <option value="<?= e($company['ma_cong_ty']) ?>" <?= (string) ($old['ma_cong_ty'] ?? '') === (string) $company['ma_cong_ty'] ? 'selected' : '' ?>><?= e($company['ten_cong_ty']) ?><?= $company['trang_thai'] !== 'HOAT_DONG' ? ' (Đã ngừng)' : '' ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error('ma_cong_ty', $errors) ?>
        </div>
        <?php foreach ([
            'ten_co_so' => ['Tên cơ sở', 200, true, 'text'],
            'dia_chi' => ['Địa chỉ', 255, true, 'text'],
            'so_dien_thoai' => ['Số điện thoại (tùy chọn)', 20, false, 'tel'],
            'email' => ['Email (tùy chọn)', 150, false, 'email'],
        ] as $field => [$label, $limit, $required, $type]): ?>
            <div class="form-group">
                <label for="<?= e($field) ?>"><?= e($label) ?></label>
                <input class="form-control" id="<?= e($field) ?>" name="<?= e($field) ?>" type="<?= e($type) ?>" maxlength="<?= e($limit) ?>" <?= $required ? 'required' : '' ?><?= field_error_attributes($field, $errors) ?> value="<?= e($old[$field] ?? '') ?>">
                <?= field_error($field, $errors) ?>
            </div>
        <?php endforeach; ?>
        <div class="account-form-grid">
            <?php foreach (['gio_mo_cua' => 'Giờ mở cửa', 'gio_dong_cua' => 'Giờ đóng cửa'] as $field => $label): ?>
                <div class="form-group">
                    <label for="<?= e($field) ?>"><?= e($label) ?></label>
                    <input class="form-control" id="<?= e($field) ?>" name="<?= e($field) ?>" type="time" step="1" required<?= field_error_attributes($field, $errors) ?> value="<?= e($old[$field] ?? '') ?>">
                    <?= field_error($field, $errors) ?>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="form-hint">Giờ đóng cửa phải sau giờ mở cửa trong cùng ngày.</p>
        <div class="form-group">
            <label for="mo_ta">Mô tả (tùy chọn)</label>
            <textarea class="form-control" id="mo_ta" name="mo_ta" rows="4"<?= field_error_attributes('mo_ta', $errors) ?>><?= e($old['mo_ta'] ?? '') ?></textarea>
            <?= field_error('mo_ta', $errors) ?>
        </div>
        <div class="form-group">
            <label for="trang_thai">Trạng thái</label>
            <select class="form-control" id="trang_thai" name="trang_thai" required<?= field_error_attributes('trang_thai', $errors) ?>>
                <?php foreach (['HOAT_DONG' => 'Hoạt động', 'TAM_NGUNG' => 'Tạm ngừng'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($old['trang_thai'] ?? 'HOAT_DONG') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error('trang_thai', $errors) ?>
        </div>
        <div class="catalog-actions"><button class="btn btn--primary" type="submit" <?= !$companies ? 'disabled' : '' ?>>Lưu cơ sở</button><a class="btn btn--outline" href="<?= e(url('/admin/co-so')) ?>">Hủy</a></div>
    </form>
</section>
