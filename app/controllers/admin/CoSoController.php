<?php

namespace App\Controllers\admin;

use App\Models\CoSo;
use App\Models\San;
use App\Services\CoSoService;
use App\Services\FacilityScope;
use App\Services\AdminListService;
use App\Services\ValidationException;
use Core\Application;
use Core\Controller;
use Core\HttpException;

final class CoSoController extends Controller
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function index(): void
    {
        $filters = AdminListService::filters($_GET, ['ma_cong_ty'], ['trang_thai' => ['HOAT_DONG', 'TAM_NGUNG']]);
        $model = new CoSo($this->app->pdo());
        $scope = $this->scope();
        if ($filters['ma_cong_ty'] !== '' && $scope['company'] !== null && (int) $filters['ma_cong_ty'] !== $scope['company']) throw new HttpException(403, 'Công ty nằm ngoài phạm vi của bạn.');
        if (in_array($scope['role'], ['QUAN_LY_CO_SO', 'NHAN_VIEN'], true)) {
            $requested = $_GET['id'] ?? $_GET['ma_co_so'] ?? (string) $scope['facility'];
            if (!CoSoService::validId($requested) || (int) $requested !== $scope['facility']) {
                throw new HttpException(403, 'Cơ sở nằm ngoài phạm vi được phân công.');
            }
            $this->detail($this->record((int) $scope['facility']), true);
            return;
        }
        $result = $model->page($filters, $scope);
        if ($result['page'] !== $filters['page']) admin_list_redirect($this->listPath(), array_replace($filters, ['page' => $result['page']]));
        $notice = $_SESSION['facility_notice'] ?? null;
        unset($_SESSION['facility_notice']);
        $this->page('admin/co-so/index', $result + [
            'title' => 'Quản lý cơ sở', 'filters' => $filters, 'notice' => $notice,
            'companies' => $model->filterCompanies($scope),
        ]);
    }

    public function create(): void
    {
        if (!in_array($this->scope()['role'], ['QUAN_TRI_TAP_DOAN', 'QUAN_LY_CONG_TY'], true)) throw new HttpException(403, 'Không có quyền thêm cơ sở.');
        $this->form([], []);
    }

    public function edit(): void
    {
        FacilityScope::write($this->scope());
        $this->form($this->record($this->id($_GET['id'] ?? null)), []);
    }

    public function store(): void
    {
        $this->persist(null);
    }

    public function update(): void
    {
        $this->persist($this->id($_POST['id'] ?? null));
    }

    private function persist(?int $id): void
    {
        FacilityScope::write($this->scope());
        $item = $id === null ? [] : $this->record($id);
        try {
            (new CoSoService($this->app->pdo(), $this->scope()))->save(
                array_replace($_POST, ['ma_tinh' => $_POST['ma_tinh'] ?? ($item['ma_tinh'] ?? ''),
                    'ma_phuong_xa' => $_POST['ma_phuong_xa'] ?? ($item['ma_phuong_xa'] ?? '')]),
                $id, isset($_FILES['anh_dai_dien']) && is_array($_FILES['anh_dai_dien']) ? $_FILES['anh_dai_dien'] : null,
                isset($_FILES['logo_clb']) && is_array($_FILES['logo_clb']) ? $_FILES['logo_clb'] : null);
            $_SESSION['facility_notice'] = 'Đã lưu cơ sở.';
            admin_list_redirect($this->listPath());
        } catch (ValidationException $e) {
            $old = [];
            foreach (['ma_tinh', 'ma_phuong_xa', 'xoa_anh', 'xoa_logo', 'ma_cong_ty', 'ten_co_so', 'dia_chi', 'so_dien_thoai', 'email', 'gio_mo_cua', 'gio_dong_cua', 'mo_ta', 'trang_thai'] as $field) {
                $value = $_POST[$field] ?? '';
                $old[$field] = is_string($value) ? mb_strcut($value, 0, 65535, 'UTF-8') : '';
            }
            $this->form($item, $e->errors, $old, 422);
        }
    }

    private function form(array $item, array $errors, ?array $old = null, int $status = 200): void
    {
        $this->page('admin/co-so/form', [
            'title' => ($item ? 'Sửa' : 'Thêm') . ' cơ sở', 'item' => $item,
            'old' => $old ?? ($item ?: ['ma_cong_ty' => (string) ($this->scope()['company'] ?? '')]), 'errors' => $errors,
            'companies' => (new CoSo($this->app->pdo()))->companies(isset($item['ma_cong_ty']) ? (int) $item['ma_cong_ty'] : null, $this->scope()),
        ], $status);
    }

    public function show(): void
    {
        $this->detail($this->record($this->id($_GET['id'] ?? null)));
    }

    private function detail(array $item, bool $facilityProfile = false): void
    {
        $this->page('admin/co-so/show', [
            'title' => $facilityProfile ? 'Thông tin cơ sở' : 'Chi tiết cơ sở', 'item' => $item,
            'facilityProfile' => $facilityProfile,
            'related' => (new CoSo($this->app->pdo()))->related((int) $item['ma_co_so']),
            'courts' => (new San($this->app->pdo()))->all((int) $item['ma_co_so']),
        ]);
    }

    public function confirmDelete(): void
    {
        FacilityScope::admin($this->scope());
        $this->deletion($this->record($this->id($_GET['id'] ?? null)));
    }

    public function destroy(): void
    {
        FacilityScope::admin($this->scope());
        $id = $this->id($_POST['id'] ?? null);
        $item = $this->record($id);
        try {
            (new CoSoService($this->app->pdo(), $this->scope()))->delete($id);
            $_SESSION['facility_notice'] = 'Đã xóa cơ sở.';
            admin_list_redirect($this->listPath());
        } catch (HttpException $e) {
            if ($e->status !== 409) {
                throw $e;
            }
            $this->deletion($item, $e->getMessage(), 409);
        }
    }

    private function deletion(array $item, ?string $formError = null, int $status = 200): void
    {
        $this->page('admin/co-so/delete', [
            'title' => 'Xác nhận xóa cơ sở', 'item' => $item, 'formError' => $formError,
            'related' => (new CoSo($this->app->pdo()))->related((int) $item['ma_co_so']),
        ], $status);
    }

    private function record(int $id): array
    {
        $item = (new CoSo($this->app->pdo()))->find($id);
        if (!$item) {
            throw new HttpException(404, 'Không tìm thấy cơ sở.');
        }
        FacilityScope::check($item, $this->scope());
        return $item;
    }

    private function id($value): int
    {
        if (!CoSoService::validId($value)) {
            throw new HttpException(404, 'Không tìm thấy cơ sở.');
        }
        return (int) $value;
    }

    private function scope(): array
    {
        return FacilityScope::forUser($this->app->auth()->user() ?? []);
    }

    private function listPath(): string
    {
        return FacilityScope::root($this->scope()) . '/co-so';
    }

    private function page(string $view, array $data, int $status = 200): void
    {
        $scope = $this->scope();
        parent::render($view, $data + [
            'managementRoot' => FacilityScope::root($scope), 'managementScope' => $scope,
            'canWrite' => $scope['role'] !== 'NHAN_VIEN',
            'canCreate' => $scope['role'] !== 'NHAN_VIEN' && $scope['role'] !== 'QUAN_LY_CO_SO',
            'canDelete' => $scope['role'] === 'QUAN_TRI_TAP_DOAN',
        ], $status);
    }
}
