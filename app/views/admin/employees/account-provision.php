<?php
$basePath ??= '/admin';
$provisionErrors ??= [];
$provisionOld ??= [];
$provisionRole = $actorRole ?? ($basePath === '/admin' ? 'QUAN_TRI_TAP_DOAN' : ($basePath === '/facility' ? 'QUAN_LY_CO_SO' : 'QUAN_LY_CONG_TY'));
$provisionRoles = match ($provisionRole) {
    'QUAN_TRI_TAP_DOAN' => ['QUAN_LY_CONG_TY' => 'Quản lý công ty'],
    'QUAN_LY_CO_SO' => ['NHAN_VIEN' => 'Nhân viên'],
    default => ['QUAN_LY_CO_SO' => 'Quản lý cơ sở', 'NHAN_VIEN' => 'Nhân viên'],
};
?>
<section class="module-form-card" aria-labelledby="employee-account-heading">
    <h2 class="admin-form-heading" id="employee-account-heading">Tài khoản đăng nhập</h2>
    <?php if (!empty($accountNotice)): ?><p class="alert alert--success" role="status"><?= e($accountNotice) ?></p><?php endif; ?>
    <?php if (($employee['ma_tai_khoan'] ?? null) !== null): ?>
        <p>Đã cấp tài khoản. Tên đăng nhập và mật khẩu hiện có được giữ nguyên.</p>
        <?php if ($provisionErrors): ?><p class="field-error" role="alert"><?= e($provisionErrors['account'] ?? 'Nhân viên đã có tài khoản; không thể cấp thêm.') ?></p><?php endif; ?>
    <?php elseif (($employee['trang_thai'] ?? '') !== 'DANG_LAM'): ?>
        <p>Chưa cấp tài khoản. Không cấp tài khoản cho nhân viên đã nghỉ việc.</p>
    <?php else: ?>
        <p>Chưa cấp tài khoản. Thao tác cấp tài khoản không lưu các thay đổi thông tin nhân sự ở form phía trên.</p>
        <details<?= $provisionErrors ? ' open' : '' ?>>
            <summary class="btn btn--outline">Cấp tài khoản</summary>
            <form class="catalog-form module-form workspace-form" method="post" action="<?= e(admin_context_url($basePath . '/employees/account')) ?>">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($employee['ma_nhan_vien']) ?>"><input type="hidden" name="cap_tai_khoan" value="1">
                <?php foreach (['account', 'ma_co_so', 'ma_cong_ty'] as $field): ?><?= field_error($field, $provisionErrors) ?><?php endforeach; ?>
                <div class="module-fields">
                    <div class="form-group"><label for="provision-username">Tên đăng nhập</label><input class="form-control" id="provision-username" name="username" autocomplete="off" required minlength="6" maxlength="15" value="<?= e($provisionOld['username'] ?? '') ?>"<?= field_error_attributes('username', $provisionErrors) ?>><?= field_error('username', $provisionErrors) ?></div>
                    <div class="form-group"><label for="provision-role">Vai trò tài khoản</label><select class="form-control" id="provision-role" name="role" required<?= field_error_attributes('role', $provisionErrors) ?>><option value="">Chọn vai trò</option><?php foreach ($provisionRoles as $code => $label): ?><option value="<?= e($code) ?>"<?= ($provisionOld['role'] ?? '') === $code ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= field_error('role', $provisionErrors) ?></div>
                    <div class="form-group"><label for="provision-password">Mật khẩu</label><div class="password-control employee-password"><input class="form-control" id="provision-password" name="password" type="password" autocomplete="new-password" required minlength="8" maxlength="16"<?= isset($provisionErrors['password']) ? field_error_attributes('password', $provisionErrors) : ' aria-describedby="provision-password-help"' ?>><button class="pw-toggle" type="button" data-target="provision-password" aria-label="Hiện mật khẩu" aria-controls="provision-password" aria-pressed="false" hidden></button></div><p class="form-hint" id="provision-password-help"><?= e(App\Services\AccountService::PASSWORD_REQUIREMENT) ?></p><?= field_error('password', $provisionErrors) ?></div>
                    <div class="form-group"><label for="provision-confirmation">Xác nhận mật khẩu</label><input class="form-control" id="provision-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8" maxlength="16"<?= field_error_attributes('password_confirmation', $provisionErrors) ?>><?= field_error('password_confirmation', $provisionErrors) ?></div>
                </div>
                <div class="catalog-actions"><button class="btn btn--primary" type="submit">Cấp tài khoản</button></div>
            </form>
        </details>
    <?php endif; ?>
</section>
