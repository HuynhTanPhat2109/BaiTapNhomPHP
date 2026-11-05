<?php

namespace App\Controllers;

use App\Services\EmployeeService;
use App\Services\AdminListService;
use App\Models\{NhanVien, CoSo, TaiKhoan};
use App\Services\ValidationException;
use Core\Application;
use Core\Controller;
use Core\HttpException;

final class EmployeeController extends Controller
{
    private EmployeeService $employees;
    private ?int $facilityId = null;

    public function __construct(private Application $app)
    {
        $this->employees = new EmployeeService($app->pdo());
    }

    public function index(): void
    {
        [$role, $companyId, $basePath, $viewPrefix] = $this->context();
        $options = $this->employees->getOptions($role, $companyId);
        $filters = AdminListService::managementFilters($basePath . '/employees', $_GET);
        if ($companyId !== null) {
            if ($role === 'QUAN_LY_CONG_TY') $options['facilities'] = (new CoSo($this->app->pdo()))->byCompany((int) $companyId);
            if ($filters['ma_co_so'] !== '' && !in_array((int) $filters['ma_co_so'], array_map('intval', array_column($options['facilities'], 'ma_co_so')), true)) {
                throw new HttpException(403, 'Cơ sở nằm ngoài phạm vi công ty của bạn.');
            }
        }
        $result = (new NhanVien($this->app->pdo()))->page($filters, $companyId === null ? null : (int) $companyId, $this->facilityId);
        if ($result['page'] !== $filters['page']) admin_list_redirect($basePath . '/employees', array_replace($filters, ['page' => $result['page']]));
        $notice = $_SESSION['employee_notice'] ?? null; unset($_SESSION['employee_notice']);
        $this->render($viewPrefix . '/index', $result + [
            'title' => 'Quản lý nhân viên',
            'employees' => $result['items'], 'filters' => $filters, 'notice' => $notice, 'options' => $options,
            'basePath' => $basePath, 'actorRole' => $role,
        ]);
    }

    public function show(): void
    {
        [$role, $companyId, $basePath] = $this->context();
        try {
            $employee = $this->employees->getEmployee($role, $companyId, $_GET['id'] ?? null);
        } catch (ValidationException $e) {
            throw new HttpException(404, 'Không tìm thấy nhân viên.');
        }
        $model = new NhanVien($this->app->pdo());
        $company = $model->company((int) $employee['ma_cong_ty']);
        $facility = $employee['ma_co_so'] === null ? null : $model->facility((int) $employee['ma_co_so']);
        if ($facility !== null && (int) $facility['ma_cong_ty'] !== (int) $employee['ma_cong_ty']) {
            throw new HttpException(404, 'Không tìm thấy nhân viên trong phạm vi này.');
        }
        // identity() explicitly selects public account fields; it never reads the password hash.
        $identity = $employee['ma_tai_khoan'] === null ? null
            : (new TaiKhoan($this->app->pdo()))->identity((int) $employee['ma_tai_khoan']);
        if ($identity !== null && (int) $identity['ma_nhan_vien'] !== (int) $employee['ma_nhan_vien']) {
            throw new HttpException(404, 'Không tìm thấy tài khoản nhân viên.');
        }
        $account = $identity === null ? null : array_intersect_key($identity, array_flip(['ten_dang_nhap', 'ten_vai_tro', 'trang_thai']));
        $this->render('admin/employees/show', compact('employee', 'account', 'company', 'facility', 'basePath') + [
            'title' => 'Chi tiết nhân viên',
        ]);
    }

