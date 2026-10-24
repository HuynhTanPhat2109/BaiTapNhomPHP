<?php

namespace App\Services;

use Core\HttpException;

final class AdminListService
{
    public static function filters(array $query, array $links, array $options): array
    {
        $filters = [];
        foreach (array_merge(['q'], $links, array_keys($options)) as $field) {
            $value = $query[$field] ?? '';
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > 150 || preg_match('/\p{C}/u', $value)) {
                throw new HttpException(422, 'Bộ lọc không hợp lệ.');
            }
            $filters[$field] = trim($value);
        }
        foreach ($links as $field) {
            if ($filters[$field] !== '' && !CoSoService::validId($filters[$field])) {
                throw new HttpException(422, 'Mã liên kết trong bộ lọc không hợp lệ.');
            }
        }
        foreach ($options as $field => $allowed) {
            if ($filters[$field] !== '' && !in_array($filters[$field], $allowed, true)) {
                throw new HttpException(422, 'Giá trị bộ lọc không hợp lệ.');
            }
        }
        if (!in_array($query['per_page'] ?? '10', ['10', '20', '50'], true) || !CoSoService::validId($query['page'] ?? '1')) {
            throw new HttpException(422, 'Số trang hoặc số bản ghi mỗi trang không hợp lệ.');
        }
        return $filters + ['per_page' => (int) ($query['per_page'] ?? '10'), 'page' => (int) ($query['page'] ?? '1')];
    }
}
