<?php
$bodyClass = 'page-catalog';
$detailPath = '/admin/bo-mon'; $detailLabel = 'Bộ môn';
$detailTitle = $item['ten_bo_mon']; $detailId = $item['ma_bo_mon']; $detailCanEdit = true;
$detailStatusLabel = $item['trang_thai'] === 'HOAT_DONG' ? 'Hoạt động' : 'Ngừng';
?>
<section class="container catalog-page catalog-workspace detail-page">
    <?php require dirname(__DIR__) . '/partials/detail-header.php'; ?>
    <div class="detail-grid">
        <section class="detail-card" aria-labelledby="sport-info">
            <h2 id="sport-info">Thông tin bộ môn</h2>
            <dl class="detail-fields">
                <div><dt>Tên bộ môn</dt><dd><?= e($item['ten_bo_mon']) ?></dd></div>
                <div><dt>Mã bộ môn</dt><dd>#<?= e($item['ma_bo_mon']) ?></dd></div>
                <div class="detail-field--wide"><dt>Mô tả</dt><dd class="catalog-description"><?= e($item['mo_ta'] ?? 'Chưa có mô tả') ?></dd></div>
            </dl>
        </section>
        <section class="detail-card" aria-labelledby="sport-types">
            <h2 id="sport-types">Loại sân thuộc bộ môn</h2>
            <p class="detail-card__intro"><?= e($related['loai_san']) ?> loại sân · <?= e($related['loai_san_dang_dung']) ?> đang hoạt động</p>
            <?php if ($types): ?>
                <ul class="detail-related">
                    <?php foreach ($types as $type): ?>
                        <li><a href="<?= e(url('/admin/loai-san/show') . '&id=' . $type['ma_loai_san']) ?>"><?= e($type['ten_loai_san']) ?></a><span class="catalog-status" data-status="<?= e($type['trang_thai']) ?>"><?= $type['trang_thai'] === 'HOAT_DONG' ? 'Hoạt động' : 'Ngừng' ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?><div class="detail-empty"><svg width="28" height="28" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="3"/><path d="M8 8h8M8 12h8M8 16h4"/></svg><strong>Chưa có loại sân</strong><p>Chưa có loại sân thuộc bộ môn này.</p><a href="<?= e(url('/admin/loai-san')) ?>">Quản lý loại sân</a></div><?php endif; ?>
        </section>
    </div>
    <footer class="detail-action-panel" aria-labelledby="bo-mon-delete-heading">
        <div class="detail-action-panel__row">
            <div><h2 id="bo-mon-delete-heading">Xóa bộ môn</h2><p>Chỉ có thể xóa khi không còn dữ liệu tham chiếu. Bạn sẽ được yêu cầu xác nhận ở bước tiếp theo.</p></div>
            <a class="btn btn--outline detail-delete" href="<?= e(url('/admin/bo-mon/delete') . '&id=' . $item['ma_bo_mon']) ?>">Xóa bộ môn…</a>
        </div>
    </footer>
</section>
