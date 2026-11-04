<?php
$bodyClass = 'page-catalog';
$item = $employee;
$detailPath = $basePath . '/employees'; $detailLabel = 'Nhân viên';
$detailTitle = $employee['ho_ten']; $detailId = $employee['ma_nhan_vien']; $detailCanEdit = false;
$detailStatusLabel = ['DANG_LAM' => 'Đang làm', 'DA_NGHI' => 'Đã nghỉ'][$employee['trang_thai']] ?? 'Không xác định';
$formatDate = require dirname(__DIR__) . '/partials/format-date.php';
$roleLabels = ['QUAN_LY_CONG_TY' => 'Quản lý công ty', 'QUAN_LY_CO_SO' => 'Quản lý cơ sở', 'NHAN_VIEN' => 'Nhân viên'];
?>
<section class="container catalog-page catalog-workspace detail-page workspace-page">
    <?php require dirname(__DIR__) . '/partials/detail-header.php'; ?>
    <div class="detail-grid">
        <section class="detail-card" aria-labelledby="employee-info">
            <h2 id="employee-info">Thông tin nhân viên</h2>
            <dl class="detail-fields">
                <div><dt>Họ tên</dt><dd><?= e($employee['ho_ten']) ?></dd></div>
                <div><dt>Mã nhân viên</dt><dd>#<?= e($employee['ma_nhan_vien']) ?></dd></div>
                <div><dt>Email</dt><dd><?= e($employee['email'] ?: 'Chưa cập nhật') ?></dd></div>
                <div><dt>Số điện thoại</dt><dd><?= e($employee['so_dien_thoai'] ?: 'Chưa cập nhật') ?></dd></div>
                <div><dt>Chức vụ</dt><dd><?= e($employee['chuc_vu'] ?: 'Chưa cập nhật') ?></dd></div>
                <div><dt>Ngày vào làm</dt><dd><?= e($formatDate($employee['ngay_vao_lam'])) ?></dd></div>
                <div class="detail-field--wide"><dt>Địa chỉ</dt><dd><?= e($employee['dia_chi'] ?: 'Chưa cập nhật') ?></dd></div>
            </dl>
        </section>
        <section class="detail-card" aria-labelledby="employee-account">
            <h2 id="employee-account">Tài khoản đăng nhập</h2>
            <?php if ($account !== null): ?>
                <dl class="detail-fields">
                    <div class="detail-field--wide"><dt>Tên đăng nhập</dt><dd><?= e($account['ten_dang_nhap']) ?></dd></div>
                    <div class="detail-field--wide"><dt>Trạng thái tài khoản</dt><dd><span class="catalog-status" data-status="<?= $account['trang_thai'] === 'HOAT_DONG' ? 'HOAT_DONG' : 'NGUNG' ?>"><?= e(['HOAT_DONG' => 'Hoạt động', 'KHOA' => 'Bị khóa'][$account['trang_thai']] ?? 'Không xác định') ?></span></dd></div>
                </dl>
            <?php else: ?><div class="detail-empty"><strong>Chưa có tài khoản đăng nhập</strong><p>Nhân viên chưa được cấp tài khoản hoặc tài khoản không còn khả dụng.</p></div><?php endif; ?>
        </section>
        <section class="detail-card detail-card--wide" aria-labelledby="employee-scope">
            <h2 id="employee-scope">Phân quyền</h2>
            <dl class="detail-fields">
                <div><dt>Vai trò</dt><dd><?= e($account === null ? 'Chưa có vai trò tài khoản' : ($roleLabels[$account['ten_vai_tro']] ?? $account['ten_vai_tro'])) ?></dd></div>
                <div><dt>Trạng thái nhân viên</dt><dd><?= e($detailStatusLabel) ?></dd></div>
                <div><dt>Công ty</dt><dd><?= e($company['ten_cong_ty'] ?? 'Chưa có thông tin') ?></dd></div>
                <div><dt>Cơ sở</dt><dd><?= e($facility['ten_co_so'] ?? 'Chưa gán cơ sở') ?></dd></div>
            </dl>
        </section>
    </div>
</section>
