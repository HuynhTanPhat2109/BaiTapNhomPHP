<?php $bodyClass = 'page-auth'; $errors = $errors ?? []; ?>
<div class="container auth-page">
    <div class="auth-shell" data-auth-shell>
        <?php require __DIR__ . '/../components/auth-visual.php'; ?>
        <section class="auth-panel" aria-labelledby="auth-title">
            <div class="auth-panel__inner">
                <nav class="auth-tabs" aria-label="Tài khoản">
                    <a href="<?= e(url('/login')) ?>" aria-current="page">Đăng nhập</a>
                    <a href="<?= e(url('/register')) ?>" data-auth-link>Đăng ký</a>
                </nav>
                <div class="auth-form-hd"><p class="eyebrow">Chào mừng trở lại</p><h1 id="auth-title">Trở lại sân chơi.</h1><p>Đăng nhập để tiếp tục cùng STRYKE.</p></div>
                <?php if (!empty($notice)): ?><div class="alert alert--success" role="status"><?= e($notice) ?></div><?php endif; ?>
                <?php if (!empty($formError)): ?><div class="alert alert--danger" role="alert"><?= e($formError) ?></div><?php endif; ?>
                <form method="post" action="<?= e(url('/login')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="username">Tên đăng nhập</label>
                        <input class="form-control" id="username" name="username" maxlength="100" autocomplete="username" required<?= field_error_attributes('username', $errors) ?> value="<?= e($username ?? '') ?>">
                        <?= field_error('username', $errors) ?>
                    </div>
                    <div class="form-group">
                        <label for="password">Mật khẩu</label>
                        <div class="password-control">
                            <input class="form-control" id="password" type="password" name="password" autocomplete="current-password" required<?= field_error_attributes('password', $errors) ?>>
                            <button class="pw-toggle" type="button" data-target="password" aria-label="Hiện mật khẩu" hidden></button>
                        </div>
                        <?= field_error('password', $errors) ?>
                    </div>
                    <?php if (!empty($loginError)): ?><p class="field-error login-error" role="alert"><span aria-hidden="true">!</span> <?= e($loginError) ?></p><?php endif; ?>
                    <button type="submit" class="btn btn--primary btn--full auth-submit">Đăng nhập <span aria-hidden="true">↗</span></button>
                </form>
                <p class="auth-foot">Lần đầu đến STRYKE? <a href="<?= e(url('/register')) ?>" data-auth-link>Tạo tài khoản</a></p>
            </div>
        </section>
    </div>
</div>