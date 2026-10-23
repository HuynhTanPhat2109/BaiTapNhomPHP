<?php $bodyClass = 'page-catalog'; ?>
<section class="container catalog-page">
    <header class="catalog-heading">
        <div><p class="eyebrow">STRYKE / Chi tiết sân</p><h1><?= e($item['ten_san']) ?></h1></div>
        <div class="catalog-actions"><a class="btn btn--primary" href="<?= e(url('/admin/san/edit') . '&id=' . $item['ma_san']) ?>">Sửa</a><a class="btn btn--outline" href="<?= e(url('/admin/san/delete') . '&id=' . $item['ma_san']) ?>">Xóa</a></div>
    </header>
    <?php if ($item['trang_thai_co_so'] !== 'HOAT_DONG' || $item['trang_thai_cong_ty'] !== 'HOAT_DONG'
        || $item['trang_thai_loai_san'] !== 'HOAT_DONG' || $item['trang_thai_bo_mon'] !== 'HOAT_DONG'): ?>
        <p class="alert alert--danger" role="status">Cơ sở, công ty hoặc danh mục liên quan đã ngừng hoạt động. Sân chưa đủ điều kiện phục vụ.</p>
    <?php endif; ?>
    <dl class="catalog-details">
        <div><dt>Cơ sở</dt><dd><a href="<?= e(url('/admin/co-so/show') . '&id=' . $item['ma_co_so']) ?>"><?= e($item['ten_co_so']) ?></a></dd></div>
        <div><dt>Công ty</dt><dd><?= e($item['ten_cong_ty']) ?></dd></div>
        <div><dt>Bộ môn</dt><dd><a href="<?= e(url('/admin/bo-mon/show') . '&id=' . $item['ma_bo_mon']) ?>"><?= e($item['ten_bo_mon']) ?></a></dd></div>
        <div><dt>Loại sân</dt><dd><a href="<?= e(url('/admin/loai-san/show') . '&id=' . $item['ma_loai_san']) ?>"><?= e($item['ten_loai_san']) ?></a></dd></div>
        <div><dt>Vị trí</dt><dd><?= e($item['vi_tri'] ?? '—') ?></dd></div>
        <div><dt>Mô tả</dt><dd class="catalog-description"><?= e($item['mo_ta'] ?? 'Chưa có mô tả') ?></dd></div>
        <div><dt>Trạng thái</dt><dd><?= e(['HOAT_DONG' => 'Hoạt động', 'BAO_TRI' => 'Bảo trì', 'TAM_NGUNG' => 'Tạm ngừng'][$item['trang_thai']]) ?></dd></div>
        <div><dt>Chi tiết đặt sân</dt><dd><?= e($related['chi_tiet_dat_san']) ?> tham chiếu; <?= e($related['booking_hieu_luc']) ?> lịch đặt còn hiệu lực.</dd></div>
    </dl>
    <a class="btn btn--outline" href="<?= e(url('/admin/san')) ?>">Quay lại danh sách</a>
</section>
