<?php

namespace App\Services;

use App\Models\NhanVien;
use Core\HttpException;
use DateTimeImmutable;
use PDO;
use PDOException;

final class EmployeeService
{
    private NhanVien $employees;
    private AccountService $accounts;

    public function __construct(private PDO $pdo)
    {
        $this->employees = new NhanVien($pdo);
        $this->accounts = new AccountService($pdo);
    }

    public function listEmployees(string $actorRole, $actorCompanyId = null): array
    {
        $companyId = $this->actorCompanyId($actorRole, $actorCompanyId);
        if ($actorRole === 'QUAN_TRI_TAP_DOAN') {
            return $this->employees->companyManagers();
        }
        return $this->employees->byCompany($companyId);
    }

    public function getEmployee(string $actorRole, $actorCompanyId, $employeeId): array
    {
        $companyId = $this->actorCompanyId($actorRole, $actorCompanyId);
        $id = self::requireId($employeeId, 'ma_nhan_vien');
        $employee = $actorRole === 'QUAN_TRI_TAP_DOAN'
            ? $this->employees->findCompanyManager($id)
            : $this->employees->findForCompany($id, $companyId);
        if (!$employee) {
            throw new HttpException(404, 'Không tìm thấy nhân viên.');
        }
        return $employee;
    }

    public function getOptions(string $actorRole, $actorCompanyId = null, $currentFacilityId = null): array
    {
        $companyId = $this->actorCompanyId($actorRole, $actorCompanyId);
        if ($actorRole === 'QUAN_TRI_TAP_DOAN') {
            return ['companies' => $this->companyOptions(), 'facilities' => []];
        }
        $currentId = $currentFacilityId === null || $currentFacilityId === ''
            ? null
            : self::requireId($currentFacilityId, 'ma_co_so');
        return ['companies' => [], 'facilities' => $this->employees->facilitiesByCompany($companyId, $currentId)];
    }

    public function createEmployee(string $actorRole, $actorCompanyId, array $data, ?array $account = null): int
    {
        if ($actorRole === 'QUAN_TRI_TAP_DOAN' && $account === null) {
            throw new ValidationException(['account' => 'Quản trị tập đoàn phải cấp tài khoản quản lý công ty.']);
        }
        $companyId = $this->companyForWrite($actorRole, $actorCompanyId, $data);
        $clean = self::validatedData($data);
        $facilityId = $this->facilityForWrite($actorRole, $companyId, $clean['ma_co_so']);
        $role = $this->accountRole($actorRole, $account, $facilityId);

        try {
            $this->pdo->beginTransaction();
            $company = $this->requireCompany($companyId, true);
            if ($company['trang_thai'] !== 'HOAT_DONG') {
                throw new ValidationException(['ma_cong_ty' => 'Chỉ công ty đang hoạt động được chọn khi tạo nhân viên.']);
            }
            if ($facilityId !== null) {
                $this->requireFacility($facilityId, $companyId, true, true);
            }
            $accountId = $account === null ? null : $this->accounts->create($account['username'], $account['password'], $role);
            $id = $this->employees->insert($clean + ['ma_cong_ty' => $companyId, 'ma_tai_khoan' => $accountId]);
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->translateForeignKey($e, $facilityId === null ? 'ma_cong_ty' : 'ma_co_so');
            throw $e;
        }
    }

