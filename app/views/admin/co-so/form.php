<?php $bodyClass = 'page-catalog'; ?>
<section class="container catalog-page catalog-workspace">
    <header class="catalog-heading"><div><p class="eyebrow">STRYKE / Cơ sở thể thao</p><h1><?= e($title) ?></h1></div></header>
    <?php if (!$companies): ?><p class="alert alert--danger" role="alert">Chưa có công ty đang hoạt động. Kiểm tra <a href="<?= e(url('/admin/companies')) ?>">Quản lý Công ty</a> trước khi thêm cơ sở.</p><?php endif; ?>
    <?php if (($item['trang_thai_cong_ty'] ?? 'HOAT_DONG') !== 'HOAT_DONG'): ?><p class="alert" role="status">Công ty hiện tại đã ngừng. Cơ sở chỉ được giữ ở trạng thái Tạm ngừng hoặc chuyển sang công ty hoạt động khi chưa có dữ liệu tham chiếu.</p><?php endif; ?>
    <form class="catalog-form" method="post" enctype="multipart/form-data" action="<?= e(url('/admin/co-so/' . ($item ? 'update' : 'store'))) ?>" novalidate>
        <?= csrf_field() ?>
        <?php if ($item): ?><input type="hidden" name="id" value="<?= e($item['ma_co_so']) ?>"><?php endif; ?>
        <h2 class="admin-form-heading">Thông tin cơ sở</h2>
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
        <?php $provinces = App\Services\AdministrativeAddress::provinces(); ?>
        <div class="account-form-grid">
            <div class="form-group">
                <label for="ma_tinh">Tỉnh/Thành phố</label>
                <select class="form-control" id="ma_tinh" name="ma_tinh"<?= !$item ? ' required' : '' ?><?= field_error_attributes('ma_tinh', $errors) ?>>
                    <option value="">Chọn tỉnh/thành phố</option>
                    <?php foreach ($provinces as $province): ?>
                        <option value="<?= e($province['Code']) ?>" <?= ($old['ma_tinh'] ?? '') === $province['Code'] ? 'selected' : '' ?>><?= e($province['FullName']) ?></option>
                    <?php endforeach; ?>
                    <?php if (!empty($item['ma_tinh']) && !in_array($item['ma_tinh'], array_column($provinces, 'Code'), true)): ?>
                        <option value="<?= e($item['ma_tinh']) ?>" selected><?= e($item['ten_tinh']) ?> (đã lưu)</option>
                    <?php endif; ?>
                </select>
                <?= field_error('ma_tinh', $errors) ?>
            </div>
            <div class="form-group">
                <label for="ma_phuong_xa">Phường/Xã/Đặc khu</label>
                <select class="form-control" id="ma_phuong_xa" name="ma_phuong_xa"<?= !$item ? ' required' : '' ?><?= field_error_attributes('ma_phuong_xa', $errors) ?>>
                    <option value="">Chọn phường/xã</option>
                    <?php foreach ($provinces as $province): ?>
                        <optgroup label="<?= e($province['FullName']) ?>" data-province="<?= e($province['Code']) ?>">
                            <?php foreach ($province['Wards'] as $ward): ?>
                                <option value="<?= e($ward['Code']) ?>" <?= ($old['ma_phuong_xa'] ?? '') === $ward['Code'] ? 'selected' : '' ?>><?= e($ward['FullName']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                    <?php if (!empty($item['ma_phuong_xa'])): ?>
                        <optgroup label="Địa chỉ đã lưu" data-province="<?= e($item['ma_tinh']) ?>">
                            <option value="<?= e($item['ma_phuong_xa']) ?>" <?= ($old['ma_phuong_xa'] ?? '') === $item['ma_phuong_xa'] ? 'selected' : '' ?>><?= e($item['ten_phuong_xa']) ?> (đã lưu)</option>
                        </optgroup>
                    <?php endif; ?>
                </select>
                <?= field_error('ma_phuong_xa', $errors) ?>
            </div>
        </div>
        <p class="form-hint">Giữ địa chỉ đầy đủ bên dưới. Khi đổi tỉnh/phường, hãy rà soát lại địa chỉ. Cơ sở cũ có thể bổ sung địa phương sau.</p>
        <h2 class="admin-form-heading">Hình ảnh cơ sở</h2>
        <div class="form-group">
            <label for="anh_dai_dien">Ảnh đại diện (tùy chọn)</label>
            <input class="form-control" type="file" name="anh_dai_dien" id="anh_dai_dien" accept="image/jpeg,image/png,image/webp"<?= field_error_attributes('anh_dai_dien', $errors) ?>>
            <?= field_error('anh_dai_dien', $errors) ?>
            <p class="form-hint">JPEG, PNG hoặc WebP; tối đa 3 MB, 6000 px mỗi chiều và 20 megapixel. Nếu form có lỗi, vui lòng chọn lại file.</p>
            <?php $imagePath = $item['anh_dai_dien'] ?? null; ?>
            <img id="facility-image-preview" alt="Ảnh đại diện cơ sở" class="facility-photo" <?php if (App\Services\FacilityImage::validPath($imagePath)): ?>src="<?= e(base_path() . '/' . $imagePath) ?>"<?php else: ?>hidden<?php endif; ?>>
            <?php if ($imagePath): ?>
                <label><input type="checkbox" name="xoa_anh" value="1" <?= ($old['xoa_anh'] ?? '') === '1' ? 'checked' : '' ?>> Xóa ảnh hiện tại (ảnh mới được ưu tiên nếu tải lên)</label>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label for="logo_clb">Logo CLB (tùy chọn)</label>
            <input class="form-control" type="file" id="logo_clb" name="logo_clb" accept="image/jpeg,image/png,image/webp"<?= field_error_attributes('logo_clb', $errors) ?>>
            <?= field_error('logo_clb', $errors) ?>
            <p class="form-hint">JPEG, PNG hoặc WebP; tối đa 3 MB. Nên dùng ảnh vuông. Chọn lại file nếu form có lỗi.</p>
            <img id="facility-logo-preview" class="facility-photo" alt="Logo CLB" <?php if (App\Services\FacilityImage::validPath($item['logo_clb'] ?? null)): ?>src="<?= e(base_path() . '/' . $item['logo_clb']) ?>"<?php else: ?>hidden<?php endif; ?>>
            <?php if (!empty($item['logo_clb'])): ?><label><input type="checkbox" name="xoa_logo" value="1" <?= ($old['xoa_logo'] ?? '') === '1' ? 'checked' : '' ?>> Xóa logo hiện tại (logo mới được ưu tiên)</label><?php endif; ?>
        </div>
        <h2 class="admin-form-heading">Liên hệ &amp; hoạt động</h2>
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

<script src="<?= e(base_path() . '/assets/js/facility-form.js') ?>" defer></script>
