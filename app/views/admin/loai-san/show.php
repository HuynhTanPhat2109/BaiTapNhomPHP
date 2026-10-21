<?php $bodyClass = 'page-catalog'; ?>
<section class="container catalog-page">
    <nav class="catalog-nav" aria-label="Quản lý danh mục">
        <a href="<?= e(url('/admin')) ?>">Quản trị</a>
        <a href="<?= e(url('/admin/bo-mon')) ?>">Bộ môn</a>
        <a href="<?= e(url('/admin/loai-san')) ?>">Loại sân</a>
    </nav>
    <header class="catalog-heading">
        <div><p class="eyebrow">STRYKE / Chi tiết loại sân</p><h1><?= e($item['ten_loai_san']) ?></h1></div>
        <div class="catalog-actions">
            <a class="btn btn--primary" href="<?= e(url('/admin/loai-san/edit') . '&id=' . $item['ma_loai_san']) ?>">Sửa</a>
            <a class="btn btn--outline" href="<?= e(url('/admin/loai-san/delete') . '&id=' . $item['ma_loai_san']) ?>">Xóa</a>
        </div>
    </header>
    <dl class="catalog-details">
        <div><dt>Mã loại sân</dt><dd><?= e($item['ma_loai_san']) ?></dd></div>
        <div><dt>Bộ môn</dt><dd><a href="<?= e(url('/admin/bo-mon/show') . '&id=' . $item['ma_bo_mon']) ?>"><?= e($item['ten_bo_mon']) ?></a> (<?= $item['trang_thai_bo_mon'] === 'HOAT_DONG' ? 'Hoạt động' : 'Ngừng' ?>)</dd></div>
        <div><dt>Mô tả</dt><dd class="catalog-description"><?= e($item['mo_ta'] ?? 'Chưa có mô tả') ?></dd></div>
        <div><dt>Trạng thái</dt><dd><?= $item['trang_thai'] === 'HOAT_DONG' ? 'Hoạt động' : 'Ngừng' ?></dd></div>
        <div><dt>Sân tham chiếu</dt><dd><?= e($related['san']) ?> (<?= e($related['san_dang_dung']) ?> hoạt động/bảo trì)</dd></div>
        <div><dt>Bảng giá tham chiếu</dt><dd><?= e($related['bang_gia']) ?> (<?= e($related['bang_gia_dang_dung']) ?> đang áp dụng)</dd></div>
    </dl>
    <a class="btn btn--outline" href="<?= e(url('/admin/loai-san')) ?>">Quay lại danh sách</a>
</section>

