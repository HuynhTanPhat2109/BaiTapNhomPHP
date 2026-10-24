<?php

namespace App\Controllers\admin;

use App\Models\DichVu;
use App\Services\{CoSoService, DichVuService, ValidationException};
use Core\{Application, Controller, HttpException};

final class DichVuController extends Controller
{
    public function __construct(private Application $app) {}

    private function context(): array
    {
        $user = $this->app->auth()->user();
        if (!$user) throw new HttpException(403, 'Vui lòng đăng nhập bằng tài khoản nội bộ hợp lệ.');
        return [$user, new DichVuService($this->app->pdo(), $user), new DichVu($this->app->pdo())];
    }

    private static function id($value): int
    {
        if (!CoSoService::validId($value)) throw new HttpException(404, 'Không tìm thấy dịch vụ.');
        return (int) $value;
    }

    private function page(string $page, array $user, DichVuService $service, array $data, int $status = 200): void
    {
        $this->render($user['ten_vai_tro'] === 'QUAN_TRI_TAP_DOAN' ? 'admin/dich-vu/' . $page : 'services/page', $data + [
            'title' => 'Quản lý dịch vụ', 'user' => $user, 'canWrite' => $service->scope['write'],
            'listPath' => DichVuService::basePath($user['ten_vai_tro']), 'servicePage' => $page,
        ], $status);
    }

    public function index(): void
    {
        [$user, $service, $model] = $this->context();
        $filters = $service->filters($_GET);
        $notice = $_SESSION['service_notice'] ?? null; unset($_SESSION['service_notice']);
        $this->page('index', $user, $service, $model->page($filters, $service->scope) + [
            'filters' => $filters, 'facilities' => $model->facilities($service->scope),
            'units' => $model->units($service->scope), 'notice' => $notice,
        ]);
    }

    public function create(): void
    {
        [$user, $service, $model] = $this->context(); $service->requireWrite();
        $this->form($user, $service, $model, [], [], []);
    }

    public function edit(): void
    {
        [$user, $service, $model] = $this->context(); $service->requireWrite();
        $item = $service->find(self::id($_GET['id'] ?? null));
        $this->form($user, $service, $model, $item, [], []);
    }

    public function store(): void { $this->persist(false); }
    public function update(): void { $this->persist(true); }

    private function persist(bool $editing): void
    {
        [$user, $service, $model] = $this->context(); $service->requireWrite();
        $item = $editing ? $service->find(self::id($_POST['id'] ?? null)) : [];
        $old = [];
        foreach (['ma_co_so', 'ten_dich_vu', 'loai_dich_vu', 'don_vi_tinh', 'don_gia', 'so_luong_ton', 'theo_doi_ton', 'mo_ta', 'trang_thai', 'version'] as $field) {
            $value = $_POST[$field] ?? ''; $old[$field] = is_string($value) ? mb_strcut($value, 0, 1200, 'UTF-8') : '';
        }
        try {
            $service->save($_POST, $editing ? (int) $item['ma_dich_vu'] : null);
            $_SESSION['service_notice'] = 'Đã lưu dịch vụ.';
            $this->redirect(DichVuService::basePath($user['ten_vai_tro']));
        } catch (ValidationException $e) {
            $this->form($user, $service, $model, $item, $old, $e->errors, 422);
        } catch (HttpException $e) {
            if ($e->status !== 409) throw $e;
            $this->form($user, $service, $model, $item, $old, [], 409, $e->getMessage());
        }
    }

    private function form(array $user, DichVuService $service, DichVu $model, array $item, array $old, array $errors, int $status = 200, ?string $formError = null): void
    {
        if (!$old && $item) $old = $item + ['theo_doi_ton' => $item['so_luong_ton'] === null ? '0' : '1', 'version' => DichVuService::version($item)];
        $this->page('form', $user, $service, [
            'title' => ($item ? 'Sửa' : 'Thêm') . ' dịch vụ', 'item' => $item, 'old' => $old, 'errors' => $errors, 'formError' => $formError,
            'facilities' => $model->facilities($service->scope, true, isset($item['ma_co_so']) ? (int) $item['ma_co_so'] : null),
        ], $status);
    }

    public function show(): void
    {
        [$user, $service, $model] = $this->context();
        $item = $service->find(self::id($_GET['id'] ?? null));
        $this->details($user, $service, $model, $item, [], ($_GET['confirm'] ?? '') === '1');
    }

    public function changeStatus(): void
    {
        [$user, $service, $model] = $this->context(); $service->requireWrite();
        $item = $service->find(self::id($_POST['id'] ?? null));
        try {
            $service->changeStatus((int) $item['ma_dich_vu'], $_POST['trang_thai'] ?? null, $_POST['version'] ?? null, ($_POST['confirmed'] ?? null) === '1');
            $_SESSION['service_notice'] = 'Đã cập nhật trạng thái dịch vụ.';
            $this->redirect(DichVuService::basePath($user['ten_vai_tro']));
        } catch (ValidationException $e) {
            $this->details($user, $service, $model, $item, $e->errors, true, 422);
        } catch (HttpException $e) {
            if ($e->status !== 409) throw $e;
            $this->details($user, $service, $model, $service->find((int) $item['ma_dich_vu']), ['conflict' => $e->getMessage()], false, 409);
        }
    }

    private function details(array $user, DichVuService $service, DichVu $model, array $item, array $errors, bool $confirm, int $status = 200): void
    {
        $this->page('show', $user, $service, ['title' => 'Chi tiết dịch vụ', 'item' => $item, 'errors' => $errors,
            'confirm' => $confirm, 'related' => $model->references((int) $item['ma_dich_vu'])], $status);
    }
}
