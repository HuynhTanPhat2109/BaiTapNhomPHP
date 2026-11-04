<?php
namespace App\Services;

use Core\HttpException;

// Shared ownership checks for facilities, courts and prices. Routes still enforce roles/CSRF.
final class FacilityScope
{
    public const ADMIN = ['role' => 'QUAN_TRI_TAP_DOAN', 'company' => null, 'facility' => null];

    public static function forUser(array $user): array
    {
        return BookingManagementService::resolveScope($user);
    }

    public static function root(array $scope): string
    {
        return ['QUAN_TRI_TAP_DOAN' => '/admin', 'QUAN_LY_CONG_TY' => '/company',
            'QUAN_LY_CO_SO' => '/facility', 'NHAN_VIEN' => '/staff'][$scope['role']];
    }

    public static function write(array $scope): void
    {
        if ($scope['role'] === 'NHAN_VIEN') throw new HttpException(403, 'Nhân viên chỉ được xem dữ liệu vận hành.');
    }

    public static function admin(array $scope): void
    {
        if ($scope['role'] !== 'QUAN_TRI_TAP_DOAN') throw new HttpException(403, 'Thao tác này chỉ dành cho Quản trị tập đoàn.');
    }

    public static function check(array $facility, array $scope): void
    {
        if (($scope['company'] !== null && (int) ($facility['ma_cong_ty'] ?? 0) !== $scope['company'])
            || ($scope['facility'] !== null && (int) ($facility['ma_co_so'] ?? 0) !== $scope['facility'])) {
            throw new HttpException(403, 'Dữ liệu nằm ngoài phạm vi công ty/cơ sở của bạn.');
        }
    }

    // All three models join the trusted facility alias f; identifiers never come from the request.
    public static function where(array $scope): array
    {
        $where = $params = [];
        foreach (['company' => 'f.ma_cong_ty', 'facility' => 'f.ma_co_so'] as $field => $column) {
            if ($scope[$field] !== null) { $where[] = "$column = :scope_$field"; $params['scope_' . $field] = $scope[$field]; }
        }
        return [$where, $params];
    }
}
