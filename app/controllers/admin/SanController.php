<?php

namespace App\Controllers\admin;

use App\Models\San;
use App\Models\CoSo;
use App\Services\SanService;
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
        $notice = $_SESSION['facility_notice'] ?? null;
        unset($_SESSION['facility_notice']);
        $this->render('admin/san/index', [
            'title' => 'Quản lý sân', 'items' => (new San($this->app->pdo()))->all(), 'notice' => $notice,
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
            (new SanService($this->app->pdo()))->save($_POST, $id);
            $_SESSION['facility_notice'] = 'Đã lưu sân.';
            $this->redirect('/admin/san');
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
        $this->render('admin/san/form', [
            'title' => ($item ? 'Sửa' : 'Thêm') . ' sân', 'item' => $item,
            'old' => $old ?? $item, 'errors' => $errors,
            'facilities' => (new CoSo($this->app->pdo()))->options(isset($item['ma_co_so']) ? (int) $item['ma_co_so'] : null),
            'types' => (new San($this->app->pdo()))->types(isset($item['ma_loai_san']) ? (int) $item['ma_loai_san'] : null),
        ], $status);
    }

    public function show(): void
    {
        $item = $this->record($this->id($_GET['id'] ?? null));
        $this->render('admin/san/show', [
            'title' => 'Chi tiết sân', 'item' => $item,
            'related' => (new San($this->app->pdo()))->related((int) $item['ma_san']),
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
            (new SanService($this->app->pdo()))->delete($id);
            $_SESSION['facility_notice'] = 'Đã xóa sân.';
            $this->redirect('/admin/san');
        } catch (HttpException $e) {
            if ($e->status !== 409) {
                throw $e;
            }
            $this->deletion($item, $e->getMessage(), 409);
        }
    }

    private function deletion(array $item, ?string $formError = null, int $status = 200): void
    {
        $this->render('admin/san/delete', [
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
        return $item;
    }

    private function id($value): int
    {
        if (!CoSoService::validId($value)) {
            throw new HttpException(404, 'Không tìm thấy sân.');
        }
        return (int) $value;
    }
}
