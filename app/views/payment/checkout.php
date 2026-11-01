<?php
$bodyClass = 'page-payment';
$amountFormatted = number_format((float) $payment['so_tien'], 0, ',', '.') . ' đ';
$refCode = $payment['ma_tham_chieu'];
$isPaid = ($payment['trang_thai'] === 'THANH_CONG' || $payment['trang_thai_dat_san'] === 'DA_XAC_NHAN');
?>
<div class="container" style="max-width: 800px; margin: 40px auto; padding: 0 16px;">
    <div style="background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden;">
        
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%); color: #fff; padding: 24px 32px; text-align: center;">
            <h1 style="margin: 0 0 8px; font-size: 1.6rem; font-weight: 700;">Thanh toán chuyển khoản VietQR</h1>
            <p style="margin: 0; opacity: 0.9; font-size: 0.95rem;">Đơn đặt sân #<?= e($payment['ma_dat_san']) ?> &bull; Giữ chỗ tự động</p>
        </div>

        <div style="padding: 32px;">
            <?php if ($isPaid): ?>
                <!-- Đã thanh toán thành công -->
                <div style="text-align: center; padding: 30px 20px;">
                    <div style="width: 72px; height: 72px; background: #e8f5e9; color: #2e7d32; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 2.5rem; margin-bottom: 16px;">
                        ✓
                    </div>
                    <h2 style="color: #2e7d32; margin: 0 0 8px;">Đã thanh toán thành công!</h2>
                    <p style="color: #555; margin: 0 0 24px;">Đơn đặt sân của bạn đã được xác nhận. Chúc bạn có những giờ phút thi đấu vui vẻ!</p>
                    <a href="<?= e(url('/account')) ?>" class="btn btn--primary" style="display: inline-block; padding: 12px 28px; background: #1a73e8; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">
                        Xem chi tiết lịch đặt sân
                    </a>
                </div>
            <?php elseif ($isExpired): ?>
                <!-- Đã hết hạn -->
                <div style="text-align: center; padding: 30px 20px;">
                    <div style="width: 72px; height: 72px; background: #ffebee; color: #c62828; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 2.5rem; margin-bottom: 16px;">
                        !
                    </div>
                    <h2 style="color: #c62828; margin: 0 0 8px;">Thời gian giữ sân đã hết hạn</h2>
                    <p style="color: #555; margin: 0 0 24px;">Đơn đặt sân đã quá thời hạn giữ chỗ 6 phút. Vui lòng quay lại chọn lại khung giờ để đặt sân.</p>
                    <a href="<?= e(url('/courts')) ?>" class="btn btn--primary" style="display: inline-block; padding: 12px 28px; background: #1a73e8; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">
                        Quay lại danh sách sân
                    </a>
                </div>
            <?php elseif (!$bankAccount || !$qrUrl): ?>
                <!-- Chưa cấu hình tài khoản -->
                <div style="text-align: center; padding: 30px 20px;">
                    <div style="width: 72px; height: 72px; background: #fff3e0; color: #e65100; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 2.5rem; margin-bottom: 16px;">
                        ?
                    </div>
                    <h2 style="color: #e65100; margin: 0 0 8px;">Cơ sở chưa cấu hình tài khoản ngân hàng</h2>
                    <p style="color: #555; margin: 0 0 24px;">Cơ sở sân này hiện chưa cấu hình tài khoản nhận chuyển khoản. Vui lòng liên hệ ban quản lý cơ sở để được hỗ trợ.</p>
                </div>
            <?php else: ?>
                <!-- Đang chờ thanh toán -->
                
                <!-- Thanh đếm ngược thời gian giữ chỗ -->
                <div id="countdown-box" style="background: #fff8e1; border: 1px solid #ffe082; padding: 12px 16px; border-radius: 8px; margin-bottom: 24px; text-align: center;">
                    <span style="color: #f57f17; font-weight: 500;">Thời gian giữ sân còn lại: </span>
                    <strong id="timer-display" style="font-size: 1.25rem; color: #d84315; font-family: monospace; letter-spacing: 1px;">00:00</strong>
                </div>

                <div style="display: flex; flex-wrap: wrap; gap: 32px; align-items: center;">
                    <!-- Cột trái: Mã QR -->
                    <div style="flex: 1 1 300px; text-align: center;">
                        <div style="display: inline-block; padding: 12px; background: #fff; border: 2px dashed #1a73e8; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                            <img src="<?= e($qrUrl) ?>" alt="Mã VietQR thanh toán" style="max-width: 280px; width: 100%; height: auto; display: block; border-radius: 8px;">
                        </div>
                        <p style="margin: 12px 0 0; font-size: 0.88rem; color: #666;">
                            Mở ứng dụng Ngân hàng bất kỳ để quét mã QR
                        </p>
                    </div>

                    <!-- Cột phải: Thông tin chuyển khoản -->
                    <div style="flex: 1 1 360px;">
                        <h3 style="margin: 0 0 16px; font-size: 1.15rem; color: #333; border-bottom: 2px solid #f0f0f0; padding-bottom: 8px;">
                            Thông tin chuyển khoản
                        </h3>

                        <div style="margin-bottom: 14px;">
                            <span style="color: #666; font-size: 0.9rem; display: block;">Ngân hàng thụ hưởng</span>
                            <strong style="font-size: 1.05rem; color: #1a73e8;"><?= e($bankAccount['ten_ngan_hang']) ?></strong>
                        </div>

                        <div style="margin-bottom: 14px;">
                            <span style="color: #666; font-size: 0.9rem; display: block;">Số tài khoản</span>
                            <div style="display: flex; align-items: center; gap: 8px; margin-top: 2px;">
                                <strong id="acc-num" style="font-size: 1.25rem; font-family: monospace; color: #222;"><?= e($bankAccount['so_tai_khoan']) ?></strong>
                                <button type="button" onclick="copyToClipboard('<?= e($bankAccount['so_tai_khoan']) ?>', this)" style="padding: 4px 10px; font-size: 0.8rem; background: #f1f3f4; border: 1px solid #dadce0; border-radius: 4px; cursor: pointer;">
                                    Sao chép
                                </button>
                            </div>
                        </div>

                        <div style="margin-bottom: 14px;">
                            <span style="color: #666; font-size: 0.9rem; display: block;">Tên người nhận</span>
                            <strong style="font-size: 1.05rem; color: #222; text-transform: uppercase;"><?= e($bankAccount['ten_chu_tai_khoan']) ?></strong>
                        </div>

                        <div style="margin-bottom: 14px;">
                            <span style="color: #666; font-size: 0.9rem; display: block;">Số tiền</span>
                            <div style="display: flex; align-items: center; gap: 8px; margin-top: 2px;">
                                <strong style="font-size: 1.35rem; color: #d84315; font-weight: 700;"><?= e($amountFormatted) ?></strong>
                                <button type="button" onclick="copyToClipboard('<?= (int) round((float) $payment['so_tien']) ?>', this)" style="padding: 4px 10px; font-size: 0.8rem; background: #f1f3f4; border: 1px solid #dadce0; border-radius: 4px; cursor: pointer;">
                                    Sao chép
                                </button>
                            </div>
                        </div>

                        <div style="margin-bottom: 16px; background: #f8f9fa; padding: 12px; border-radius: 8px; border-left: 4px solid #1a73e8;">
                            <span style="color: #d32f2f; font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 4px;">
                                ⚠️ NỘI DUNG CHUYỂN KHOẢN (BẮT BUỘC CHÍNH XÁC):
                            </span>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <strong style="font-size: 1.2rem; font-family: monospace; color: #1a73e8;"><?= e($refCode) ?></strong>
                                <button type="button" onclick="copyToClipboard('<?= e($refCode) ?>', this)" style="padding: 4px 10px; font-size: 0.8rem; background: #e8f0fe; color: #1a73e8; border: 1px solid #1a73e8; border-radius: 4px; cursor: pointer; font-weight: 600;">
                                    Sao chép
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gợi ý hướng dẫn -->
                <div style="margin-top: 28px; padding-top: 20px; border-top: 1px solid #eee; font-size: 0.85rem; color: #666;">
                    <p style="margin: 0 0 6px;">💡 <strong>Mẹo:</strong> Quét mã QR sẽ tự động điền sẵn số tài khoản, số tiền và nội dung chuyển khoản chuẩn xác nhất.</p>
                    <p style="margin: 0;">Trang web sẽ tự động xác nhận và chuyển hướng ngay khi giao dịch được hệ thống ghi nhận thành công.</p>
                </div>

                <!-- Script đếm ngược và kiểm tra trạng thái thanh toán tự động -->
                <script>
                    let remaining = <?= (int) $remainingSeconds ?>;
                    const timerDisplay = document.getElementById('timer-display');

                    function updateTimer() {
                        if (remaining <= 0) {
                            timerDisplay.textContent = '00:00';
                            document.getElementById('countdown-box').innerHTML = '<span style="color:#c62828;font-weight:600;">Đã hết thời gian giữ sân! Vui lòng tải lại trang.</span>';
                            return;
                        }
                        const m = Math.floor(remaining / 60);
                        const s = remaining % 60;
                        timerDisplay.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
                        remaining--;
                    }
                    setInterval(updateTimer, 1000);
                    updateTimer();

                    function copyToClipboard(text, btn) {
                        navigator.clipboard.writeText(text).then(() => {
                            const original = btn.textContent;
                            btn.textContent = 'Đã chép!';
                            btn.style.background = '#e8f5e9';
                            btn.style.color = '#2e7d32';
                            setTimeout(() => {
                                btn.textContent = original;
                                btn.style.background = '';
                                btn.style.color = '';
                            }, 2000);
                        });
                    }

                    // Tự động kiểm tra trạng thái thanh toán mỗi 4 giây
                    const checkInterval = setInterval(function() {
                        fetch('<?= e(url('/payment/status?ref=' . urlencode($refCode))) ?>')
                            .then(res => res.json())
                            .then(data => {
                                if (!data) return;
                                const isTerminal = data.is_confirmed || data.is_expired ||
                                    ['DA_HUY', 'HET_HAN', 'DA_XAC_NHAN', 'HOAN_THANH'].includes(data.booking_status) ||
                                    ['THANH_CONG', 'THAT_BAI', 'DA_HUY'].includes(data.payment_status);
                                if (isTerminal) {
                                    clearInterval(checkInterval);
                                    if (data.is_confirmed) {
                                        window.location.reload();
                                    }
                                }
                            })
                            .catch(err => console.log('Polling check error:', err));
                    }, 4000);
                </script>
            <?php endif; ?>
        </div>
    </div>
</div>
