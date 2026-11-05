<?php // Render only verified states. Receipt acceptance is not payment confirmation. ?>
<section class="payment-result" data-payment-result <?= (!$isConfirmedBooking && !$isReceiptWait) ? 'hidden' : '' ?> aria-labelledby="payment-result-title">
    <div class="payment-result-content">
        <div class="payment-result-check" aria-hidden="true">
            <svg viewBox="0 0 80 80" fill="none"><path d="m21 40 13 13 26-28" stroke="currentColor" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="payment-result-copy" role="status" aria-live="polite" aria-atomic="true">
            <h1 id="payment-result-title" data-result-title>
                <?= $isConfirmedBooking
                    ? 'Thanh toán thành công!'
                    : 'Gửi xác nhận thanh toán thành công!' ?>
            </h1>

            <p data-result-description>
                <?= $isConfirmedBooking
                    ? 'Yêu cầu của bạn đã được tiếp nhận. Theo dõi đơn đặt sân trong lịch sử đặt sân.'
                    : 'Cảm ơn bạn đã đặt sân tại STRYKE!' ?>
            </p>
        </div>
        <svg class="payment-result-calendar" viewBox="0 0 300 195" fill="none" aria-hidden="true">
            <defs>
                <linearGradient id="result-paper" x1="90" y1="45" x2="200" y2="165" gradientUnits="userSpaceOnUse"><stop stop-color="#f2f9ec"/><stop offset="1" stop-color="#9bc89e"/></linearGradient>
                <linearGradient id="result-green" x1="80" y1="20" x2="230" y2="155" gradientUnits="userSpaceOnUse"><stop stop-color="#a9e859"/><stop offset="1" stop-color="#357b42"/></linearGradient>
            </defs>
            <ellipse cx="150" cy="173" rx="118" ry="18" fill="#224b31" opacity=".45"/>
            <ellipse cx="150" cy="171" rx="84" ry="10" fill="#173820"/>
            <g transform="translate(10 0) skewX(-6)">
                <rect x="75" y="35" width="154" height="132" rx="17" fill="#183e26"/>
                <rect x="68" y="27" width="154" height="132" rx="15" fill="url(#result-paper)"/>
                <path d="M83 27h124a15 15 0 0 1 15 15v25H68V42a15 15 0 0 1 15-15Z" fill="url(#result-green)"/>
                <path d="M102 18v27m81-27v27" stroke="#173c25" stroke-width="13" stroke-linecap="round"/>
                <path d="M100 16v25m81-25v25" stroke="#ecf8e6" stroke-width="10" stroke-linecap="round"/>
                <g fill="#71a77c" opacity=".5"><rect x="91" y="82" width="23" height="23" rx="4"/><rect x="128" y="82" width="23" height="23" rx="4"/><rect x="165" y="82" width="23" height="23" rx="4"/><rect x="91" y="119" width="23" height="23" rx="4"/><rect x="128" y="119" width="23" height="23" rx="4"/></g>
            </g>
            <circle cx="220" cy="137" r="33" fill="url(#result-green)" stroke="#bce97e" stroke-width="2"/>
            <path d="m204 136 11 11 21-23" stroke="#f5ffe9" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <nav class="payment-result-actions" aria-label="Điều hướng sau gửi biên lai">
            <a class="payment-result-home" href="<?= e(url('/')) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m3 10 9-8 9 8M5 9v12h5v-7h4v7h5V9" stroke-linejoin="round" stroke-linecap="round"/></svg>
                Về trang chủ
            </a>
            <a class="payment-result-history" href="<?= e(url('/booking/history')) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 2v6m10-6v6M3 11h18M7 15h2m3 0h2m3 0h1M7 18h2m3 0h2" stroke-linecap="round"/></svg>
                Xem lịch đặt sân
            </a>
        </nav>
    </div>
</section>
