<?php $bodyClass = 'page-catalog';
$errors = $errors ?? [];
$old = $old ?? [];
$value = static function (array $data, string $field, string $default = ''): string {
    if (!array_key_exists($field, $data)) {
        return $default;
    }
    return is_scalar($data[$field]) ? (string) $data[$field] : '';
};
$status = $value($old, 'trang_thai', 'HOAT_DONG');
?>
<section class="container account-page catalog-page">
    <header class="account-page__intro catalog-heading">
        <p class="eyebrow">Quản trị tập đoàn / Công ty nhượng quyền</p>
        <h1 class="account-h1">Thêm công ty</h1>
    </header>
    <form class="catalog-form" method="post" action="<?= e(url('/admin/companies')) ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="ten_cong_ty">Tên công ty</label>
            <input class="form-control" id="ten_cong_ty" name="ten_cong_ty" required maxlength="200" value="<?= e($value($old, 'ten_cong_ty')) ?>"<?= field_error_attributes('ten_cong_ty', $errors) ?>>
            <?= field_error('ten_cong_ty', $errors) ?>
        </div>
        <div class="form-group">
            <label for="ma_so_thue">Mã số thuế</label>
            <input class="form-control" id="ma_so_thue" name="ma_so_thue" maxlength="30" value="<?= e($value($old, 'ma_so_thue')) ?>"<?= field_error_attributes('ma_so_thue', $errors) ?>>
            <?= field_error('ma_so_thue', $errors) ?>
        </div>
        <div class="form-group">
            <label for="nguoi_dai_dien">Người đại diện</label>
            <input class="form-control" id="nguoi_dai_dien" name="nguoi_dai_dien" maxlength="150" value="<?= e($value($old, 'nguoi_dai_dien')) ?>"<?= field_error_attributes('nguoi_dai_dien', $errors) ?>>
            <?= field_error('nguoi_dai_dien', $errors) ?>
        </div>
        <div class="form-group">
            <label for="dia_chi">Địa chỉ</label>
            <input class="form-control" id="dia_chi" name="dia_chi" required maxlength="255" value="<?= e($value($old, 'dia_chi')) ?>"<?= field_error_attributes('dia_chi', $errors) ?>>
            <?= field_error('dia_chi', $errors) ?>
        </div>
        <div class="form-group">
            <label for="so_dien_thoai">Số điện thoại</label>
            <input class="form-control" id="so_dien_thoai" name="so_dien_thoai" maxlength="20" value="<?= e($value($old, 'so_dien_thoai')) ?>"<?= field_error_attributes('so_dien_thoai', $errors) ?>>
            <?= field_error('so_dien_thoai', $errors) ?>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input class="form-control" id="email" type="email" name="email" maxlength="150" value="<?= e($value($old, 'email')) ?>"<?= field_error_attributes('email', $errors) ?>>
            <?= field_error('email', $errors) ?>
        </div>
        <div class="form-group">
            <label for="ngay_tham_gia">Ngày tham gia</label>
            <input class="form-control" id="ngay_tham_gia" type="date" name="ngay_tham_gia" value="<?= e($value($old, 'ngay_tham_gia')) ?>"<?= field_error_attributes('ngay_tham_gia', $errors) ?>>
            <?= field_error('ngay_tham_gia', $errors) ?>
        </div>
        <div class="form-group">
            <label for="trang_thai">Trạng thái</label>
            <select class="form-control" id="trang_thai" name="trang_thai" required<?= field_error_attributes('trang_thai', $errors) ?>>
                <option value="" <?= $status === '' ? 'selected' : '' ?>>Chọn trạng thái</option>
                <option value="HOAT_DONG" <?= $status === 'HOAT_DONG' ? 'selected' : '' ?>>Hoạt động</option>
                <option value="NGUNG_HOAT_DONG" <?= $status === 'NGUNG_HOAT_DONG' ? 'selected' : '' ?>>Ngừng hoạt động</option>
            </select>
            <?= field_error('trang_thai', $errors) ?>
        </div>
        <div class="catalog-actions"><button class="btn btn--primary" type="submit">Lưu công ty</button>
        <a class="btn btn--outline" href="<?= e(url('/admin/companies')) ?>">Quay lại</a></div>
    </form>
</section>
