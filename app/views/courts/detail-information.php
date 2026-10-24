<dl class="facility-facts">
    <div><dt>Địa chỉ</dt><dd><?= e($facility['dia_chi']) ?></dd></div>
    <div><dt>Khu vực</dt><dd><?= e(implode(' · ', array_filter([$facility['ten_phuong_xa'], $facility['ten_tinh']]))) ?: 'Chưa bổ sung' ?></dd></div>
    <div><dt>Giờ hoạt động</dt><dd><?= e(substr($facility['gio_mo_cua'], 0, 5)) ?> — <?= e(substr($facility['gio_dong_cua'], 0, 5)) ?></dd></div>
    <?php if ($facility['so_dien_thoai']): ?><div><dt>Điện thoại</dt><dd><?= e($facility['so_dien_thoai']) ?></dd></div><?php endif; ?>
    <?php if ($facility['email']): ?><div><dt>Email</dt><dd><?= e($facility['email']) ?></dd></div><?php endif; ?>
</dl>
<?php if ($facility['mo_ta']): ?><p class="courts-description"><?= e($facility['mo_ta']) ?></p><?php endif; ?>
