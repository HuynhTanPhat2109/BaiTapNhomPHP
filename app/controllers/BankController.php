<?php

namespace App\Controllers;

use App\Models\CoSo;
use App\Models\TaiKhoanNganHang;
use App\Services\ValidationException;
use App\Services\VietQRService;
use Core\Application;
use Core\Controller;
use Core\HttpException;

final class BankController extends Controller
{
    private TaiKhoanNganHang $bankModel;
    private CoSo $facilityModel;
    private VietQRService $vietQrService;

    public function __construct(private Application $app)
    {
        $pdo = $app->pdo();
        $this->bankModel = new TaiKhoanNganHang($pdo);
        $this->facilityModel = new CoSo($pdo);
        $this->vietQrService = new VietQRService();
    }

    /**
     * Danh sách tài khoản ngân hàng của cơ sở.
     */
    public function index(): void
    {
        $user = $this->app->auth()->user();
        if (($user['ten_vai_tro'] ?? '') === 'QUAN_TRI_TAP_DOAN') {
            $this->monitor();
            return;
        }
        $facilities = match ($user['ten_vai_tro'] ?? '') {
            'QUAN_LY_CONG_TY' => $this->facilityModel->byCompany((int) $user['ma_cong_ty']),
            'QUAN_TRI_TAP_DOAN' => $this->facilityModel->all(),
            default => [],
        };
        $selectedId = $_GET['facility_id'] ?? ($facilities[0]['ma_co_so'] ?? null);
        [$role, $facilityId, $facility] = $this->resolveFacilityContext($selectedId === null ? null : (int) $selectedId, true);
        $accounts = $this->bankModel->allByFacility($facilityId);

        $notice = $_SESSION['bank_notice'] ?? null;
        $error = $_SESSION['bank_error'] ?? null;
        unset($_SESSION['bank_notice'], $_SESSION['bank_error']);

        $this->render('facility/bank/index', [
            'title' => 'Tài khoản ngân hàng cơ sở',
            'facility' => $facility,
            'accounts' => $accounts,
            'notice' => $notice,
            'error' => $error,
            'role' => $role,
            'facilities' => $facilities,
        ]);
    }

    /**
     * Giao diện thêm mới tài khoản ngân hàng.
     */
    public function create(): void
    {
        [$role, $facilityId, $facility] = $this->resolveFacilityContext();
        $banks = $this->vietQrService->getBankList();

        $errors = $_SESSION['bank_errors'] ?? [];
        $old = $_SESSION['bank_old'] ?? [];
        unset($_SESSION['bank_errors'], $_SESSION['bank_old']);

        $this->render('facility/bank/form', [
            'title' => 'Thêm tài khoản ngân hàng',
            'facility' => $facility,
            'banks' => $banks,
            'item' => null,
            'errors' => $errors,
            'old' => $old,
            'role' => $role,
            'isEdit' => false,
        ]);
    }

    /**
     * Lưu tài khoản ngân hàng mới.
     */
    public function store(): void
    {
        [$role, $facilityId, $facility] = $this->resolveFacilityContext();

        try {
            $validated = $this->vietQrService->validateAccountInput($_POST);
            $validated['ma_co_so'] = $facilityId;
            $validated['la_mac_dinh'] = !empty($_POST['la_mac_dinh']) ? 1 : 0;
            $validated['trang_thai'] = $_POST['trang_thai'] ?? 'HOAT_DONG';

            $this->bankModel->create($validated);
            $_SESSION['bank_notice'] = 'Thêm tài khoản ngân hàng thành công.';
            $this->redirect('/facility/bank?facility_id=' . $facilityId);
        } catch (ValidationException $e) {
            $_SESSION['bank_errors'] = $e->errors;
            $_SESSION['bank_old'] = $_POST;
            $this->redirect('/facility/bank/create?facility_id=' . $facilityId);
        }
    }

    /**
     * Giao diện chỉnh sửa tài khoản ngân hàng.
     */
    public function edit(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $account = $this->bankModel->find($id);
        if (!$account) {
            throw new HttpException(404, 'Không tìm thấy tài khoản ngân hàng.');
        }

        [$role, $facilityId, $facility] = $this->resolveFacilityContext((int) $account['ma_co_so']);
        $banks = $this->vietQrService->getBankList();

        $errors = $_SESSION['bank_errors'] ?? [];
        $old = $_SESSION['bank_old'] ?? [];
        unset($_SESSION['bank_errors'], $_SESSION['bank_old']);

        $this->render('facility/bank/form', [
            'title' => 'Cập nhật tài khoản ngân hàng',
            'facility' => $facility,
            'banks' => $banks,
            'item' => $account,
            'errors' => $errors,
            'old' => $old,
            'role' => $role,
            'isEdit' => true,
        ]);
    }

    /**
     * Cập nhật thông tin tài khoản ngân hàng.
     */
    public function update(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $account = $this->bankModel->find($id);
        if (!$account) {
            throw new HttpException(404, 'Không tìm thấy tài khoản ngân hàng.');
        }

        [$role, $facilityId] = $this->resolveFacilityContext((int) $account['ma_co_so']);

        try {
            $validated = $this->vietQrService->validateAccountInput($_POST);
            $this->bankModel->update($id, $validated);
            $_SESSION['bank_notice'] = 'Cập nhật tài khoản ngân hàng thành công.';
            $this->redirect('/facility/bank?facility_id=' . $facilityId);
        } catch (ValidationException $e) {
            $_SESSION['bank_errors'] = $e->errors;
            $_SESSION['bank_old'] = $_POST;
            $this->redirect('/facility/bank/edit?id=' . $id);
        }
    }

