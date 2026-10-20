<?php
$code = (int) ($title ?? 500);
$headings = [403 => 'Bạn chưa có quyền truy cập.', 404 => 'Có vẻ bạn đã đi lạc sân.', 405 => 'Yêu cầu chưa đúng cách.', 500 => 'Một chút gián đoạn.'];
?>
<section class="container error-page">
    <p class="eyebrow">STRYKE / Thông báo</p>
    <div class="error-page__code"><?= e($code) ?></div>
    <h1><?= e($headings[$code] ?? 'Đã xảy ra lỗi.') ?></h1>
    <p role="alert"><?= e($message ?? 'Hệ thống tạm thời không khả dụng. Vui lòng thử lại sau.') ?></p>
    <div class="error-page__actions"><a class="btn btn--primary" href="<?= e(url('/')) ?>">Về trang chủ <span aria-hidden="true">↗</span></a><?php if ($code === 403): ?><a class="btn btn--outline" href="<?= e(url('/login')) ?>">Đăng nhập</a><?php endif; ?></div>
</section>