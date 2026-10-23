<section class="container courts-page">
    <a class="courts-back" href="<?= e(url('/courts')) ?>">← Tất cả cơ sở</a>
    <?php require __DIR__ . '/logo.php'; ?>
    <div class="courts-detail">
        <?php require __DIR__ . '/photo.php'; ?>
        <header class="courts-heading"><p class="eyebrow"><?= e($facility['ten_cong_ty']) ?></p><h1><?= e($facility['ten_co_so']) ?></h1><p><?= e($facility['dia_chi']) ?></p><p><?= e(implode(' · ', array_filter([$facility['ten_phuong_xa'], $facility['ten_tinh']]))) ?></p><p class="courts-hours"><?= e(substr($facility['gio_mo_cua'], 0, 5)) ?> — <?= e(substr($facility['gio_dong_cua'], 0, 5)) ?></p>
            <?php if ($facility['so_dien_thoai']): ?><p>Điện thoại: <?= e($facility['so_dien_thoai']) ?></p><?php endif; ?>
            <?php if ($facility['email']): ?><p>Email: <?= e($facility['email']) ?></p><?php endif; ?>
            <a class="btn btn--primary booking-choice-link" href="<?= e(url('/courts/schedule') . '&id=' . $facility['ma_co_so']) ?>">Đặt lịch ↗</a>
        </header>
    </div>
    <p class="courts-description"><?= e($facility['mo_ta'] ?? 'Cơ sở chưa bổ sung mô tả.') ?></p>
    <h2>Sân &amp; bộ môn</h2><div class="courts-grid">
        <?php foreach ($courts as $court): ?>
            <article class="court-card court-card-body"><p class="eyebrow"><?= e($court['ten_bo_mon']) ?></p><h3><?= e($court['ten_san']) ?></h3><p><?= e($court['ten_loai_san']) ?></p><p><?= e($court['vi_tri'] ?? '') ?></p><p><?= e($court['mo_ta'] ?? '') ?></p><strong><?= $court['trang_thai'] === 'HOAT_DONG' && $court['trang_thai_loai_san'] === 'HOAT_DONG' && $court['trang_thai_bo_mon'] === 'HOAT_DONG' ? 'Đang hoạt động' : 'Hiện không khả dụng' ?></strong></article>
        <?php endforeach; ?>
    </div><?php if (!$courts): ?><p class="courts-empty">Cơ sở chưa có sân để hiển thị.</p><?php endif; ?>
</section>

<?php require __DIR__ . '/booking-modal.php'; ?>
