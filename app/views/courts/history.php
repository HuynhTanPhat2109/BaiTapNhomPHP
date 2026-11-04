<script src="<?= e(base_path() . '/assets/js/booking-history.js') ?>" defer></script>
<?php
$from = $from ?? ''; $to = $to ?? ''; $sort = $sort ?? 'newest';
$historyUrl = static fn(string $filter = '', int $page = 1): string => url('/booking/history') . '&' . http_build_query(['status'=>$filter,'from'=>$from,'to'=>$to,'sort'=>$sort,'page'=>$page]);
$formatDate = static fn(string $value): string => (new DateTimeImmutable($value))->format('d/m/Y');
$formatCost = static fn($value): string => is_numeric($value) && (float)$value >= 0
    ? number_format((float)$value,0,',','.') . ' ₫' : 'Chưa có dữ liệu snapshot';
$stats = $history['stats'] ?? $history['counts'];
$totalBookings = array_sum($stats);
$filtered = $status !== '' || $from !== '' || $to !== '' || $sort !== 'newest';
// Fixed local SVG paths only; no user data enters this markup.
$icons = [
    'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4m8-4v4"/>',
    'check'=>'<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
    'flag'=>'<path d="M5 21V4m0 1c5-4 9 4 14 0v10c-5 4-9-4-14 0"/>',
    'court'=>'<rect x="3" y="3" width="18" height="18" rx="1"/><path d="M12 3v18M3 8h5v8H3m18-8h-5v8h5"/><circle cx="12" cy="12" r="3"/>',
    'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/>',
];
$icon = static fn(string $name): string => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true">'.$icons[$name].'</svg>';
?>
<link rel="stylesheet" href="<?= e(base_path() . '/assets/css/booking-history.css') ?>">
<section class="booking-history" aria-labelledby="history-title">
    <header class="booking-history-hero">
        <div class="booking-history-intro">
            <h1 id="history-title">Lịch sử <span>đặt sân.</span></h1>
            <p>Theo dõi và xem lại những lịch đặt sân của bạn tại STRYKE.</p>
        </div>
        <dl class="booking-history-stats" aria-label="Tổng quan toàn bộ lịch sử đặt sân">
            <?php foreach ([['calendar','Tổng lượt đặt',$totalBookings],['check','Đã xác nhận',$stats['DA_XAC_NHAN'] ?? 0],['flag','Đã hoàn thành',$stats['HOAN_THANH'] ?? 0]] as [$symbol,$name,$count]): ?>
                <div><span class="booking-history-stat-icon"><?= $icon($symbol) ?></span><div><dt><?= e($name) ?></dt><dd><?= e($count) ?></dd></div></div>
            <?php endforeach; ?>
        </dl>
    </header>
    <div class="booking-history-filterbar">
        <nav class="booking-history-filters" aria-label="Lọc theo trạng thái">
            <a href="<?= e($historyUrl()) ?>" <?= $status === '' ? 'aria-current="page"' : '' ?>>Tất cả <span>(<?= e(array_sum($history['counts'])) ?>)</span></a>
            <?php foreach ($statuses as $code => [$label,$tone]): ?>
                <?php if ($code === 'CHO_XAC_NHAN' && empty($history['counts'][$code]) && $status !== $code) continue; ?>
                <a href="<?= e($historyUrl($code)) ?>" <?= $status === $code ? 'aria-current="page"' : '' ?>><?= e($label) ?> <span>(<?= e($history['counts'][$code] ?? 0) ?>)</span></a>
            <?php endforeach; ?>
        </nav>
        <form class="booking-history-query" method="get" action="<?= e(base_path().'/index.php') ?>">
            <input type="hidden" name="route" value="/booking/history">
            <input type="hidden" name="status" value="<?= e($status) ?>">
            <fieldset class="booking-history-dates">
                <div><label>Từ ngày<input type="date" name="from" value="<?= e($from) ?>" <?= $to !== '' ? 'max="'.e($to).'"' : '' ?>></label>
                <span class="booking-history-date-divider" aria-hidden="true">–</span>
                <label>Đến ngày<input type="date" name="to" value="<?= e($to) ?>" <?= $from !== '' ? 'min="'.e($from).'"' : '' ?>></label></div>
            </fieldset>
            <label class="booking-history-sort">
                Sắp xếp
                <span class="booking-history-select">
                    <select name="sort">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>
                            Mới nhất
                        </option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>
                            Cũ nhất
                        </option>
                    </select>

                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><!--!Font Awesome Free v7.3.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M297.4 470.6C309.9 483.1 330.2 483.1 342.7 470.6L534.7 278.6C547.2 266.1 547.2 245.8 534.7 233.3C522.2 220.8 501.9 220.8 489.4 233.3L320 402.7L150.6 233.4C138.1 220.9 117.8 220.9 105.3 233.4C92.8 245.9 92.8 266.2 105.3 278.7L297.3 470.7z"/></svg>
                </span>
            </label>
            <div class="booking-history-actions">
                <button class="booking-history-apply" type="submit">
                    Áp dụng
                </button>

                <a class="booking-history-reset"
                href="<?= e(url('/booking/history')) ?>">
                    Xóa lọc
                </a>
            </div>
        </form>
    </div>
    <?php if (!$history['items']): ?>
        <div class="booking-history-empty">
            <?= $icon('calendar') ?>
            <h2><?= $totalBookings === 0 ? 'Bạn chưa có lịch đặt sân nào' : 'Không có lịch đặt phù hợp bộ lọc' ?></h2>
            <p><?= $totalBookings === 0 ? 'Tìm sân phù hợp và bắt đầu buổi chơi tiếp theo của bạn.' : 'Thử thay đổi trạng thái hoặc khoảng ngày tạo booking.' ?></p>
        </div>
    <?php else: ?>
        <div class="booking-history-list">
        <?php foreach ($history['items'] as $item): ?>
            <?php
            [$label,$tone] = $statuses[$item['trang_thai_dat_san']] ?? [$item['trang_thai_dat_san'],'expired'];
            $groups = [];
            foreach ($item['lines'] as $line) {
                $key = $line['ngay_choi'].':'.$line['ma_san'];
                $groups[$key]['date'] = $line['ngay_choi'];
                $groups[$key]['name'] = $line['ten_san_snapshot'];
                $groups[$key]['times'][] = substr($line['gio_bat_dau'],0,5).'–'.substr($line['gio_ket_thuc'],0,5);
            }
            $photo = $item['anh_dai_dien'] ?? null;
            $hasPhoto = App\Services\FacilityImage::validPath($photo) && is_file(dirname(__DIR__,3).'/public/'.$photo);
            ?>
            <article class="booking-history-card" aria-labelledby="booking-<?= e($item['ma_dat_san']) ?>">
                <div class="booking-history-thumbnail">
                    <?php if ($hasPhoto): ?><img src="<?= e(base_path().'/'.$photo) ?>" alt="" loading="lazy" width="144" height="100">
                    <?php else: ?><div class="booking-history-placeholder" aria-hidden="true"><?= $icon('court') ?><span>STRYKE.</span></div><?php endif; ?>
                </div>
                <div class="booking-history-card-main">
                    <span class="booking-history-reference">ĐẶT SÂN #<?= e($item['ma_dat_san']) ?></span>
                    <h2 id="booking-<?= e($item['ma_dat_san']) ?>"><?= e($item['ten_co_so']) ?></h2>
                    <ul class="booking-history-slots">
                        <?php foreach ($groups as $group): ?>
                            <li><time datetime="<?= e($group['date']) ?>"><?= $icon('calendar') ?><?= e($formatDate($group['date'])) ?></time>
                                <span class="booking-history-court"><?= $icon('court') ?><strong><?= e($group['name']) ?></strong></span>
                                <span class="booking-history-times"><?= $icon('clock') ?><span><?php foreach ($group['times'] as $time): ?><span><?= e($time) ?></span><?php endforeach; ?></span></span>
                            </li>
                        <?php endforeach; ?>
                        <?php if (!$groups): ?><li>Chưa có thông tin khung giờ được lưu.</li><?php endif; ?>
                    </ul>
                    <p class="booking-history-created">Ngày tạo: <time datetime="<?= e(str_replace(' ','T',$item['ngay_tao']).'+07:00') ?>"><?= e((new DateTimeImmutable($item['ngay_tao']))->format('d/m/Y – H:i')) ?></time></p>
                </div>
                <div class="booking-history-card-aside">
                    <span class="booking-history-badge booking-history-badge--<?= e($tone) ?>"><?= e($label) ?></span>
                    <div class="booking-history-amount"><span>Tổng tiền</span><strong><?= e(number_format((float)$item['tong_tien_tam_tinh'],0,',','.')) ?> <small>₫</small></strong></div>
                </div>
                <details class="booking-history-costs">
                    <summary>
                        <span class="booking-history-costs-show">Xem chi tiết</span>
                        <span class="booking-history-costs-hide">Thu gọn chi tiết</span>
                        <svg class="booking-history-costs-chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><!--!Font Awesome Free v7.3.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M297.4 470.6C309.9 483.1 330.2 483.1 342.7 470.6L534.7 278.6C547.2 266.1 547.2 245.8 534.7 233.3C522.2 220.8 501.9 220.8 489.4 233.3L320 402.7L150.6 233.4C138.1 220.9 117.8 220.9 105.3 233.4C92.8 245.9 92.8 266.2 105.3 278.7L297.3 470.7z"/></svg>
                    </summary>
                    <section class="booking-history-costs-body" aria-labelledby="booking-costs-<?= e($item['ma_dat_san']) ?>">

                        <?php
                            $bookingNote = trim((string) ($item['ghi_chu'] ?? ''));
                            $receiptImage = trim((string) ($item['receipt_url'] ?? ''));
                        ?>

                            <div class="booking-history-extra-row">
                                <strong>Ghi chú:</strong>
                                <span class="booking-history-note">
                                    <?= e($bookingNote !== '' ? $bookingNote : 'Không có') ?>
                                </span>
                            </div>

                        <?php if ($receiptImage !== ''): ?>
                            <div class="booking-history-extra-row">
                                <strong>Ảnh minh chứng:</strong>

                                <button
                                    type="button"
                                    class="booking-history-view-proof"
                                    data-view-proof
                                    data-proof-src="<?= e($receiptImage) ?>"
                                    aria-label="Xem ảnh minh chứng chuyển khoản của booking #<?= e($item['ma_dat_san']) ?>"
                                    aria-haspopup="dialog"
                                    aria-controls="booking-history-proof"
                                >
                                    <svg viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" aria-hidden="true">
                                        <rect x="3" y="3" width="18" height="18" rx="3"/>
                                        <circle cx="8.5" cy="8.5" r="1.5"/>
                                        <path d="m3 17 5-5 4 4 3-3 6 6"/>
                                    </svg>
                                    Xem ảnh chuyển khoản
                                </button>
                            </div>
                        <?php endif; ?>

                            <div class="booking-history-extra-divider"></div>

                        <div class="booking-history-costs-row"><strong>Tiền sân</strong><strong><?= e($formatCost($item['tong_tien_san'] ?? null)) ?></strong></div>
                        <div class="booking-history-costs-row"><strong>Tiền dịch vụ</strong><strong><?= e($formatCost($item['tong_tien_dich_vu'] ?? null)) ?></strong></div>
                        <?php if (!empty($item['service_lines'])): ?>
                            <ul class="booking-history-costs-services">
                                <?php foreach ($item['service_lines'] as $service): ?>
                                    <li>
                                        <div class="booking-history-costs-service">
                                            <span><?= e(trim($service['ten_dich_vu_snapshot'] ?? '') ?: 'Thiếu tên dịch vụ snapshot') ?> × <?= e(isset($service['so_luong']) && (int)$service['so_luong'] > 0 ? $service['so_luong'] : 'Chưa có số lượng') ?></span>
                                            <small>Đơn giá: <?= e($formatCost($service['don_gia'] ?? null)) ?><?php if (trim($service['don_vi_tinh_snapshot'] ?? '') !== ''): ?> / <?= e($service['don_vi_tinh_snapshot']) ?><?php endif; ?></small>
                                        </div>
                                        <span><?= e($formatCost($service['thanh_tien'] ?? null)) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php elseif (isset($item['service_lines']) && is_numeric($item['tong_tien_dich_vu'] ?? null) && (float)$item['tong_tien_dich_vu'] === 0.0): ?>
                            <p class="booking-history-costs-note">Không sử dụng dịch vụ</p>
                        <?php else: ?>
                            <p class="booking-history-costs-note">Chưa có chi tiết dịch vụ snapshot. Tổng tiền được hiển thị theo dữ liệu booking đã lưu.</p>
                        <?php endif; ?>
                        <div class="booking-history-costs-total"><strong>Tổng tiền</strong><strong><?= e($formatCost($item['tong_tien_tam_tinh'] ?? null)) ?></strong></div>
                    </section>
                </details>
            </article>
        <?php endforeach; ?>
        </div>
        <div class="booking-history-bottom">
            <p>Hiển thị <?= e(($history['page']-1)*$history['perPage']+1) ?>–<?= e(min($history['page']*$history['perPage'],$history['total'])) ?> / <?= e($history['total']) ?> lịch đặt</p>
            <?php if ($history['pages'] > 1): ?>
            <nav class="booking-history-pagination" aria-label="Phân trang lịch sử">
                <?php if ($history['page'] > 1): ?><a href="<?= e($historyUrl($status,$history['page']-1)) ?>" aria-label="Trang trước">‹</a><?php else: ?><span aria-disabled="true">‹</span><?php endif; ?>
                <?php for ($p=max(1,$history['page']-1);$p<=min($history['pages'],$history['page']+1);$p++): ?><a href="<?= e($historyUrl($status,$p)) ?>" aria-label="Trang <?= e($p) ?>" <?= $p === $history['page'] ? 'aria-current="page"' : '' ?>><?= e($p) ?></a><?php endfor; ?>
                <?php if ($history['page']<$history['pages']): ?><a href="<?= e($historyUrl($status,$history['page']+1)) ?>" aria-label="Trang sau">›</a><?php else: ?><span aria-disabled="true">›</span><?php endif; ?>
            </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<dialog class="booking-history-proof-modal"
     id="booking-history-proof"
     data-proof-modal
     aria-label="Ảnh minh chứng chuyển khoản">

    <div class="booking-history-proof-container">
        <button type="button"
                class="booking-history-proof-close"
                data-close-proof
                aria-label="Đóng ảnh">
            &times;
        </button>

        <img data-proof-image
             alt="Ảnh minh chứng chuyển khoản">
        <p class="booking-history-proof-error" data-proof-error hidden role="status">Không thể tải ảnh minh chứng. Vui lòng đóng và thử lại.</p>
    </div>
</dialog>

