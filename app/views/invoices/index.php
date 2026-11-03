<?php
$roleLabels = [
    'QUAN_TRI_TAP_DOAN' => 'Quản trị tập đoàn',
    'QUAN_LY_CONG_TY' => 'Quản lý công ty',
    'QUAN_LY_CO_SO' => 'Quản lý cơ sở',
    'NHAN_VIEN' => 'Nhân viên',
    'KHACH_HANG' => 'Khách hàng',
];
$displayName = $user['ten_khach_hang'] ?? $user['ho_ten'] ?? $user['ten_dang_nhap'] ?? 'Khách hàng';
$initial = mb_strtoupper(mb_substr($displayName, 0, 1, 'UTF-8'), 'UTF-8');
?>
<section class="container account-page">
    <div class="account-page__intro">
        <p class="eyebrow">STRYKE / TÀI KHOẢN / HÓA ĐƠN</p>
        <h1 class="account-h1">Lịch sử hóa đơn thanh toán.</h1>
        <p>Tra cứu và in biên lai điện tử cho tất cả các giao dịch đặt sân đã thanh toán thành công.</p>
    </div>

    <div class="account-layout">
        <!-- SIDEBAR TÀI KHOẢN -->
        <aside class="account-sidebar" aria-label="Menu tài khoản">
            <div class="account-identity">
                <div class="profile-avatar" aria-hidden="true"><?= e($initial) ?></div>
                <div class="account-identity__info">
                    <h2><?= e($displayName) ?></h2>
                    <span class="account-role">Khách hàng STRYKE</span>
                </div>
            </div>
            <nav class="account-sidebar__nav" style="display: flex; flex-direction: column; gap: 6px; margin-top: 16px;">
                <a class="account-area-link" href="<?= e(url('/account')) ?>" style="padding: 10px 14px; border-radius: 6px; text-decoration: none; color: inherit; background: transparent;">
                    👤 Thông tin cá nhân
                </a>
                <a class="account-area-link" href="<?= e(url('/invoices')) ?>" style="padding: 10px 14px; border-radius: 6px; text-decoration: none; font-weight: 700; background: #e6f4ea; color: #137333;">
                    🧾 Lịch sử hóa đơn (<?= number_format($total) ?>)
                </a>
                <a class="account-area-link" href="<?= e(url('/account/settings')) ?>" style="padding: 10px 14px; border-radius: 6px; text-decoration: none; color: inherit; background: transparent;">
                    ⚙️ Cài đặt tài khoản
                </a>
            </nav>
        </aside>

        <!-- NỘI DUNG CHÍNH: DANH SÁCH HÓA ĐƠN -->
        <div class="account-content">
            <section class="account-card" aria-label="Danh sách hóa đơn">
                
                <!-- BỘ LỌC TÌM KIẾM HÓA ĐƠN -->
                <form method="get" action="<?= e(base_path() . '/index.php') ?>" style="display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #edf0ee;">
                    <input type="hidden" name="route" value="/invoices">

                    <div style="flex: 1 1 200px;">
                        <input class="form-control" type="search" name="q" placeholder="Số hóa đơn / Mã đơn đặt..." value="<?= e($filters['q']) ?>" style="font-size: 0.9rem;">
                    </div>
                    <div style="flex: 0 1 140px;">
                        <input class="form-control" type="date" name="date_from" value="<?= e($filters['date_from']) ?>" title="Từ ngày" style="font-size: 0.9rem;">
                    </div>
                    <div style="flex: 0 1 140px;">
                        <input class="form-control" type="date" name="date_to" value="<?= e($filters['date_to']) ?>" title="Đến ngày" style="font-size: 0.9rem;">
                    </div>
                    <button class="btn btn--primary btn--small" type="submit" style="padding: 8px 14px;">
                        Tìm kiếm
                    </button>
                    <?php if (!empty($filters['q']) || !empty($filters['date_from']) || !empty($filters['date_to'])): ?>
                        <a class="btn btn--outline btn--small" href="<?= e(url('/invoices')) ?>" style="padding: 8px 12px;">
                            Đặt lại
                        </a>
                    <?php endif; ?>
                </form>

                <!-- BẢNG HÓA ĐƠN -->
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.92rem;">
                        <thead>
                            <tr style="border-bottom: 2px solid #edf0ee; color: #666; font-size: 0.82rem; text-transform: uppercase;">
                                <th style="padding: 10px 12px;">Số hóa đơn</th>
                                <th style="padding: 10px 12px;">Đơn đặt sân</th>
                                <th style="padding: 10px 12px;">Cơ sở thể thao</th>
                                <th style="padding: 10px 12px; text-align: right;">Tổng tiền</th>
                                <th style="padding: 10px 12px;">Ngày lập</th>
                                <th style="padding: 10px 12px; text-align: center;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $row): ?>
                                <tr style="border-bottom: 1px solid #f2f4f3;">
                                    <td style="padding: 12px; font-weight: 700; font-family: monospace; color: #1a73e8;">
                                        <?= e($row['so_hoa_don'] ?: ('HD' . str_pad($row['ma_hoa_don'], 6, '0', STR_PAD_LEFT))) ?>
                                    </td>
                                    <td style="padding: 12px; font-weight: 600;">
                                        #<?= (int) $row['ma_dat_san'] ?>
                                    </td>
                                    <td style="padding: 12px; color: #444;">
                                        <?= e($row['ten_co_so']) ?>
                                    </td>
                                    <td style="padding: 12px; text-align: right; font-weight: 700; color: #0f5132;">
                                        <?= number_format((float) $row['tong_tien'], 0, ',', '.') ?> đ
                                    </td>
                                    <td style="padding: 12px; color: #666; font-size: 0.85rem;">
                                        <?= e(date('d/m/Y H:i', strtotime($row['ngay_lap']))) ?>
                                    </td>
                                    <td style="padding: 12px; text-align: center; white-space: nowrap;">
                                        <a class="btn btn--outline btn--small" href="<?= e(url('/invoices/show') . '&id=' . $row['ma_hoa_don']) ?>" style="padding: 6px 14px; text-decoration: none; font-size: 0.85rem; font-weight: 600;" title="Xem chi tiết hóa đơn">
                                            Xem chi tiết ↗
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="6" style="padding: 36px 16px; text-align: center; color: #888;">
                                        <div style="font-size: 2rem; margin-bottom: 8px;">🧾</div>
                                        <p style="margin: 0; font-size: 0.95rem;">Bạn chưa có hóa đơn thanh toán nào.</p>
                                        <p style="margin: 4px 0 0; font-size: 0.85rem; color: #aaa;">Khi bạn hoàn tất chuyển khoản và được xác nhận, hóa đơn sẽ tự động hiển thị tại đây.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- PHÂN TRANG -->
                <?php if ($pages > 1): ?>
                    <nav aria-label="Phân trang hóa đơn" style="margin-top: 20px; display: flex; justify-content: center; gap: 8px;">
                        <?php for ($p = 1; $p <= $pages; $p++): ?>
                            <?php
                            $pageParams = array_merge($_GET, ['page' => $p, 'route' => '/invoices']);
                            $pageUrl = base_path() . '/index.php?' . http_build_query($pageParams);
                            ?>
                            <a class="btn <?= $p === $page ? 'btn--primary' : 'btn--outline' ?> btn--small" href="<?= e($pageUrl) ?>" style="padding: 6px 12px;">
                                <?= $p ?>
                            </a>
                        <?php endfor; ?>
                    </nav>
                <?php endif; ?>

            </section>
        </div>
    </div>
</section>
