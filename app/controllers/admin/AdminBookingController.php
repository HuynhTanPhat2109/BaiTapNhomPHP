<?php

namespace App\Controllers\admin;

use App\Models\AdminBooking;
use App\Models\CoSo;
use App\Services\BookingManagementService;
use App\Services\BookingReceiptService;
use App\Services\ValidationException;
use Core\Application;
use Core\Controller;
use Core\HttpException;

final class AdminBookingController extends Controller
{
    private AdminBooking $model;
    private BookingManagementService $service;
    private BookingReceiptService $receipts;

    public function __construct(private Application $app, ?BookingReceiptService $receipts = null)
    {
        $pdo = $app->pdo();
        $this->model = new AdminBooking($pdo);
        $this->receipts = $receipts ?? new BookingReceiptService($pdo);
        $this->service = new BookingManagementService($pdo, receipts: $this->receipts);
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
        $receiptDetails = [];
        $payments = $this->model->paymentsFor(array_column($data['items'], 'ma_dat_san'));
        foreach ($data['items'] as $item) {
            $payment = $payments[$item['ma_dat_san']] ?? null;
            $receiptDetails[$item['ma_dat_san']] = ['image' => $payment ? $this->receipts->info($payment) : null, 'can_confirm' => false];
            if ($payment) {
                try { $this->receipts->requireConfirmation($payment + $item); $receiptDetails[$item['ma_dat_san']]['can_confirm'] = true; }
                catch (ValidationException $e) { $receiptDetails[$item['ma_dat_san']]['reason'] = reset($e->errors); }
            }
        }

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
            'receiptDetails' => $receiptDetails,
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
        
        $payment = (new \App\Models\ThanhToan($this->app->pdo()))->findByBookingId($id);
        $receiptInfo = $payment ? $this->receipts->info($payment) : null;
        $receiptImage = $receiptInfo ? url('/booking/receipt?id=' . $id) : null;
        $receiptCanConfirm = false; $receiptReason = 'Khách hàng chưa gửi minh chứng chuyển khoản.';
        if ($payment) {
            try { $this->receipts->requireConfirmation($payment); $receiptCanConfirm = true; }
            catch (ValidationException $e) { $receiptReason = reset($e->errors); }
        }
        $confirmation = null;
        if (!empty($payment['xac_nhan_thu_cong_luc'])) {
            $actor = $this->app->pdo()->prepare('SELECT ten_dang_nhap FROM tai_khoan WHERE ma_tai_khoan=:id');
            $actor->execute(['id' => $payment['ma_tai_khoan_xac_nhan']]);
            $confirmation = ['at' => $payment['xac_nhan_thu_cong_luc'], 'by' => $actor->fetchColumn() ?: ('#' . $payment['ma_tai_khoan_xac_nhan'])];
        }
        $rawPaymentNote = $booking['ghi_chu_thanh_toan'] ?? '';
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
            'receiptInfo' => $receiptInfo, 'receiptCanConfirm' => $receiptCanConfirm, 'receiptReason' => $receiptReason,
            'confirmation' => $confirmation,
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

        try {
            // This POST attests that the operator checked the actual receiving account.
            // Bank IDs/timestamps/amounts or confirmation modes supplied by a client are ignored.
            if (($_POST['confirmation_mode'] ?? '') === 'reconciliation') {
                $value = $_POST['ngay_thanh_toan'] ?? '';
                $format = is_string($value) && strlen($value) === 19 ? 'Y-m-d\TH:i:s' : 'Y-m-d\TH:i';
                $date = is_string($value) ? \DateTimeImmutable::createFromFormat('!' . $format, $value, new \DateTimeZone('Asia/Ho_Chi_Minh')) : false;
                if (!$date || $date->format($format) !== $value || $date->format('Y') < '1000') {
                    throw new ValidationException(['reconciliation' => 'Nhập ngày giờ giao dịch thực tế trong quy trình ngoại lệ.']);
                }
                $version = $_POST['receipt_version'] ?? '';
                if (!is_string($version) || !preg_match('/\A[a-f0-9]{64}\z/', $version)) throw new ValidationException(['receipt' => 'Tải lại minh chứng trước khi đối soát.']);
                $result = $this->service->confirmReconciledPayment($id, post_string('ma_giao_dich'), post_string('so_tien_thuc_nhan'),
                    $value, post_string('ghi_chu'), $user, $version);
            } else {
                $version = $_POST['receipt_version'] ?? '';
                if (!is_string($version) || !preg_match('/\A[a-f0-9]{64}\z/', $version)) throw new ValidationException(['receipt' => 'Tải lại minh chứng để kiểm tra trước khi xác nhận.']);
                $result = $this->service->confirmReceiptPayment($id, $user, $version);
            }

            $_SESSION['booking_notice'] = $result['message'];
        } catch (ValidationException $e) {
            $_SESSION['booking_validation_errors'] = $e->errors;
            $_SESSION['booking_old'] = $_POST;
        } catch (\Throwable $e) {
            error_log('Booking confirmation failed: ' . get_class($e) . ' at ' . basename($e->getFile()) . ':' . $e->getLine());
            $_SESSION['booking_error'] = 'Không thể hoàn tất xác nhận. Vui lòng tải lại và kiểm tra trạng thái trước khi thử lại.';
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
