<?php require __DIR__ . '/photo.php'; ?>
<div class="detail-identity"><?php require __DIR__ . '/logo.php'; ?><div><h3><?= e($facility['ten_co_so']) ?></h3><p><?= e($facility['ten_cong_ty']) ?></p></div></div>
<dl class="facility-facts">
    <div><dt>Địa chỉ</dt><dd><?= e($facility['dia_chi']) ?></dd></div>
    <div><dt>Khu vực</dt><dd><?= e(implode(' · ', array_filter([$facility['ten_phuong_xa'], $facility['ten_tinh']]))) ?: 'Chưa bổ sung' ?></dd></div>
    <div><dt>Giờ hoạt động</dt><dd><?= e(substr($facility['gio_mo_cua'], 0, 5)) ?> — <?= e(substr($facility['gio_dong_cua'], 0, 5)) ?></dd></div>
    <?php if ($facility['so_dien_thoai']): ?><div><dt>Điện thoại</dt><dd><?= e($facility['so_dien_thoai']) ?></dd></div><?php endif; ?>
    <?php if ($facility['email']): ?><div><dt>Email</dt><dd><?= e($facility['email']) ?></dd></div><?php endif; ?>
</dl>
<?php if ($facility['mo_ta']): ?><p class="courts-description"><?= e($facility['mo_ta']) ?></p><?php endif; ?>
<h3>Sân &amp; bộ môn</h3>
<ul class="detail-courts"><?php foreach ($courts as $court): ?><li><strong><?= e($court['ten_san']) ?></strong><span><?= e($court['ten_bo_mon'] . ' · ' . $court['ten_loai_san']) ?></span><small><?= $court['trang_thai'] === 'HOAT_DONG' && $court['trang_thai_loai_san'] === 'HOAT_DONG' && $court['trang_thai_bo_mon'] === 'HOAT_DONG' ? 'Đang hoạt động' : 'Hiện không khả dụng' ?></small></li><?php endforeach; ?></ul>
<?php if (!$courts): ?><p>Chưa có sân để hiển thị.</p><?php endif; ?>
<div class="detail-book"><a class="btn btn--primary booking-choice-link" href="<?= e(url('/courts/schedule') . '&id=' . $facility['ma_co_so']) ?>">Đặt lịch ↗</a></div>
