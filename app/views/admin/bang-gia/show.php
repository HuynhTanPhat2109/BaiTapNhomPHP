<?php
$bodyClass = 'page-catalog';
$active = $item['trang_thai'] === 'DANG_AP_DUNG';
$formatPrice = require __DIR__ . '/format-price.php';
?>
<section class="container catalog-page">
    <header class="catalog-heading">
        <div><p class="eyebrow">STRYKE / Chi tiết bảng giá</p><h1><?= e($item['ten_bang_gia']) ?></h1></div>
        <a class="btn btn--primary" href="<?= e(url('/admin/bang-gia/edit') . '&id=' . $item['ma_bang_gia']) ?>">Sửa bảng giá</a>
    </header>
    <?php if ($errors): ?><div class="alert alert--danger" role="alert"><p>Chưa thể thay đổi trạng thái:</p><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if ($item['trang_thai_co_so'] !== 'HOAT_DONG' || $item['trang_thai_cong_ty'] !== 'HOAT_DONG' || $item['trang_thai_loai_san'] !== 'HOAT_DONG' || $item['trang_thai_bo_mon'] !== 'HOAT_DONG'): ?><p class="alert" role="status">Dữ liệu cha đã ngừng hoạt động. Chưa đủ điều kiện kích hoạt bảng giá.</p><?php endif; ?>
    <dl class="catalog-details">
        <div><dt>Mã bảng giá</dt><dd>#<?= e($item['ma_bang_gia']) ?></dd></div>
        <div><dt>Cơ sở</dt><dd><a href="<?= e(url('/admin/co-so/show') . '&id=' . $item['ma_co_so']) ?>"><?= e($item['ten_co_so']) ?></a></dd></div>
        <div><dt>Công ty</dt><dd><?= e($item['ten_cong_ty']) ?></dd></div>
        <div><dt>Bộ môn — Loại sân</dt><dd><a href="<?= e(url('/admin/loai-san/show') . '&id=' . $item['ma_loai_san']) ?>"><?= e($item['ten_bo_mon'] . ' — ' . $item['ten_loai_san']) ?></a></dd></div>
        <div><dt>Loại ngày</dt><dd><?= $item['loai_ngay'] === 'CUOI_TUAN' ? 'Cuối tuần (Thứ Bảy, Chủ nhật)' : 'Ngày thường (Thứ Hai đến Thứ Sáu)' ?></dd></div>
        <div><dt>Khung giờ</dt><dd><?= e(substr($item['gio_bat_dau'], 0, 5)) ?>–<?= e(substr($item['gio_ket_thuc'], 0, 5)) ?></dd></div>
        <div><dt>Đơn giá</dt><dd><?= e($formatPrice($item['don_gia'])) ?></dd></div>
        <div><dt>Thời hạn áp dụng</dt><dd><?= e($item['ngay_bat_dau']) ?> đến <?= e($item['ngay_ket_thuc'] ?? 'Không thời hạn') ?> (tính cả hai ngày)</dd></div>
        <div><dt>Trạng thái</dt><dd><span class="catalog-status"><?= $active ? 'Đang áp dụng' : 'Ngừng áp dụng' ?></span></dd></div>
    </dl>
    <section class="account-card" id="status-confirmation" aria-labelledby="status-heading">
        <h2 id="status-heading"><?= $active ? 'Ngừng áp dụng bảng giá' : 'Kích hoạt lại bảng giá' ?></h2>
        <p class="account-card__description">Thao tác không thay đổi giá đã chốt trong chi tiết đặt sân, hóa đơn hoặc thanh toán. Không xóa bản ghi bảng giá.</p>
        <?php if ($active && !$confirm): ?>
            <a class="btn btn--outline" href="<?= e(url('/admin/bang-gia/show') . '&id=' . $item['ma_bang_gia'] . '&confirm=1#status-confirmation') ?>">Ngừng áp dụng…</a>
        <?php else: ?>
            <form method="post" action="<?= e(url('/admin/bang-gia/status')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e($item['ma_bang_gia']) ?>">
                <input type="hidden" name="trang_thai" value="<?= $active ? 'NGUNG' : 'DANG_AP_DUNG' ?>">
                <?php if ($active): ?>
                    <div class="form-group"><label><input type="checkbox" name="confirmed" value="1" required<?= field_error_attributes('confirmed', $errors) ?>> Tôi xác nhận ngừng áp dụng bảng giá này.</label><?= field_error('confirmed', $errors) ?></div>
                <?php else: ?><p class="form-hint">Hệ thống sẽ kiểm tra lại dữ liệu, trạng thái cha và toàn bộ khoảng giá chồng lấn trước khi kích hoạt.</p><?php endif; ?>
                <div class="catalog-actions"><button class="btn btn--primary" type="submit"><?= $active ? 'Xác nhận ngừng áp dụng' : 'Kích hoạt bảng giá' ?></button><?php if ($active): ?><a class="btn btn--outline" href="<?= e(url('/admin/bang-gia/show') . '&id=' . $item['ma_bang_gia']) ?>">Hủy</a><?php endif; ?></div>
            </form>
        <?php endif; ?>
    </section>
    <a class="btn btn--outline" href="<?= e(url('/admin/bang-gia')) ?>">Quay lại danh sách</a>
</section>
