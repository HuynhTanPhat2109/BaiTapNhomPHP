<?php

namespace App\Controllers\admin;

use App\Models\HoaDon;
use App\Services\BookingManagementService;
use App\Services\InvoiceService;
use Core\Application;
use Core\Controller;
use Core\HttpException;

final class AdminInvoiceController extends Controller
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
     * Danh sách hóa đơn có bộ lọc đa tiêu chí, phân quyền phạm vi và phân trang.
     */
    public function index(): void
    {
        $user = $this->app->auth()->user();
        if (!$user) {
            throw new HttpException(403, 'Vui lòng đăng nhập để truy cập.');
        }

        $scope = BookingManagementService::resolveScope($user);
        $data = $this->service->listInvoices($_GET, $scope);
        $facilities = $this->model->facilities($scope);

        $notice = $_SESSION['invoice_notice'] ?? null;
        $error = $_SESSION['invoice_error'] ?? null;
        unset($_SESSION['invoice_notice'], $_SESSION['invoice_error']);

        $this->render('admin/invoices/index', [
            'title' => 'Quản lý hóa đơn',
            'user' => $user,
            'scope' => $scope,
            'filters' => $data['filters'],
            'facilities' => $facilities,
            'items' => $data['items'],
            'total' => $data['total'],
            'page' => $data['page'],
            'pages' => $data['pages'],
            'per_page' => $data['per_page'],
            'notice' => $notice,
            'error' => $error,
        ]);
    }

    /**
     * Chi tiết hóa đơn và giao diện in ấn hóa đơn thanh toán.
     */
    public function show(): void
    {
        $user = $this->app->auth()->user();
        if (!$user) {
            throw new HttpException(403, 'Vui lòng đăng nhập để truy cập.');
        }

        $scope = BookingManagementService::resolveScope($user);

        $id = (int) ($_GET['id'] ?? 0);
        $bookingId = (int) ($_GET['booking_id'] ?? 0);

        if ($id <= 0 && $bookingId <= 0) {
            throw new HttpException(404, 'Mã hóa đơn hoặc mã đặt sân không hợp lệ.');
        }

        $data = null;
        if ($id > 0) {
            $data = $this->service->getInvoice($id, $scope, null);
        } elseif ($bookingId > 0) {
            $data = $this->service->getInvoiceByBooking($bookingId, $scope, null);
        }

        if (!$data) {
            throw new HttpException(404, 'Không tìm thấy hóa đơn hoặc bạn không có quyền xem hóa đơn này.');
        }

        $invoice = $data['invoice'];
        $facility = $data['facility'];
        $courts = $data['courts'];
        $services = $data['services'];
        $receiptImage = $data['receipt_image'] ?? null;

        $notice = $_SESSION['invoice_notice'] ?? null;
        $error = $_SESSION['invoice_error'] ?? null;
        unset($_SESSION['invoice_notice'], $_SESSION['invoice_error']);

        $this->render('admin/invoices/show', [
            'title' => "Hóa đơn #{$invoice['so_hoa_don']}",
            'user' => $user,
            'scope' => $scope,
            'invoice' => $invoice,
            'facility' => $facility,
            'courts' => $courts,
            'services' => $services,
            'receiptImage' => $receiptImage,
            'notice' => $notice,
            'error' => $error,
        ]);
    }

    /**
     * Màn hình in hóa đơn độc lập (chuyên dụng cho in nhiệt POS hoặc in A4).
     */
    public function print(): void
    {
        $user = $this->app->auth()->user();
        if (!$user) {
            throw new HttpException(403, 'Vui lòng đăng nhập để truy cập.');
        }

        $scope = BookingManagementService::resolveScope($user);

        $id = (int) ($_GET['id'] ?? 0);
        $bookingId = (int) ($_GET['booking_id'] ?? 0);

        if ($id <= 0 && $bookingId <= 0) {
            throw new HttpException(404, 'Mã hóa đơn hoặc mã đặt sân không hợp lệ.');
        }

        $data = null;
        if ($id > 0) {
            $data = $this->service->getInvoice($id, $scope, null);
        } elseif ($bookingId > 0) {
            $data = $this->service->getInvoiceByBooking($bookingId, $scope, null);
        }

        if (!$data) {
            throw new HttpException(404, 'Không tìm thấy hóa đơn.');
        }

        $invoice = $data['invoice'];
        $facility = $data['facility'];
        $courts = $data['courts'];
        $services = $data['services'];

        require dirname(__DIR__, 2) . '/views/admin/invoices/print.php';
    }
}
