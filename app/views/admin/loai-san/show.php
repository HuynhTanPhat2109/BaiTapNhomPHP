<?php
$bodyClass = 'page-catalog';
$detailPath = '/admin/loai-san'; $detailLabel = 'Loại sân';
$detailTitle = $item['ten_loai_san']; $detailId = $item['ma_loai_san']; $detailCanEdit = true;
$detailStatusLabel = $item['trang_thai'] === 'HOAT_DONG' ? 'Hoạt động' : 'Ngừng';
?>
<section class="container catalog-page catalog-workspace detail-page">
    <?php require dirname(__DIR__) . '/partials/detail-header.php'; ?>
    <div class="detail-grid">
        <section class="detail-card" aria-labelledby="type-info">
            <h2 id="type-info">Thông tin loại sân</h2>
            <dl class="detail-fields">
                <div><dt>Tên loại sân</dt><dd><?= e($item['ten_loai_san']) ?></dd></div>
                <div><dt>Mã loại sân</dt><dd>#<?= e($item['ma_loai_san']) ?></dd></div>
                <div class="detail-field--wide"><dt>Bộ môn</dt><dd><a href="<?= e(url('/admin/bo-mon/show') . '&id=' . $item['ma_bo_mon']) ?>"><?= e($item['ten_bo_mon']) ?></a><span class="detail-inline-note"><?= $item['trang_thai_bo_mon'] === 'HOAT_DONG' ? 'Hoạt động' : 'Ngừng' ?></span></dd></div>
                <div class="detail-field--wide"><dt>Mô tả</dt><dd class="catalog-description"><?= e($item['mo_ta'] ?? 'Chưa có mô tả') ?></dd></div>
            </dl>
        </section>
        <section class="detail-card" aria-labelledby="type-related">
            <h2 id="type-related">Dữ liệu liên quan</h2>
            <dl class="detail-fields detail-fields--metrics">
                <div><dt>Sân liên quan</dt><dd><?= e($related['san']) ?> sân</dd></div>
                <div><dt>Sân hoạt động / bảo trì</dt><dd><?= e($related['san_dang_dung']) ?> sân</dd></div>
                <div><dt>Bảng giá liên quan</dt><dd><?= e($related['bang_gia']) ?> bảng giá</dd></div>
                <div><dt>Bảng giá đang áp dụng</dt><dd><?= e($related['bang_gia_dang_dung']) ?> bảng giá</dd></div>
            </dl>
        </section>
    </div>
    <footer class="detail-action-panel" aria-labelledby="loai-san-delete-heading">
        <div class="detail-action-panel__row">
            <div><h2 id="loai-san-delete-heading">Xóa loại sân</h2><p>Chỉ có thể xóa khi không còn dữ liệu tham chiếu. Bạn sẽ được yêu cầu xác nhận ở bước tiếp theo.</p></div>
            <a class="btn btn--outline detail-delete" href="<?= e(url('/admin/loai-san/delete') . '&id=' . $item['ma_loai_san']) ?>">Xóa loại sân…</a>
        </div>
    </footer>
</section>
