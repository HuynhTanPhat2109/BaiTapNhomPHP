<?php

namespace App\Controllers;

use App\Models\TaiKhoanNganHang;
use App\Models\ThanhToan;
use App\Services\PaymentService;
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

        header('Location: ' . url('/booking/wait') . '&id=' . (int)$payment['ma_dat_san'], true, 303);
    }

    /**
     * Endpoint API (AJAX Polling) để trình duyệt kiểm tra xem thanh toán đã hoàn thành hay chưa.
     */
    public function status(): void
    {
        [$user, $payment] = $this->requireAuthorizedPayment(true);

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        // Same read-only effective state as history/wait. Receipt review has no six-minute expiry.
        $booking = (new \App\Services\BookingService($this->app->pdo()))->inspect(
            (int)$payment['ma_dat_san'], (int)$user['ma_khach_hang']
        );
        $isExpired = $booking['trang_thai_dat_san'] === 'HET_HAN';
        $isSuccess = in_array($booking['trang_thai_dat_san'], ['DA_XAC_NHAN', 'HOAN_THANH'], true)
            && $payment['trang_thai'] === 'THANH_CONG';

        echo json_encode([
            'payment_id' => (int) $payment['ma_thanh_toan'],
            'booking_id' => (int) $payment['ma_dat_san'],
            'reference_code' => $payment['ma_tham_chieu'],
            'payment_status' => $payment['trang_thai'],
            'booking_status' => $booking['trang_thai_dat_san'],
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

        if ($payment['trang_thai_dat_san'] !== 'CHO_THANH_TOAN'
            || $payment['trang_thai'] !== 'CHO_THANH_TOAN'
            || empty($payment['het_han_giu_san_luc'])
            || new DateTimeImmutable($payment['het_han_giu_san_luc'], new DateTimeZone('Asia/Ho_Chi_Minh')) <= PaymentService::now()) {
            throw new HttpException(409, 'Yêu cầu thanh toán không còn hiệu lực.');
        }

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

        // Downloads stay same-origin and never fall back to an HTML/external redirect.
        if (($_GET['download'] ?? '') === '1') {
            $info = $imageData ? @getimagesizefromstring($imageData) : false;
            $extensions = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','image/svg+xml'=>'svg'];
            $mime = $info['mime'] ?? '';
            // VietQR QuickLink can return SVG even when its URL ends in .png.
            if (!$info && $imageData && !preg_match('/<!DOCTYPE|<!ENTITY/i', $imageData)) {
                $previous = libxml_use_internal_errors(true);
                try {
                    $svg = new \DOMDocument();
                    if ($svg->loadXML($imageData, LIBXML_NONET)
                        && $svg->documentElement?->localName === 'svg'
                        && $svg->documentElement->namespaceURI === 'http://www.w3.org/2000/svg') {
                        $mime = 'image/svg+xml';
                    }
                } finally {
                    libxml_clear_errors();
                    libxml_use_internal_errors($previous);
                }
            }
            if (!isset($extensions[$mime])) {
                http_response_code(503);
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-store');
                echo json_encode(['error'=>'Chưa tải được ảnh VietQR hợp lệ. Vui lòng thử lại.'], JSON_UNESCAPED_UNICODE);
                return;
            }
            header('Content-Type: '.$mime);
            header('Content-Disposition: attachment; filename="VietQR_DonHang_'.(int)$payment['ma_dat_san'].'.'.$extensions[$mime].'"');
            header('Cache-Control: private, no-store');
            header('X-Content-Type-Options: nosniff');
            header('Content-Length: '.strlen($imageData));
            echo $imageData;
            return;
        }

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
