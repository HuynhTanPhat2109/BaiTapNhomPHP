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
        [$role, $facilityId, $facility] = $this->resolveFacilityContext();
        $accounts = $this->bankModel->allByFacility($facilityId);

        $notice = $_SESSION['bank_notice'] ?? null;
        unset($_SESSION['bank_notice']);

        $this->render('facility/bank/index', [
            'title' => 'Tài khoản ngân hàng cơ sở',
            'facility' => $facility,
            'accounts' => $accounts,
            'notice' => $notice,
            'role' => $role,
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
            $validated['trang_thai'] = ($_POST['trang_thai'] ?? 'HOAT_DONG') === 'NGUNG' ? 'NGUNG' : 'HOAT_DONG';

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
            $validated['la_mac_dinh'] = !empty($_POST['la_mac_dinh']) ? 1 : 0;
            $validated['trang_thai'] = ($_POST['trang_thai'] ?? 'HOAT_DONG') === 'NGUNG' ? 'NGUNG' : 'HOAT_DONG';

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
        $this->bankModel->setDefault($id, $facilityId);

        $_SESSION['bank_notice'] = 'Đã đặt tài khoản làm mặc định nhận thanh toán.';
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

        $this->bankModel->toggleStatus($id, $newStatus);
        $_SESSION['bank_notice'] = 'Đã thay đổi trạng thái tài khoản ngân hàng.';
        $this->redirect('/facility/bank?facility_id=' . $facilityId);
    }

    /**
     * Xác thực quyền của người dùng đăng nhập đối với cơ sở sân.
     */
    private function resolveFacilityContext(?int $targetFacilityId = null): array
    {
        $user = $this->app->auth()->user();
        if (!is_array($user)) {
            throw new HttpException(403, 'Bạn cần đăng nhập để thực hiện chức năng này.');
        }

        $role = $user['ten_vai_tro'] ?? '';

        if ($role === 'QUAN_LY_CO_SO') {
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
}
