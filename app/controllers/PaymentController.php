<?php

namespace App\Controllers;

use App\Models\TaiKhoanNganHang;
use App\Models\ThanhToan;
use App\Services\PaymentService;
use App\Services\ValidationException;
use App\Services\VietQRService;
use Core\Application;
use Core\Controller;
use Core\HttpException;
use DateTimeImmutable;
use DateTimeZone;

final class PaymentController extends Controller
{
    private PaymentService $paymentService;
    private ThanhToan $thanhToanModel;
    private TaiKhoanNganHang $bankModel;
    private VietQRService $vietQrService;

    public function __construct(private Application $app)
    {
        $pdo = $app->pdo();
        $this->thanhToanModel = new ThanhToan($pdo);
        $this->bankModel = new TaiKhoanNganHang($pdo);
        $this->vietQrService = new VietQRService();
        $this->paymentService = new PaymentService($pdo, $this->thanhToanModel, $this->bankModel, $this->vietQrService);
    }

    /**
     * Xác thực người dùng và kiểm tra quyền sở hữu booking (chống IDOR).
     *
     * @param bool $isJsonEndpoint Trả về JSON nếu là API
     * @return array [$user, $payment]
     * @throws HttpException
     */
    private function requireAuthorizedPayment(bool $isJsonEndpoint = false): array
    {
        $user = $this->app->auth()->user();
        if (!$user) {
            if ($isJsonEndpoint) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Vui lòng đăng nhập để thực hiện.']);
                exit;
            }
            header('Location: ' . url('/login'), true, 303);
            exit;
        }

