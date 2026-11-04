<?php

namespace App\Controllers\admin;

use App\Models\BangGia;
use App\Models\CoSo;
use App\Services\BangGiaService;
use App\Services\FacilityScope;
use App\Services\CoSoService;
use App\Services\ValidationException;
use Core\Application;
use Core\Controller;
use Core\HttpException;

final class BangGiaController extends Controller
{
    public function __construct(private Application $app) {}

    public function index(): void
    {
        $filters = BangGiaService::filters($_GET);
        $model = new BangGia($this->app->pdo());
        $scope = $this->scope();
        if ($filters['ma_co_so'] !== '') {
            $facility = (new CoSo($this->app->pdo()))->find((int) $filters['ma_co_so']);
            if (!$facility) throw new HttpException(404, 'Không tìm thấy cơ sở.');
            FacilityScope::check($facility, $scope);
        }
        $result = $model->page($filters, $scope);
        if ($result['page'] !== $filters['page']) admin_list_redirect($this->listPath(), array_replace($filters, ['page' => $result['page']]));
        $notice = $_SESSION['pricing_notice'] ?? null;
        unset($_SESSION['pricing_notice']);
        $this->page('admin/bang-gia/index', $result + [
            'title' => 'Quản lý bảng giá', 'filters' => $filters, 'notice' => $notice,
            'facilities' => (new CoSo($this->app->pdo()))->all($scope), 'types' => $model->types(true),
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
        $this->form($this->record($_GET['id'] ?? null), []);
    }

    public function store(): void
    {
        $this->persist(null);
    }

    public function update(): void
    {
        $item = $this->record($_POST['id'] ?? null);
        $this->persist($item);
    }

    private function persist(?array $item): void
    {
        FacilityScope::write($this->scope());
        try {
            (new BangGiaService($this->app->pdo(), $this->scope()))->save($_POST, $item ? (int) $item['ma_bang_gia'] : null);
            $_SESSION['pricing_notice'] = 'Đã lưu bảng giá.';
            admin_list_redirect($this->listPath());
        } catch (ValidationException $e) {
            $old = [];
            foreach (['ma_co_so', 'ma_loai_san', 'ten_bang_gia', 'loai_ngay', 'gio_bat_dau', 'gio_ket_thuc',
                'don_gia', 'ngay_bat_dau', 'ngay_ket_thuc', 'trang_thai'] as $field) {
                $value = $_POST[$field] ?? '';
                $old[$field] = is_string($value) ? mb_strcut($value, 0, 600, 'UTF-8') : '';
            }
            $this->form($item ?? [], $e->errors, $old, 422);
        }
    }

    private function form(array $item, array $errors, ?array $old = null, int $status = 200): void
    {
        $this->page('admin/bang-gia/form', [
            'title' => ($item ? 'Sửa' : 'Thêm') . ' bảng giá', 'item' => $item, 'old' => $old ?? $item, 'errors' => $errors,
            'facilities' => (new CoSo($this->app->pdo()))->options(isset($item['ma_co_so']) ? (int) $item['ma_co_so'] : null, $this->scope()),
            'types' => (new BangGia($this->app->pdo()))->types(false, isset($item['ma_loai_san']) ? (int) $item['ma_loai_san'] : null),
        ], $status);
    }

    public function show(): void
    {
        $this->details($this->record($_GET['id'] ?? null), [], ($_GET['confirm'] ?? '') === '1');
    }

    public function changeStatus(): void
    {
        FacilityScope::write($this->scope());
        $item = $this->record($_POST['id'] ?? null);
        try {
            $status = $_POST['trang_thai'] ?? null;
            if ($status === 'NGUNG' && ($_POST['confirmed'] ?? null) !== '1') {
                throw new ValidationException(['confirmed' => 'Vui lòng xác nhận trước khi ngừng áp dụng.']);
            }
            (new BangGiaService($this->app->pdo(), $this->scope()))->changeStatus((int) $item['ma_bang_gia'], $status);
            $_SESSION['pricing_notice'] = $status === 'NGUNG' ? 'Đã ngừng áp dụng bảng giá.' : 'Đã kích hoạt bảng giá.';
            admin_list_redirect($this->listPath());
        } catch (ValidationException $e) {
            $this->details($this->record((string) $item['ma_bang_gia']), $e->errors, true, 422);
        }
    }

    private function details(array $item, array $errors, bool $confirm, int $status = 200): void
    {
        $this->page('admin/bang-gia/show', [
            'title' => 'Chi tiết bảng giá', 'item' => $item, 'errors' => $errors, 'confirm' => $confirm,
        ], $status);
    }

    private function record($value): array
    {
        $item = CoSoService::validId($value) ? (new BangGia($this->app->pdo()))->find((int) $value) : null;
        if (!$item) {
            throw new HttpException(404, 'Không tìm thấy bảng giá.');
        }
        FacilityScope::check($item, $this->scope());
        return $item;
    }

    private function scope(): array
    {
        return FacilityScope::forUser($this->app->auth()->user() ?? []);
    }

    private function listPath(): string
    {
        return FacilityScope::root($this->scope()) . '/bang-gia';
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
