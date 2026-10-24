<?php

namespace App\Controllers\admin;

use App\Models\LoaiSan;
use App\Models\BoMon;
use App\Services\LoaiSanService;
use App\Services\AdminListService;
use App\Services\ValidationException;
use Core\Application;
use Core\Controller;
use Core\HttpException;

final class LoaiSanController extends Controller
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function index(): void
    {
        $filters = AdminListService::filters($_GET, ['ma_bo_mon'], ['trang_thai' => ['HOAT_DONG', 'NGUNG']]);
        $notice = $_SESSION['catalog_notice'] ?? null;
        unset($_SESSION['catalog_notice']);
        $this->render('admin/loai-san/index', (new LoaiSan($this->app->pdo()))->page($filters) + [
            'title' => 'Quản lý loại sân', 'filters' => $filters, 'notice' => $notice,
            'sports' => (new BoMon($this->app->pdo()))->all(),
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
            (new LoaiSanService($this->app->pdo()))->save($_POST, $id);
            $_SESSION['catalog_notice'] = 'Đã lưu loại sân.';
            $this->redirect('/admin/loai-san');
        } catch (ValidationException $e) {
            $old = [];
            foreach (['ma_bo_mon', 'ten_loai_san', 'mo_ta', 'trang_thai'] as $field) {
                $value = $_POST[$field] ?? '';
                $old[$field] = is_string($value) ? mb_substr($value, 0, 1000, 'UTF-8') : '';
            }
            $this->form($item, $e->errors, $old, 422);
        }
    }

    private function form(array $item, array $errors, ?array $old = null, int $status = 200): void
    {
        $this->render('admin/loai-san/form', [
            'title' => ($item ? 'Sửa' : 'Thêm') . ' loại sân', 'item' => $item,
            'old' => $old ?? $item, 'errors' => $errors,
            'sports' => (new BoMon($this->app->pdo()))->options(isset($item['ma_bo_mon']) ? (int) $item['ma_bo_mon'] : null),
        ], $status);
    }

    public function show(): void
    {
        $item = $this->record($this->id($_GET['id'] ?? null));
        $this->render('admin/loai-san/show', [
            'title' => 'Chi tiết loại sân', 'item' => $item,
            'related' => (new LoaiSan($this->app->pdo()))->related((int) $item['ma_loai_san']),
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
            (new LoaiSanService($this->app->pdo()))->delete($id);
            $_SESSION['catalog_notice'] = 'Đã xóa loại sân.';
            $this->redirect('/admin/loai-san');
        } catch (HttpException $e) {
            if ($e->status !== 409) {
                throw $e;
            }
            $this->deletion($item, $e->getMessage(), 409);
        }
    }

    private function deletion(array $item, ?string $formError = null, int $status = 200): void
    {
        $this->render('admin/loai-san/delete', [
            'title' => 'Xác nhận xóa loại sân', 'item' => $item, 'formError' => $formError,
            'related' => (new LoaiSan($this->app->pdo()))->related((int) $item['ma_loai_san']),
        ], $status);
    }

    private function record(int $id): array
    {
        $item = (new LoaiSan($this->app->pdo()))->find($id);
        if (!$item) {
            throw new HttpException(404, 'Không tìm thấy loại sân.');
        }
        return $item;
    }

    private function id($value): int
    {
        if (!is_string($value) || !preg_match('/\A[1-9][0-9]*\z/', $value)
            || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) === false) {
            throw new HttpException(404, 'Không tìm thấy loại sân.');
        }
        return (int) $value;
    }
}

