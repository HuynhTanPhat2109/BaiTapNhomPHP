<?php $tabPrefix = 'facility-' . (int) $facility['ma_co_so']; ?>
<div class="facility-tabs" data-facility-tabs>
    <div class="facility-tablist" aria-label="Chi tiết cơ sở">
        <?php foreach (['info' => 'Thông tin', 'services' => 'Dịch vụ', 'images' => 'Hình ảnh', 'rules' => 'Điều khoản & quy định', 'reviews' => 'Đánh giá'] as $key => $label): ?>
            <a id="<?= e($tabPrefix . '-tab-' . $key) ?>" href="#<?= e($tabPrefix . '-' . $key) ?>" data-facility-tab="<?= e($key) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <section id="<?= e($tabPrefix . '-info') ?>" data-facility-panel="info" aria-labelledby="<?= e($tabPrefix . '-tab-info') ?>">
        <?php require __DIR__ . '/detail-information.php'; ?>
    </section>
    <section id="<?= e($tabPrefix . '-services') ?>" data-facility-panel="services" aria-labelledby="<?= e($tabPrefix . '-tab-services') ?>">
        <h3>Dịch vụ & bảng giá</h3><p class="facility-tab-empty">Thông tin dịch vụ và bảng giá chưa được cập nhật. Vui lòng liên hệ cơ sở để được hỗ trợ.</p>
    </section>
    <section id="<?= e($tabPrefix . '-images') ?>" data-facility-panel="images" aria-labelledby="<?= e($tabPrefix . '-tab-images') ?>">
        <h3>Hình ảnh cơ sở</h3>
        <?php if (App\Services\FacilityImage::validPath($facility['anh_dai_dien'] ?? null)): ?>
            <div class="facility-gallery"><figure><?php require __DIR__ . '/photo.php'; ?><figcaption><?= e($facility['ten_co_so']) ?></figcaption></figure></div>
        <?php else: ?><p class="facility-tab-empty">Cơ sở chưa bổ sung hình ảnh.</p><?php endif; ?>
    </section>
    <section id="<?= e($tabPrefix . '-rules') ?>" data-facility-panel="rules" aria-labelledby="<?= e($tabPrefix . '-tab-rules') ?>">
        <h3>Điều khoản & quy định</h3><p class="facility-tab-empty">Cơ sở chưa công bố nội quy trên website. Vui lòng liên hệ cơ sở để biết quy định sử dụng sân.</p>
    </section>
    <section id="<?= e($tabPrefix . '-reviews') ?>" data-facility-panel="reviews" aria-labelledby="<?= e($tabPrefix . '-tab-reviews') ?>">
        <h3>Đánh giá</h3><p class="facility-tab-empty">Chưa có dữ liệu đánh giá để hiển thị.</p>
    </section>
</div>
