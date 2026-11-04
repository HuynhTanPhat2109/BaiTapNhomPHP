<?php

namespace App\Services;

use Core\HttpException;

final class AdminListService
{
    public const NAME_SORTS = ['newest' => 'Mới nhất', 'name_asc' => 'Tên A–Z', 'name_desc' => 'Tên Z–A'];
    public const CONTRACT_SORTS = ['newest' => 'Mới nhất', 'start_asc' => 'Bắt đầu tăng dần', 'start_desc' => 'Bắt đầu giảm dần', 'end_asc' => 'Kết thúc tăng dần', 'end_desc' => 'Kết thúc giảm dần'];

    public static function managementFilters(string $path, array $query): array
    {
        [$links, $options] = match ($path) {
            '/admin/companies' => [[], ['trang_thai' => ['HOAT_DONG', 'NGUNG_HOAT_DONG']]],
            '/admin/contracts' => [['ma_cong_ty'], ['trang_thai' => ['HIEU_LUC', 'HET_HAN', 'CHAM_DUT']]],
            '/admin/employees' => [['ma_cong_ty'], ['trang_thai' => ['DANG_LAM', 'DA_NGHI'], 'vai_tro' => ['QUAN_TRI_TAP_DOAN', 'QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO', 'NHAN_VIEN']]],
            '/company/employees' => [['ma_co_so'], ['trang_thai' => ['DANG_LAM', 'DA_NGHI'], 'vai_tro' => ['QUAN_LY_CO_SO', 'NHAN_VIEN']]],
            default => throw new \LogicException('Unknown management list'),
        };
        $sorts = $path === '/admin/contracts' ? self::CONTRACT_SORTS : self::NAME_SORTS;
        $filters = self::filters($query, $links, $options + ['sort' => array_keys($sorts)]);
        if ($filters['sort'] === '') $filters['sort'] = 'newest';
        if ($path === '/admin/contracts') {
            foreach (['tu_ngay', 'den_ngay'] as $field) {
                $date = $query[$field] ?? '';
                if (!is_string($date) || ($date !== '' && (!preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $date)
                    || !($parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date)) || $parsed->format('Y-m-d') !== $date))) {
                    throw new HttpException(422, 'Khoảng ngày lọc không hợp lệ.');
                }
                $filters[$field] = $date;
            }
            if ($filters['tu_ngay'] !== '' && $filters['den_ngay'] !== '' && $filters['den_ngay'] < $filters['tu_ngay']) {
                throw new HttpException(422, 'Ngày đến phải bằng hoặc sau ngày từ.');
            }
        }
        return $filters;
    }

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
