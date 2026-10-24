<form class="list-toolbar" method="get" action="<?= e(base_path() . '/index.php') ?>" aria-label="Tìm kiếm và lọc <?= e($listLabel) ?>">
    <input type="hidden" name="route" value="<?= e($listPath) ?>">
    <?php foreach ($filterFields as $field => $definition): ?>
        <div class="form-group list-toolbar__<?= e(match ($field) { 'q' => 'search', 'loai_ngay' => 'day', 'trang_thai' => 'status', default => 'field' }) ?>">
            <label for="filter-<?= e($field) ?>"><?= e($definition['label']) ?></label>
            <?php if ($field === 'q'): ?>
                <input class="form-control" id="filter-q" type="search" name="q" maxlength="150" placeholder="<?= e($definition['label']) ?>…" value="<?= e($filters['q']) ?>">
            <?php else: ?>
                <select class="form-control" id="filter-<?= e($field) ?>" name="<?= e($field) ?>">
                    <option value="">Tất cả</option>
                    <?php foreach ($definition['options'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $filters[$field] === (string) $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <div class="form-group list-toolbar__size">
        <label for="per-page">Hiển thị</label>
        <select class="form-control" id="per-page" name="per_page">
            <?php foreach ([10, 20, 50] as $size): ?><option value="<?= e($size) ?>" <?= $filters['per_page'] === $size ? 'selected' : '' ?>><?= e($size) ?> / trang</option><?php endforeach; ?>
        </select>
    </div>
    <div class="list-toolbar__actions">
        <button class="btn btn--primary" type="submit"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg><span>Tìm kiếm</span></button>
        <a class="btn btn--outline" href="<?= e(url($listPath)) ?>"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 4v6h6M4.5 10a8 8 0 1 1-.2 4"/></svg><span>Đặt lại</span></a>
    </div>
</form>
