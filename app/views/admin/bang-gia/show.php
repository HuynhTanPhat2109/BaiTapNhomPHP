<?php
$bodyClass = 'page-catalog';
$active = $item['trang_thai'] === 'DANG_AP_DUNG';
$formatPrice = require __DIR__ . '/format-price.php';
$detailPath = '/admin/bang-gia'; $detailLabel = 'Bảng giá';
$detailTitle = $item['ten_bang_gia']; $detailId = $item['ma_bang_gia']; $detailCanEdit = true;
$detailStatusLabel = $active ? 'Đang áp dụng' : 'Ngừng áp dụng';
?>
<section class="container catalog-page catalog-workspace detail-page">
    <?php require dirname(__DIR__) . '/partials/detail-header.php'; ?>
    <?php if ($errors): ?><div class="alert alert--danger" role="alert"><p>Chưa thể thay đổi trạng thái:</p><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if ($item['trang_thai_co_so'] !== 'HOAT_DONG' || $item['trang_thai_cong_ty'] !== 'HOAT_DONG' || $item['trang_thai_loai_san'] !== 'HOAT_DONG' || $item['trang_thai_bo_mon'] !== 'HOAT_DONG'): ?><p class="alert" role="status">Dữ liệu cha đã ngừng hoạt động. Chưa đủ điều kiện kích hoạt bảng giá.</p><?php endif; ?>
    <div class="detail-grid">
        <section class="detail-card" aria-labelledby="price-scope">
            <h2 id="price-scope">Phạm vi áp dụng</h2>
            <dl class="detail-fields">
                <div class="detail-field--wide"><dt>Tên bảng giá</dt><dd><?= e($item['ten_bang_gia']) ?></dd></div>
                <div class="detail-field--wide"><dt>Cơ sở</dt><dd><a href="<?= e(url('/admin/co-so/show') . '&id=' . $item['ma_co_so']) ?>"><?= e($item['ten_co_so']) ?></a></dd></div>
                <div class="detail-field--wide"><dt>Công ty</dt><dd><?= e($item['ten_cong_ty']) ?></dd></div>
                <div class="detail-field--wide"><dt>Bộ môn — Loại sân</dt><dd><a href="<?= e(url('/admin/loai-san/show') . '&id=' . $item['ma_loai_san']) ?>"><?= e($item['ten_bo_mon'] . ' — ' . $item['ten_loai_san']) ?></a></dd></div>
                <div><dt>Mã bảng giá</dt><dd>#<?= e($item['ma_bang_gia']) ?></dd></div>
                <div><dt>Loại ngày</dt><dd><?= $item['loai_ngay'] === 'CUOI_TUAN' ? 'Cuối tuần (Thứ Bảy, Chủ nhật)' : 'Ngày thường (Thứ Hai đến Thứ Sáu)' ?></dd></div>
            </dl>
        </section>
        <section class="detail-card" aria-labelledby="price-time">
            <h2 id="price-time">Đơn giá &amp; thời gian</h2>
            <div class="detail-highlight"><span>Đơn giá theo giờ</span><strong><?= e($formatPrice($item['don_gia'])) ?></strong></div>
            <dl class="detail-fields">
                <div class="detail-field--wide"><dt>Khung giờ áp dụng</dt><dd class="detail-emphasis"><?= e(substr($item['gio_bat_dau'], 0, 5)) ?> – <?= e(substr($item['gio_ket_thuc'], 0, 5)) ?></dd></div>
                <div><dt>Ngày bắt đầu</dt><dd><?= e($item['ngay_bat_dau']) ?></dd></div>
                <div><dt>Ngày kết thúc</dt><dd><?= e($item['ngay_ket_thuc'] ?? 'Không thời hạn') ?></dd></div>
            </dl>
            <p class="form-hint">Thời hạn áp dụng tính cả ngày bắt đầu và ngày kết thúc.</p>
        </section>
    </div>
    <section class="account-card detail-action-panel" data-action="<?= $active ? 'stop' : 'activate' ?>" id="status-confirmation" aria-labelledby="status-heading">
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
