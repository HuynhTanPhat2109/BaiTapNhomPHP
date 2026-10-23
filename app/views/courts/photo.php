<?php if (App\Services\FacilityImage::validPath($facility['anh_dai_dien'] ?? null)): ?>
    <img class="court-photo" src="<?= e(base_path() . '/' . $facility['anh_dai_dien']) ?>" alt="<?= e($facility['ten_co_so']) ?>" loading="lazy">
<?php else: ?>
    <div class="court-photo court-photo-placeholder" role="img" aria-label="Cơ sở chưa có ảnh"><span>STRYKE.</span><small>Gặp nhau trên sân</small></div>
<?php endif; ?>
