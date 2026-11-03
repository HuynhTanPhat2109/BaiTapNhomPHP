<?php
$bodyClass = 'page-catalog';
$facilityId = (int) $facility['ma_co_so'];
$selectedBankCode = $old['bank_code'] ?? ($item['ma_bin'] ?? '');
$currentAccNumber = $old['so_tai_khoan'] ?? ($item['so_tai_khoan'] ?? '');
$currentAccName = $old['ten_chu_tai_khoan'] ?? ($item['ten_chu_tai_khoan'] ?? '');
$isDefault = isset($old['la_mac_dinh']) ? !empty($old['la_mac_dinh']) : (!empty($item['la_mac_dinh']));
$isActive = ($old['trang_thai'] ?? ($item['trang_thai'] ?? 'HOAT_DONG')) === 'HOAT_DONG';
$formAction = $isEdit ? url('/facility/bank/update') : url('/facility/bank/store');
?>
<section class="container catalog-page catalog-workspace workspace-page">
    <header class="catalog-heading module-heading">
        <div>
            <p class="eyebrow">STRYKE / <?= e($facility['ten_co_so']) ?></p>
            <h1><?= $isEdit ? 'Chỉnh sửa tài khoản ngân hàng' : 'Thêm tài khoản ngân hàng nhận tiền' ?></h1>
        </div>
        <a class="btn btn--outline admin-back-link" href="<?= e(url('/facility/bank') . '&facility_id=' . $facilityId) ?>">
            Quay lại danh sách
        </a>
    </header>

    <?php if (!empty($errors)): ?>
        <div class="alert alert--danger" role="alert">
            <ul>
                <?php foreach ($errors as $field => $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div>
        <form class="catalog-form module-form" method="post" action="<?= e($formAction) ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="facility_id" value="<?= e($facilityId) ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= e($item['ma_tk_ngan_hang']) ?>">
            <?php endif; ?>

            <section class="module-form-card"><h2 class="admin-form-heading">Thông tin ngân hàng</h2><div class="module-fields">
            <div class="form-group">
                <label for="bank_code">
                    Ngân hàng thụ hưởng <span>*</span>
                </label>
                <select class="form-control" id="bank_code" name="bank_code" required>
                    <option value="">-- Chọn ngân hàng trong hệ thống VietQR --</option>
                    <?php foreach ($banks as $b): ?>
                        <?php
                            $bCode = $b['code'] ?? $b['bin'];
                            $bBin = $b['bin'];
                            $isSelected = ($selectedBankCode === $bCode || $selectedBankCode === $bBin);
                            $displayName = ($b['shortName'] ?? $b['code']) . ' - ' . ($b['name'] ?? '');
                        ?>
                        <option value="<?= e($bCode) ?>" data-bin="<?= e($bBin) ?>" <?= $isSelected ? 'selected' : '' ?>>
                            <?= e($displayName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-hint">Hệ thống tự động liên kết mã định danh chuẩn VietQR/NAPAS 247 khi bạn chọn ngân hàng.</small>
            <?= field_error('bank_code', $errors ?? []) ?>
            </div>

            <div class="form-group">
                <label for="so_tai_khoan">
                    Số tài khoản ngân hàng <span>*</span>
                </label>
                <input class="form-control" id="so_tai_khoan" name="so_tai_khoan" type="text"
                       required placeholder="Ví dụ: 0123456789"
                       value="<?= e($currentAccNumber) ?>"
                      >
            <?= field_error('so_tai_khoan', $errors ?? []) ?>
            </div>

            <div class="form-group">
                <label for="ten_chu_tai_khoan">
                    Tên chủ tài khoản <span>*</span>
                </label>
                <input class="form-control" id="ten_chu_tai_khoan" name="ten_chu_tai_khoan" type="text"
                       required placeholder="Ví dụ: NGUYEN VAN A (in hoa không dấu)"
                       value="<?= e($currentAccName) ?>"
                       oninput="this.value = this.value.toUpperCase()"
                      >
                <small class="form-hint">Nên nhập tên in hoa không dấu giống như trên thẻ ATM hoặc App ngân hàng.</small>
            <?= field_error('ten_chu_tai_khoan', $errors ?? []) ?>
            </div>

            <div class="form-group">
                <label class="workspace-check"><input type="checkbox" name="la_mac_dinh" value="1" <?= $isDefault ? 'checked' : '' ?>>
                    <span>Đặt làm tài khoản mặc định nhận thanh toán của cơ sở</span>
                </label>
            </div>

            <div class="form-group">
                <label class="workspace-check"><input type="checkbox" name="trang_thai" value="HOAT_DONG" <?= $isActive ? 'checked' : '' ?>>
                    <span>Kích hoạt sử dụng</span>
                </label>
            </div>

            </div></section>
            <div class="catalog-actions">
                <a class="btn btn--outline admin-back-link" href="<?= e(url('/facility/bank') . '&facility_id=' . $facilityId) ?>">
                    Hủy bỏ
                </a>
                <button type="submit" class="btn btn--primary">
                    <?= $isEdit ? 'Lưu thay đổi' : 'Thêm tài khoản' ?>
                </button>
            </div>
        </form>
    </div>
</section>
