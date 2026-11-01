<?php
$backRoute = $template === 'courts/index' ? '/' : '/courts';
$backLabel = $template === 'courts/index' ? 'Trang chủ' : '';
$backUrl = $bookingBackUrl ?? url($backRoute);
$cancelHold = $template === 'courts/booking-wait';
$backAccessibleLabel = isset($bookingBackUrl) ? 'Về lịch sân' : ($template === 'courts/index' ? 'Về trang chủ' : 'Về danh sách cơ sở');
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#101411">
    <title><?= e($title ?? 'Tìm sân') ?> — STRYKE</title>
    <link rel="stylesheet" href="<?= e(base_path() . '/assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= e(base_path() . '/assets/css/courts.css') ?>">
    <link rel="stylesheet" href="<?= e(base_path() . '/assets/css/court-flow.css') ?>">
    <script src="<?= e(base_path() . '/assets/js/courts.js') ?>" defer></script>
</head>
<body class="court-flow">
<a class="skip-link" href="#main-content">Bỏ qua điều hướng</a>
<header class="court-flow-bar">
    <?php if ($cancelHold): ?>
    <form action="<?= e(url('/booking/cancel')) ?>" method="post" class="court-flow-cancel" data-booking-cancel>
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($booking['ma_dat_san']) ?>">
        <button class="court-flow-back" type="submit" aria-label="Hủy giữ sân và về lịch sân" title="Về lịch sân">
    <?php else: ?>
    <a class="court-flow-back" href="<?= e($backUrl) ?>" aria-label="<?= e($backAccessibleLabel) ?>" <?= isset($bookingBackUrl) ? 'data-booking-back' : '' ?>>
    <?php endif; ?>
        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><!--!Font Awesome Free v7.3.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M169.4 297.4C156.9 309.9 156.9 330.2 169.4 342.7L361.4 534.7C373.9 547.2 394.2 547.2 406.7 534.7C419.2 522.2 419.2 501.9 406.7 489.4L237.3 320L406.6 150.6C419.1 138.1 419.1 117.8 406.6 105.3C394.1 92.8 373.8 92.8 361.3 105.3L169.3 297.3z"/></svg> <?= e($backLabel) ?>
    <?php if ($cancelHold): ?></button></form><?php else: ?></a><?php endif; ?>
    <?php
    $isVisualBooking = in_array($template, [
        'courts/schedule',
        'courts/booking-details',
        'courts/booking-wait',
    ], true);
    ?>

    <?php if ($isVisualBooking): ?>
        <span class="court-flow-title">ĐẶT LỊCH THEO SÂN - TRỰC QUAN</span>
    <?php else: ?>
        <a class="court-flow-brand" href="<?= e(url('/')) ?>" aria-label="STRYKE — Trang chủ">
            STRYKE<span>.</span>
        </a>
    <?php endif; ?>
</header>
<main id="main-content" tabindex="-1"><?= $content ?></main>
</body>
</html>
