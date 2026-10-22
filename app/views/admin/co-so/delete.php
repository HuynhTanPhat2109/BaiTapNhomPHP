<?php $bodyClass = 'page-catalog'; ?>
<section class="container catalog-page">
    <header class="catalog-heading"><div><p class="eyebrow">STRYKE / Cơ sở thể thao</p><h1>Xác nhận xóa cơ sở</h1></div></header>
    <?php if (!empty($formError)): ?><p class="alert alert--danger" role="alert"><?= e($formError) ?></p><?php endif; ?>
    <?php $blocked = $related['san'] || $related['nhan_vien'] || $related['bang_gia'] || $related['dat_san'] || $related['dich_vu']; ?>
    <div class="catalog-delete">
        <p>Bạn muốn xóa <strong><?= e($item['ten_co_so']) ?></strong>?</p>
        <p>Dữ liệu tham chiếu: <?= e($related['san']) ?> sân, <?= e($related['nhan_vien']) ?> nhân viên, <?= e($related['bang_gia']) ?> bảng giá, <?= e($related['dat_san']) ?> booking và <?= e($related['dich_vu']) ?> dịch vụ.</p>
        <?php if ($blocked): ?><p class="alert alert--danger" role="alert">Không thể xóa khi còn dữ liệu tham chiếu, kể cả dữ liệu đã ngừng hoặc là lịch sử.</p><?php else: ?><p>Thao tác này không thể hoàn tác.</p><?php endif; ?>
        <form method="post" action="<?= e(url('/admin/co-so/delete')) ?>">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($item['ma_co_so']) ?>">
            <div class="catalog-actions"><button class="btn btn--primary" type="submit" <?= $blocked ? 'disabled' : '' ?>>Xác nhận xóa</button><a class="btn btn--outline" href="<?= e(url('/admin/co-so')) ?>">Hủy</a></div>
        </form>
    </div>
</section>
