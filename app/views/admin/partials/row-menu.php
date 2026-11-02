<?php if ($rowMenuUsePopover ?? false): ?>
<details class="list-action-menu" data-row-actions>
    <summary class="list-action" title="Thao tác" aria-label="Thao tác cho <?= e($rowMenuLabel) ?>" aria-haspopup="<?= $rowMenuHasForms ? 'dialog' : 'menu' ?>" aria-expanded="false" aria-controls="<?= e($rowMenuId) ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
    </summary>
    <div class="list-action-menu__panel" id="<?= e($rowMenuId) ?>" role="<?= $rowMenuHasForms ? 'dialog' : 'menu' ?>" aria-label="Thao tác bản ghi">
        <?= $rowMenuContent /* Only escaped markup captured from trusted views. */ ?>
    </div>
</details>
<?php else: ?>
<div class="row-actions" role="group" aria-label="Thao tác cho <?= e($rowMenuLabel) ?>">
    <?= $rowMenuContent /* The same escaped links/forms, displayed directly. */ ?>
</div>
<?php endif; ?>