    public function updateEmployee(string $actorRole, $actorCompanyId, $employeeId, array $data): bool
    {
        $companyId = $this->actorCompanyId($actorRole, $actorCompanyId);
        $id = self::requireId($employeeId, 'ma_nhan_vien');
        $clean = self::validatedData($data);
        if ($actorRole === 'QUAN_TRI_TAP_DOAN') {
            $companyId = self::requireId($data['ma_cong_ty'] ?? null, 'ma_cong_ty');
        }
        $facilityId = $this->facilityForWrite($actorRole, $companyId, $clean['ma_co_so']);

        try {
            $this->pdo->beginTransaction();
            $current = $actorRole === 'QUAN_TRI_TAP_DOAN'
                ? $this->employees->findCompanyManager($id, true)
                : $this->employees->findForCompany($id, $companyId, true);
            if (!$current) {
                throw new HttpException(404, 'Không tìm thấy nhân viên.');
            }
            $company = $this->requireCompany($companyId, true);
            $companyChanged = (int) $current['ma_cong_ty'] !== $companyId;
            $facilityChanged = ($current['ma_co_so'] === null ? null : (int) $current['ma_co_so']) !== $facilityId;
            if ($companyChanged && $company['trang_thai'] !== 'HOAT_DONG') {
                throw new ValidationException(['ma_cong_ty' => 'Chỉ công ty đang hoạt động được chọn khi chuyển nhân viên.']);
            }
            if ($facilityId !== null) {
                $this->requireFacility($facilityId, $companyId, true, $facilityChanged || $companyChanged);
                if (($facilityChanged || $companyChanged) && $company['trang_thai'] !== 'HOAT_DONG') {
                    throw new ValidationException(['ma_cong_ty' => 'Công ty phải đang hoạt động khi gán cơ sở mới.']);
                }
            }
            $linkedRole = $this->employees->accountRoleForEmployee($id);
            if (($linkedRole === 'QUAN_LY_CONG_TY' && $facilityId !== null)
                || (in_array($linkedRole, ['QUAN_LY_CO_SO', 'NHAN_VIEN'], true) && $facilityId === null)) {
                throw new ValidationException(['ma_co_so' => 'Cơ sở không phù hợp với vai trò tài khoản hiện tại.']);
            }
            if (!$this->employees->update($id, (int) $current['ma_cong_ty'], $clean + ['ma_cong_ty' => $companyId])) {
                throw new HttpException(409, 'Nhân viên vừa được cập nhật. Vui lòng tải lại trang.');
            }
            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->translateForeignKey($e, $facilityId === null ? 'ma_cong_ty' : 'ma_co_so');
            throw $e;
        }
    }

