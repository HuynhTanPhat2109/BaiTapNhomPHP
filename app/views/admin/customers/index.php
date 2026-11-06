<?php
$bodyClass = 'page-catalog';
$listPath = '/admin/customers';
$listLabel = 'khách hàng';
$filterFields = ['q' => ['label' => 'Tên, số điện thoại hoặc email']];
$dateTime = static fn($value) => $value ? date('d/m/Y H:i', strtotime($value)) : '—';
?>
<section class="container catalog-page admin-list catalog-workspace workspace-page">
    <header class="catalog-heading module-heading">
        <div><p class="eyebrow">Công ty / Khách hàng</p><h1>Quản lý khách hàng</h1><p>Khách hàng đã đặt sân trong phạm vi <?= $scope['facility'] === null ? 'công ty' : 'cơ sở được phân công' ?>. Chỉ xem thông tin.</p></div>
    </header>
    <?php require dirname(__DIR__) . '/partials/list-toolbar.php'; ?>
    <?php $tableLabel = 'Khách hàng'; $tableTotal = $total; require dirname(__DIR__) . '/partials/list-summary.php'; ?>
    <?php if (!$items): ?>
        <div class="workspace-empty" role="status"><h2><?= $filters['q'] !== '' ? 'Không tìm thấy khách hàng phù hợp' : 'Chưa có khách hàng đặt sân' ?></h2><p>Chỉ hiển thị khách hàng có booking thuộc phạm vi được phép xem.</p></div>
    <?php else: ?>
    <div class="catalog-table-wrap" tabindex="0" role="region" aria-label="Danh sách khách hàng">
        <table class="catalog-table workspace-table">
            <thead><tr><th scope="col">STT</th><th scope="col">Mã khách hàng</th><th scope="col">Họ và tên</th><th scope="col">Số điện thoại</th><th scope="col">Email</th><th scope="col">Số lượt đặt sân</th><th scope="col">Lần đặt sân gần nhất</th><th scope="col">Thao tác</th></tr></thead>
            <tbody><?php foreach ($items as $index => $customer): ?>
                <tr>
                    <td data-label="STT"><?= e($offset + $index + 1) ?></td>
                    <td data-label="Mã khách hàng">#<?= e($customer['ma_khach_hang']) ?></td>
                    <th scope="row"><?= e($customer['ho_ten']) ?></th>
                    <td data-label="Số điện thoại"><?= e($customer['so_dien_thoai']) ?></td>
                    <td data-label="Email"><?= e($customer['email'] ?: 'Chưa cập nhật') ?></td>
                    <td class="workspace-number" data-label="Số lượt đặt sân"><?= e($customer['booking_count']) ?></td>
                    <td data-label="Lần đặt sân gần nhất"><?= e($dateTime($customer['last_booking_at'])) ?></td>
                    <td data-label="Thao tác"><a class="admin-table-link" href="<?= e(url('/admin/customers/show') . '&id=' . (int) $customer['ma_khach_hang']) ?>">Xem chi tiết</a></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php require dirname(__DIR__) . '/partials/pagination.php'; ?>
</section>
