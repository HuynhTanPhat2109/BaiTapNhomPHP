<?php
use App\Services\DashboardService;
$revenuePage ??= false;
$dashboardRole = $user['ten_vai_tro'];
$dashboardHome = ['QUAN_TRI_TAP_DOAN' => '/admin', 'QUAN_LY_CONG_TY' => '/company', 'QUAN_LY_CO_SO' => '/facility', 'NHAN_VIEN' => '/staff'][$dashboardRole];
$dashboardTitle = ['QUAN_TRI_TAP_DOAN' => 'Tổng quan tập đoàn', 'QUAN_LY_CONG_TY' => 'Tổng quan công ty', 'QUAN_LY_CO_SO' => 'Tổng quan cơ sở', 'NHAN_VIEN' => 'Tổng quan ca trực'][$dashboardRole];
if ($revenuePage) { $dashboardHome .= '/revenue'; $dashboardTitle = 'Thống kê doanh thu'; }
$isGroup = in_array($dashboardRole, ['QUAN_TRI_TAP_DOAN', 'QUAN_LY_CONG_TY'], true);
$money = static fn($amount) => DashboardService::money((string) $amount);
$statusLabels = ['CHO_XAC_NHAN' => 'Chờ xác nhận', 'CHO_THANH_TOAN' => 'Chờ thanh toán', 'DA_XAC_NHAN' => 'Đã xác nhận', 'HOAN_THANH' => 'Hoàn thành', 'DA_HUY' => 'Đã hủy', 'HET_HAN' => 'Hết hạn'];
$paymentLabels = ['THANH_CONG' => 'Thành công', 'CHO_THANH_TOAN' => 'Chờ thanh toán', 'CAN_DOI_SOAT' => 'Cần đối soát', 'HUY' => 'Đã hủy', 'HET_HAN' => 'Hết hạn'];
?>
<section class="container catalog-page catalog-workspace workspace-page dashboard-page" data-dashboard>
    <header class="catalog-heading module-heading dashboard-heading"><div><p class="eyebrow">STRYKE / BÁO CÁO VẬN HÀNH</p><h1><?= e($dashboardTitle) ?></h1><p>Xin chào, <strong><?= e($user['ten_nhan_vien'] ?? $user['ten_dang_nhap']) ?></strong>. Theo dõi doanh thu và hoạt động trong phạm vi của bạn.</p></div><span class="dashboard-live"><span aria-hidden="true"></span> Dữ liệu từ hệ thống</span></header>
    <?php if (!empty($dashboardError)): ?><div class="dashboard-card dashboard-error" role="alert"><h2>Chưa thể tải Dashboard</h2><p><?= e($dashboardError) ?></p><a class="btn btn--outline" href="<?= e(url($dashboardHome)) ?>">Thử lại</a></div><?php return; endif; ?>
    <?php $f = $dashboard['filters']; $scope = $dashboard['scope']; $totals = $dashboard['totals']; $states = $dashboard['states']; $assets = $dashboard['assets']; ?>
    <?php if ($revenuePage): ?>
    <form class="dashboard-card dashboard-filters dashboard-filters--<?= $dashboardRole === 'QUAN_TRI_TAP_DOAN' ? 'admin' : ($isGroup ? 'company' : 'facility') ?>" method="get" action="<?= e(base_path() . '/index.php') ?>" data-dashboard-filters>
        <input type="hidden" name="route" value="<?= e($dashboardHome) ?>">
        <div class="dashboard-filter-heading"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg><h2>Bộ lọc báo cáo</h2><span>Chọn khoảng thời gian và phạm vi</span></div>
        <div class="dashboard-filter-field"><label for="dashboard-period">Thời gian</label><select id="dashboard-period" name="period" class="form-control"><?php foreach (['today' => 'Hôm nay', 'week' => '7 ngày gần nhất', 'month' => 'Tháng này', 'last_month' => 'Tháng trước', 'custom' => 'Khoảng ngày tùy chọn'] as $key => $label): ?><option value="<?= e($key) ?>"<?= $f['period'] === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div class="dashboard-filter-field"><label for="dashboard-from">Từ ngày</label><input class="form-control" id="dashboard-from" name="from" type="date" value="<?= e($f['from']) ?>" required></div>
        <div class="dashboard-filter-field"><label for="dashboard-to">Đến ngày</label><input class="form-control" id="dashboard-to" name="to" type="date" value="<?= e($f['to']) ?>" required></div>
        <div class="dashboard-filter-field"><label for="dashboard-group">Nhóm biểu đồ</label><select class="form-control" id="dashboard-group" name="group_by"><?php foreach (['auto' => 'Tự động', 'day' => 'Theo ngày', 'month' => 'Theo tháng'] as $key => $label): ?><option value="<?= e($key) ?>"<?= $f['group_mode'] === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <?php if ($dashboardRole === 'QUAN_TRI_TAP_DOAN'): ?><div class="dashboard-filter-field"><label for="dashboard-company">Công ty</label><select id="dashboard-company" name="company_id" class="form-control"><option value="">Toàn hệ thống</option><?php foreach ($dashboard['companyOptions'] as $option): ?><option value="<?= (int) $option['ma_cong_ty'] ?>"<?= $scope['company'] === (int) $option['ma_cong_ty'] ? ' selected' : '' ?>><?= e($option['ten_cong_ty']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
        <?php if ($isGroup): ?><div class="dashboard-filter-field"><label for="dashboard-facility">Cơ sở</label><select id="dashboard-facility" name="facility_id" class="form-control"><option value="">Tất cả cơ sở trong phạm vi</option><?php foreach ($dashboard['facilityOptions'] as $option): ?><option value="<?= (int) $option['ma_co_so'] ?>" data-company="<?= (int) $option['ma_cong_ty'] ?>"<?= $scope['facility'] === (int) $option['ma_co_so'] ? ' selected' : '' ?>><?= e($option['ten_co_so']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
        <div class="dashboard-filter-actions"><button class="btn btn--primary" type="submit"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg><span data-submit-label>Áp dụng</span></button><a class="btn btn--outline" href="<?= e(url($dashboardHome)) ?>"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 11a9 9 0 1 1 2 7M3 5v6h6"/></svg>Đặt lại</a></div>
        <p class="dashboard-filter-note">Asia/Ho_Chi_Minh · Doanh thu theo ngày thanh toán; booking theo ngày tạo. Khoảng tùy chọn tối đa 10 năm.</p>
    </form>
    <?php else: ?><div class="dashboard-period-summary"><p><strong>Tháng này</strong> · <?= e(date('d/m/Y', strtotime($f['from'])) . ' – ' . date('d/m/Y', strtotime($f['to']))) ?> · Asia/Ho_Chi_Minh</p><a class="btn btn--outline" href="<?= e(url($dashboardHome . '/revenue')) ?>">Thống kê doanh thu & bộ lọc</a></div><?php endif; ?>
    <?php if ($dashboardRole === 'NHAN_VIEN'): ?><p class="dashboard-readonly">Dashboard chỉ xem · Số liệu thuộc cơ sở được phân công. Không có thao tác thay đổi dữ liệu tại đây.</p><?php endif; ?>
    <div class="dashboard-section-heading"><div><h2><?= e($scope['facility'] !== null ? (array_values(array_filter($dashboard['facilities'], static fn($row) => (int) $row['ma_co_so'] === $scope['facility']))[0]['ten_co_so'] ?? 'Cơ sở') : ($scope['company'] !== null ? $dashboard['companies'][0]['ten_cong_ty'] : 'Toàn hệ thống')) ?></h2><p><?= e(date('d/m/Y', strtotime($f['from'])) . ' – ' . date('d/m/Y', strtotime($f['to']))) ?></p></div><span class="dashboard-unit">VND · Thanh toán thành công</span></div>
    <?php
    $kpis = [];
    if ($isGroup) $kpis[] = ['lifetime', 'Tổng đã thanh toán', $money($dashboard['lifetime']['total']), 'Toàn thời gian · Trong phạm vi đang lọc'];
    $kpis[] = ['revenue', 'Doanh thu đã thu', $money($totals['total']), number_format((int) $totals['bookings']) . ' booking thanh toán hợp lệ trong kỳ'];
    $kpis[] = ['bookings', 'Tổng booking trong kỳ', number_format(array_sum($states)), 'Theo ngày tạo booking'];
    $kpis[] = ['confirmed', 'Đã xác nhận / Hoàn thành', number_format($states['DA_XAC_NHAN'] + $states['HOAN_THANH']), 'Trạng thái hiện tại của booking trong kỳ'];
    $kpis[] = ['pending', 'Chờ thanh toán', number_format($states['CHO_THANH_TOAN']), $states['CHO_XAC_NHAN'] . ' booking khác đang chờ xác nhận'];
    $kpis[] = ['cancelled', 'Đã hủy / Hết hạn', number_format($states['DA_HUY'] + $states['HET_HAN']), 'Không tự tính là doanh thu'];
    if (!$isGroup) $kpis[] = ['paid', 'Booking đã thanh toán', number_format((int) $totals['bookings']), 'Theo ngày thanh toán thành công'];
    $kpiIcons = [
        'lifetime' => 'M4 4h16v16H4zM8 8h8M8 12h5M8 16h8',
        'revenue' => 'M3 6h18v14H3zM3 10h18M7 15h4M16 15h1',
        'bookings' => 'M4 5h16v16H4zM8 3v4M16 3v4M4 11h16M8 15h2M14 15h2',
        'confirmed' => 'M20 11v9H4V4h11M9 11l4 4 8-9',
        'pending' => 'M12 8v5l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0',
        'cancelled' => 'M9 9l6 6M15 9l-6 6M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0',
        'paid' => 'M4 3h16v18l-4-2-4 2-4-2-4 2zM8 10l3 3 5-6',
    ];
    ?>
    <div class="dashboard-kpis"><?php foreach ($kpis as [$key, $label, $value, $hint]): ?>
        <section class="dashboard-card dashboard-kpi dashboard-kpi--<?= e($key) ?>">
            <span class="dashboard-kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="<?= e($kpiIcons[$key]) ?>"/></svg></span>
            <h3><?= e($label) ?></h3><p class="dashboard-kpi-value" data-kpi="<?= e($key) ?>"><?= e($value) ?></p><small><?= e($hint) ?></small>
        </section>
    <?php endforeach; ?></div>
    <?php if (!$revenuePage): ?>
    <section class="dashboard-assets dashboard-card">
        <header><h2>Hạ tầng hiện tại</h2><p>Trong phạm vi đang lọc; không hồi tố trạng thái theo ngày.</p></header>
        <div class="dashboard-asset-grid">
            <?php foreach (($isGroup ? ['companies' => 'Công ty', 'facilities' => 'Tổng cơ sở', 'active_facilities' => 'Cơ sở hoạt động'] : []) + ['courts' => 'Sân hoạt động', 'services' => 'Dịch vụ đang cung cấp'] as $key => $label): ?>
                <div class="dashboard-asset"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="<?= e(match ($key) { 'companies' => 'M4 21V3h11v18M15 9h5v12M8 7h3M8 11h3M8 15h3M2 21h20', 'courts' => 'M3 5h18v14H3zM12 5v14M3 12h18', 'services' => 'M3 8h18l-2 13H5zM8 8V6a4 4 0 0 1 8 0v2', default => 'M12 22s8-7 8-14a8 8 0 0 0-16 0c0 7 8 14 8 14zM15 8a3 3 0 1 1-6 0 3 3 0 0 1 6 0' }) ?>"/></svg><div><strong><?= number_format($assets[$key]) ?></strong><span><?= e($label) ?></span></div></div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    <?php
    $groupRows = array_map(static fn($g) => ['label' => $g['name'], 'value' => (string) $g['total']], $dashboard['groups']);
    $stateRows = []; foreach ($states as $key => $count) $stateRows[] = ['label' => $statusLabels[$key] ?? $key, 'value' => (string) $count];
    $charts = [['id' => 'dashboard-timeline', 'title' => 'Doanh thu theo ' . ($f['group_by'] === 'month' ? 'tháng' : 'ngày'), 'subtitle' => 'Thanh toán thành công trong khoảng đã chọn', 'unit' => 'đ', 'type' => 'time', 'rows' => $dashboard['timeline']]];
    if ($isGroup) $charts[] = ['id' => 'dashboard-comparison', 'title' => 'Doanh thu theo ' . ($dashboard['groupType'] === 'company' ? 'công ty' : 'cơ sở'), 'subtitle' => 'So sánh trong cùng phạm vi và khoảng ngày', 'unit' => 'đ', 'type' => 'bars', 'rows' => $groupRows];
    $charts[] = ['id' => 'dashboard-booking-states', 'title' => 'Booking theo trạng thái', 'subtitle' => 'Booking tạo trong kỳ · Trạng thái hiện tại', 'unit' => 'booking', 'type' => 'donut', 'rows' => $stateRows];
    $charts[] = ['id' => 'dashboard-revenue-share', 'title' => $isGroup ? 'Tỷ trọng doanh thu' : 'Cơ cấu tiền sân và dịch vụ', 'subtitle' => $isGroup ? 'Cùng định nghĩa doanh thu trên mọi đơn vị' : 'Từ các tổng tiền snapshot đã lưu của booking', 'unit' => 'đ', 'type' => 'donut', 'rows' => $isGroup ? $groupRows : [['label' => 'Tiền sân', 'value' => (string) $totals['courts']], ['label' => 'Dịch vụ', 'value' => (string) $totals['services']]]];
    ?>
    <div class="dashboard-charts"><?php foreach ($charts as $chart) require __DIR__ . '/dashboard-chart.php'; ?></div>
    <?php if ($isGroup): ?><section class="dashboard-card dashboard-report"><header><h2>Chi tiết doanh thu theo <?= $dashboard['groupType'] === 'company' ? 'công ty' : 'cơ sở' ?></h2><p>Booking đã thanh toán hợp lệ, theo ngày thanh toán thành công.</p></header><div class="dashboard-table-scroll" tabindex="0" role="region" aria-label="Thống kê doanh thu"><table class="dashboard-table"><thead><tr><th scope="col"><?= $dashboard['groupType'] === 'company' ? 'Công ty' : 'Cơ sở' ?></th><?php if ($dashboard['groupType'] === 'company'): ?><th scope="col">Cơ sở</th><?php endif; ?><th scope="col">Booking đã thu</th><th scope="col">Tiền sân</th><th scope="col">Dịch vụ</th><th scope="col">Tổng đã thu</th><th scope="col">Tỷ trọng</th></tr></thead><tbody><?php foreach ($dashboard['groups'] as $row): ?><tr><th scope="row"><?= e($row['name']) ?></th><?php if ($dashboard['groupType'] === 'company'): ?><td><?= (int) $row['facilities'] ?></td><?php endif; ?><td><?= (int) $row['bookings'] ?></td><td><?= e($money($row['courts'])) ?></td><td><?= e($money($row['services'])) ?></td><td><strong><?= e($money($row['total'])) ?></strong></td><td><?= (float) $totals['total'] > 0 ? number_format((float) $row['total'] / (float) $totals['total'] * 100, 1, ',', '.') : '0' ?>%</td></tr><?php endforeach; ?><?php if (!$dashboard['groups']): ?><tr><td colspan="<?= $dashboard['groupType'] === 'company' ? 7 : 6 ?>">Chưa có đơn vị trong phạm vi được phân quyền.</td></tr><?php endif; ?></tbody><tfoot><tr><th scope="row">Tổng trong phạm vi</th><?php if ($dashboard['groupType'] === 'company'): ?><td><?= $assets['facilities'] ?></td><?php endif; ?><td><?= (int) $totals['bookings'] ?></td><td><?= e($money($totals['courts'])) ?></td><td><?= e($money($totals['services'])) ?></td><td><?= e($money($totals['total'])) ?></td><td><?= (float) $totals['total'] > 0 ? '100' : '0' ?>%</td></tr></tfoot></table></div></section><?php endif; ?>
    <?php if ($revenuePage) require __DIR__ . '/revenue-tables.php'; ?>
    <?php if (!$revenuePage): ?><div class="dashboard-charts dashboard-popular"><?php foreach (['popular_courts' => ['Sân được đặt nhiều', 'booking đã thanh toán'], 'popular_services' => ['Dịch vụ được sử dụng nhiều', 'số lượng từ snapshot']] as $key => [$label, $unit]): ?><section class="dashboard-card"><header><h2><?= e($label) ?></h2><p>Top 5 trong kỳ · <?= e($unit) ?></p></header><?php if (!$dashboard[$key]): ?><p class="dashboard-inline-empty">Chưa có dữ liệu phù hợp trong khoảng đã chọn.</p><?php else: ?><ol class="dashboard-top-list"><?php foreach ($dashboard[$key] as $row): ?><li><span><?= e($row['name']) ?></span><strong><?= (int) $row['quantity'] ?> <small><?= $key === 'popular_services' ? e($row['don_vi_tinh_snapshot']) : 'booking' ?></small></strong></li><?php endforeach; ?></ol><?php endif; ?></section><?php endforeach; ?></div>
    <?php endif; ?>
    <section class="dashboard-card dashboard-report"><header><h2>Booking gần đây</h2><p>8 booking gần nhất được tạo trong khoảng ngày, thuộc phạm vi được phân quyền.</p></header><?php if (!$dashboard['recent']): ?><p class="dashboard-inline-empty">Chưa có booking trong khoảng này.</p><?php else: ?><div class="dashboard-table-scroll" tabindex="0" role="region" aria-label="Booking gần đây"><table class="dashboard-table"><thead><tr><th scope="col">Booking / Ngày tạo</th><th scope="col">Cơ sở</th><th scope="col">Trạng thái booking</th><th scope="col">Thanh toán</th><th scope="col">Giá trị booking</th></tr></thead><tbody><?php foreach ($dashboard['recent'] as $row): ?><tr><th scope="row">#<?= (int) $row['ma_dat_san'] ?><small><?= e(date('d/m/Y H:i', strtotime($row['ngay_tao']))) ?></small></th><td class="dashboard-cell-name"><?= e($row['ten_co_so']) ?></td><td><span class="dashboard-badge"><?= e($statusLabels[$row['trang_thai_dat_san']] ?? $row['trang_thai_dat_san']) ?></span></td><td><span class="dashboard-badge<?= $row['payment_status'] === 'THANH_CONG' ? ' dashboard-badge--paid' : '' ?>"><?= e($paymentLabels[$row['payment_status']] ?? 'Chưa tạo thanh toán') ?></span></td><td><?= e($money($row['tong_tien_tam_tinh'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
    <?php if (!$revenuePage && $dashboardRole === 'QUAN_TRI_TAP_DOAN'): $dashboardLinks = (require dirname(__DIR__, 4) . '/config/admin-menu.php')[$dashboardRole]['QUẢN LÝ HỆ THỐNG']; ?><section class="dashboard-card dashboard-quick"><header><h2>Truy cập nhanh</h2><p>Danh mục và các chức năng quản trị hiện có.</p></header><div class="admin-quick__links"><?php foreach ($dashboardLinks as $link): if ($link['route'] === null) continue; ?><a class="admin-quick-link" href="<?= e(url($link['route'])) ?>"><span><strong><?= e($link['label']) ?></strong><small>Mở khu vực quản lý</small></span><span aria-hidden="true">↗</span></a><?php endforeach; ?></div><div class="dashboard-catalog-counts"><p>Bộ môn: <strong data-stat="bo_mon"><?= e($stats['bo_mon']) ?></strong></p><p>Loại sân: <strong data-stat="loai_san"><?= e($stats['loai_san']) ?></strong></p></div></section><?php endif; ?>
    <p class="dashboard-disclaimer">Thống kê thanh toán thành công hợp lệ, không phải doanh thu thuần, lợi nhuận hoặc phí nhượng quyền. Chưa có sổ hoàn tiền để khấu trừ tự động. Tiền sân/dịch vụ dùng tổng tiền snapshot; không tính lại theo giá hiện tại. Dữ liệu lịch sử theo công ty hiện đang sở hữu cơ sở.</p>
</section>
