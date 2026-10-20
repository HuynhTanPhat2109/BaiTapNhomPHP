<?php $bodyClass = 'page-auth'; $errors = $errors ?? []; ?>
<div class="container auth-page">
    <div class="auth-shell auth-shell--register" data-auth-shell>
        <?php require __DIR__ . '/../components/auth-visual.php'; ?>
        <section class="auth-panel" aria-labelledby="auth-title">
            <div class="auth-panel__inner">
                <nav class="auth-tabs" aria-label="Tài khoản">
                    <a href="<?= e(url('/login')) ?>" data-auth-link>Đăng nhập</a>
                    <a href="<?= e(url('/register')) ?>" aria-current="page">Đăng ký</a>
                </nav>
                <div class="auth-form-hd"><p class="eyebrow">Bắt đầu cùng STRYKE</p><h1 id="auth-title">Đăng ký tài khoản</h1></div>
                <?php if (!empty($formError)): ?><div class="alert alert--danger" role="alert"><?= e($formError) ?></div><?php endif; ?>
                <form method="post" action="<?= e(url('/register')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="username">Tên đăng nhập</label>
                            <input class="form-control" id="username" name="username" minlength="6" maxlength="15" autocomplete="username" required<?= field_error_attributes('username', $errors) ?> value="<?= e($old['username'] ?? '') ?>">
                            <?= field_error('username', $errors) ?>
                        </div>
                        <div class="form-group">
                            <label for="name">Họ và tên</label>
                            <input class="form-control" id="name" name="name" maxlength="150" autocomplete="name" required<?= field_error_attributes('name', $errors) ?> value="<?= e($old['name'] ?? '') ?>">
                            <?= field_error('name', $errors) ?>
                        </div>
                        <div class="form-group">
                            <label for="phone">Số điện thoại</label>
                            <input class="form-control" id="phone" type="tel" name="phone" inputmode="numeric" pattern="0[0-9]{9}" minlength="10" maxlength="10" autocomplete="tel" title="Số điện thoại phải gồm đúng 10 chữ số và bắt đầu bằng 0." required<?= field_error_attributes('phone', $errors) ?> value="<?= e($old['phone'] ?? '') ?>">
                            <?= field_error('phone', $errors) ?>
                        </div>
                        <div class="form-group">
                            <label for="email">Email <span class="optional">(tùy chọn)</span></label>
                            <input class="form-control" id="email" type="email" name="email" maxlength="150" autocomplete="email"<?= field_error_attributes('email', $errors) ?> value="<?= e($old['email'] ?? '') ?>">
                            <?= field_error('email', $errors) ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="password">Mật khẩu</label>
                        <div class="password-control">
                            <input class="form-control" id="password" type="password" name="password" autocomplete="new-password" required<?= field_error_attributes('password', $errors) ?><?= isset($errors['password']) ? '' : ' aria-describedby="password-help"' ?>>
                            <button class="pw-toggle" type="button" data-target="password" aria-label="Hiện mật khẩu" hidden></button>
                        </div>
                        <?= field_error('password', $errors) ?>
                        <?php if (!isset($errors['password'])): ?><small class="form-hint" id="password-help">8–16 ký tự, gồm chữ hoa, chữ thường, số và ký tự đặc biệt.</small><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="password_confirmation">Nhập lại mật khẩu</label>
                        <div class="password-control">
                            <input class="form-control" id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required<?= field_error_attributes('password_confirmation', $errors) ?>>
                            <button class="pw-toggle" type="button" data-target="password_confirmation" aria-label="Hiện mật khẩu" hidden></button>
                        </div>
                        <?= field_error('password_confirmation', $errors) ?>
                    </div>
                    <button type="submit" class="btn btn--primary btn--full auth-submit">Tạo tài khoản <span aria-hidden="true">↗</span></button>
                </form>
                <p class="auth-foot">Đã là một phần của STRYKE? <a href="<?= e(url('/login')) ?>" data-auth-link>Đăng nhập</a></p>
            </div>
        </section>
    </div>
</div>
