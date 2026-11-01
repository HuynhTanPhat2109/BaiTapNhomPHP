<?php
$bodyClass = 'page-catalog';
$detailPath = '/admin/san'; $detailLabel = 'Sân';
$detailTitle = $item['ten_san']; $detailId = $item['ma_san']; $detailCanEdit = true;
$detailStatusLabel = ['HOAT_DONG' => 'Hoạt động', 'BAO_TRI' => 'Bảo trì', 'TAM_NGUNG' => 'Tạm ngừng'][$item['trang_thai']];
?>
<section class="container catalog-page catalog-workspace detail-page">
    <?php require dirname(__DIR__) . '/partials/detail-header.php'; ?>
    <?php if ($item['trang_thai_co_so'] !== 'HOAT_DONG' || $item['trang_thai_cong_ty'] !== 'HOAT_DONG'
        || $item['trang_thai_loai_san'] !== 'HOAT_DONG' || $item['trang_thai_bo_mon'] !== 'HOAT_DONG'): ?>
        <p class="alert alert--danger" role="status">Cơ sở, công ty hoặc danh mục liên quan đã ngừng hoạt động. Sân chưa đủ điều kiện phục vụ.</p>
    <?php endif; ?>
    <div class="detail-grid">
        <section class="detail-card" aria-labelledby="court-info">
            <h2 id="court-info">Thông tin sân</h2>
            <dl class="detail-fields">
                <div><dt>Tên sân</dt><dd><?= e($item['ten_san']) ?></dd></div>
                <div><dt>Mã sân</dt><dd>#<?= e($item['ma_san']) ?></dd></div>
                <div class="detail-field--wide"><dt>Cơ sở</dt><dd><a href="<?= e(url('/admin/co-so/show') . '&id=' . $item['ma_co_so']) ?>"><?= e($item['ten_co_so']) ?></a></dd></div>
                <div class="detail-field--wide"><dt>Công ty</dt><dd><?= e($item['ten_cong_ty']) ?></dd></div>
                <div><dt>Bộ môn</dt><dd><a href="<?= e(url('/admin/bo-mon/show') . '&id=' . $item['ma_bo_mon']) ?>"><?= e($item['ten_bo_mon']) ?></a></dd></div>
                <div><dt>Loại sân</dt><dd><a href="<?= e(url('/admin/loai-san/show') . '&id=' . $item['ma_loai_san']) ?>"><?= e($item['ten_loai_san']) ?></a></dd></div>
            </dl>
        </section>
        <section class="detail-card" aria-labelledby="court-extra">
            <h2 id="court-extra">Thông tin bổ sung</h2>
            <dl class="detail-fields">
                <div class="detail-field--wide"><dt>Vị trí</dt><dd><?= e($item['vi_tri'] ?? '—') ?></dd></div>
                <div class="detail-field--wide"><dt>Mô tả</dt><dd class="catalog-description"><?= e($item['mo_ta'] ?? 'Chưa có mô tả') ?></dd></div>
                <div><dt>Chi tiết đặt sân</dt><dd><?= e($related['chi_tiet_dat_san']) ?> tham chiếu</dd></div>
                <div><dt>Lịch đặt còn hiệu lực</dt><dd><?= e($related['booking_hieu_luc']) ?></dd></div>
            </dl>
            <p class="detail-empty">Chưa có ảnh riêng cho sân trong dữ liệu hiện tại.</p>
        </section>
    </div>
    <footer class="detail-action-panel" aria-labelledby="san-delete-heading">
        <div class="detail-action-panel__row">
            <div><h2 id="san-delete-heading">Xóa sân</h2><p>Chỉ có thể xóa khi không còn dữ liệu tham chiếu. Bạn sẽ được yêu cầu xác nhận ở bước tiếp theo.</p></div>
            <a class="btn btn--outline detail-delete" href="<?= e(url('/admin/san/delete') . '&id=' . $item['ma_san']) ?>">Xóa sân…</a>
        </div>
    </footer>
</section>
