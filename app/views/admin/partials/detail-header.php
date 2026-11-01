<nav class="detail-breadcrumb" aria-label="Đường dẫn">
    <a href="<?= e(url(substr($detailPath, 0, strrpos($detailPath, '/')))) ?>">Khu vực của bạn</a>
    <span aria-hidden="true">/</span><a href="<?= e(url($detailPath)) ?>"><?= e($detailLabel) ?></a>
    <span aria-hidden="true">/</span><span aria-current="page">Chi tiết</span>
</nav>
<header class="detail-heading">
    <a class="detail-back" href="<?= e(url($detailPath)) ?>"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m12 5-7 7 7 7M5 12h14"/></svg>Quay lại danh sách</a>
    <div class="detail-title"><h1><?= e($detailTitle) ?></h1><span class="catalog-status" data-status="<?= e($item['trang_thai']) ?>"><?= e($detailStatusLabel) ?></span></div>
    <?php if ($detailCanEdit): ?>
        <a class="btn btn--primary detail-edit" href="<?= e(url($detailPath . '/edit') . '&id=' . $detailId) ?>"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m16 3 5 5M3 21l5-1L21 7a2.1 2.1 0 0 0-5-3L3 17v4Z"/></svg>Chỉnh sửa</a>
    <?php endif; ?>
</header>