    public function create(): void
    {
        [$role, $companyId, $basePath, $viewPrefix] = $this->context();
        $this->render($viewPrefix . '/create', [
            'title' => 'Thêm nhân viên',
            'basePath' => $basePath,
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
            $_SESSION['employee_notice'] = 'Đã thêm nhân viên.';
            admin_list_redirect($basePath . '/employees');
        } catch (ValidationException $e) {
            $old = $data;
            $old['cap_tai_khoan'] = $account !== null;
            if ($account !== null) {
                if (is_string($account['username'])) {
                    $old['username'] = $account['username'];
                }
                if (in_array($role, ['QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO'], true)) {
                    $old['role'] = $account['role'];
                }
            }
            $this->render($viewPrefix . '/create', [
                'title' => 'Thêm nhân viên',
                'basePath' => $basePath,
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
        $employee = $this->employees->getEmployee($role, $companyId, $id, true);
        $this->render($viewPrefix . '/edit', [
            'title' => 'Cập nhật nhân viên',
            'basePath' => $basePath,
            'employee' => $employee,
            'options' => $this->employees->getOptions($role, $companyId, $employee['ma_co_so'] ?? null),
        ]);
    }

    public function update(): void
    {
        [$role, $companyId, $basePath, $viewPrefix] = $this->context();
        $id = $_POST['id'] ?? null;
        $employee = $this->employees->getEmployee($role, $companyId, $id, true);
        $data = $this->formData($role);

        try {
            $this->employees->updateEmployee($role, $companyId, $id, $data);
            $_SESSION['employee_notice'] = 'Đã cập nhật nhân viên.';
            admin_list_redirect($basePath . '/employees');
        } catch (ValidationException $e) {
            $this->render($viewPrefix . '/edit', [
                'title' => 'Cập nhật nhân viên',
                'basePath' => $basePath,
                'employee' => $employee,
                'options' => $this->employees->getOptions($role, $companyId, $employee['ma_co_so'] ?? null),
                'errors' => $e->errors,
                'old' => $data,
            ], 422);
        }
    }

    public function changeStatus(): void
    {
        [$role, $companyId, $basePath, $viewPrefix] = $this->context();
        $id = $_POST['id'] ?? null;
        $status = $_POST['trang_thai'] ?? '';
        try {
            $this->employees->changeStatus($role, $companyId, $id, $status);
            $_SESSION['employee_notice'] = 'Đã cập nhật trạng thái nhân viên.';
            admin_list_redirect($basePath . '/employees');
        } catch (ValidationException $e) {
            $employee = $this->employees->getEmployee($role, $companyId, $id);
            $this->render($viewPrefix . '/edit', ['title' => 'Cập nhật nhân viên', 'employee' => $employee, 'basePath' => $basePath,
                'options' => $this->employees->getOptions($role, $companyId, $employee['ma_co_so'] ?? null),
                'errors' => $e->errors, 'old' => ['trang_thai' => $status]], 422);
        }
    }

    private function context(): array
    {
        $user = $this->app->auth()->user();
        if (!is_array($user)) {
            throw new HttpException(403, 'Bạn cần đăng nhập để thực hiện thao tác này.');
        }
        $role = $user['ten_vai_tro'] ?? '';
        $companyId = $role === 'QUAN_TRI_TAP_DOAN' ? null : ($user['ma_cong_ty'] ?? null);
        $this->facilityId = $role === 'QUAN_LY_CO_SO' ? (int) ($user['ma_co_so'] ?? 0) : null;
        $this->employees = new EmployeeService($this->app->pdo(), $user);
        if ($role === 'QUAN_TRI_TAP_DOAN') {
            return [$role, null, '/admin', 'admin/employees'];
        }
        if ($role === 'QUAN_LY_CONG_TY') {
            return [$role, $companyId, '/company', 'company/employees'];
        }
        if ($role === 'QUAN_LY_CO_SO') {
            return [$role, $companyId, '/facility', 'company/employees'];
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
            'ma_co_so' => $_POST['ma_co_so'] ?? '',
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
        $allowed = $role === 'QUAN_LY_CO_SO' ? ['NHAN_VIEN'] : ['QUAN_LY_CO_SO', 'NHAN_VIEN'];
        $allowedRole = is_string($requestedRole) && in_array($requestedRole, $allowed, true)
            ? $requestedRole
            : '';
        return [
            'username' => $_POST['username'] ?? '',
            'password' => $_POST['password'] ?? '',
            'role' => $allowedRole,
        ];
    }
}
