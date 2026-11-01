<?php
$bodyClass = 'page-catalog';
$detailPath = '/admin/co-so'; $detailLabel = 'Cơ sở';
$detailTitle = $item['ten_co_so']; $detailId = $item['ma_co_so']; $detailCanEdit = true;
$detailStatusLabel = $item['trang_thai'] === 'HOAT_DONG' ? 'Hoạt động' : 'Tạm ngừng';
?>
<section class="container catalog-page catalog-workspace detail-page">
    <?php require dirname(__DIR__) . '/partials/detail-header.php'; ?>
    <?php if ($item['trang_thai_cong_ty'] !== 'HOAT_DONG'): ?><p class="alert alert--danger" role="status">Công ty đã ngừng hoạt động. Không thể tạo hoặc kích hoạt sân tại cơ sở này.</p><?php endif; ?>
    <div class="detail-grid">
        <section class="detail-card" aria-labelledby="facility-info">
            <h2 id="facility-info">Thông tin cơ sở</h2>
            <dl class="detail-fields">
                <div class="detail-field--wide"><dt>Tên cơ sở</dt><dd><?= e($item['ten_co_so']) ?></dd></div>
                <div class="detail-field--wide"><dt>Công ty</dt><dd><a href="<?= e(url('/admin/companies/edit') . '&id=' . $item['ma_cong_ty']) ?>"><?= e($item['ten_cong_ty']) ?></a></dd></div>
                <div><dt>Tỉnh/Thành phố</dt><dd><?= e($item['ten_tinh'] ?? 'Chưa bổ sung') ?></dd></div>
                <div><dt>Phường/Xã</dt><dd><?= e($item['ten_phuong_xa'] ?? 'Chưa bổ sung') ?></dd></div>
                <div class="detail-field--wide"><dt>Địa chỉ</dt><dd><?= e($item['dia_chi']) ?></dd></div>
                <div class="detail-field--wide"><dt>Mô tả</dt><dd class="catalog-description"><?= e($item['mo_ta'] ?? 'Chưa có mô tả') ?></dd></div>
            </dl>
        </section>
        <section class="detail-card" aria-labelledby="facility-contact">
            <h2 id="facility-contact">Liên hệ &amp; hoạt động</h2>
            <dl class="detail-fields">
                <div><dt>Điện thoại</dt><dd><?= e($item['so_dien_thoai'] ?? '—') ?></dd></div>
                <div><dt>Mã cơ sở</dt><dd>#<?= e($item['ma_co_so']) ?></dd></div>
                <div class="detail-field--wide"><dt>Email</dt><dd><?= e($item['email'] ?? '—') ?></dd></div>
                <div class="detail-field--wide"><dt>Giờ hoạt động</dt><dd class="detail-emphasis"><?= e($item['gio_mo_cua']) ?> – <?= e($item['gio_dong_cua']) ?></dd></div>
            </dl>
            <div class="detail-media">
                <?php if (App\Services\FacilityImage::validPath($item['anh_dai_dien'] ?? null)): ?>
                    <img src="<?= e(base_path() . '/' . $item['anh_dai_dien']) ?>" alt="<?= e($item['ten_co_so']) ?>" class="facility-photo">
                <?php else: ?><p class="detail-empty">Chưa có ảnh đại diện cơ sở.</p><?php endif; ?>
                <?php if (App\Services\FacilityImage::validPath($item['logo_clb'] ?? null)): ?><img class="facility-logo" src="<?= e(base_path() . '/' . $item['logo_clb']) ?>" alt="Logo CLB"><?php endif; ?>
            </div>
        </section>
        <section class="detail-card detail-card--wide" aria-labelledby="facility-related">
            <h2 id="facility-related">Dữ liệu liên quan</h2>
            <dl class="detail-fields detail-fields--metrics">
                <?php foreach (['san' => 'Sân', 'nhan_vien' => 'Nhân viên', 'bang_gia' => 'Bảng giá', 'dat_san' => 'Booking', 'dich_vu' => 'Dịch vụ', 'nhan_vien_dang_lam' => 'Nhân viên đang làm', 'booking_hieu_luc' => 'Lịch đặt còn hiệu lực'] as $key => $label): ?>
                    <div><dt><?= e($label) ?></dt><dd><?= e($related[$key]) ?></dd></div>
                <?php endforeach; ?>
            </dl>
            <div class="detail-card__heading detail-subheading"><h3 id="facility-courts">Sân thuộc cơ sở</h3><a class="detail-link" href="<?= e(url('/admin/san/create')) ?>">Thêm sân</a></div>
            <?php if ($courts): ?>
                <ul class="detail-related">
                    <?php foreach ($courts as $court): ?><li><div><a href="<?= e(url('/admin/san/show') . '&id=' . $court['ma_san']) ?>"><?= e($court['ten_san']) ?></a><small><?= e($court['ten_bo_mon']) ?> / <?= e($court['ten_loai_san']) ?></small></div></li><?php endforeach; ?>
                </ul>
            <?php else: ?><div class="detail-empty"><strong>Chưa có sân</strong><p>Chưa có sân thuộc cơ sở này.</p></div><?php endif; ?>
        </section>
    </div>
    <footer class="detail-action-panel" aria-labelledby="co-so-delete-heading">
        <div class="detail-action-panel__row">
            <div><h2 id="co-so-delete-heading">Xóa cơ sở</h2><p>Chỉ có thể xóa khi không còn dữ liệu tham chiếu. Bạn sẽ được yêu cầu xác nhận ở bước tiếp theo.</p></div>
            <a class="btn btn--outline detail-delete" href="<?= e(url('/admin/co-so/delete') . '&id=' . $item['ma_co_so']) ?>">Xóa cơ sở…</a>
        </div>
    </footer>
</section>
