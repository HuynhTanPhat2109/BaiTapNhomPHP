<?php
$errors = $errors ?? [];
$old = $old ?? [];
$companies = $companies ?? [];
$contract = $contract ?? [];
$value = static function (array $data, array $fallback, string $field, string $default = ''): string {
    if (array_key_exists($field, $data)) {
        return is_scalar($data[$field]) ? (string) $data[$field] : '';
    }
    if (isset($fallback[$field]) && is_scalar($fallback[$field])) {
        return (string) $fallback[$field];
    }
    return $default;
};
$status = $value($old, $contract, 'trang_thai');
$id = $contract['ma_hop_dong'] ?? '';
?>
<section class="container account-page">
    <div class="account-page__intro">
        <p class="eyebrow">Quản trị tập đoàn / Hợp đồng nhượng quyền</p>
        <h1 class="account-h1">Sửa hợp đồng</h1>
    </div>
    <form method="post" action="<?= e(url('/admin/contracts/update') . '&id=' . rawurlencode((string) $id)) ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="ma_cong_ty">Công ty</label>
            <select class="form-control" id="ma_cong_ty" name="ma_cong_ty" required<?= field_error_attributes('ma_cong_ty', $errors) ?>>
                <option value="">Chọn công ty</option>
                <?php foreach ($companies as $company): ?>
                    <?php $companyId = (string) ($company['ma_cong_ty'] ?? ''); ?>
                    <option value="<?= e($companyId) ?>" <?= $value($old, $contract, 'ma_cong_ty') === $companyId ? 'selected' : '' ?>><?= e($company['ten_cong_ty'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error('ma_cong_ty', $errors) ?>
        </div>
        <div class="form-group">
            <label for="so_hop_dong">Số hợp đồng</label>
            <input class="form-control" id="so_hop_dong" name="so_hop_dong" type="text" required maxlength="50" value="<?= e($value($old, $contract, 'so_hop_dong')) ?>"<?= field_error_attributes('so_hop_dong', $errors) ?>>
            <?= field_error('so_hop_dong', $errors) ?>
        </div>
        <div class="form-group">
            <label for="ngay_ky">Ngày ký</label>
            <input class="form-control" id="ngay_ky" name="ngay_ky" type="date" required value="<?= e($value($old, $contract, 'ngay_ky')) ?>"<?= field_error_attributes('ngay_ky', $errors) ?>>
            <?= field_error('ngay_ky', $errors) ?>
        </div>
        <div class="form-group">
            <label for="ngay_bat_dau">Ngày bắt đầu</label>
            <input class="form-control" id="ngay_bat_dau" name="ngay_bat_dau" type="date" required value="<?= e($value($old, $contract, 'ngay_bat_dau')) ?>"<?= field_error_attributes('ngay_bat_dau', $errors) ?>>
            <?= field_error('ngay_bat_dau', $errors) ?>
        </div>
        <div class="form-group">
            <label for="ngay_ket_thuc">Ngày kết thúc</label>
            <input class="form-control" id="ngay_ket_thuc" name="ngay_ket_thuc" type="date" required value="<?= e($value($old, $contract, 'ngay_ket_thuc')) ?>"<?= field_error_attributes('ngay_ket_thuc', $errors) ?>>
            <?= field_error('ngay_ket_thuc', $errors) ?>
        </div>
        <div class="form-group">
            <label for="phi_nhuong_quyen">Phí nhượng quyền</label>
            <input class="form-control" id="phi_nhuong_quyen" name="phi_nhuong_quyen" type="number" required min="0" step="0.01" value="<?= e($value($old, $contract, 'phi_nhuong_quyen')) ?>"<?= field_error_attributes('phi_nhuong_quyen', $errors) ?>>
            <?= field_error('phi_nhuong_quyen', $errors) ?>
        </div>
        <div class="form-group">
            <label for="ty_le_phi">Tỷ lệ phí (%)</label>
            <input class="form-control" id="ty_le_phi" name="ty_le_phi" type="number" required min="0" max="100" step="0.01" value="<?= e($value($old, $contract, 'ty_le_phi')) ?>"<?= field_error_attributes('ty_le_phi', $errors) ?>>
            <?= field_error('ty_le_phi', $errors) ?>
        </div>
        <div class="form-group">
            <label for="noi_dung">Nội dung</label>
            <textarea class="form-control" id="noi_dung" name="noi_dung"<?= field_error_attributes('noi_dung', $errors) ?>><?= e($value($old, $contract, 'noi_dung')) ?></textarea>
            <?= field_error('noi_dung', $errors) ?>
        </div>
        <div class="form-group">
            <label for="trang_thai">Trạng thái</label>
            <select class="form-control" id="trang_thai" name="trang_thai" required<?= field_error_attributes('trang_thai', $errors) ?>>
                <option value="HIEU_LUC" <?= $status === 'HIEU_LUC' ? 'selected' : '' ?>>Hiệu lực</option>
                <option value="HET_HAN" <?= $status === 'HET_HAN' ? 'selected' : '' ?>>Hết hạn</option>
                <option value="CHAM_DUT" <?= $status === 'CHAM_DUT' ? 'selected' : '' ?>>Chấm dứt</option>
            </select>
            <?= field_error('trang_thai', $errors) ?>
        </div>
        <button class="btn btn--primary" type="submit">Lưu thay đổi</button>
        <a class="btn btn--small" href="<?= e(url('/admin/contracts')) ?>">Quay lại</a>
    </form>
</section>