        if (($user['ten_vai_tro'] ?? '') !== 'KHACH_HANG') {
            if ($isJsonEndpoint) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Chức năng chỉ dành cho tài khoản khách hàng.']);
                exit;
            }
            throw new HttpException(403, 'Chức năng chỉ dành cho tài khoản khách hàng.');
        }

        $bookingId = (int) ($_GET['booking_id'] ?? 0);
        $reference = trim((string) ($_GET['ref'] ?? ''));

        $payment = null;
        if ($bookingId > 0) {
            $payment = $this->paymentService->getPaymentByBooking($bookingId);
        } elseif ($reference !== '') {
            $payment = $this->paymentService->getPaymentByReference($reference);
            if ($payment) {
                $bookingId = (int) $payment['ma_dat_san'];
            }
        }

        if (!$payment && $bookingId > 0) {
            // Chỉ tạo yêu cầu thanh toán nếu booking thực sự thuộc khách hàng đang đăng nhập
            $booking = $this->thanhToanModel->findBooking($bookingId);
            if ($booking && (int) ($booking['ma_khach_hang'] ?? 0) === (int) ($user['ma_khach_hang'] ?? 0)) {
                try {
                    $res = $this->paymentService->createPaymentRequest($bookingId, $booking['tong_tien_tam_tinh']);
                    $payment = $this->paymentService->getPayment($res['payment_id']);
                } catch (ValidationException $e) {
                    $firstError = reset($e->errors) ?: 'Không thể tạo yêu cầu thanh toán.';
                    if ($isJsonEndpoint) {
                        http_response_code(400);
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['error' => $firstError]);
                        exit;
                    }
                    throw new HttpException(400, $firstError);
                }
            }
        }

        // Chống IDOR: Nếu không có bản ghi hoặc booking không thuộc về khách hàng này -> trả 404 chống enumeration
        $customerId = (int) ($user['ma_khach_hang'] ?? 0);
        if (!$payment || (int) ($payment['ma_khach_hang'] ?? 0) !== $customerId) {
            if ($isJsonEndpoint) {
                http_response_code(404);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Không tìm thấy thông tin thanh toán.']);
                exit;
            }
            throw new HttpException(404, 'Không tìm thấy thông tin thanh toán.');
        }

        return [$user, $payment];
    }

    /**
     * Hiển thị trang thanh toán VietQR cho khách hàng.
     */
    public function show(): void
    {
        [$user, $payment] = $this->requireAuthorizedPayment(false);

        // Lấy thông tin tài khoản ngân hàng của cơ sở
        $facilityId = (int) $payment['ma_co_so'];
        $bankAccount = $this->bankModel->getDefaultByFacility($facilityId);

        $qrUrl = null;
        if ($bankAccount) {
            $qrUrl = $this->vietQrService->generateQuickLink(
                $bankAccount['ma_bin'],
                $bankAccount['so_tai_khoan'],
                $payment['so_tien'],
                $payment['ma_tham_chieu'],
                $bankAccount['ten_chu_tai_khoan']
            );
        }

        // Tính thời gian hết hạn còn lại
        $expiresAt = $payment['het_han_giu_san_luc'] ?? null;
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
        $remainingSeconds = 0;
        $isExpired = false;

        if ($expiresAt) {
            $expDate = new DateTimeImmutable($expiresAt, $now->getTimezone());
            $diff = $expDate->getTimestamp() - $now->getTimestamp();
            $remainingSeconds = max(0, $diff);
            $isExpired = ($diff <= 0);
        }

        $this->render('payment/checkout', [
            'title' => 'Thanh toán đặt sân qua VietQR',
            'payment' => $payment,
            'bankAccount' => $bankAccount,
            'qrUrl' => $qrUrl,
            'remainingSeconds' => $remainingSeconds,
            'isExpired' => $isExpired,
        ]);
    }

    /**
     * Endpoint API (AJAX Polling) để trình duyệt kiểm tra xem thanh toán đã hoàn thành hay chưa.
     */
    public function status(): void
    {
        [$user, $payment] = $this->requireAuthorizedPayment(true);

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
        $isExpired = false;
        if (!empty($payment['het_han_giu_san_luc'])) {
            $expDate = new DateTimeImmutable($payment['het_han_giu_san_luc'], $now->getTimezone());
            $isExpired = ($now > $expDate);
        }

        $isSuccess = ($payment['trang_thai'] === 'THANH_CONG' || $payment['trang_thai_dat_san'] === 'DA_XAC_NHAN');

        echo json_encode([
            'payment_id' => (int) $payment['ma_thanh_toan'],
            'booking_id' => (int) $payment['ma_dat_san'],
            'reference_code' => $payment['ma_tham_chieu'],
            'payment_status' => $payment['trang_thai'],
            'booking_status' => $payment['trang_thai_dat_san'],
            'is_confirmed' => $isSuccess,
            'is_expired' => $isExpired && !$isSuccess,
        ]);
    }

    /**
     * Endpoint proxy phục vụ ảnh VietQR trực tiếp từ domain nội bộ ('self').
     * Giải quyết triệt để rào cản Content-Security-Policy (img-src 'self').
     */
    public function qrImage(): void
    {
        [$user, $payment] = $this->requireAuthorizedPayment(false);

        $facilityId = (int) $payment['ma_co_so'];
        $bankAccount = $this->bankModel->getDefaultByFacility($facilityId);

        if (!$bankAccount) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            exit('Cơ sở chưa cấu hình tài khoản ngân hàng.');
        }

        $imageData = $this->vietQrService->fetchQrImage(
            $bankAccount['ma_bin'],
            $bankAccount['so_tai_khoan'],
            $payment['so_tien'],
            $payment['ma_tham_chieu'],
            $bankAccount['ten_chu_tai_khoan']
        );

        if ($imageData) {
            $trimmed = ltrim($imageData);
            if (str_starts_with($trimmed, '<svg') || str_starts_with($trimmed, '<?xml')) {
                $mime = 'image/svg+xml';
            } elseif (str_starts_with($imageData, "\x89PNG")) {
                $mime = 'image/png';
            } elseif (str_starts_with($imageData, "\xFF\xD8\xFF")) {
                $mime = 'image/jpeg';
            } else {
                $mime = 'image/svg+xml';
            }

            header('Content-Type: ' . $mime);
            header('Cache-Control: private, max-age=86400');
            header('Content-Length: ' . strlen($imageData));
            echo $imageData;
            exit;
        }

        // Fallback: Chuyển hướng trực tiếp đến ảnh VietQR
        $url = $this->vietQrService->generateQuickLink(
            $bankAccount['ma_bin'],
            $bankAccount['so_tai_khoan'],
            $payment['so_tien'],
            $payment['ma_tham_chieu'],
            $bankAccount['ten_chu_tai_khoan']
        );
        header('Location: ' . $url, true, 302);
        exit;
    }
}
