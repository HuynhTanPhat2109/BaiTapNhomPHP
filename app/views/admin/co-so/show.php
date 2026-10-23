<?php $bodyClass = 'page-catalog'; ?>
<section class="container catalog-page">
    <header class="catalog-heading">
        <div><p class="eyebrow">STRYKE / Chi tiết cơ sở</p><h1><?= e($item['ten_co_so']) ?></h1></div>
        <div class="catalog-actions">
            <a class="btn btn--primary" href="<?= e(url('/admin/co-so/edit') . '&id=' . $item['ma_co_so']) ?>">Sửa</a>
            <a class="btn btn--outline" href="<?= e(url('/admin/co-so/delete') . '&id=' . $item['ma_co_so']) ?>">Xóa</a>
        </div>
    </header>
    <?php if ($item['trang_thai_cong_ty'] !== 'HOAT_DONG'): ?><p class="alert alert--danger" role="status">Công ty đã ngừng hoạt động. Không thể tạo hoặc kích hoạt sân tại cơ sở này.</p><?php endif; ?>
    <?php if (App\Services\FacilityImage::validPath($item['anh_dai_dien'] ?? null)): ?>
        <img src="<?= e(base_path() . '/' . $item['anh_dai_dien']) ?>" alt="<?= e($item['ten_co_so']) ?>" class="facility-photo">
    <?php else: ?><p class="form-hint">Chưa có ảnh đại diện.</p><?php endif; ?>
    <?php if (App\Services\FacilityImage::validPath($item['logo_clb'] ?? null)): ?><img class="facility-logo" src="<?= e(base_path() . '/' . $item['logo_clb']) ?>" alt="Logo CLB"><?php endif; ?>
    <dl class="catalog-details">
        <div><dt>Tỉnh/Thành phố</dt><dd><?= e($item['ten_tinh'] ?? 'Chưa bổ sung') ?></dd></div>
        <div><dt>Phường/Xã</dt><dd><?= e($item['ten_phuong_xa'] ?? 'Chưa bổ sung') ?></dd></div>
        <div><dt>Công ty</dt><dd><a href="<?= e(url('/admin/companies/edit') . '&id=' . $item['ma_cong_ty']) ?>"><?= e($item['ten_cong_ty']) ?></a></dd></div>
        <div><dt>Địa chỉ</dt><dd><?= e($item['dia_chi']) ?></dd></div>
        <div><dt>Điện thoại</dt><dd><?= e($item['so_dien_thoai'] ?? '—') ?></dd></div>
        <div><dt>Email</dt><dd><?= e($item['email'] ?? '—') ?></dd></div>
        <div><dt>Giờ hoạt động</dt><dd><?= e($item['gio_mo_cua']) ?> – <?= e($item['gio_dong_cua']) ?></dd></div>
        <div><dt>Trạng thái</dt><dd><?= $item['trang_thai'] === 'HOAT_DONG' ? 'Hoạt động' : 'Tạm ngừng' ?></dd></div>
        <div><dt>Mô tả</dt><dd class="catalog-description"><?= e($item['mo_ta'] ?? 'Chưa có mô tả') ?></dd></div>
        <div><dt>Dữ liệu liên quan</dt><dd><?= e($related['san']) ?> sân; <?= e($related['nhan_vien']) ?> nhân viên; <?= e($related['bang_gia']) ?> bảng giá; <?= e($related['dat_san']) ?> booking; <?= e($related['dich_vu']) ?> dịch vụ.</dd></div>
        <div><dt>Nhân viên đang làm</dt><dd><?= e($related['nhan_vien_dang_lam']) ?></dd></div>
        <div><dt>Lịch đặt còn hiệu lực</dt><dd><?= e($related['booking_hieu_luc']) ?></dd></div>
    </dl>
    <h2>Sân thuộc cơ sở</h2>
    <?php if ($courts): ?>
        <ul class="catalog-related">
            <?php foreach ($courts as $court): ?>
                <li><a href="<?= e(url('/admin/san/show') . '&id=' . $court['ma_san']) ?>"><?= e($court['ten_san']) ?></a> — <?= e($court['ten_bo_mon']) ?> / <?= e($court['ten_loai_san']) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?><p class="account-card__description">Chưa có sân thuộc cơ sở này.</p><?php endif; ?>
    <div class="catalog-actions"><a class="btn btn--outline" href="<?= e(url('/admin/co-so')) ?>">Quay lại danh sách</a><a class="btn btn--primary" href="<?= e(url('/admin/san/create')) ?>">Thêm sân</a></div>
</section>
