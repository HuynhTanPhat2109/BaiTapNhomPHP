<?php

namespace App\Controllers;

use App\Models\HoaDon;
use App\Services\InvoiceService;
use Core\Application;
use Core\Controller;
use Core\HttpException;

final class InvoiceController extends Controller
{
    private HoaDon $model;
    private InvoiceService $service;

    public function __construct(private Application $app)
    {
        $pdo = $app->pdo();
        $this->model = new HoaDon($pdo);
        $this->service = new InvoiceService($pdo, $this->model);
    }

    /**
     * Xác thực tài khoản khách hàng.
     */
    private function requireCustomer(): array
    {
        $user = $this->app->auth()->user();
        if (!$user) {
            header('Location: ' . url('/login'), true, 303);
            exit;
        }

        if (($user['ten_vai_tro'] ?? '') !== 'KHACH_HANG' || empty($user['ma_khach_hang'])) {
            throw new HttpException(403, 'Chức năng chỉ dành cho tài khoản khách hàng.');
        }

        return $user;
    }

    /**
     * Danh sách hóa đơn của khách hàng đang đăng nhập.
     */
    public function index(): void
    {
        $user = $this->requireCustomer();
        $customerId = (int) $user['ma_khach_hang'];

        $data = $this->service->listCustomerInvoices($customerId, $_GET);

        $this->render('invoices/index', [
            'title' => 'Lịch sử hóa đơn',
            'user' => $user,
            'items' => $data['items'],
            'total' => $data['total'],
            'page' => $data['page'],
            'pages' => $data['pages'],
            'per_page' => $data['per_page'],
            'filters' => [
                'q' => trim((string)($_GET['q'] ?? '')),
                'date_from' => trim((string)($_GET['date_from'] ?? '')),
                'date_to' => trim((string)($_GET['date_to'] ?? '')),
            ],
        ]);
    }

    /**
     * Chi tiết hóa đơn và giao diện in ấn / lưu biên lai cho khách hàng.
     */
    public function show(): void
    {
        $user = $this->requireCustomer();
        $customerId = (int) $user['ma_khach_hang'];

        $id = (int) ($_GET['id'] ?? 0);
        $bookingId = (int) ($_GET['booking_id'] ?? 0);

        if ($id <= 0 && $bookingId <= 0) {
            throw new HttpException(404, 'Mã hóa đơn không hợp lệ.');
        }

        $data = null;
        if ($id > 0) {
            $data = $this->service->getInvoice($id, null, $customerId);
        } elseif ($bookingId > 0) {
            $data = $this->service->getInvoiceByBooking($bookingId, null, $customerId);
        }

        if (!$data) {
            throw new HttpException(404, 'Không tìm thấy hóa đơn hoặc bạn không có quyền xem hóa đơn này.');
        }

        $this->render('invoices/show', [
            'title' => "Hóa đơn #{$data['invoice']['so_hoa_don']}",
            'user' => $user,
            'invoice' => $data['invoice'],
            'facility' => $data['facility'],
            'courts' => $data['courts'],
            'services' => $data['services'],
            'receiptImage' => $data['receipt_image'] ?? null,
        ]);
    }

    /**
     * Màn hình in hóa đơn độc lập cho khách hàng.
     */
    public function print(): void
    {
        $user = $this->requireCustomer();
        $customerId = (int) $user['ma_khach_hang'];

        $id = (int) ($_GET['id'] ?? 0);
        $bookingId = (int) ($_GET['booking_id'] ?? 0);

        if ($id <= 0 && $bookingId <= 0) {
            throw new HttpException(404, 'Mã hóa đơn không hợp lệ.');
        }

        $data = null;
        if ($id > 0) {
            $data = $this->service->getInvoice($id, null, $customerId);
        } elseif ($bookingId > 0) {
            $data = $this->service->getInvoiceByBooking($bookingId, null, $customerId);
        }

        if (!$data) {
            throw new HttpException(404, 'Không tìm thấy hóa đơn.');
        }

        $invoice = $data['invoice'];
        $facility = $data['facility'];
        $courts = $data['courts'];
        $services = $data['services'];

        require dirname(__DIR__) . '/views/admin/invoices/print.php';
    }
}
