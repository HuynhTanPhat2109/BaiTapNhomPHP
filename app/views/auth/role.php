<?php
$bodyClass = 'page-catalog';
$role = $user['ten_vai_tro'] ?? '';
$facilityName = $user['ten_co_so'] ?? null;
$companyName = $user['ten_cong_ty'] ?? null;
$displayName = $user['ten_nhan_vien'] ?? $user['ten_dang_nhap'] ?? 'Nhân viên';
?>
<section class="container catalog-page">
    <header class="admin-dashboard__heading" style="margin-bottom: 24px;">
        <p class="eyebrow">STRYKE / <?= e($title) ?></p>
        <h1><?= e($facilityName ?: $title) ?></h1>
        <p>Xin chào, <strong><?= e($displayName) ?></strong>. Chào mừng bạn đến không gian vận hành STRYKE.</p>
    </header>

    <?php if (in_array($role, ['QUAN_LY_CO_SO', 'NHAN_VIEN'], true)): ?>
        <!-- KHỐI 1: THỐNG KÊ NHANH CƠ SỞ -->
        <div class="admin-stats">
            <section class="admin-stat" aria-labelledby="bookings-stat-title">
                <span class="admin-stat__icon" aria-hidden="true">
                    <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                </span>
                <h2 id="bookings-stat-title">Đơn đặt sân cơ sở</h2>
                <p class="admin-stat__value" data-stat="bookings"><?= number_format((int) ($stats['bookings'] ?? 0)) ?></p>
                <p>Tổng số đơn đặt sân thuộc phạm vi cơ sở</p>
            </section>

            <section class="admin-stat" aria-labelledby="invoices-stat-title">
                <span class="admin-stat__icon" aria-hidden="true">
                    <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                    </svg>
                </span>
                <h2 id="invoices-stat-title">Hóa đơn thanh toán</h2>
                <p class="admin-stat__value" data-stat="invoices"><?= number_format((int) ($stats['invoices'] ?? 0)) ?></p>
                <p>Hóa đơn thu ngân đã phát hành tại cơ sở</p>
            </section>
        </div>

        <!-- KHỐI 2: TRUY CẬP NGHIỆP VỤ NHANH -->
        <section class="account-card admin-quick" aria-labelledby="quick-title" style="margin-bottom: 24px;">
            <h2 id="quick-title">Truy cập nghiệp vụ nhanh</h2>
            <p>Quản lý toàn diện các hoạt động vận hành sân, duyệt thanh toán và thu ngân tại cơ sở.</p>
            <div class="admin-quick__links">
                <a class="admin-quick-link" href="<?= e(url('/admin/bookings')) ?>">
                    <span>
                        <strong>📅 Quản lý &amp; Duyệt Booking</strong>
                        <small>Xem danh sách đặt sân, đối soát giao dịch VietQR, xác nhận nhận tiền hoặc hủy giữ sân</small>
                    </span>
                    <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </a>

                <a class="admin-quick-link" href="<?= e(url('/admin/invoices')) ?>">
                    <span>
                        <strong>🧾 Quản lý &amp; In hóa đơn</strong>
                        <small>Tra cứu danh sách hóa đơn, xem chi tiết và in phiếu hóa đơn nhiệt POS cho khách</small>
                    </span>
                    <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </a>

                <a class="admin-quick-link" href="<?= e(url($role === 'NHAN_VIEN' ? '/staff/dich-vu' : '/facility/dich-vu')) ?>">
                    <span>
                        <strong>🥤 <?= $role === 'NHAN_VIEN' ? 'Bảng giá dịch vụ tại cơ sở' : 'Quản lý dịch vụ tại cơ sở' ?></strong>
                        <small><?= $role === 'NHAN_VIEN' ? 'Tra cứu đơn giá nước uống, thuê bóng, thuê vợt tại cơ sở' : 'Cập nhật giá và tồn kho nước uống, thuê bóng, vợt tại cơ sở' ?></small>
                    </span>
                    <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </a>

                <?php if ($role === 'QUAN_LY_CO_SO'): ?>
                    <a class="admin-quick-link" href="<?= e(url('/facility/bank')) ?>">
                        <span>
                            <strong>🏦 Tài khoản ngân hàng VietQR</strong>
                            <small>Cấu hình tài khoản ngân hàng nhận tiền để tạo mã QR VietQR tự động</small>
                        </span>
                        <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                    </a>
                <?php endif; ?>
            </div>
        </section>

        <!-- KHỐI 3: THÔNG TIN CƠ SỞ & PHÂN CÔNG -->
        <?php if ($facilityName || !empty($user['ten_cong_ty'])): ?>
            <section class="account-card" aria-labelledby="facility-details-title">
                <h2 id="facility-details-title" style="font-size: 1.15rem; margin-top: 0; margin-bottom: 16px;">Thông tin đơn vị công tác</h2>
                <dl class="catalog-details">
                    <?php if ($facilityName): ?>
                        <div><dt>Cơ sở phân công</dt><dd><strong><?= e($facilityName) ?></strong></dd></div>
                    <?php endif; ?>
                    <?php if (!empty($user['ten_cong_ty'])): ?>
                        <div><dt>Công ty quản lý</dt><dd><?= e($user['ten_cong_ty']) ?></dd></div>
                    <?php endif; ?>
                    <div><dt>Nhân sự phụ trách</dt><dd><?= e($displayName) ?> (<?= e($role === 'QUAN_LY_CO_SO' ? 'Quản lý cơ sở' : 'Nhân viên cơ sở') ?>)</dd></div>
                    <div><dt>Trạng thái phân công</dt><dd><span class="catalog-status" data-status="HOAT_DONG">Đang hoạt động</span></dd></div>
                </dl>
            </section>
        <?php endif; ?>

    <?php elseif ($role === 'QUAN_LY_CONG_TY'): ?>
        <section class="account-card admin-quick" aria-labelledby="quick-title" style="margin-bottom: 24px;">
            <h2 id="quick-title">Chức năng quản lý công ty</h2>
            <p>Điều hành các hoạt động kinh doanh và nhân sự của các cơ sở trực thuộc.</p>
            <div class="admin-quick__links">
                <a class="admin-quick-link" href="<?= e(url('/company/employees')) ?>">
                    <span>
                        <strong>Quản lý nhân viên</strong>
                        <small>Thêm, sửa và phân quyền nhân viên theo cơ sở</small>
                    </span>
                    <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </a>

                <a class="admin-quick-link" href="<?= e(url('/admin/bookings')) ?>">
                    <span>
                        <strong>Quản lý đặt sân</strong>
                        <small>Theo dõi đơn đặt sân trên toàn bộ các cơ sở của công ty</small>
                    </span>
                    <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </a>

                <a class="admin-quick-link" href="<?= e(url('/admin/invoices')) ?>">
                    <span>
                        <strong>Hóa đơn công ty</strong>
                        <small>Quản lý toàn bộ hóa đơn thanh toán của các cơ sở</small>
                    </span>
                    <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </a>

                <a class="admin-quick-link" href="<?= e(url('/company/dich-vu')) ?>">
                    <span>
                        <strong>Quản lý dịch vụ</strong>
                        <small>Bảng giá và dịch vụ áp dụng chung cho các cơ sở</small>
                    </span>
                    <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </a>
            </div>
        </section>

    <?php elseif ($role === 'QUAN_TRI_TAP_DOAN'): ?>
        <section class="account-card admin-quick" aria-labelledby="quick-title" style="margin-bottom: 24px;">
            <h2 id="quick-title">Truy cập quản trị</h2>
            <div class="admin-quick__links">
                <a class="admin-quick-link" href="<?= e(url('/admin')) ?>">
                    <span><strong>Tổng quan hệ thống</strong><small>Xem báo cáo toàn diện</small></span>
                    <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </a>
                <a class="admin-quick-link" href="<?= e(url('/admin/bookings')) ?>">
                    <span><strong>Quản lý booking</strong><small>Đơn đặt sân toàn hệ thống</small></span>
                    <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </a>
            </div>
        </section>

    <?php else: ?>
        <p class="role-notice">Bạn đã đăng nhập vào khu vực dành cho vai trò của mình.</p>
    <?php endif; ?>
</section>
