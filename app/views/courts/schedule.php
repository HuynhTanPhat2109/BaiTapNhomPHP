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
<link rel="stylesheet" href="<?= e(base_path() . '/assets/css/booking.css') ?>">
<link
    rel="stylesheet"
    href="<?= e(base_path() . '/assets/css/action-button.css') ?>"
>

<section
    class="booking-schedule"
    id="court-schedule"
    data-slot-count="<?= count($schedule['slots']) ?>"
    data-now="<?= e($now->format(DATE_ATOM)) ?>"
    data-date="<?= e($date) ?>"
    data-endpoint="<?= e(url('/courts/schedule') . '&id=' . $facility['ma_co_so']) ?>"
>
    <!-- Thanh điều khiển -->
    <?php if (!empty($bookingNotice)): ?><p class="booking-notice" role="status"><?= e($bookingNotice) ?></p><?php endif; ?>
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
            <a
                class="booking-price-cta"
                href="<?= e(url('/courts/prices') . '&id=' . $facility['ma_co_so'] . '&date=' . rawurlencode($date)) ?>"
            >
                <span class="span">Xem sân &amp; bảng giá</span>

                <span class="second">
                    <svg
                        width="50px"
                        height="20px"
                        viewBox="0 0 66 43"
                        xmlns="http://www.w3.org/2000/svg"
                        aria-hidden="true"
                    >
                        <g
                            stroke="none"
                            stroke-width="1"
                            fill="none"
                            fill-rule="evenodd"
                        >
                            <path
                                class="one"
                                d="M40.1543933,3.89485454 L43.9763149,0.139296592 C44.1708311,-0.0518420739 44.4826329,-0.0518571125 44.6771675,0.139262789 L65.6916134,20.7848311 C66.0855801,21.1718824 66.0911863,21.8050225 65.704135,22.1989893 C65.7000188,22.2031791 65.6958657,22.2073326 65.6916762,22.2114492 L44.677098,42.8607841 C44.4825957,43.0519059 44.1708242,43.0519358 43.9762853,42.8608513 L40.1545186,39.1069479 C39.9575152,38.9134427 39.9546793,38.5968729 40.1481845,38.3998695 C40.1502893,38.3977268 40.1524132,38.395603 40.1545562,38.3934985 L56.9937789,21.8567812 C57.1908028,21.6632968 57.193672,21.3467273 57.0001876,21.1497035 C56.9980647,21.1475418 56.9959223,21.1453995 56.9937605,21.1432767 L40.1545208,4.60825197 C39.9574869,4.41477773 39.9546013,4.09820839 40.1480756,3.90117456 C40.1501626,3.89904911 40.1522686,3.89694235 40.1543933,3.89485454 Z"
                                fill="#FFFFFF"
                            ></path>

                            <path
                                class="two"
                                d="M20.1543933,3.89485454 L23.9763149,0.139296592 C24.1708311,-0.0518420739 24.4826329,-0.0518571125 24.6771675,0.139262789 L45.6916134,20.7848311 C46.0855801,21.1718824 46.0911863,21.8050225 45.704135,22.1989893 C45.7000188,22.2031791 45.6958657,22.2073326 45.6916762,22.2114492 L24.677098,42.8607841 C24.4825957,43.0519059 24.1708242,43.0519358 23.9762853,42.8608513 L20.1545186,39.1069479 C19.9575152,38.9134427 19.9546793,38.5968729 20.1481845,38.3998695 C20.1502893,38.3977268 20.1524132,38.395603 20.1545562,38.3934985 L36.9937789,21.8567812 C37.1908028,21.6632968 37.193672,21.3467273 37.0001876,21.1497035 C36.9980647,21.1475418 36.9959223,21.1453995 36.9937605,21.1432767 L20.1545208,4.60825197 C19.9574869,4.41477773 19.9546013,4.09820839 20.1480756,3.90117456 C20.1501626,3.89904911 20.1522686,3.89694235 20.1543933,3.89485454 Z"
                                fill="#FFFFFF"
                            ></path>

                            <path
                                class="three"
                                d="M0.154393339,3.89485454 L3.97631488,0.139296592 C4.17083111,-0.0518420739 4.48263286,-0.0518571125 4.67716753,0.139262789 L25.6916134,20.7848311 C26.0855801,21.1718824 26.0911863,21.8050225 25.704135,22.1989893 C25.7000188,22.2031791 25.6958657,22.2073326 25.6916762,22.2114492 L4.67709797,42.8607841 C4.48259567,43.0519059 4.17082418,43.0519358 3.97628526,42.8608513 L0.154518591,39.1069479 C-0.0424848215,38.9134427 -0.0453206733,38.5968729 0.148184538,38.3998695 C0.150289256,38.3977268 0.152413239,38.395603 0.154556228,38.3934985 L16.9937789,21.8567812 C17.1908028,21.6632968 17.193672,21.3467273 17.0001876,21.1497035 C16.9980647,21.1475418 16.9959223,21.1453995 16.9937605,21.1432767 L0.15452076,4.60825197 C-0.0425130651,4.41477773 -0.0453986756,4.09820839 0.148075568,3.90117456 C0.150162624,3.89904911 0.152268631,3.89694235 0.154393339,3.89485454 Z"
                                fill="#FFFFFF"
                            ></path>
                        </g>
                    </svg>
                </span>
            </a>
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

    </section>
    
    <form
        id="booking-next"
        method="post"
        action="<?= e(url('/booking/start')) ?>"
        data-quote-url="<?= e(url('/booking/quote')) ?>"
        data-facility="<?= e($facility['ma_co_so']) ?>"
    >
        <?= csrf_field() ?>

        <input type="hidden" name="selection" value="">

        <div class="booking-next-summary">
            <div class="booking-next-duration">
                <span>Tổng thời gian</span>
                <strong id="selection-duration">0 giờ</strong>
            </div>

            <div class="booking-next-price">
                <span>Tạm tính</span>
                <strong id="selection-price">—</strong>
            </div>
        </div>

        <?php
            $buttonText = 'TIẾP THEO';
            $buttonType = 'submit';
            $buttonDisabled = true;
            $buttonClass = '';
            $buttonAttributes = [];

            require __DIR__ . '/../components/action-button.php';
        ?>
    </form>

</section>
