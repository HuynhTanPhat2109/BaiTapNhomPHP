<?php

// Reuse the catalog money formatter; hourly prices only add their display unit.
$formatMoney = require dirname(__DIR__) . '/partials/format-money.php';
return static function ($value) use ($formatMoney): string {
    $money = $formatMoney($value);
    return $money === '—' ? $money : $money . '/giờ';
};
