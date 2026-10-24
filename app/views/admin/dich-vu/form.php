<?php
$bodyClass = 'page-catalog';
$formatMoney = require dirname(__DIR__) . '/partials/format-money.php';
?>
<section class="container catalog-page service-module">
    <p class="service-breadcrumb"><a class="admin-table-link" href="<?= e(url($listPath)) ?>">Dịch vụ</a> / <?= $item ? 'Chỉnh sửa' : 'Thêm mới' ?></p>
    <header class="catalog-heading"><div><p class="eyebrow">STRYKE / Dịch vụ</p><h1><?= e($title) ?></h1></div></header>
    <?php if ($formError): ?><div class="alert alert--danger" role="alert"><p><?= e($formError) ?></p><p>Dữ liệu bạn nhập vẫn ở bên dưới. <a href="<?= e(url($listPath . '/edit') . '&id=' . ($item['ma_dich_vu'] ?? '')) ?>">Tải lại dữ liệu mới nhất</a> trước khi sửa tiếp.</p></div><?php endif; ?>
    <?php if (!$facilities): ?><p class="alert alert--danger" role="alert">Chưa có cơ sở/công ty hoạt động trong phạm vi được phép.</p><?php endif; ?>
    <form class="catalog-form service-form" method="post" action="<?= e(url($listPath . ($item ? '/update' : '/store'))) ?>" novalidate>
        <?= csrf_field() ?>
        <?php if ($item): ?><input type="hidden" name="id" value="<?= e($item['ma_dich_vu']) ?>"><input type="hidden" name="version" value="<?= e($old['version'] ?? '') ?>"><?php endif; ?>
        <div class="form-group">
            <label for="ma_co_so">Cơ sở *</label>
            <select class="form-control" id="ma_co_so" name="ma_co_so" required<?= field_error_attributes('ma_co_so', $errors) ?>>
                <option value="">Chọn cơ sở</option>
                <?php foreach ($facilities as $facility): ?><option value="<?= e($facility['ma_co_so']) ?>" <?= (string) ($old['ma_co_so'] ?? '') === (string) $facility['ma_co_so'] ? 'selected' : '' ?>><?= e($facility['ten_co_so'] . ' — ' . $facility['ten_cong_ty']) ?><?= $facility['trang_thai'] !== 'HOAT_DONG' || $facility['trang_thai_cong_ty'] !== 'HOAT_DONG' ? ' (Đã ngừng)' : '' ?></option><?php endforeach; ?>
            </select><?= field_error('ma_co_so', $errors) ?>
        </div>
        <div class="form-group"><label for="ten_dich_vu">Tên dịch vụ *</label><input class="form-control" id="ten_dich_vu" name="ten_dich_vu" maxlength="150" required value="<?= e($old['ten_dich_vu'] ?? '') ?>"<?= field_error_attributes('ten_dich_vu', $errors) ?>><?= field_error('ten_dich_vu', $errors) ?></div>
        <div class="account-form-grid">
            <div class="form-group"><label for="loai_dich_vu">Loại dịch vụ *</label><select class="form-control" id="loai_dich_vu" name="loai_dich_vu" required<?= field_error_attributes('loai_dich_vu', $errors) ?>><option value="">Chọn loại</option><?php foreach (App\Services\DichVuService::TYPES as $value => $label): ?><option value="<?= e($value) ?>" <?= ($old['loai_dich_vu'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= field_error('loai_dich_vu', $errors) ?></div>
            <div class="form-group"><label for="don_vi_tinh">Đơn vị tính *</label><input class="form-control" id="don_vi_tinh" name="don_vi_tinh" list="service-units" maxlength="30" required value="<?= e($old['don_vi_tinh'] ?? '') ?>"<?= field_error_attributes('don_vi_tinh', $errors) ?>><datalist id="service-units"><?php foreach (['chai', 'lon', 'cái', 'cây', 'bộ', 'buổi', 'lượt'] as $unit): ?><option value="<?= e($unit) ?>"><?php endforeach; ?></datalist><?= field_error('don_vi_tinh', $errors) ?></div>
            <div class="form-group"><label for="don_gia">Đơn giá (VNĐ/đơn vị) *</label><input class="form-control" id="don_gia" name="don_gia" inputmode="decimal" maxlength="13" placeholder="Ví dụ: 10000 hoặc 10000.50" required value="<?= e($old['don_gia'] ?? '') ?>"<?= field_error_attributes('don_gia', $errors) ?>><?= field_error('don_gia', $errors) ?><?php if (($old['don_gia'] ?? '') !== ''): ?><small class="form-hint"><?= e($formatMoney($old['don_gia'])) ?>/<?= e($old['don_vi_tinh'] ?? 'đơn vị') ?></small><?php endif; ?></div>
            <div class="form-group"><label for="so_luong_ton">Số lượng tồn</label><input class="form-control" id="so_luong_ton" name="so_luong_ton" inputmode="numeric" maxlength="10" value="<?= e($old['so_luong_ton'] ?? '') ?>"<?= field_error_attributes('so_luong_ton', $errors) ?>><?= field_error('so_luong_ton', $errors) ?><small class="form-hint">Bắt buộc nhập số nguyên khi theo dõi tồn; không dùng số thập phân.</small></div>
        </div>
        <fieldset class="service-stock-mode"><legend>Theo dõi tồn kho *</legend>
            <label><input type="radio" name="theo_doi_ton" value="1" <?= ($old['theo_doi_ton'] ?? '0') === '1' ? 'checked' : '' ?>> Theo dõi tồn kho</label>
            <label><input type="radio" name="theo_doi_ton" value="0" <?= ($old['theo_doi_ton'] ?? '0') === '0' ? 'checked' : '' ?>> Không theo dõi tồn kho (lưu NULL, bỏ qua ô số lượng)</label>
            <?= field_error('theo_doi_ton', $errors) ?>
        </fieldset>
        <p class="form-hint">Không thể đổi đơn vị nếu tồn hiện tại lớn hơn 0 hoặc có booking chưa hoàn tất. Loại dịch vụ cũng bị khóa khi có booking chưa hoàn tất. Dụng cụ cho thuê chưa được theo dõi giao/nhận trong PR4.</p>
        <div class="form-group"><label for="mo_ta">Mô tả</label><textarea class="form-control" id="mo_ta" name="mo_ta" maxlength="255" rows="3"<?= field_error_attributes('mo_ta', $errors) ?>><?= e($old['mo_ta'] ?? '') ?></textarea><?= field_error('mo_ta', $errors) ?></div>
        <div class="form-group">
            <?php if ($item): ?><input type="hidden" name="trang_thai" value="<?= e($old['trang_thai'] ?? $item['trang_thai']) ?>"><p>Trạng thái: <?= e(App\Services\DichVuService::STATES[$item['trang_thai']] ?? 'Không xác định') ?>. <a href="<?= e(url($listPath . '/show') . '&id=' . $item['ma_dich_vu']) ?>">Ngừng/Kích hoạt tại trang chi tiết</a>.</p>
            <?php else: ?><label for="trang_thai">Trạng thái *</label><select class="form-control" id="trang_thai" name="trang_thai"<?= field_error_attributes('trang_thai', $errors) ?>><?php foreach (App\Services\DichVuService::STATES as $value => $label): ?><option value="<?= e($value) ?>" <?= ($old['trang_thai'] ?? 'DANG_CUNG_CAP') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?php endif; ?>
            <?= field_error('trang_thai', $errors) ?>
        </div>
        <div class="catalog-actions"><button class="btn btn--primary" type="submit" <?= !$facilities ? 'disabled' : '' ?>>Lưu dịch vụ</button><a class="btn btn--outline" href="<?= e(url($listPath)) ?>">Hủy</a></div>
    </form>
</section>
