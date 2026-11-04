<?php

namespace App\Controllers\admin;

use App\Models\AdminBooking;
use App\Models\CoSo;
use App\Services\BookingManagementService;
use App\Services\ValidationException;
use Core\Application;
use Core\Controller;
use Core\HttpException;

final class AdminBookingController extends Controller
{
    private AdminBooking $model;
    private BookingManagementService $service;

    public function __construct(private Application $app)
    {
        $pdo = $app->pdo();
        $this->model = new AdminBooking($pdo);
        $this->service = new BookingManagementService($pdo);
    }

    /**
     * Danh sách booking có lọc đa tiêu chí, phân quyền và phân trang.
     */
    public function index(): void
    {
        $user = $this->app->auth()->user();
        if (!$user) {
            throw new HttpException(403, 'Vui lòng đăng nhập để truy cập.');
        }

        $scope = BookingManagementService::resolveScope($user);
        $filters = $this->service->validateFilters($_GET, $scope);

        $facilities = $this->model->facilities($scope);
        if (!empty($filters['facility_id']) && $scope['company'] !== null) {
            $facility = (new CoSo($this->app->pdo()))->find((int) $filters['facility_id']);
            if (!$facility || (int) $facility['ma_cong_ty'] !== $scope['company']) {
                throw new HttpException(403, 'Cơ sở nằm ngoài phạm vi công ty của bạn.');
            }
        }
        $courts = !empty($filters['facility_id']) ? $this->model->courtsByFacility((int) $filters['facility_id'], $scope) : [];

        $data = $this->model->page($filters, $scope);

        $notice = $_SESSION['booking_notice'] ?? null;
        $error = $_SESSION['booking_error'] ?? null;
        $validationErrors = $_SESSION['booking_validation_errors'] ?? [];
        $oldInput = $_SESSION['booking_old'] ?? [];
        unset($_SESSION['booking_validation_errors'], $_SESSION['booking_old']);
        unset($_SESSION['booking_notice'], $_SESSION['booking_error']);

        $this->render('admin/bookings/index', [
            'title' => 'Quản lý đơn đặt sân',
            'user' => $user,
            'scope' => $scope,
            'filters' => $filters,
            'facilities' => $facilities,
            'courts' => $courts,
            'items' => $data['items'],
            'total' => $data['total'],
            'page' => $data['page'],
            'pages' => $data['pages'],
            'per_page' => $data['per_page'],
            'notice' => $notice,
            'error' => $error,
            'validationErrors' => $validationErrors,
            'oldInput' => $oldInput,
        ]);
    }

    /**
     * Chi tiết một booking và quy trình đối soát / xác nhận thủ công.
     */
    public function show(): void
    {
        $user = $this->app->auth()->user();
        if (!$user) {
            throw new HttpException(403, 'Vui lòng đăng nhập để truy cập.');
        }

        $scope = BookingManagementService::resolveScope($user);
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            throw new HttpException(404, 'Mã đặt sân không hợp lệ.');
        }

        $booking = $this->model->find($id, $scope);
        if (!$booking) {
            throw new HttpException(404, 'Không tìm thấy đơn đặt sân hoặc bạn không có quyền xem đơn này.');
        }

        $courts = $this->model->courts($id);
        $services = $this->model->services($id);
        $bankAccount = $this->model->bankAccount((int) $booking['ma_co_so']);
        
        $rawPaymentNote = $booking['ghi_chu_thanh_toan'] ?? '';
        $receiptImage = \App\Services\ReceiptImage::findForBooking((int) $id, $rawPaymentNote);
        $booking['ghi_chu_thanh_toan'] = \App\Services\ReceiptImage::stripReceiptTag($rawPaymentNote);

        $notice = $_SESSION['booking_notice'] ?? null;
        $error = $_SESSION['booking_error'] ?? null;
        $validationErrors = $_SESSION['booking_validation_errors'] ?? [];
        $oldInput = $_SESSION['booking_old'] ?? [];
        unset($_SESSION['booking_notice'], $_SESSION['booking_error'], $_SESSION['booking_validation_errors'], $_SESSION['booking_old']);

