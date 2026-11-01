<div class="facility-cover"><?php require __DIR__ . '/photo.php'; ?><span class="facility-cover-caption">STRYKE / Gặp nhau trên sân</span></div>
<div class="facility-overview">
<div class="detail-identity"><?php require __DIR__ . '/logo.php'; ?><div><h2><?= e($facility['ten_co_so']) ?></h2><p><?= e($facility['ten_cong_ty']) ?></p></div></div>
<div class="facility-sports"><?php foreach (array_unique(array_column($courts, 'ten_bo_mon')) as $sport): ?><span><?= e($sport) ?></span><?php endforeach; ?></div>
<dl class="facility-overview-facts">
    <div><dt><span aria-hidden="true">⌖</span> Địa chỉ</dt><dd><?= e($facility['dia_chi']) ?></dd></div>
    <div><dt><span aria-hidden="true">◷</span> Giờ mở cửa</dt><dd><?= e(substr($facility['gio_mo_cua'], 0, 5)) ?>–<?= e(substr($facility['gio_dong_cua'], 0, 5)) ?></dd></div>
    <?php if ($facility['so_dien_thoai']): ?><div><dt><span aria-hidden="true">☎</span> Liên hệ</dt><dd><?= e($facility['so_dien_thoai']) ?></dd></div><?php endif; ?>
    <?php if ($facility['email']): ?><div><dt><span aria-hidden="true">✉</span> Email</dt><dd><?= e($facility['email']) ?></dd></div><?php endif; ?>
</dl>
</div>
<?php require __DIR__ . '/detail-tabs.php'; ?>
<div class="detail-book"><span>Chọn sân & khung giờ phù hợp</span><a class="btn btn--primary" href="<?= e(url('/courts/schedule') . '&id=' . $facility['ma_co_so']) ?>">Đặt lịch</a></div>
