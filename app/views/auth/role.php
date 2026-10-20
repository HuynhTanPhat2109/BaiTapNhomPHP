<section class="container inner-page role-page">
    <header class="page-heading"><p class="eyebrow">STRYKE / Khu vực của bạn</p><h1><?= e($title) ?></h1><p>Xin chào, <strong><?= e($user['ten_dang_nhap']) ?></strong>.</p></header>
    <p class="role-notice">Bạn đã đăng nhập vào khu vực dành cho vai trò của mình. Các chức năng nghiệp vụ sẽ được bổ sung ở giai đoạn tiếp theo.</p>
    <a class="btn btn--outline" href="<?= e(url('/account')) ?>"><span aria-hidden="true">←</span> Quay lại tài khoản</a>
</section>