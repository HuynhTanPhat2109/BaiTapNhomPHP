<?php
// $chart: constant title/type plus scoped, server-generated labels/values. No executable chart payload.
$chartValues = array_map(static fn($row) => (float) $row['value'], $chart['rows']);
$chartSum = array_sum($chartValues);
$chartMax = max([.01, ...$chartValues]);
$chartMaximumAmount = $chart['rows'][array_search(max($chartValues ?: [0]), $chartValues, true)]['value'] ?? '0';
$chartMoney = ($chart['unit'] ?? '') === 'đ';
$chartValue = static fn($value) => $chartMoney ? \App\Services\DashboardService::money((string) $value) : number_format((int) $value, 0, ',', '.') . ' booking';
?>
<section class="dashboard-card dashboard-chart dashboard-chart--<?= e($chart['type']) ?>" aria-labelledby="<?= e($chart['id']) ?>">
    <header><div><h2 id="<?= e($chart['id']) ?>"><?= e($chart['title']) ?></h2><p><?= e($chart['subtitle']) ?></p></div><span class="dashboard-unit"><?= e($chart['unit']) ?></span></header>
    <?php if ($chartSum <= 0): ?>
        <div class="dashboard-empty"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 4v16h16M8 16v-4m5 4V8m5 8v-6"/></svg><strong>Chưa có dữ liệu trong khoảng này</strong><p>Chọn khoảng thời gian hoặc phạm vi khác để xem số liệu.</p></div>
    <?php elseif ($chart['type'] === 'time'): ?>
        <div class="dashboard-plot">
            <div class="dashboard-y-axis"><span title="<?= e($chartValue($chartMaximumAmount)) ?>"><?= e($chartMax >= 1000000000 ? number_format($chartMax / 1000000000, 1, ',', '.') . ' tỷ' : ($chartMax >= 1000000 ? number_format($chartMax / 1000000, 1, ',', '.') . ' tr' : number_format($chartMax, 0, ',', '.'))) ?></span><span>0 đ</span></div>
            <div class="dashboard-plot-content"><svg class="dashboard-time-svg" viewBox="0 0 660 180" preserveAspectRatio="none" role="img" aria-labelledby="<?= e($chart['id']) ?>">
                <path class="dashboard-gridline" d="M0 1H660M0 90H660M0 179H660"/>
                <?php $step = 660 / count($chart['rows']); $barWidth = min(36, $step * .68); foreach ($chart['rows'] as $index => $row): $height = (float) $row['value'] / $chartMax * 172; ?>
                <rect class="dashboard-bar" x="<?= $index * $step + ($step - $barWidth) / 2 ?>" y="<?= 179 - $height ?>" width="<?= $barWidth ?>" height="<?= $height ?>" rx="3" tabindex="0" data-dashboard-tooltip="<?= e(($row['date'] ?? $row['label']) . ': ' . $chartValue($row['value'])) ?>"><title><?= e(($row['date'] ?? $row['label']) . ': ' . $chartValue($row['value'])) ?></title></rect>
                <?php endforeach; ?>
            </svg><div class="dashboard-x-axis"><?php foreach ($chart['rows'] as $index => $row): if ($index !== 0 && $index !== count($chart['rows']) - 1 && $index % max(1, (int) ceil(count($chart['rows']) / 4)) !== 0) continue; ?><span><?= e($row['label']) ?></span><?php endforeach; ?></div></div>
        </div>
    <?php elseif ($chart['type'] === 'bars'): ?>
        <ol class="dashboard-ranking"><?php foreach (array_slice($chart['rows'], 0, 8) as $index => $row): ?><li><div><span><?= e($row['label']) ?></span><strong><?= e($chartValue($row['value'])) ?></strong></div><svg viewBox="0 0 100 8" preserveAspectRatio="none" aria-hidden="true"><rect class="dashboard-track" width="100" height="8" rx="4"/><rect class="dashboard-bar" width="<?= (float) $row['value'] / $chartMax * 100 ?>" height="8" rx="4"/></svg></li><?php endforeach; ?></ol>
        <?php if (count($chart['rows']) > 8): ?><p class="dashboard-caption">Biểu đồ hiển thị 8 đơn vị có doanh thu cao nhất. Bảng bên dưới hiển thị đầy đủ.</p><?php endif; ?>
    <?php else: ?>
        <div class="dashboard-donut-layout"><svg class="dashboard-donut" viewBox="0 0 120 120" role="img" aria-labelledby="<?= e($chart['id']) ?>"><circle class="dashboard-track" cx="60" cy="60" r="44" fill="none" stroke-width="16"/>
        <?php $offset = 0; foreach ($chart['rows'] as $index => $row): $part = (float) $row['value'] / $chartSum * 276.460154; ?>
            <circle class="dashboard-color-<?= $index % 6 ?>" cx="60" cy="60" r="44" fill="none" stroke-width="16" stroke-dasharray="<?= $part ?> <?= 276.460154 - $part ?>" stroke-dashoffset="<?= -$offset ?>" transform="rotate(-90 60 60)" tabindex="0"><title><?= e($row['label'] . ': ' . $chartValue($row['value'])) ?></title></circle>
        <?php $offset += $part; endforeach; ?></svg>
        <ul class="dashboard-legend"><?php foreach ($chart['rows'] as $index => $row): ?><li><span class="dashboard-swatch dashboard-color-<?= $index % 6 ?>" aria-hidden="true"></span><span><?= e($row['label']) ?><strong><?= e($chartValue($row['value'])) ?> <small>· <?= number_format((float) $row['value'] / $chartSum * 100, 1, ',', '.') ?>%</small></strong></span></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <?php if ($chartSum > 0): ?><details class="dashboard-chart-data"><summary>Xem số liệu biểu đồ</summary><div class="dashboard-table-scroll" tabindex="0"><table><thead><tr><th scope="col">Mốc / Nhóm</th><th scope="col">Giá trị (<?= e($chart['unit']) ?>)</th></tr></thead><tbody><?php foreach ($chart['rows'] as $row): ?><tr><th scope="row"><?= e($row['date'] ?? $row['label']) ?></th><td><?= e($chartValue($row['value'])) ?></td></tr><?php endforeach; ?></tbody></table></div></details><?php endif; ?>
</section>
