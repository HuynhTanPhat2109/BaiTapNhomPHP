<?php
$serviceTabDate = static fn(string $date): string => date('d/m/Y', strtotime($date));
?>
<div class="facility-service-tables">
    <h3>BẢNG GIÁ SÂN</h3>
    <?php if (empty($facilityPriceGroups)): ?>
        <p class="facility-service-empty">Cơ sở chưa cập nhật bảng giá.</p>
    <?php else: ?>
        <?php foreach ($facilityPriceGroups as $sport): ?>
        <div class="facility-service-scroll" tabindex="0" role="region" aria-label="<?= e('Bảng giá ' . $sport['name']) ?>">
            <table class="facility-service-prices">
                <caption><?= e($sport['name']) ?></caption>
                <thead><tr><th scope="col">Loại sân</th><th scope="col">Ngày</th><th scope="col">Khung giờ</th><th scope="col">Giá sân<br></th></tr></thead>
                <?php foreach ($sport['types'] as $type): ?>
                <tbody>
                    <?php foreach ($type['rates'] as $index => $rate): ?>
                    <tr>
                        <?php if ($index === 0): ?><th scope="rowgroup" rowspan="<?= count($type['rates']) ?>"><?= e($type['name']) ?></th><?php endif; ?>
                        <?php if ($rate['day_span'] > 0): ?><td rowspan="<?= e($rate['day_span']) ?>"><?= e($rate['day_label']) ?></td><?php endif; ?>
                        <td><?= e(substr($rate['gio_bat_dau'], 0, 5) . '–' . substr($rate['gio_ket_thuc'], 0, 5)) ?></td>
                        <td><?= e(App\Services\BookingPricingService::display((string) $rate['don_gia'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h3>DANH SÁCH DỊCH VỤ</h3>
    <?php if (empty($facilityServiceGroups)): ?>
        <p class="facility-service-empty">Cơ sở chưa cập nhật dịch vụ.</p>
    <?php else: ?>
    <div class="facility-service-scroll" tabindex="0" role="region" aria-label="Danh sách dịch vụ cơ sở">
        <table class="facility-service-list">
            <thead><tr><th scope="col">Tên dịch vụ</th><th scope="col">Đơn giá / Đơn vị tính</th></tr></thead>
            <?php foreach ($facilityServiceGroups as $code => $services): ?>
            <tbody>
                <tr class="facility-service-group"><th colspan="2" scope="rowgroup"><?= e(App\Services\DichVuService::TYPES[$code] ?? $code) ?></th></tr>
                <?php foreach ($services as $service): ?>
                <tr><th scope="row"><?= e($service['ten_dich_vu']) ?></th><td><?= e(App\Services\BookingPricingService::display((string) $service['don_gia'])) ?> / <?= e($service['don_vi_tinh']) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
</div>