    /**
     * Đặt tài khoản làm mặc định cho cơ sở.
     */
    public function setDefault(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $account = $this->bankModel->find($id);
        if (!$account) {
            throw new HttpException(404, 'Không tìm thấy tài khoản ngân hàng.');
        }

        [$role, $facilityId] = $this->resolveFacilityContext((int) $account['ma_co_so']);
        try {
            $this->bankModel->setDefault($id, $facilityId);
            $_SESSION['bank_notice'] = 'Đã đặt tài khoản làm mặc định nhận thanh toán.';
        } catch (ValidationException $e) {
            $_SESSION['bank_error'] = implode(' ', $e->errors);
        }
        $this->redirect('/facility/bank?facility_id=' . $facilityId);
    }

    /**
     * Bật/Ngừng sử dụng tài khoản ngân hàng.
     */
    public function toggleStatus(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $account = $this->bankModel->find($id);
        if (!$account) {
            throw new HttpException(404, 'Không tìm thấy tài khoản ngân hàng.');
        }

        [$role, $facilityId] = $this->resolveFacilityContext((int) $account['ma_co_so']);
        $newStatus = ($account['trang_thai'] === 'HOAT_DONG') ? 'NGUNG' : 'HOAT_DONG';

        try {
            $this->bankModel->toggleStatus($id, $newStatus);
            $_SESSION['bank_notice'] = 'Đã thay đổi trạng thái tài khoản ngân hàng.';
        } catch (ValidationException $e) {
            $_SESSION['bank_error'] = implode(' ', $e->errors);
        }
        $this->redirect('/facility/bank?facility_id=' . $facilityId);
    }

    /**
     * Xác thực quyền của người dùng đăng nhập đối với cơ sở sân.
     */
    private function resolveFacilityContext(?int $targetFacilityId = null, bool $allowEmployeeRead = false): array
    {
        $user = $this->app->auth()->user();
        if (!is_array($user)) {
            throw new HttpException(403, 'Bạn cần đăng nhập để thực hiện chức năng này.');
        }

        $role = $user['ten_vai_tro'] ?? '';

        if ($role === 'QUAN_LY_CO_SO' || ($role === 'NHAN_VIEN' && $allowEmployeeRead)) {
            $userFacilityId = (int) ($user['ma_co_so'] ?? 0);
            if ($userFacilityId <= 0) {
                throw new HttpException(403, 'Tài khoản chưa được gán cơ sở quản lý.');
            }
            if ($targetFacilityId !== null && $targetFacilityId !== $userFacilityId) {
                throw new HttpException(403, 'Bạn không có quyền quản lý tài khoản của cơ sở khác.');
            }
            $facilityId = $userFacilityId;
        } elseif ($role === 'QUAN_LY_CONG_TY') {
            $userCompanyId = (int) ($user['ma_cong_ty'] ?? 0);
            $facilityId = $targetFacilityId ?? (int) ($_GET['facility_id'] ?? $_POST['facility_id'] ?? 0);
            if ($facilityId <= 0) {
                throw new HttpException(400, 'Thiếu thông tin cơ sở cần quản lý.');
            }
            $f = $this->facilityModel->find($facilityId);
            if (!$f || (int) $f['ma_cong_ty'] !== $userCompanyId) {
                throw new HttpException(403, 'Bạn chỉ được quản lý cơ sở thuộc công ty của mình.');
            }
        } elseif ($role === 'QUAN_TRI_TAP_DOAN') {
            if (!$allowEmployeeRead) throw new HttpException(403, 'Admin chỉ có quyền giám sát tài khoản ngân hàng.');
            $facilityId = $targetFacilityId ?? (int) ($_GET['facility_id'] ?? $_POST['facility_id'] ?? 0);
            if ($facilityId <= 0) {
                throw new HttpException(400, 'Vui lòng chọn cơ sở.');
            }
        } else {
            throw new HttpException(403, 'Bạn không có quyền truy cập chức năng này.');
        }

        $facility = $this->facilityModel->find($facilityId);
        if (!$facility) {
            throw new HttpException(404, 'Cơ sở không tồn tại.');
        }

        return [$role, $facilityId, $facility];
    }

    private function monitor(): void
    {
        $filters = [];
        foreach (['q', 'company_id', 'facility_id', 'status'] as $key) {
            $value = $_GET[$key] ?? '';
            if ((!is_string($value) && !is_int($value)) || mb_strlen((string) $value) > 150) throw new HttpException(422, 'Bộ lọc ngân hàng không hợp lệ.');
            $filters[$key] = trim((string) $value);
        }
        foreach (['company_id', 'facility_id'] as $key) {
            if ($filters[$key] !== '' && !filter_var($filters[$key], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) throw new HttpException(422, 'Mã công ty/cơ sở không hợp lệ.');
        }
        if (!in_array($filters['status'], ['', 'HOAT_DONG', 'NGUNG'], true)) throw new HttpException(422, 'Trạng thái tài khoản không hợp lệ.');
        $this->render('facility/bank/monitor', ['title' => 'Giám sát tài khoản ngân hàng',
            'role' => 'QUAN_TRI_TAP_DOAN', 'user' => $this->app->auth()->user(), 'filters' => $filters,
            'companies' => $this->facilityModel->filterCompanies(), 'facilities' => $this->facilityModel->all(),
            'accounts' => $this->bankModel->monitor($filters)]);
    }
}
