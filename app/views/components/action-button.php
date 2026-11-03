
<?php
/**
 * Component: action-button
 *
 * Biến đầu vào:
 * $buttonText       Nội dung nút
 * $buttonType       submit | button | reset
 * $buttonClass      Class bổ sung
 * $buttonDisabled   true | false
 * $buttonAttributes Các thuộc tính HTML bổ sung
 */

$buttonText = $buttonText ?? 'TIẾP THEO';
$buttonType = $buttonType ?? 'submit';
$buttonClass = $buttonClass ?? '';
$buttonDisabled = $buttonDisabled ?? false;
$buttonAttributes = $buttonAttributes ?? [];

if (!in_array($buttonType, ['submit', 'button', 'reset'], true)) {
    $buttonType = 'submit';
}

$attributes = '';

foreach ($buttonAttributes as $name => $value) {
    // Chỉ cho phép các thuộc tính cần thiết
    if (!in_array($name, [
        'formaction', 'formmethod', 'name', 'value',
        'id', 'data-confirm-booking', 'aria-label'
    ], true)) {
        continue;
    }

    if ($value === false || $value === null) {
        continue;
    }

    if ($value === true) {
        $attributes .= ' ' . $name;
    } else {
        $attributes .= ' ' . $name . '="' . e((string) $value) . '"';
    }
}
?>

<button
    type="<?= e($buttonType) ?>"
    class="stryke-action-btn <?= e($buttonClass) ?>"
    <?= $buttonDisabled ? 'disabled' : '' ?>
    <?= $attributes ?>
>
    <span class="stryke-action-btn__wrapper">
        <span class="stryke-action-btn__text">
            <?= e($buttonText) ?>
        </span>

        <?php for ($i = 1; $i <= 12; $i++): ?>
            <span
                class="stryke-action-btn__circle stryke-action-btn__circle--<?= $i ?>"
                aria-hidden="true"
            ></span>
        <?php endfor; ?>
    </span>
</button>
