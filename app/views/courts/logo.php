<?php if (App\Services\FacilityImage::validPath($facility['logo_clb'] ?? null)): ?>
    <img class="club-logo" src="<?= e(base_path() . '/' . $facility['logo_clb']) ?>" alt="Logo <?= e($facility['ten_co_so']) ?>" loading="lazy">
<?php else: ?>
    <span class="club-logo club-logo-placeholder" aria-label="Chưa có logo CLB"><?= e(mb_substr($facility['ten_co_so'], 0, 1)) ?></span>
<?php endif; ?>
