<?php

return static function ($value): string {
    if ((!is_string($value) && !is_int($value)) || !preg_match('/\A([0-9]{1,10})(?:\.([0-9]{1,2}))?\z/', (string) $value, $parts)) return '—';
    $whole = preg_replace('/\B(?=([0-9]{3})+(?![0-9]))/', '.', ltrim($parts[1], '0') ?: '0');
    $cents = str_pad($parts[2] ?? '', 2, '0');
    return $whole . ($cents === '00' ? '' : ',' . $cents) . ' ₫';
};
