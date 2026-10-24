<?php

namespace App\Controllers;

use App\Services\EmployeeService;
use App\Services\ValidationException;
use Core\Application;
use Core\Controller;
use Core\HttpException;

final class EmployeeController extends Controller
{
    private EmployeeService $employees;

    public function __construct(private Application $app)
    {
        $this->employees = new EmployeeService($app->pdo());
    }

    public function index(): void
    {
        [$role, $companyId, $basePath, $viewPrefix] = $this->context();
        $this->render($viewPrefix . '/index', [
            'title' => 'Quản lý nhân viên',
            'employees' => $this->employees->listEmployees($role, $companyId),
        ]);
    }

    public function create(): void
    {
        [$role, $companyId, $basePath, $viewPrefix] = $this->context();
        $this->render($viewPrefix . '/create', [
            'title' => 'Thêm nhân viên',
            'options' => $this->employees->getOptions($role, $companyId),
        ]);
    }

    public function store(): void
    {
        [$role, $companyId, $basePath, $viewPrefix] = $this->context();
        $data = $this->formData($role);
        $account = $this->accountData($role);

        try {
            $this->employees->createEmployee($role, $companyId, $data, $account);
            $this->redirect($basePath . '/employees');
        } catch (ValidationException $e) {
            $old = $data;
            $old['cap_tai_khoan'] = $account !== null;
            if ($account !== null) {
                if (is_string($account['username'])) {
                    $old['username'] = $account['username'];
                }
                if ($role === 'QUAN_LY_CONG_TY') {
                    $old['role'] = $account['role'];
                }
            }
            $this->render($viewPrefix . '/create', [
                'title' => 'Thêm nhân viên',
                'options' => $this->employees->getOptions($role, $companyId),
                'errors' => $e->errors,
                'old' => $old,
            ], 422);
        }
    }

    public function edit(): void
    {
        [$role, $companyId, $basePath, $viewPrefix] = $this->context();
        $id = $_GET['id'] ?? null;
        $employee = $this->employees->getEmployee($role, $companyId, $id);
        $this->render($viewPrefix . '/edit', [
            'title' => 'Cập nhật nhân viên',
            'employee' => $employee,
            'options' => $this->employees->getOptions($role, $companyId, $employee['ma_co_so'] ?? null),
        ]);
    }

    public function update(): void
    {
        [$role, $companyId, $basePath, $viewPrefix] = $this->context();
        $id = $_POST['id'] ?? null;
        $employee = $this->employees->getEmployee($role, $companyId, $id);
        $data = $this->formData($role);

        try {
            $this->employees->updateEmployee($role, $companyId, $id, $data);
            $this->redirect($basePath . '/employees');
        } catch (ValidationException $e) {
            $this->render($viewPrefix . '/edit', [
                'title' => 'Cập nhật nhân viên',
                'employee' => $employee,
                'options' => $this->employees->getOptions($role, $companyId, $employee['ma_co_so'] ?? null),
                'errors' => $e->errors,
                'old' => $data,
            ], 422);
        }
    }

    public function changeStatus(): void
    {
        [$role, $companyId, $basePath] = $this->context();
        $id = $_POST['id'] ?? null;
        $status = $_POST['trang_thai'] ?? '';
        $this->employees->changeStatus($role, $companyId, $id, $status);
        $this->redirect($basePath . '/employees');
    }

    private function context(): array
    {
        $user = $this->app->auth()->user();
        if (!is_array($user)) {
            throw new HttpException(403, 'Bạn cần đăng nhập để thực hiện thao tác này.');
        }
        $role = $user['ten_vai_tro'] ?? '';
        $companyId = $role === 'QUAN_LY_CONG_TY' ? ($user['ma_cong_ty'] ?? null) : null;
        if ($role === 'QUAN_TRI_TAP_DOAN') {
            return [$role, null, '/admin', 'admin/employees'];
        }
        if ($role === 'QUAN_LY_CONG_TY') {
            return [$role, $companyId, '/company', 'company/employees'];
        }
        throw new HttpException(403, 'Không có quyền thực hiện thao tác này.');
    }

    private function formData(string $role): array
    {
        $data = [
            'ho_ten' => $_POST['ho_ten'] ?? '',
            'ngay_sinh' => $_POST['ngay_sinh'] ?? '',
            'gioi_tinh' => $_POST['gioi_tinh'] ?? '',
            'so_dien_thoai' => $_POST['so_dien_thoai'] ?? '',
            'email' => $_POST['email'] ?? '',
            'dia_chi' => $_POST['dia_chi'] ?? '',
            'chuc_vu' => $_POST['chuc_vu'] ?? '',
            'ngay_vao_lam' => $_POST['ngay_vao_lam'] ?? '',
            'trang_thai' => $_POST['trang_thai'] ?? 'DANG_LAM',
            'ma_co_so' => $role === 'QUAN_TRI_TAP_DOAN' ? null : ($_POST['ma_co_so'] ?? ''),
        ];
        if ($role === 'QUAN_TRI_TAP_DOAN') {
            $data['ma_cong_ty'] = $_POST['ma_cong_ty'] ?? '';
        }
        return $data;
    }

    private function accountData(string $role): ?array
    {
        if ($role === 'QUAN_TRI_TAP_DOAN') {
            return [
                'username' => $_POST['username'] ?? '',
                'password' => $_POST['password'] ?? '',
                'role' => 'QUAN_LY_CONG_TY',
            ];
        }
        if (empty($_POST['cap_tai_khoan'])) {
            return null;
        }
        $requestedRole = $_POST['role'] ?? '';
        $allowedRole = is_string($requestedRole) && in_array($requestedRole, ['QUAN_LY_CO_SO', 'NHAN_VIEN'], true)
            ? $requestedRole
            : '';
        return [
            'username' => $_POST['username'] ?? '',
            'password' => $_POST['password'] ?? '',
            'role' => $allowedRole,
        ];
    }
}
