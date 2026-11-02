
<?php
use App\Services\BookingPricingService as Money;
?>
<link rel="stylesheet" href="<?= e(base_path().'/assets/css/booking.css') ?>">
<script src="<?= e(base_path().'/assets/js/booking.js') ?>" defer></script>

<section class="booking-page booking-details"
         data-booking-navigation
         data-back-url="<?= e($bookingBackUrl) ?>">

    <?php if ($error): ?>
        <p class="booking-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post"
          action="<?= e(url('/booking/review')) ?>"
          data-booking-form>

        <?= csrf_field() ?>
        <input type="hidden" name="draft" value="<?= e($key) ?>">
        <p class="booking-error" data-booking-feedback role="alert" hidden></p>

        <?php if ($quote): ?>
            <input type="hidden"
                   name="quote"
                   value="<?= e($quote['fingerprint']) ?>">

            <div class="booking-columns">
                <div class="booking-information">
                    <section class="booking-section">
                        <div class="booking-section-title"><span class="booking-section-number">01</span><h2>Thông tin cơ sở</h2></div>
                        <div class="booking-facility">
                            <?php if (App\Services\FacilityImage::validPath($quote['facility']['anh_dai_dien'] ?? null)): ?>
                                <img class="booking-facility-photo" src="<?= e(base_path().'/'.$quote['facility']['anh_dai_dien']) ?>" alt="<?= e($quote['facility']['ten_co_so']) ?>">
                            <?php else: ?>
                                <div class="booking-facility-photo booking-facility-placeholder" role="img" aria-label="Cơ sở chưa có ảnh">STRYKE<span>.</span></div>
                            <?php endif; ?>
                            <div class="booking-facility-copy">
                                <div class="booking-facility-detail">
                                    <span class="booking-facility-label">Tên cơ sở</span>
                                    <strong class="booking-facility-value">
                                        <?= e($quote['facility']['ten_co_so']) ?>
                                    </strong>
                                </div>

                                <div class="booking-facility-detail">
                                    <span class="booking-facility-label">Địa chỉ</span>
                                    <span class="booking-facility-value">
                                        <?= e($quote['facility']['dia_chi']) ?>
                                    </span>
                                </div>

                                <div class="booking-facility-detail">
                                    <span class="booking-facility-label">Giờ hoạt động</span>
                                    <span class="booking-facility-value booking-facility-hours">
                                        <?= e(
                                            substr($quote['facility']['gio_mo_cua'], 0, 5)
                                            . ' – ' .
                                            substr($quote['facility']['gio_dong_cua'], 0, 5)
                                        ) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </section>
                    <section class="booking-section">
                        <div class="booking-section-title">
                            <span class="booking-section-number">02</span><h2>Thông tin lịch đặt</h2>
                            <div class="booking-play-date">
                                <span>Ngày đặt: </span>
                                <time datetime="<?= e($draft['input']['date']) ?>"><?= e((new DateTimeImmutable($draft['input']['date']))->format('d/m/Y')) ?></time>
                            </div>
                        </div>
                        
                        <?php
                            // Chỉ gom các dòng để hiển thị.
                            // Không thay đổi quote, booking hoặc giá gốc.
                            $scheduleGroups = [];

                            $lines = $quote['lines'];

                            // Sắp xếp theo sân và giờ bắt đầu.
                            usort($lines, static function ($a, $b) {
                                $courtA = (string) (
                                    $a['court_id'] ?? $a['ma_san'] ?? $a['name'] ?? ''
                                );

                                $courtB = (string) (
                                    $b['court_id'] ?? $b['ma_san'] ?? $b['name'] ?? ''
                                );

                                return strcmp($courtA, $courtB)
                                    ?: strcmp($a['start'], $b['start']);
                            });

                            foreach ($lines as $line) {
                                // Ưu tiên mã sân, tạm dùng tên sân khi quote
                                // chưa cung cấp mã sân.
                                $courtKey = (string) (
                                    $line['court_id']
                                    ?? $line['ma_san']
                                    ?? $line['name']
                                );

                                $lastIndex = count($scheduleGroups) - 1;

                                if (
                                    $lastIndex >= 0
                                    && $scheduleGroups[$lastIndex]['_court_key'] === $courtKey
                                    && $scheduleGroups[$lastIndex]['sport'] === $line['sport']
                                    && $scheduleGroups[$lastIndex]['end'] === $line['start']
                                ) {
                                    // Hai khung giờ liên tiếp của cùng một sân.
                                    $scheduleGroups[$lastIndex]['end'] = $line['end'];

                                    $scheduleGroups[$lastIndex]['minutes'] +=
                                        (int) $line['minutes'];

                                    $scheduleGroups[$lastIndex]['_amount_cents'] +=
                                        Money::cents($line['amount']);
                                } else {
                                    $group = $line;

                                    $group['_court_key'] = $courtKey;

                                    $group['minutes'] = (int) $line['minutes'];

                                    $group['_amount_cents'] =
                                        Money::cents($line['amount']);

                                    $scheduleGroups[] = $group;
                                }
                            }
                        ?>

                        <div class="booking-schedule-list">
                            <?php foreach ($scheduleGroups as $line): ?>
                                <article class="booking-schedule-item">

                                    <div class="booking-schedule-item-main">
                                        <h3><?= e($line['name']) ?></h3>
                                        <span class="booking-schedule-sport">
                                            <?= e($line['sport']) ?>
                                        </span>
                                    </div>

                                    <div class="booking-schedule-time">
                                        <?= e($line['start'] . ' – ' . $line['end']) ?>
                                    </div>

                                    <div class="booking-schedule-duration">
                                        <?php
                                            $minutes = (int) $line['minutes'];
                                            $hours = intdiv($minutes, 60);
                                            $remainingMinutes = $minutes % 60;
                                        ?>

                                        <?php if ($hours > 0): ?>
                                            <?= $hours ?> giờ
                                        <?php endif; ?>

                                        <?php if ($remainingMinutes > 0): ?>
                                            <?= $remainingMinutes ?> phút
                                        <?php endif; ?>
                                    </div>

                                    <div class="booking-schedule-price">
                                        <strong>
                                            <?= e(Money::display(
                                                Money::decimal($line['_amount_cents'])
                                            )) ?>
                                        </strong>
                                    </div>

                                </article>
                            <?php endforeach; ?>
                        </div>


                    </section>
                    <section class="booking-section booking-service-section">
                        <div class="booking-section-title booking-service-title">
                            <span class="booking-section-number">03</span>
                            <h2>Dịch vụ bổ sung</h2>

                            <button
                                type="button"
                                class="booking-add-service"
                                data-open-services
                                <?= !$quote['offers'] ? 'disabled' : '' ?>
                            >
                                <span aria-hidden="true">⊕</span>
                                Thêm dịch vụ
                            </button>
                        </div>
                        <div data-selected-services aria-live="polite"></div>
                        <?php if (!$quote['offers']): ?><p class="booking-subtle">Cơ sở chưa có dịch vụ đang cung cấp.</p><?php endif; ?>
                        <button type="button" class="booking-update" data-update-services hidden>Cập nhật dịch vụ</button>
                    </section>
                </div>
                <aside class="booking-sidebar" aria-label="Thanh toán và người đặt">
                    <div class="booking-summary">
                        <section class="booking-payment"><h2>Thông tin thanh toán</h2>

                            <dl class="booking-summary-rows">
                                <div>
                                    <dt>Tiền sân</dt>
                                    <dd><?= e(Money::display($quote['court_total'])) ?></dd>
                                </div>

                                <div>
                                    <dt>Tiền dịch vụ</dt>
                                    <dd><?= e(Money::display($quote['service_total'])) ?></dd>
                                </div>

                                <?php if ($quote['services']): ?>
                                    <ul class="booking-service-summary">
                                        <?php foreach ($quote['services'] as $line): ?>
                                            <li>
                                                <span><?= e($line['name'] . ' × ' . $line['quantity']) ?></span>
                                                <span><?= e(Money::display($line['amount'])) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                                <div>
                                    <dt>Tổng giờ chơi</dt>
                                    <dd>
                                        <?php
                                            $totalMinutes = (int) $quote['minutes'];
                                            $hours = intdiv($totalMinutes, 60);
                                            $minutes = $totalMinutes % 60;
                                        ?>

                                        <?php if ($hours > 0): ?>
                                            <?= $hours ?> giờ
                                        <?php endif; ?>

                                        <?php if ($minutes > 0): ?>
                                            <?= $minutes ?> phút
                                        <?php endif; ?>

                                        <?php if ($totalMinutes === 0): ?>
                                            0 phút
                                        <?php endif; ?>
                                    </dd>
                                </div>
                            </dl>

                            <div class="booking-total-row">
                                <span>Tổng thanh toán</span>
                                <strong class="booking-total">
                                    <?= e(Money::display($quote['total'])) ?>
                                </strong>
                            </div>
                        </section>
                        <div class="booking-action"><?php if ($user): ?>
                        <button class="btn btn--primary booking-confirm"
                                type="submit"
                                formaction="<?= e(url('/booking/confirm')) ?>"
                                data-confirm-booking>
                            Xác nhận &amp; Thanh toán
                        </button>
                    <?php else: ?>
                        <button class="btn btn--primary booking-confirm"
                                type="submit"
                                name="action"
                                value="login">
                            Đăng nhập để xác nhận
                        </button>
                    <?php endif; ?>
                    </div>
                    </div>
                    <section class="booking-booker">
                        <h2>Thông tin người đặt</h2>
                        <?php if ($user): ?>
                            <dl class="booking-customer">
                                <div>
                                    <dt>Họ và tên</dt>
                                    <dd><?= e($user['ten_khach_hang']) ?></dd>
                                </div>
                                <div>
                                    <dt>Số điện thoại</dt>
                                    <dd><?= e($user['so_dien_thoai_khach']) ?></dd>
                                </div>
                            </dl>

                            <?php if (!preg_match('/\A0[0-9]{9}\z/', $user['so_dien_thoai_khach'] ?? '')): ?>
                                <a class="booking-account-link"
                                   href="<?= e(url('/account')) ?>">
                                    Cập nhật số điện thoại
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="booking-subtle">
                                Đăng nhập để tiếp tục đặt sân.
                                Lựa chọn của bạn sẽ được giữ lại.
                            </p>

                            <div class="booking-auth-actions">
                                <button class="btn btn--outline"
                                        name="action"
                                        value="login">
                                    Đăng nhập
                                </button>
                                <button class="booking-text-button"
                                        name="action"
                                        value="register">
                                    Đăng ký
                                </button>
                            </div>
                        <?php endif; ?>

                        <div class="booking-note-field">
                            <label for="booking-note">
                                Ghi chú cho chủ sân
                            </label>

                            <textarea id="booking-note"
                                      name="note"
                                      maxlength="400"
                                      rows="2"><?= e($draft['extras']['note']) ?></textarea>
                        </div>
                    </section>
                </aside>
            </div>

            <!-- MODAL CHỌN DỊCH VỤ -->
            <?php if ($quote['offers']): ?>
                <dialog class="booking-service-dialog"
                        data-service-dialog
                        aria-labelledby="booking-service-dialog-title">

                    <div class="booking-dialog-header">
                        <div>
                            <h2 id="booking-service-dialog-title">
                                Chọn dịch vụ
                            </h2>
                        </div>

                        <button type="button"
                                class="booking-dialog-close"
                                data-close-services
                                aria-label="Đóng">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><!--!Font Awesome Free v7.3.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M183.1 137.4C170.6 124.9 150.3 124.9 137.8 137.4C125.3 149.9 125.3 170.2 137.8 182.7L275.2 320L137.9 457.4C125.4 469.9 125.4 490.2 137.9 502.7C150.4 515.2 170.7 515.2 183.2 502.7L320.5 365.3L457.9 502.6C470.4 515.1 490.7 515.1 503.2 502.6C515.7 490.1 515.7 469.8 503.2 457.3L365.8 320L503.1 182.6C515.6 170.1 515.6 149.8 503.1 137.3C490.6 124.8 470.3 124.8 457.8 137.3L320.5 274.7L183.1 137.4z"/></svg>
                        </button>
                    </div>

                    <p class="booking-error" data-service-feedback role="alert" hidden></p>

                    <div class="booking-dialog-search">
                        <input type="search"
                               data-service-search
                               placeholder="Tìm tên dịch vụ..."
                               aria-label="Tìm dịch vụ">
                    </div>

                    <div class="booking-dialog-list">
                        <?php foreach ($quote['offers'] as $id => $offer):
                            $available = $offer['purchasable']
                                && ($offer['available'] === null || $offer['available'] > 0);

                            $quantity = $draft['extras']['services'][$id] ?? 0;
                        ?>
                            <article class="booking-service"
                                data-service-row="<?= e($id) ?>"
                                data-unit-price-cents="<?= e(Money::cents($offer['don_gia'])) ?>">

                                <div class="booking-service-name">
                                    <h3><?= e($offer['ten_dich_vu']) ?></h3>

                                    <small>
                                        <?= e(Money::display($offer['don_gia']) . ' / ' . $offer['don_vi_tinh']) ?>
                                    </small>

                                    <small class="booking-stock">
                                        <?= !$offer['purchasable']
                                            ? 'Cho thuê: liên hệ cơ sở'
                                            : ($offer['available'] === null
                                                ? 'Đang cung cấp'
                                                : 'Còn ' . $offer['available']) ?>
                                    </small>
                                </div>

                                <div class="booking-service-controls">
                                    <div class="booking-quantity">
                                        <button type="button"
                                                data-quantity="-1"
                                                aria-label="Giảm <?= e($offer['ten_dich_vu']) ?>"
                                                <?= !$available ? 'disabled' : '' ?>>
                                            −
                                        </button>

                                        <input type="number"
                                               name="services[<?= e($id) ?>]"
                                               aria-label="Số lượng <?= e($offer['ten_dich_vu']) ?>"
                                               min="0"
                                               max="<?= e(min(999999, $offer['available'] ?? 999999)) ?>"
                                               step="1"
                                               value="<?= e($quantity) ?>"
                                               <?= !$available ? 'disabled' : '' ?>>

                                        <button type="button"
                                                data-quantity="1"
                                                aria-label="Tăng <?= e($offer['ten_dich_vu']) ?>"
                                                <?= !$available ? 'disabled' : '' ?>>
                                            +
                                        </button>
                                    </div>

                                    <button type="button"
                                            class="booking-remove-service"
                                            data-remove-service
                                            <?= !$available ? 'disabled' : '' ?>>
                                        Xóa
                                    </button>

                                    <span class="booking-service-amount"
                                          data-service-amount>
                                        <?= $quantity
                                            ? e(Money::display(Money::decimal(Money::cents($offer['don_gia']) * $quantity)))
                                            : '—' ?>
                                    </span>
                                </div>
                            </article>
                        <?php endforeach; ?>

                        <p class="booking-search-empty"
                           data-service-search-empty
                           hidden>
                            Không tìm thấy dịch vụ phù hợp.
                        </p>
                    </div>

                    <div class="booking-dialog-footer">
                        <button type="button"
                                class="booking-apply-services"
                                data-apply-services>
                            Áp dụng dịch vụ
                        </button>
                    </div>
                </dialog>
            <?php endif; ?>

        <?php else: ?>
            <input type="hidden"
                   name="note"
                   value="<?= e($draft['extras']['note']) ?>">

            <button class="btn btn--outline" type="submit">
                Bỏ dịch vụ và kiểm tra lại lịch
            </button>
        <?php endif; ?>

    </form>
</section>
