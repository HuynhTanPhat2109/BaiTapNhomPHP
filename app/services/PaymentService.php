<?php

namespace App\Services;

use App\Models\TaiKhoanNganHang;
use App\Models\ThanhToan;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class PaymentService
{
    private PDO $pdo;
    private ThanhToan $thanhToanModel;
    private TaiKhoanNganHang $bankModel;
    private VietQRService $vietQrService;

    public function __construct(
        PDO $pdo,
        ?ThanhToan $thanhToanModel = null,
        ?TaiKhoanNganHang $bankModel = null,
        ?VietQRService $vietQrService = null
    ) {
        $this->pdo = $pdo;
        $this->thanhToanModel = $thanhToanModel ?? new ThanhToan($pdo);
        $this->bankModel = $bankModel ?? new TaiKhoanNganHang($pdo);
        $this->vietQrService = $vietQrService ?? new VietQRService();
    }

    /**
     * Lấy thời điểm hiện tại theo múi giờ hệ thống quy định (Asia/Ho_Chi_Minh).
     */
    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
    }

    /**
     * Tạo yêu cầu thanh toán cho đơn đặt sân và tạo link mã VietQR theo tài khoản ngân hàng của cơ sở.
     *
     * @param int $bookingId ID đơn đặt sân (ma_dat_san)
     * @param float|string|int $amount Số tiền cần thanh toán
     * @param array $options Tùy chọn mở rộng (ghi chú, v.v.)
     * @return array Cấu trúc dữ liệu thanh toán và mã QR VietQR trả về cho Checkout:
     *               [
     *                   'success' => bool,
     *                   'payment_id' => int,
     *                   'booking_id' => int,
     *                   'facility_id' => int,
     *                   'reference_code' => string,
     *                   'amount' => string,
     *                   'method' => 'CHUYEN_KHOAN',
     *                   'status' => 'CHO_THANH_TOAN',
     *                   'created_at' => string,
     *                   'expires_at' => ?string,
     *                   'bank' => array|null,
     *                   'qr_url' => string|null,
     *                   'note' => ?string,
     *               ]
     * @throws ValidationException
     */
    public function createPaymentRequest(int $bookingId, float|string|int $amount, array $options = []): array
    {
        // 1. Kiểm tra tham số cơ bản
        $errors = [];
        if ($bookingId <= 0) {
            $errors['booking_id'] = 'Mã đặt sân không hợp lệ.';
        }

        if (!is_numeric($amount) || (float) $amount < 0) {
            $errors['amount'] = 'Số tiền thanh toán không hợp lệ.';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        $formattedAmount = number_format((float) $amount, 2, '.', '');
        $note = isset($options['note']) && is_string($options['note']) ? trim($options['note']) : null;

        // 2. Thực thi trong Transaction đảm bảo tính nhất quán
        $inTx = $this->pdo->inTransaction();
        if (!$inTx) {
            $this->pdo->beginTransaction();
        }

        try {
            // Khóa dòng booking để kiểm tra đồng thời
            $booking = $this->thanhToanModel->findBooking($bookingId, true);
            if (!$booking) {
                throw new ValidationException(['booking_id' => 'Không tìm thấy thông tin đặt sân.']);
            }

            // 3. Kiểm tra trạng thái booking
            $status = $booking['trang_thai_dat_san'];
            if ($status === 'DA_XAC_NHAN') {
                throw new ValidationException(['booking' => 'Đơn đặt sân đã được xác nhận trước đó.']);
            }
            if ($status === 'HOAN_THANH') {
                throw new ValidationException(['booking' => 'Đơn đặt sân đã hoàn thành.']);
            }
            if ($status === 'DA_HUY') {
                throw new ValidationException(['booking' => 'Đơn đặt sân đã bị hủy.']);
            }
            if ($status === 'HET_HAN') {
                throw new ValidationException(['booking' => 'Đơn đặt sân đã hết hạn giữ chỗ.']);
            }
            if (!in_array($status, ['CHO_THANH_TOAN', 'CHO_XAC_NHAN'], true)) {
                throw new ValidationException(['booking' => 'Trạng thái đặt sân không hợp lệ để tạo yêu cầu thanh toán.']);
            }

            // 4. Kiểm tra thời hạn giữ sân (Asia/Ho_Chi_Minh)
            $now = self::now();
            if (!empty($booking['het_han_giu_san_luc'])) {
                $expiresAt = new DateTimeImmutable($booking['het_han_giu_san_luc'], $now->getTimezone());
                if ($now > $expiresAt) {
                    throw new ValidationException(['booking' => 'Thời gian giữ sân đã hết hạn. Vui lòng đặt lại.']);
                }
            }

            // 5. Kiểm tra số tiền khớp với tổng tiền tạm tính của booking
            $bookingAmount = number_format((float) $booking['tong_tien_tam_tinh'], 2, '.', '');
            if ($formattedAmount !== $bookingAmount) {
                throw new ValidationException([
                    'amount' => "Số tiền yêu cầu ($formattedAmount) không khớp với tổng tiền đặt sân ($bookingAmount)."
                ]);
            }

            // 6. Lấy tài khoản ngân hàng mặc định của cơ sở được đặt sân
            $facilityId = (int) $booking['ma_co_so'];
            $bankAccount = $this->bankModel->getDefaultByFacility($facilityId);

            // 7. Kiểm tra giao dịch thanh toán hiện có (idempotency)
            $existing = $this->thanhToanModel->findByBookingId($bookingId, true);

            if ($existing) {
                if ($existing['trang_thai'] === 'THANH_CONG') {
                    throw new ValidationException(['payment' => 'Đơn đặt sân này đã được thanh toán thành công.']);
                }

                $referenceCode = $existing['ma_tham_chieu'];
                $paymentId = (int) $existing['ma_thanh_toan'];

                if ($existing['trang_thai'] !== 'CHO_THANH_TOAN') {
                    // Nếu trước đó bị HUY hoặc HET_HAN nhưng booking được kích hoạt lại
                    $referenceCode = $this->generateReferenceCode($bookingId);
                    $this->thanhToanModel->refreshPaymentRequest($paymentId, $referenceCode, $formattedAmount, $note);
                }
            } else {
                // Tạo mới bản ghi thanh toán
                $referenceCode = $this->generateReferenceCode($bookingId);
                $createdAt = $now->format('Y-m-d H:i:s');

                $paymentId = $this->thanhToanModel->create([
                    'ma_dat_san' => $bookingId,
                    'ma_tham_chieu' => $referenceCode,
                    'so_tien' => $formattedAmount,
                    'phuong_thuc' => 'CHUYEN_KHOAN',
                    'trang_thai' => 'CHO_THANH_TOAN',
                    'ngay_tao' => $createdAt,
                    'ghi_chu' => $note,
                ]);
            }

            // 8. Tạo mã VietQR nếu cơ sở đã cấu hình tài khoản ngân hàng
            $qrUrl = null;
            $bankData = null;

            if ($bankAccount) {
                $bankData = [
                    'ten_ngan_hang' => $bankAccount['ten_ngan_hang'],
                    'bank_name' => $bankAccount['ten_ngan_hang'],
                    'ma_bin' => $bankAccount['ma_bin'],
                    'bin' => $bankAccount['ma_bin'],
                    'so_tai_khoan' => $bankAccount['so_tai_khoan'],
                    'account_number' => $bankAccount['so_tai_khoan'],
                    'ten_chu_tai_khoan' => $bankAccount['ten_chu_tai_khoan'],
                    'account_name' => $bankAccount['ten_chu_tai_khoan'],
                ];

                $qrUrl = $this->vietQrService->generateQuickLink(
                    $bankAccount['ma_bin'],
                    $bankAccount['so_tai_khoan'],
                    $formattedAmount,
                    $referenceCode,
                    $bankAccount['ten_chu_tai_khoan']
                );
            }

            if (!$inTx) {
                $this->pdo->commit();
            }

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'booking_id' => $bookingId,
                'facility_id' => $facilityId,
                'reference_code' => $referenceCode,
                'amount' => $formattedAmount,
                'method' => 'CHUYEN_KHOAN',
                'status' => 'CHO_THANH_TOAN',
                'created_at' => $now->format('Y-m-d H:i:s'),
                'expires_at' => $booking['het_han_giu_san_luc'],
                'bank' => $bankData,
                'qr_url' => $qrUrl,
                'note' => $note,
            ];
        } catch (\Throwable $e) {
            if (!$inTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Tra cứu thông tin thanh toán theo ID thanh toán.
     */
    public function getPayment(int $paymentId): ?array
    {
        return $this->thanhToanModel->find($paymentId);
    }

    /**
     * Tra cứu thông tin thanh toán theo mã đặt sân.
     */
    public function getPaymentByBooking(int $bookingId): ?array
    {
        return $this->thanhToanModel->findByBookingId($bookingId);
    }

    /**
     * Tra cứu thông tin thanh toán theo mã tham chiếu.
     */
    public function getPaymentByReference(string $reference): ?array
    {
        return $this->thanhToanModel->findByReference($reference);
    }

    /**
     * Sinh mã tham chiếu duy nhất cho đơn đặt sân:
     * Định dạng chuẩn gọn gàng, tối ưu cho nội dung chuyển khoản ngân hàng:
     * PAY + [NămThángNgày: 6 số] + B[Mã Booking: 4 số] + [Chuỗi ngẫu nhiên: 4 ký tự]
     * Ví dụ: PAY261101B0015A8F9
     */
    public function generateReferenceCode(int $bookingId): string
    {
        $prefix = 'PAY' . self::now()->format('ymd') . 'B' . sprintf('%04d', $bookingId % 10000);

        do {
            $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
            $candidate = $prefix . $random;
        } while ($this->thanhToanModel->existsReference($candidate));

        return $candidate;
    }
}