    public function changeStatus(string $actorRole, $actorCompanyId, $employeeId, $status): bool
    {
        $companyId = $this->actorCompanyId($actorRole, $actorCompanyId);
        $id = self::requireId($employeeId, 'ma_nhan_vien');
        if (!is_string($status) || !in_array($status, ['DANG_LAM', 'DA_NGHI'], true)) {
            throw new ValidationException(['trang_thai' => 'Trạng thái nhân viên không hợp lệ.']);
        }
        try {
            $this->pdo->beginTransaction();
            $employee = $actorRole === 'QUAN_TRI_TAP_DOAN'
                ? $this->employees->findCompanyManager($id, true)
                : $this->employees->findForCompany($id, $companyId, true);
            if (!$employee) {
                throw new HttpException(404, 'Không tìm thấy nhân viên.');
            }
            $updated = $this->employees->changeStatus($id, (int) $employee['ma_cong_ty'], $status);
            if (!$updated) {
                throw new HttpException(409, 'Nhân viên vừa được cập nhật. Vui lòng tải lại trang.');
            }
            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function validId($value): bool
    {
        return (is_string($value) && preg_match('/\A[1-9][0-9]*\z/', $value))
            || (is_int($value) && $value > 0);
    }

    private static function requireId($value, string $field): int
    {
        if (!self::validId($value) || filter_var((string) $value, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) === false) {
            throw new ValidationException([$field => 'ID không hợp lệ.']);
        }
        return (int) $value;
    }

    private function actorCompanyId(string $role, $companyId): ?int
    {
        if ($role === 'QUAN_TRI_TAP_DOAN') {
            return null;
        }
        if ($role !== 'QUAN_LY_CONG_TY') {
            throw new HttpException(403, 'Không có quyền thực hiện thao tác này.');
        }
        $companyId = self::requireId($companyId, 'ma_cong_ty');
        $this->requireCompany($companyId);
        return $companyId;
    }

    private function companyForWrite(string $role, $actorCompanyId, array $data): int
    {
        if ($role === 'QUAN_TRI_TAP_DOAN') {
            return self::requireId($data['ma_cong_ty'] ?? null, 'ma_cong_ty');
        }
        return $this->actorCompanyId($role, $actorCompanyId);
    }

    private function facilityForWrite(string $role, int $companyId, ?int $facility): ?int
    {
        if ($role === 'QUAN_TRI_TAP_DOAN' && $facility !== null) {
            throw new ValidationException(['ma_co_so' => 'Quản lý công ty không được gán cơ sở.']);
        }
        return $facility;
    }

    private function accountRole(string $actorRole, ?array $account, ?int $facilityId): ?string
    {
        if ($account === null) {
            return null;
        }
        $role = $account['role'] ?? null;
        $allowed = $actorRole === 'QUAN_TRI_TAP_DOAN' ? ['QUAN_LY_CONG_TY'] : ['QUAN_LY_CO_SO', 'NHAN_VIEN'];
        if (!is_string($role) || !in_array($role, $allowed, true)) {
            throw new ValidationException(['role' => 'Vai trò tài khoản không được phép cấp.']);
        }
        if (($role === 'QUAN_LY_CONG_TY' && $facilityId !== null)
            || (in_array($role, ['QUAN_LY_CO_SO', 'NHAN_VIEN'], true) && $facilityId === null)) {
            throw new ValidationException(['ma_co_so' => 'Cơ sở không phù hợp với vai trò tài khoản.']);
        }
        if (!is_string($account['username'] ?? null) || !is_string($account['password'] ?? null)) {
            throw new ValidationException(['account' => 'Thông tin tài khoản không hợp lệ.']);
        }
        return $role;
    }

    private static function validatedData(array $data): array
    {
        $errors = [];
        $clean = [];
        $name = $data['ho_ten'] ?? null;
        if (!is_string($name) || !mb_check_encoding($name, 'UTF-8') || preg_match('/\p{C}/u', $name)
            || trim($name) === '' || mb_strlen(trim($name), 'UTF-8') > 150) {
            $errors['ho_ten'] = 'Họ tên bắt buộc, UTF-8 hợp lệ và tối đa 150 ký tự.';
        } else {
            $clean['ho_ten'] = trim($name);
        }
        $limits = ['so_dien_thoai' => 20, 'email' => 150, 'dia_chi' => 255, 'chuc_vu' => 100];
        foreach ($limits as $field => $limit) {
            $value = $data[$field] ?? null;
            if ($value === null || $value === '') {
                $clean[$field] = null;
            } elseif (!is_string($value) || !mb_check_encoding($value, 'UTF-8')
                || preg_match('/\p{C}/u', trim($value)) || mb_strlen(trim($value), 'UTF-8') > $limit) {
                $errors[$field] = 'Giá trị không hợp lệ hoặc vượt quá ' . $limit . ' ký tự.';
            } else {
                $value = trim($value);
                if ($field === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = 'Email không hợp lệ.';
                }
                $clean[$field] = $value === '' ? null : $value;
            }
        }
        foreach (['ngay_sinh', 'ngay_vao_lam'] as $field) {
            $value = $data[$field] ?? null;
            if ($value === null || $value === '') {
                $clean[$field] = null;
            } elseif (!is_string($value) || !self::validDate($value)) {
                $errors[$field] = 'Ngày phải hợp lệ theo định dạng Y-m-d.';
            } else {
                $clean[$field] = $value;
            }
        }
        $gender = $data['gioi_tinh'] ?? null;
        if ($gender === null || $gender === '') {
            $clean['gioi_tinh'] = null;
        } elseif (!is_string($gender) || !in_array($gender, ['NAM', 'NU', 'KHAC'], true)) {
            $errors['gioi_tinh'] = 'Giới tính không hợp lệ.';
        } else {
            $clean['gioi_tinh'] = $gender;
        }
        $status = $data['trang_thai'] ?? 'DANG_LAM';
        if (!is_string($status) || !in_array($status, ['DANG_LAM', 'DA_NGHI'], true)) {
            $errors['trang_thai'] = 'Trạng thái nhân viên không hợp lệ.';
        } else {
            $clean['trang_thai'] = $status;
        }
        $facility = $data['ma_co_so'] ?? null;
        if ($facility === null || $facility === '') {
            $clean['ma_co_so'] = null;
        } else {
            $clean['ma_co_so'] = self::requireId($facility, 'ma_co_so');
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        return $clean;
    }

    private static function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        return $date !== false && $date->format('Y-m-d') === $value
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }

    private function requireCompany(int $id, bool $lock = false): array
    {
        $company = $this->employees->company($id, $lock);
        if (!$company) {
            throw new ValidationException(['ma_cong_ty' => 'Công ty không tồn tại.']);
        }
        return $company;
    }

    private function requireFacility(int $id, int $companyId, bool $lock = false, bool $requireActive = false): array
    {
        $facility = $this->employees->facility($id, $lock);
        if (!$facility || (int) $facility['ma_cong_ty'] !== $companyId) {
            throw new ValidationException(['ma_co_so' => 'Cơ sở không tồn tại hoặc không thuộc công ty này.']);
        }
        if ($requireActive && $facility['trang_thai'] !== 'HOAT_DONG') {
            throw new ValidationException(['ma_co_so' => 'Chỉ cơ sở đang hoạt động được gán mới.']);
        }
        return $facility;
    }

    private function companyOptions(): array
    {
        return $this->employees->companies();
    }

    private function translateForeignKey(\Throwable $error, string $field): void
    {
        if ($error instanceof PDOException && (int) ($error->errorInfo[1] ?? 0) === 1452) {
            throw new ValidationException([$field => 'Công ty hoặc cơ sở không còn tồn tại. Vui lòng tải lại và thử lại.']);
        }
    }
}
