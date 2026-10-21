<?php

namespace App\Controllers\admin;

use App\Models\BoMon;
use App\Models\LoaiSan;
use App\Services\BoMonService;
use App\Services\ValidationException;
use Core\Application;
use Core\Controller;
use Core\HttpException;

final class BoMonController extends Controller
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function index(): void
    {
        $notice = $_SESSION['catalog_notice'] ?? null;
        unset($_SESSION['catalog_notice']);
        $this->render('admin/bo-mon/index', [
            'title' => 'Quản lý bộ môn', 'items' => (new BoMon($this->app->pdo()))->all(), 'notice' => $notice,
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
            (new BoMonService($this->app->pdo()))->save($_POST, $id);
            $_SESSION['catalog_notice'] = 'Đã lưu bộ môn.';
            $this->redirect('/admin/bo-mon');
        } catch (ValidationException $e) {
            $old = [];
            foreach (['ten_bo_mon', 'mo_ta', 'trang_thai'] as $field) {
                $value = $_POST[$field] ?? '';
                $old[$field] = is_string($value) ? mb_substr($value, 0, 1000, 'UTF-8') : '';
            }
            $this->form($item, $e->errors, $old, 422);
        }
    }

    private function form(array $item, array $errors, ?array $old = null, int $status = 200): void
    {
        $this->render('admin/bo-mon/form', [
            'title' => ($item ? 'Sửa' : 'Thêm') . ' bộ môn', 'item' => $item,
            'old' => $old ?? $item, 'errors' => $errors,
        ], $status);
    }

    public function show(): void
    {
        $item = $this->record($this->id($_GET['id'] ?? null));
        $this->render('admin/bo-mon/show', [
            'title' => 'Chi tiết bộ môn', 'item' => $item,
            'types' => (new LoaiSan($this->app->pdo()))->all((int) $item['ma_bo_mon']),
            'related' => (new BoMon($this->app->pdo()))->related((int) $item['ma_bo_mon']),
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
            (new BoMonService($this->app->pdo()))->delete($id);
            $_SESSION['catalog_notice'] = 'Đã xóa bộ môn.';
            $this->redirect('/admin/bo-mon');
        } catch (HttpException $e) {
            if ($e->status !== 409) {
                throw $e;
            }
            $this->deletion($item, $e->getMessage(), 409);
        }
    }

    private function deletion(array $item, ?string $formError = null, int $status = 200): void
    {
        $this->render('admin/bo-mon/delete', [
            'title' => 'Xác nhận xóa bộ môn', 'item' => $item, 'formError' => $formError,
            'related' => (new BoMon($this->app->pdo()))->related((int) $item['ma_bo_mon']),
        ], $status);
    }

    private function record(int $id): array
    {
        $item = (new BoMon($this->app->pdo()))->find($id);
        if (!$item) {
            throw new HttpException(404, 'Không tìm thấy bộ môn.');
        }
        return $item;
    }

    private function id($value): int
    {
        if (!is_string($value) || !preg_match('/\A[1-9][0-9]*\z/', $value)
            || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) === false) {
            throw new HttpException(404, 'Không tìm thấy bộ môn.');
        }
        return (int) $value;
    }
}

