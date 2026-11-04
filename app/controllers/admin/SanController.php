<?php

namespace App\Controllers\admin;

use App\Models\San;
use App\Models\CoSo;
use App\Models\LoaiSan;
use App\Services\AdminListService;
use App\Services\SanService;
use App\Services\FacilityScope;
use App\Services\CoSoService;
use App\Services\ValidationException;
use Core\Application;
use Core\Controller;
use Core\HttpException;

final class SanController extends Controller
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function index(): void
    {
        $filters = AdminListService::filters($_GET, ['ma_co_so', 'ma_loai_san'],
            ['trang_thai' => ['HOAT_DONG', 'BAO_TRI', 'TAM_NGUNG']]);
        $scope = $this->scope();
        if ($filters['ma_co_so'] !== '') {
            $facility = (new CoSo($this->app->pdo()))->find((int) $filters['ma_co_so']);
            if (!$facility) throw new HttpException(404, 'Không tìm thấy cơ sở.');
            FacilityScope::check($facility, $scope);
        }
        $result = (new San($this->app->pdo()))->page($filters, $scope);
        if ($result['page'] !== $filters['page']) admin_list_redirect($this->listPath(), array_replace($filters, ['page' => $result['page']]));
        $notice = $_SESSION['facility_notice'] ?? null;
        unset($_SESSION['facility_notice']);
        $this->page('admin/san/index', $result + [
            'title' => 'Quản lý sân', 'filters' => $filters, 'notice' => $notice,
            'facilities' => (new CoSo($this->app->pdo()))->all($scope), 'types' => (new LoaiSan($this->app->pdo()))->all(),
        ]);
    }

    public function create(): void
    {
        FacilityScope::write($this->scope());
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
            (new SanService($this->app->pdo(), $this->scope()))->save($_POST, $id);
            $_SESSION['facility_notice'] = 'Đã lưu sân.';
            admin_list_redirect($this->listPath());
        } catch (ValidationException $e) {
            $old = [];
            foreach (['ma_co_so', 'ma_loai_san', 'ten_san', 'vi_tri', 'mo_ta', 'trang_thai'] as $field) {
                $value = $_POST[$field] ?? '';
                $old[$field] = is_string($value) ? mb_strcut($value, 0, 65535, 'UTF-8') : '';
            }
            $this->form($item, $e->errors, $old, 422);
        }
    }

    private function form(array $item, array $errors, ?array $old = null, int $status = 200): void
    {
        $this->page('admin/san/form', [
            'title' => ($item ? 'Sửa' : 'Thêm') . ' sân', 'item' => $item,
            'old' => $old ?? $item, 'errors' => $errors,
            'facilities' => (new CoSo($this->app->pdo()))->options(isset($item['ma_co_so']) ? (int) $item['ma_co_so'] : null, $this->scope()),
            'types' => (new San($this->app->pdo()))->types(isset($item['ma_loai_san']) ? (int) $item['ma_loai_san'] : null),
        ], $status);
    }

    public function show(): void
    {
        $item = $this->record($this->id($_GET['id'] ?? null));
        $this->page('admin/san/show', [
            'title' => 'Chi tiết sân', 'item' => $item,
            'related' => (new San($this->app->pdo()))->related((int) $item['ma_san']),
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
            (new SanService($this->app->pdo(), $this->scope()))->delete($id);
            $_SESSION['facility_notice'] = 'Đã xóa sân.';
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
        $this->page('admin/san/delete', [
            'title' => 'Xác nhận xóa sân', 'item' => $item, 'formError' => $formError,
            'related' => (new San($this->app->pdo()))->related((int) $item['ma_san']),
        ], $status);
    }

    private function record(int $id): array
    {
        $item = (new San($this->app->pdo()))->find($id);
        if (!$item) {
            throw new HttpException(404, 'Không tìm thấy sân.');
        }
        FacilityScope::check($item, $this->scope());
        return $item;
    }

    private function id($value): int
    {
        if (!CoSoService::validId($value)) {
            throw new HttpException(404, 'Không tìm thấy sân.');
        }
        return (int) $value;
    }

    private function scope(): array
    {
        return FacilityScope::forUser($this->app->auth()->user() ?? []);
    }

    private function listPath(): string
    {
        return FacilityScope::root($this->scope()) . '/san';
    }

    private function page(string $view, array $data, int $status = 200): void
    {
        $scope = $this->scope();
        parent::render($view, $data + [
            'managementRoot' => FacilityScope::root($scope), 'managementScope' => $scope,
            'canWrite' => $scope['role'] !== 'NHAN_VIEN',
            'canCreate' => $scope['role'] !== 'NHAN_VIEN',
            'canDelete' => $scope['role'] === 'QUAN_TRI_TAP_DOAN',
        ], $status);
    }
}
