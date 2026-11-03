<?php

namespace App\Controllers;

use App\Services\ContractService;
use App\Services\AdminListService;
use App\Models\HopDongNhuongQuyen;
use App\Services\ValidationException;
use Core\Application;
use Core\Controller;
use Core\HttpException;

final class ContractController extends Controller
{
    private ContractService $contracts;

    public function __construct(private Application $app)
    {
        $this->contracts = new ContractService($app->pdo());
    }

    public function index(): void
    {
        $filters = AdminListService::managementFilters('/admin/contracts', $_GET);
        $result = (new HopDongNhuongQuyen($this->app->pdo()))->page($filters);
        if ($result['page'] !== $filters['page']) admin_list_redirect('/admin/contracts', array_replace($filters, ['page' => $result['page']]));
        $notice = $_SESSION['contract_notice'] ?? null; unset($_SESSION['contract_notice']);
        $this->render('admin/contracts/index', $result + [
            'title' => 'Quản lý hợp đồng nhượng quyền',
            'contracts' => $result['items'], 'filters' => $filters, 'notice' => $notice, 'companies' => $this->contracts->companies(),
        ]);
    }

    public function create(): void
    {
        $this->render('admin/contracts/create', [
            'title' => 'Thêm hợp đồng nhượng quyền',
            'companies' => $this->contracts->companies(),
        ]);
    }

    public function store(): void
    {
        $data = $this->formData();

        try {
            $this->contracts->create($data);
            $_SESSION['contract_notice'] = 'Đã thêm hợp đồng.';
            admin_list_redirect('/admin/contracts');
        } catch (ValidationException $e) {
            $this->render('admin/contracts/create', [
                'title' => 'Thêm hợp đồng nhượng quyền',
                'errors' => $e->errors,
                'old' => $data,
                'companies' => $this->contracts->companies(),
            ], 422);
        }
    }

    public function edit(): void
    {
        $id = $this->contractId();
        $contract = $this->contracts->find($id);
        if ($contract === null) {
            throw new HttpException(404, 'Không tìm thấy hợp đồng.');
        }

        $this->render('admin/contracts/edit', [
            'title' => 'Cập nhật hợp đồng nhượng quyền',
            'contract' => $contract,
            'companies' => $this->contracts->companies(),
        ]);
    }

    public function update(): void
    {
        $id = $this->contractId();
        $contract = $this->contracts->find($id);
        if ($contract === null) {
            throw new HttpException(404, 'Không tìm thấy hợp đồng.');
        }
        $data = $this->formData();

        try {
            $this->contracts->update($id, $data);
            $_SESSION['contract_notice'] = 'Đã cập nhật hợp đồng.';
            admin_list_redirect('/admin/contracts');
        } catch (ValidationException $e) {
            $this->render('admin/contracts/edit', [
                'title' => 'Cập nhật hợp đồng nhượng quyền',
                'contract' => $contract,
                'companies' => $this->contracts->companies(),
                'errors' => $e->errors,
                'old' => $data,
            ], 422);
        }
    }

    public function changeStatus(): void
    {
        $id = $this->contractId();
        $contract = $this->contracts->find($id);
        if ($contract === null) {
            throw new HttpException(404, 'Không tìm thấy hợp đồng.');
        }
        $status = $_POST['trang_thai'] ?? '';

        try {
            $this->contracts->changeStatus($id, is_string($status) ? $status : '');
            $_SESSION['contract_notice'] = 'Đã cập nhật trạng thái hợp đồng.';
            admin_list_redirect('/admin/contracts');
        } catch (ValidationException $e) {
            $this->render('admin/contracts/edit', [
                'title' => 'Cập nhật hợp đồng nhượng quyền',
                'contract' => $contract,
                'companies' => $this->contracts->companies(),
                'errors' => $e->errors,
                'old' => ['trang_thai' => $status],
            ], 422);
        }
    }

    private function formData(): array
    {
        return [
            'ma_cong_ty' => $_POST['ma_cong_ty'] ?? '',
            'so_hop_dong' => $_POST['so_hop_dong'] ?? '',
            'ngay_ky' => $_POST['ngay_ky'] ?? '',
            'ngay_bat_dau' => $_POST['ngay_bat_dau'] ?? '',
            'ngay_ket_thuc' => $_POST['ngay_ket_thuc'] ?? '',
            'phi_nhuong_quyen' => $_POST['phi_nhuong_quyen'] ?? '',
            'ty_le_phi' => $_POST['ty_le_phi'] ?? '',
            'noi_dung' => $_POST['noi_dung'] ?? '',
            'trang_thai' => $_POST['trang_thai'] ?? '',
        ];
    }

    private function contractId(): int
    {
        $value = $_GET['id'] ?? null;
        if (!is_string($value) || !preg_match('/\A[1-9][0-9]*\z/', $value)) {
            throw new HttpException(404, 'Không tìm thấy hợp đồng.');
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new HttpException(404, 'Không tìm thấy hợp đồng.');
        }

        return $id;
    }
}
