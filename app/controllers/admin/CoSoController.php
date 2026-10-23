<?php

namespace App\Controllers\admin;

use App\Models\CoSo;
use App\Models\San;
use App\Services\CoSoService;
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
        $notice = $_SESSION['facility_notice'] ?? null;
        unset($_SESSION['facility_notice']);
        $this->render('admin/co-so/index', [
            'title' => 'Quản lý cơ sở', 'items' => (new CoSo($this->app->pdo()))->all(), 'notice' => $notice,
        ]);
    }

    public function create(): void
    {
        $this->form([], []);
    }

    public function edit(): void
    {
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
        $item = $id === null ? [] : $this->record($id);
        try {
            (new CoSoService($this->app->pdo()))->save(
                array_replace($_POST, ['ma_tinh' => $_POST['ma_tinh'] ?? ($item['ma_tinh'] ?? ''),
                    'ma_phuong_xa' => $_POST['ma_phuong_xa'] ?? ($item['ma_phuong_xa'] ?? '')]),
                $id, isset($_FILES['anh_dai_dien']) && is_array($_FILES['anh_dai_dien']) ? $_FILES['anh_dai_dien'] : null,
                isset($_FILES['logo_clb']) && is_array($_FILES['logo_clb']) ? $_FILES['logo_clb'] : null);
            $_SESSION['facility_notice'] = 'Đã lưu cơ sở.';
            $this->redirect('/admin/co-so');
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
        $this->render('admin/co-so/form', [
            'title' => ($item ? 'Sửa' : 'Thêm') . ' cơ sở', 'item' => $item,
            'old' => $old ?? $item, 'errors' => $errors,
            'companies' => (new CoSo($this->app->pdo()))->companies(isset($item['ma_cong_ty']) ? (int) $item['ma_cong_ty'] : null),
        ], $status);
    }

    public function show(): void
    {
        $item = $this->record($this->id($_GET['id'] ?? null));
        $this->render('admin/co-so/show', [
            'title' => 'Chi tiết cơ sở', 'item' => $item,
            'related' => (new CoSo($this->app->pdo()))->related((int) $item['ma_co_so']),
            'courts' => (new San($this->app->pdo()))->all((int) $item['ma_co_so']),
        ]);
    }

    public function confirmDelete(): void
    {
        $this->deletion($this->record($this->id($_GET['id'] ?? null)));
    }

    public function destroy(): void
    {
        $id = $this->id($_POST['id'] ?? null);
        $item = $this->record($id);
        try {
            (new CoSoService($this->app->pdo()))->delete($id);
            $_SESSION['facility_notice'] = 'Đã xóa cơ sở.';
            $this->redirect('/admin/co-so');
        } catch (HttpException $e) {
            if ($e->status !== 409) {
                throw $e;
            }
            $this->deletion($item, $e->getMessage(), 409);
        }
    }

    private function deletion(array $item, ?string $formError = null, int $status = 200): void
    {
        $this->render('admin/co-so/delete', [
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
        return $item;
    }

    private function id($value): int
    {
        if (!CoSoService::validId($value)) {
            throw new HttpException(404, 'Không tìm thấy cơ sở.');
        }
        return (int) $value;
    }
}
