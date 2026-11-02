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
<section class="container catalog-page catalog-workspace" style="max-width: 720px; margin: 30px auto;">
    <header class="catalog-heading" style="margin-bottom: 24px;">
        <div>
            <p class="eyebrow">STRYKE / <?= e($facility['ten_co_so']) ?></p>
            <h1><?= $isEdit ? 'Chỉnh sửa tài khoản ngân hàng' : 'Thêm tài khoản ngân hàng nhận tiền' ?></h1>
        </div>
        <a class="btn btn--secondary" href="<?= e(url('/facility/bank?facility_id=' . $facilityId)) ?>">
            Quay lại
        </a>
    </header>

    <?php if (!empty($errors)): ?>
        <div class="alert alert--danger" role="alert" style="margin-bottom: 20px; padding: 12px 16px; background: #ffebee; color: #c62828; border-radius: 6px;">
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($errors as $field => $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="auth-panel" style="background: #fff; padding: 28px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <form method="post" action="<?= e($formAction) ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="facility_id" value="<?= e($facilityId) ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= e($item['ma_tk_ngan_hang']) ?>">
            <?php endif; ?>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="bank_code" style="display: block; font-weight: 600; margin-bottom: 8px;">
                    Ngân hàng thụ hưởng <span style="color: red;">*</span>
                </label>
                <select class="form-control" id="bank_code" name="bank_code" required style="width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 1rem;">
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
                <small style="color: #666; display: block; margin-top: 4px;">Hệ thống tự động liên kết mã định danh chuẩn VietQR/NAPAS 247 khi bạn chọn ngân hàng.</small>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="so_tai_khoan" style="display: block; font-weight: 600; margin-bottom: 8px;">
                    Số tài khoản ngân hàng <span style="color: red;">*</span>
                </label>
                <input class="form-control" id="so_tai_khoan" name="so_tai_khoan" type="text"
                       required placeholder="Ví dụ: 0123456789"
                       value="<?= e($currentAccNumber) ?>"
                       style="width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 6px; font-family: monospace; font-size: 1.1rem;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="ten_chu_tai_khoan" style="display: block; font-weight: 600; margin-bottom: 8px;">
                    Tên chủ tài khoản <span style="color: red;">*</span>
                </label>
                <input class="form-control" id="ten_chu_tai_khoan" name="ten_chu_tai_khoan" type="text"
                       required placeholder="Ví dụ: NGUYEN VAN A (in hoa không dấu)"
                       value="<?= e($currentAccName) ?>"
                       oninput="this.value = this.value.toUpperCase()"
                       style="width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 1rem; text-transform: uppercase;">
                <small style="color: #666; display: block; margin-top: 4px;">Nên nhập tên in hoa không dấu giống như trên thẻ ATM hoặc App ngân hàng.</small>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="la_mac_dinh" value="1" <?= $isDefault ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                    <span style="font-weight: 500;">Đặt làm tài khoản mặc định nhận thanh toán của cơ sở</span>
                </label>
            </div>

            <div class="form-group" style="margin-bottom: 28px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="trang_thai" value="HOAT_DONG" <?= $isActive ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                    <span style="font-weight: 500;">Kích hoạt sử dụng</span>
                </label>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a class="btn btn--secondary" href="<?= e(url('/facility/bank?facility_id=' . $facilityId)) ?>" style="padding: 10px 20px; text-decoration: none; border: 1px solid #ccc; border-radius: 6px; color: #333;">
                    Hủy bỏ
                </a>
                <button type="submit" class="btn btn--primary" style="padding: 10px 24px; background: #1a73e8; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">
                    <?= $isEdit ? 'Lưu thay đổi' : 'Thêm tài khoản' ?>
                </button>
            </div>
        </form>
    </div>
</section>
