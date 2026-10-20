<?php $passwordErrors = $passwordErrors ?? []; ?>
<section class="container account-page account-settings-page">
    <div class="account-page__intro">
        <p class="eyebrow">STRYKE / Tài khoản / Cài đặt</p>
        <h1 class="account-h1">Cài đặt tài khoản.</h1>
        <p>Quản lý mật khẩu cho tài khoản <?= e($user['ten_dang_nhap'] ?? '') ?>.</p>
    </div>
    <?php if (!empty($notice)): ?><div class="alert alert--success account-notice" role="status"><?= e($notice) ?></div><?php endif; ?>
    <section class="account-card" aria-labelledby="settings-title">
        <div class="account-card__heading"><div><h2 id="settings-title">Đổi mật khẩu</h2></div></div>
        <br>
        <form method="post" action="<?= e(url('/account/password')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="current-password">Mật khẩu hiện tại</label>
                <div class="password-control"><input class="form-control" id="current-password" type="password" name="current_password" autocomplete="current-password" required<?= field_error_attributes('current_password', $passwordErrors) ?>><button class="pw-toggle" type="button" data-target="current-password" aria-label="Hiện mật khẩu" hidden></button></div>
                <?= field_error('current_password', $passwordErrors) ?>
            </div>
            <div class="account-form-grid">
                <div class="form-group">
                    <label for="new-password">Mật khẩu mới</label>
                    <div class="password-control"><input class="form-control" id="new-password" type="password" name="new_password" autocomplete="new-password" required<?= field_error_attributes('new_password', $passwordErrors) ?>><button class="pw-toggle" type="button" data-target="new-password" aria-label="Hiện mật khẩu" hidden></button></div>
                    <?= field_error('new_password', $passwordErrors) ?>
                </div>
                <div class="form-group">
                    <label for="new-password-confirmation">Nhập lại mật khẩu mới</label>
                    <div class="password-control"><input class="form-control" id="new-password-confirmation" type="password" name="new_password_confirmation" autocomplete="new-password" required<?= field_error_attributes('new_password_confirmation', $passwordErrors) ?>><button class="pw-toggle" type="button" data-target="new-password-confirmation" aria-label="Hiện mật khẩu" hidden></button></div>
                    <?= field_error('new_password_confirmation', $passwordErrors) ?>
                </div>
            </div>
            <?php if (!isset($passwordErrors['new_password'])): ?><small class="form-hint account-password-hint">8–16 ký tự, gồm chữ hoa, chữ thường, số và ký tự đặc biệt.</small><?php endif; ?>
            <div class="account-settings-actions">
                <button class="btn btn--primary account-save" type="submit">Đổi mật khẩu</button>
            </div>
        </form>
    </section>
</section>
