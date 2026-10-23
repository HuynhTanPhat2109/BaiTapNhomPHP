<?php

namespace App\Services;

final class AdministrativeAddress
{
    public static function provinces(): array
    {
        static $data;
        return $data ??= json_decode(file_get_contents(dirname(__DIR__, 2) . '/resources/address/vietnam.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function resolve($province, $ward): array
    {
        if (is_string($province) && is_string($ward)) {
            foreach (self::provinces() as $p) {
                if ($p['Code'] !== $province) continue;
                foreach ($p['Wards'] as $w) {
                    if ($w['Code'] === $ward) {
                        return ['ma_tinh' => $province, 'ten_tinh' => $p['FullName'],
                            'ma_phuong_xa' => $ward, 'ten_phuong_xa' => $w['FullName']];
                    }
                }
            }
        }
        throw new ValidationException(['ma_phuong_xa' => 'Vui lòng chọn tỉnh và phường/xã thuộc tỉnh đó.']);
    }
}