        $this->render('admin/bookings/show', [
            'title' => "Chi tiết đơn đặt sân #$id",
            'user' => $user,
            'scope' => $scope,
            'booking' => $booking,
            'courts' => $courts,
            'services' => $services,
            'bankAccount' => $bankAccount,
            'receiptImage' => $receiptImage,
            'notice' => $notice,
            'error' => $error,
            'validationErrors' => $validationErrors,
            'oldInput' => $oldInput,
            'now' => BookingManagementService::now(),
        ]);
    }

    /**
     * Thao tác xác nhận thanh toán và booking thủ công (POST).
     */
    public function confirm(): void
    {
        $user = $this->app->auth()->user();
        if (!$user) {
            throw new HttpException(403, 'Vui lòng đăng nhập.');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new HttpException(404, 'Mã đặt sân không hợp lệ.');
        }

        $bankTxnId = trim((string) ($_POST['ma_giao_dich'] ?? ''));
        $actualAmount = trim((string) ($_POST['so_tien_thuc_nhan'] ?? ''));
        $paidAt = trim((string) ($_POST['ngay_thanh_toan'] ?? ''));
        $note = trim((string) ($_POST['ghi_chu'] ?? ''));

        try {
            $result = $this->service->confirmBookingAndPayment(
                $id,
                $bankTxnId,
                $actualAmount,
                $paidAt,
                $note,
                $user
            );

            $_SESSION['booking_notice'] = $result['message'];
        } catch (ValidationException $e) {
            $_SESSION['booking_validation_errors'] = $e->errors;
            $_SESSION['booking_old'] = $_POST;
        } catch (\Throwable $e) {
            $_SESSION['booking_error'] = 'Lỗi xử lý xác nhận: ' . $e->getMessage();
        }

        $this->redirectAfterAction($id, $user);
    }

    /**
     * Thao tác đánh dấu chuyển sang Cần đối soát (CAN_DOI_SOAT) khi có bất thường (POST).
     */
    public function reconcile(): void
    {
        $user = $this->app->auth()->user();
        if (!$user) {
            throw new HttpException(403, 'Vui lòng đăng nhập.');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new HttpException(404, 'Mã đặt sân không hợp lệ.');
        }

        $actualAmount = trim((string) ($_POST['so_tien_thuc_nhan'] ?? '0'));
        if ($actualAmount === '') $actualAmount = '0';
        $bankTxnId = trim((string) ($_POST['ma_giao_dich'] ?? ''));
        $reason = trim((string) ($_POST['ly_do_doi_soat'] ?? ''));

        try {
            $result = $this->service->markNeedsReconciliation(
                $id,
                $actualAmount,
                $bankTxnId,
                $reason,
                $user
            );

            $_SESSION['booking_notice'] = $result['message'];
        } catch (ValidationException $e) {
            $_SESSION['booking_validation_errors'] = $e->errors;
            $_SESSION['booking_old'] = $_POST;
        } catch (\Throwable $e) {
            $_SESSION['booking_error'] = 'Lỗi xử lý đối soát: ' . $e->getMessage();
        }

        $this->redirectAfterAction($id, $user);
    }

    /**
     * Thao tác hủy đơn đặt sân bởi quản trị viên / nhân viên (POST).
     * Giải phóng khung giờ đặt sân về trạng thái TRỐNG.
     */
    public function cancel(): void
    {
        $user = $this->app->auth()->user();
        if (!$user) {
            throw new HttpException(403, 'Vui lòng đăng nhập.');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new HttpException(404, 'Mã đặt sân không hợp lệ.');
        }

        $reason = trim((string) ($_POST['ly_do_huy'] ?? ''));

        try {
            $result = $this->service->cancelBookingByAdmin(
                $id,
                $reason,
                $user
            );

            $_SESSION['booking_notice'] = $result['message'];
        } catch (ValidationException $e) {
            $_SESSION['booking_validation_errors'] = $e->errors;
            $_SESSION['booking_old'] = $_POST;
        } catch (\Throwable $e) {
            $_SESSION['booking_error'] = 'Lỗi xử lý hủy đơn đặt sân: ' . $e->getMessage();
        }

        $this->redirectAfterAction($id, $user);
    }

    private function redirectAfterAction(int $id, array $user): void
    {
        if (($_POST['from_list'] ?? '') === '1') {
            $query = is_array($_POST['list'] ?? null) ? $_POST['list'] : [];
            $filters = $this->service->validateFilters($query, BookingManagementService::resolveScope($user));
            $this->redirect('/admin/bookings?' . http_build_query($filters));
        }
        $this->redirect('/admin/bookings/show?id=' . $id);
    }
}
