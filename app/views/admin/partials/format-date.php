<?php
return static function ($value): string {
    if (!is_string($value) || $value === '') return '—';
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));
    return $date && $date->format('Y-m-d') === substr($value, 0, 10) ? $date->format('d/m/Y') : '—';
};
