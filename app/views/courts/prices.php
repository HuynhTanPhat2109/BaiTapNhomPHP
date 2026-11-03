<?php use App\Services\BookingPricingService; ?>
<link rel="stylesheet" href="<?= e(base_path() . '/assets/css/court-prices.css') ?>">
<section class="court-prices" aria-label="Bảng giá sân">
    <?php if (!$sports): ?>
        <p class="court-prices-empty">Cơ sở chưa có sân đang hoạt động để hiển thị bảng giá.</p>
    <?php endif; ?>
    <?php foreach ($sports as $sport): ?>
        <table class="court-prices-table">
            <caption><?= e($sport['name']) ?></caption>
            <colgroup><col class="court-prices-type-col"><col class="court-prices-day-col"><col class="court-prices-time-col"><col class="court-prices-money-col"></colgroup>
            <thead><tr><th scope="col">Loại sân</th><th scope="col">Thứ</th><th scope="col">Khung giờ</th><th scope="col">Giá sân</th></tr></thead>
            <?php foreach ($sport['types'] as $type): ?>
            <tbody>
                <?php if (!$type['rates']): ?>
                    <tr><th class="court-prices-type" scope="row"><?= e($type['name']) ?></th><td colspan="3" class="court-prices-empty">Chưa có bảng giá có hiệu lực cho loại sân này.</td></tr>
                <?php endif; ?>
                <?php foreach ($type['rates'] as $index => $rate): ?>
                    <tr>
                        <?php if ($index === 0): ?><th class="court-prices-type" scope="rowgroup" rowspan="<?= count($type['rates']) ?>"><?= e($type['name']) ?></th><?php endif; ?>
                        <?php if ($rate['day_span'] > 0): ?><td rowspan="<?= e($rate['day_span']) ?>"><?= e($rate['day_label']) ?></td><?php endif; ?>
                        <?php
                            $formatHour = static function (string $time): string {
                                [$hour, $minute] = explode(':', $time);

                                return (int) $hour . 'h' . ($minute !== '00' ? $minute : '');
                            };
                        ?>

                        <td>
                            <?= e($formatHour($rate['gio_bat_dau'])) ?>
                            -
                            <?= e($formatHour($rate['gio_ket_thuc'])) ?>
                        </td>
                        <td class="court-prices-amount"><?= e(BookingPricingService::display((string)$rate['don_gia'])) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
            <?php endforeach; ?>
        </table>
    <?php endforeach; ?>
</section>
