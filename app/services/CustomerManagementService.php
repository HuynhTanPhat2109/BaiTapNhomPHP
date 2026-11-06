<?php

namespace App\Services;

use App\Models\ManagedCustomer;
use Core\HttpException;
use PDO;

final class CustomerManagementService
{
    public const ROLES = ['QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO', 'NHAN_VIEN'];

    private ManagedCustomer $model;

    public function __construct(PDO $pdo)
    {
        $this->model = new ManagedCustomer($pdo);
    }

    private function scope(array $user): array
    {
        if (!in_array($user['ten_vai_tro'] ?? '', self::ROLES, true)) {
            throw new HttpException(403, 'Bạn không có quyền xem khách hàng trong khu vực này.');
        }
        return FacilityScope::forUser($user);
    }

    public function listing(array $user, array $query): array
    {
        $scope = $this->scope($user);
        $filters = AdminListService::filters($query, [], []);
        return $this->model->page($scope, $filters) + compact('scope', 'filters');
    }

    public function detail(array $user, mixed $id, array $query): array
    {
        $scope = $this->scope($user);
        if (!CoSoService::validId($id)) throw new HttpException(404, 'Không tìm thấy khách hàng.');
        $customer = $this->model->find((int) $id, $scope);
        if (!$customer) throw new HttpException(404, 'Không tìm thấy khách hàng trong phạm vi của bạn.');
        $filters = AdminListService::filters($query, [], []);
        // Pagination links retain only this validated customer ID and paging fields.
        $filters = ['id' => (string) $id, 'per_page' => $filters['per_page'], 'page' => $filters['page']];
        return $this->model->history((int) $id, $scope, $filters, (int) $customer['booking_count'])
            + compact('customer', 'scope', 'filters');
    }
}
