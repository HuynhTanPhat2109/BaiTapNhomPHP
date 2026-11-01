<?php
$labels = [
    'free' => 'Trống',
    'booked' => 'Đã đặt',
    'held' => 'Đang giữ',
    'unavailable' => 'Khóa',
];
?>

<link rel="stylesheet" href="<?= e(base_path() . '/assets/css/schedule.css') ?>">
<script src="<?= e(base_path() . '/assets/js/schedule.js') ?>" defer></script>

<section
    class="booking-schedule"
    id="court-schedule"
    data-slot-count="<?= count($schedule['slots']) ?>"
    data-now="<?= e($now->format(DATE_ATOM)) ?>"
    data-date="<?= e($date) ?>"
    data-endpoint="<?= e(url('/courts/schedule') . '&id=' . $facility['ma_co_so']) ?>"
>
    <!-- Thanh điều khiển -->
    <header class="booking-toolbar">

        <div class="booking-legend">
            <span>
                <i class="legend-free"></i>
                Trống
            </span>

            <span>
                <i class="legend-booked"></i>
                Đã đặt
            </span>

            <span>
                <i class="legend-held"></i>
                Đang giữ
            </span>

            <span>
                <i class="legend-locked"></i>
                Khóa
            </span>
        </div>

        <form
            method="get"
            action="<?= e(base_path() . '/index.php') ?>"
            class="booking-date"
            id="schedule-date-form"
        >
            <input type="hidden" name="route" value="/courts/schedule">
            <input type="hidden" name="id" value="<?= e($facility['ma_co_so']) ?>">

            <label for="play-date">Ngày chơi</label>

            <input
                id="play-date"
                type="date"
                name="date"
                min="<?= e($now->format('Y-m-d')) ?>"
                value="<?= e($date) ?>"
                required
            >
        </form>

    </header>

    <?php if ($error): ?>
        <p class="field-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if (!$schedule['rows'] || !$schedule['slots']): ?>

        <div class="booking-empty">
            Chưa có lịch sân phù hợp.
        </div>

    <?php else: ?>
        <!-- LƯỚI SÂN -->
        <div
            class="booking-grid-scroll"
            tabindex="0"
            role="region"
            aria-label="Lịch sân"
        >
            <table class="booking-grid">
                <colgroup>
                    <col class="court-column">
                    <?php foreach ($schedule['slots'] as $_): ?>
                        <col class="slot-column">
                    <?php endforeach; ?>
                    <!-- Cột đệm cuối timeline -->
                    <col class="timeline-spacer-column">
                </colgroup>
                <thead>
                    <tr>
                        <th class="court-name-head" scope="col"></th>

                        <?php foreach ($schedule['slots'] as $slotIndex => $slot): ?>
                            <th
                                class="slot-time<?= $slotIndex === 0 ? ' slot-time--first' : '' ?>"
                                scope="col"
                                title="<?= e(
                                    substr($slot['start'], 0, 5)
                                    . '–'
                                    . substr($slot['end'], 0, 5)
                                ) ?>"
                            >
                                <span class="timeline-start">
                                    <?= e(substr($slot['start'], 0, 5)) ?>
                                </span>
                                <?php if ($slotIndex === count($schedule['slots']) - 1): ?>
                                    <span class="timeline-end-label">
                                        <?= e(substr($slot['end'], 0, 5)) ?>
                                    </span>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                        <!-- Cột đệm cuối timeline -->
                        <th class="timeline-spacer timeline-spacer--last" aria-hidden="true"></th>
                    </tr>
                </thead>
                
                <tbody>

                    <?php foreach ($schedule['rows'] as $row): ?>

                        <tr
                            data-court="<?= e($row['court']['ma_san']) ?>"
                            data-name="<?= e($row['court']['ten_san']) ?>"
                        >

                            <th class="court-name">

                                <strong>
                                    <?= e($row['court']['ten_san']) ?>
                                </strong>

                                <small>
                                    <?= e($row['court']['ten_bo_mon']) ?>
                                </small>

                            </th>

                            <?php foreach ($row['cells'] as $index => $cell): ?>

                                <?php
                                $state = $cell['state'];
                                $disabled = $state !== 'free';
                                ?>

                                <td class="slot-wrapper">

                                    <button
                                        type="button"
                                        class="time-cell is-<?= e($state) ?>"
                                        data-index="<?= e($index) ?>"
                                        data-court="<?= e($row['court']['ma_san']) ?>"
                                        data-court-name="<?= e($row['court']['ten_san']) ?>"
                                        data-start="<?= e(substr($cell['start'], 0, 5)) ?>"
                                        data-end="<?= e(substr($cell['end'], 0, 5)) ?>"
                                        <?= $disabled ? 'disabled' : '' ?>
                                        aria-pressed="false"
                                        aria-label="<?= e(
                                            $row['court']['ten_san']
                                            . ' · '
                                            . substr($cell['start'], 0, 5)
                                            . '–'
                                            . substr($cell['end'], 0, 5)
                                            . ' · '
                                            . $labels[$state]
                                        ) ?>"
                                    ><span hidden></span></button>

                                </td>

                            <?php endforeach; ?>
                            <!-- Ô đệm cuối -->
                            <td class="timeline-spacer-cell" aria-hidden="true"></td>
                        </tr>
                            
                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

        <!-- ZOOM -->
        <div class="booking-grid-controls">
            <div class="booking-zoom">
                <button type="button" data-zoom="-1">−</button>
                <output id="zoom-level">
                    Zoom
                </output>
                <button type="button" data-zoom="1">+</button>
            </div>
        </div>
    <?php endif; ?>

    <!-- LỰA CHỌN -->
    <section class="booking-selection">

        <div class="selection-heading">
            <div>
                <span class="selection-eyebrow">
                    LỰA CHỌN CỦA BẠN
                </span>

            </div>

            <button
                type="button"
                id="clear-selection"
                class="selection-clear"
                disabled
            >
                Xóa lựa chọn
            </button>
        </div>

        <div
            id="selected-courts"
            class="selected-courts"
        >
            <p class="selection-empty" id="selection-preview" aria-live="polite">
                Chọn các ô trống trên lịch để bắt đầu.
            </p>
        </div>

        <div class="selection-summary">

            <div>
                <span>Ngày chơi</span>
                <strong><?= e($date) ?></strong>
            </div>

            <div>
                <span>Tổng thời gian</span>
                <strong id="selection-duration">0 giờ</strong>
            </div>

            <div>
                <span>Tạm tính</span>
                <strong id="selection-price">—</strong>
                <small>Chờ dữ liệu bảng giá</small>
            </div>

        </div>

    </section>

</section>
