<?php

namespace App\Controllers;

use App\Services\CompanyService;
use App\Services\ValidationException;
use Core\Application;
use Core\Controller;
use Core\HttpException;

final class CompanyController extends Controller
{
    private Application $app;
    private CompanyService $companies;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->companies = new CompanyService($app->pdo());
    }

    public function index(): void
    {
        $this->render('admin/companies/index', [
            'title' => 'Quản lý công ty nhượng quyền',
            'companies' => $this->companies->all(),
        ]);
    }

    public function create(): void
    {
        $this->render('admin/companies/create', [
            'title' => 'Thêm công ty nhượng quyền',
        ]);
    }

    public function store(): void
    {
        $data = $this->formData();

        try {
            $this->companies->create($data);
            $this->redirect('/admin/companies');
        } catch (ValidationException $e) {
            $this->render('admin/companies/create', [
                'title' => 'Thêm công ty nhượng quyền',
                'errors' => $e->errors,
                'old' => $data,
            ], 422);
        }
    }

    public function edit(): void
    {
        $id = $this->companyId();
        $company = $this->companies->find($id);
        if ($company === null) {
            throw new HttpException(404, 'Không tìm thấy công ty.');
        }

        $this->render('admin/companies/edit', [
            'title' => 'Cập nhật công ty nhượng quyền',
            'company' => $company,
        ]);
    }

    public function update(): void
    {
        $id = $this->companyId();
        $company = $this->companies->find($id);
        if ($company === null) {
            throw new HttpException(404, 'Không tìm thấy công ty.');
        }
        $data = $this->formData();

        try {
            $this->companies->update($id, $data);
            $this->redirect('/admin/companies');
        } catch (ValidationException $e) {
            $this->render('admin/companies/edit', [
                'title' => 'Cập nhật công ty nhượng quyền',
                'company' => $company,
                'errors' => $e->errors,
                'old' => $data,
            ], 422);
        }
    }

    public function changeStatus(): void
    {
        $id = $this->companyId();
        $company = $this->companies->find($id);
        if ($company === null) {
            throw new HttpException(404, 'Không tìm thấy công ty.');
        }
        $status = $_POST['trang_thai'] ?? '';

        try {
            $this->companies->changeStatus($id, is_string($status) ? $status : '');
            $this->redirect('/admin/companies');
        } catch (ValidationException $e) {
            $this->render('admin/companies/edit', [
                'title' => 'Cập nhật công ty nhượng quyền',
                'company' => $company,
                'errors' => $e->errors,
                'old' => ['trang_thai' => $status],
            ], 422);
        }
    }

    private function formData(): array
    {
        return [
            'ten_cong_ty' => $_POST['ten_cong_ty'] ?? '',
            'ma_so_thue' => $_POST['ma_so_thue'] ?? '',
            'nguoi_dai_dien' => $_POST['nguoi_dai_dien'] ?? '',
            'dia_chi' => $_POST['dia_chi'] ?? '',
            'so_dien_thoai' => $_POST['so_dien_thoai'] ?? '',
            'email' => $_POST['email'] ?? '',
            'ngay_tham_gia' => $_POST['ngay_tham_gia'] ?? '',
            'trang_thai' => $_POST['trang_thai'] ?? '',
        ];
    }

    private function companyId(): int
    {
        $value = $_GET['id'] ?? null;
        if (!is_string($value) || !preg_match('/\A[1-9][0-9]*\z/', $value)) {
            throw new HttpException(404, 'Không tìm thấy công ty.');
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new HttpException(404, 'Không tìm thấy công ty.');
        }

        return $id;
    }
}