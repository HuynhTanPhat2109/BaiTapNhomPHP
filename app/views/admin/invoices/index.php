<?php
$bodyClass = 'page-catalog';
$statusLabels = [
    'DA_LAP' => 'Đã lập',
    'HUY' => 'Đã hủy',
];
$roleRoot = match ($scope['role'] ?? '') {
    'QUAN_LY_CONG_TY' => ['/company', 'Công ty'],
    'QUAN_LY_CO_SO' => ['/facility', 'Cơ sở'],
    'NHAN_VIEN' => ['/staff', 'Cơ sở'],
    default => ['/admin', 'Quản trị'],
};
?>
<section class="container catalog-page catalog-workspace admin-list invoice-list">
    <nav class="catalog-nav" aria-label="Điều hướng quản lý hóa đơn">
        <a href="<?= e(url($roleRoot[0])) ?>"><?= e($roleRoot[1]) ?></a>
        <span aria-hidden="true">/</span>
        <a href="<?= e(url('/admin/invoices')) ?>" aria-current="page">Quản lý hóa đơn</a>
    </nav>

    <header class="catalog-heading">
        <div>
            <p class="eyebrow">STRYKE / KẾ TOÁN &amp; VẬN HÀNH</p>
            <h1>Quản lý hóa đơn</h1>
        </div>
        <div>
            <span class="catalog-status" data-status="HOAT_DONG" style="font-size: 0.95rem; padding: 8px 14px;">
                Tổng cộng: <strong><?= number_format($total) ?></strong> hóa đơn
            </span>
        </div>
    </header>

    <?php if (!empty($notice)): ?>
        <p class="alert alert--success" role="status" style="margin-bottom: 20px;"><?= e($notice) ?></p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p class="alert alert--danger" role="alert" style="margin-bottom: 20px;"><?= e($error) ?></p>
    <?php endif; ?>

    <!-- THANH CÔNG CỤ TÌM KIẾM VÀ BỘ LỌC ĐA TIÊU CHÍ -->
    <form class="list-toolbar" method="get" action="<?= e(base_path() . '/index.php') ?>" aria-label="Tìm kiếm và lọc hóa đơn" style="display: flex; flex-wrap: wrap; gap: 14px; background: var(--surface, #fff); padding: 18px; border: 1px solid var(--border, #e0e0e0); border-radius: 8px; margin-bottom: 24px;">
        <input type="hidden" name="route" value="/admin/invoices">

        <!-- 1. Tìm kiếm từ khóa -->
        <div class="form-group" style="flex: 1 1 240px; min-width: 200px;">
            <label for="filter-q" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 5px; color: var(--muted, #666);">Số hóa đơn / Mã đơn / Khách hàng / SĐT</label>
            <input class="form-control" id="filter-q" type="search" name="q" maxlength="100" placeholder="VD: INV2611..., 15, Nguyễn Văn A..." value="<?= e($filters['q']) ?>">
        </div>

        <!-- 2. Từ ngày lập -->
        <div class="form-group" style="flex: 0 1 140px; min-width: 130px;">
            <label for="filter-date-from" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 5px; color: var(--muted, #666);">Từ ngày lập</label>
            <input class="form-control" id="filter-date-from" type="date" name="date_from" value="<?= e($filters['date_from']) ?>">
        </div>

        <!-- 3. Đến ngày lập -->
        <div class="form-group" style="flex: 0 1 140px; min-width: 130px;">
            <label for="filter-date-to" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 5px; color: var(--muted, #666);">Đến ngày lập</label>
            <input class="form-control" id="filter-date-to" type="date" name="date_to" value="<?= e($filters['date_to']) ?>">
        </div>

        <!-- 4. Cơ sở thể thao (nếu tài khoản quản lý nhiều cơ sở) -->
        <?php if ($scope['facility'] === null): ?>
            <div class="form-group" style="flex: 1 1 180px; min-width: 160px;">
                <label for="filter-facility" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 5px; color: var(--muted, #666);">Cơ sở thể thao</label>
                <select class="form-control" id="filter-facility" name="facility_id">
                    <option value="">Tất cả cơ sở</option>
                    <?php foreach ($facilities as $fac): ?>
                        <option value="<?= e($fac['ma_co_so']) ?>" <?= ((string) $filters['facility_id']) === (string) $fac['ma_co_so'] ? 'selected' : '' ?>>
                            <?= e($fac['ten_co_so']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <!-- 5. Trạng thái hóa đơn -->
        <div class="form-group" style="flex: 0 1 150px; min-width: 130px;">
            <label for="filter-status" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 5px; color: var(--muted, #666);">Trạng thái</label>
            <select class="form-control" id="filter-status" name="status">
                <option value="">Tất cả trạng thái</option>
                <?php foreach ($statusLabels as $val => $lbl): ?>
                    <option value="<?= e($val) ?>" <?= $filters['status'] === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- 6. Số lượng hiển thị -->
        <div class="form-group" style="flex: 0 1 100px;">
            <label for="filter-per-page" style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 5px; color: var(--muted, #666);">Hiển thị</label>
            <select class="form-control" id="filter-per-page" name="per_page">
                <?php foreach ([10, 20, 50] as $sz): ?>
                    <option value="<?= $sz ?>" <?= $per_page === $sz ? 'selected' : '' ?>><?= $sz ?> / trang</option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Nút hành động -->
        <div class="list-toolbar__actions" style="display: flex; align-items: flex-end; gap: 8px;">
            <button class="btn btn--primary" type="submit" style="padding: 9px 18px;">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" style="width: 16px; height: 16px;"><circle cx="10.5" cy="10.5" r="6.5" stroke="currentColor" stroke-width="2"/><path d="m16 16 5 5" stroke="currentColor" stroke-width="2"/></svg>
                <span>Tìm kiếm</span>
            </button>
            <a class="btn btn--outline" href="<?= e(url('/admin/invoices')) ?>" style="padding: 9px 14px;">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" style="width: 16px; height: 16px;"><path d="M4 4v6h6M4.5 10a8 8 0 1 1-.2 4" stroke="currentColor" stroke-width="2"/></svg>
                <span>Đặt lại</span>
            </a>
        </div>
    </form>

    <!-- BẢNG DANH SÁCH HÓA ĐƠN -->
    <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách hóa đơn">
        <table class="catalog-table">
            <colgroup><col class="invoice-col-stt"><col class="invoice-col-code"><col class="invoice-col-booking"><col><col><col><col><col class="invoice-col-date"><col class="invoice-col-status"><col class="invoice-col-actions"></colgroup>
            <thead>
                <tr>
                    <th class="list-stt" scope="col">STT</th>
                    <th scope="col">Số HĐ / Mã HĐ</th>
                    <th scope="col">Mã Booking</th>
                    <th scope="col">Khách hàng</th>
                    <th scope="col">Cơ sở thể thao</th>
                    <th scope="col">Tiền sân / Dịch vụ</th>
                    <th scope="col">Tổng tiền</th>
                    <th scope="col">Ngày lập</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col">Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $index => $row): ?>
                <?php
                $stt = ($page - 1) * $per_page + $index + 1;
                ?>
                <tr>
                    <td class="list-stt" data-label="STT"><?= $stt ?></td>
                    <th scope="row" class="invoice-code">
                        <strong class="invoice-number">
                            <?= e($row['so_hoa_don'] ?: ('HD' . str_pad($row['ma_hoa_don'], 6, '0', STR_PAD_LEFT))) ?>
                        </strong>
                        <small class="workspace-secondary">ID: #<?= (int) $row['ma_hoa_don'] ?></small>
                    </th>
                    <td data-label="Mã Booking">
                        <a href="<?= e(url('/admin/bookings/show') . '&id=' . $row['ma_dat_san']) ?>" title="Xem chi tiết đơn booking">
                            #<?= (int) $row['ma_dat_san'] ?> ↗
                        </a>
                    </td>
                    <td data-label="Khách hàng">
                        <div><?= e($row['ten_khach_hang']) ?></div>
                        <small class="workspace-secondary"><?= e($row['sdt_khach_hang']) ?></small>
                    </td>
                    <td data-label="Cơ sở thể thao">
                        <span><?= e($row['ten_co_so']) ?></span>
                    </td>
                    <td class="invoice-amounts" data-label="Tiền sân / Dịch vụ">
                        <div>Sân: <?= number_format((float) $row['tien_san'], 0, ',', '.') ?> đ</div>
                        <div>DV: <?= number_format((float) $row['tien_dich_vu'], 0, ',', '.') ?> đ</div>
                    </td>
                    <td class="invoice-total" data-label="Tổng tiền">
                        <?= number_format((float) $row['tong_tien'], 0, ',', '.') ?> đ
                    </td>
                    <td data-label="Ngày lập">
                        <div class="invoice-date"><?= e(date('d/m/Y', strtotime($row['ngay_lap']))) ?></div>
                        <small class="workspace-secondary"><?= e(date('H:i:s', strtotime($row['ngay_lap']))) ?></small>
                    </td>
                    <td data-label="Trạng thái">
                        <span class="catalog-status<?= $row['trang_thai'] === 'HUY' ? ' invoice-status--cancelled' : '' ?>">
                            <?= e($statusLabels[$row['trang_thai']] ?? $row['trang_thai']) ?>
                        </span>
                    </td>
                    <td data-label="Thao tác">
                        <div class="invoice-actions" role="group" aria-label="Thao tác hóa đơn #<?= (int) $row['ma_hoa_don'] ?>">
                            <a class="btn btn--outline" href="<?= e(url('/admin/invoices/show') . '&id=' . $row['ma_hoa_don']) ?>" title="Xem chi tiết hóa đơn">
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 3h9l4 4v14H6zM14 3v5h5M9 12h7M9 16h5"/></svg>Chi tiết
                            </a>
                            <a class="btn btn--primary" href="<?= e(url('/admin/invoices/print') . '&id=' . $row['ma_hoa_don']) ?>" target="_blank" rel="noopener" title="Mở trang in hóa đơn trong tab mới">
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 8V3h10v5M7 17H4V9h16v8h-3M7 14h10v7H7zM16 11h1"/></svg>In
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$items): ?>
                <tr>
                    <td colspan="10">
                        Không tìm thấy hóa đơn nào phù hợp với bộ lọc hiện tại.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PHÂN TRANG -->
    <?php if ($pages > 1): ?>
        <nav class="catalog-pagination" aria-label="Phân trang hóa đơn" style="margin-top: 24px; display: flex; justify-content: center; gap: 8px;">
            <?php for ($p = 1; $p <= $pages; $p++): ?>
                <?php
                $pageParams = array_merge($_GET, ['page' => $p, 'route' => '/admin/invoices']);
                $pageUrl = base_path() . '/index.php?' . http_build_query($pageParams);
                ?>
                <a class="btn <?= $p === $page ? 'btn--primary' : 'btn--outline' ?>" href="<?= e($pageUrl) ?>" style="padding: 6px 12px;">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</section>
