<?php
$first = $total ? ($page - 1) * $filters['per_page'] + 1 : 0;
$last = min($page * $filters['per_page'], $total);
$pageUrl = static fn(int $number): string => url($listPath) . '&' . http_build_query(array_replace($filters, ['page' => $number]));
?>
<div class="list-pagination">
    <p class="list-pagination__summary">Hiển thị <?= e($first) ?>–<?= e($last) ?> trong tổng số <?= e($total) ?> kết quả <span>· Trang <?= e($page) ?>/<?= e($pages) ?></span></p>
    <?php if ($pages > 1): ?>
        <nav class="list-pagination__links" aria-label="Phân trang <?= e($listLabel) ?>">
            <?php if ($page > 1): ?><a class="list-page" rel="prev" href="<?= e($pageUrl($page - 1)) ?>">Trang trước</a><?php else: ?><span class="list-page is-disabled" aria-disabled="true">Trang trước</span><?php endif; ?>
            <?php for ($number = max(1, $page - 2); $number <= min($pages, $page + 2); $number++): ?>
                <a class="list-page" href="<?= e($pageUrl($number)) ?>"<?= $number === $page ? ' aria-current="page"' : '' ?>><?= e($number) ?></a>
            <?php endfor; ?>
            <?php if ($page < $pages): ?><a class="list-page" rel="next" href="<?= e($pageUrl($page + 1)) ?>">Trang sau</a><?php else: ?><span class="list-page is-disabled" aria-disabled="true">Trang sau</span><?php endif; ?>
        </nav>
    <?php endif; ?>
</div>
