<?php

namespace App\Services;

use App\Models\HoaDon;
use Core\HttpException;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class InvoiceService
{
    private HoaDon $model;

    public function __construct(private PDO $pdo, ?HoaDon $model = null)
    {
        $this->model = $model ?? new HoaDon($pdo);
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
    }

    /**
     * Tạo hóa đơn chính thức cho đơn đặt sân đã thanh toán thành công (PR4).
     * BẮT BUỘC thực thi trong cùng Transaction xác nhận thanh toán của BookingManagementService.
     *
     * @param int $bookingId ID đơn đặt sân
     * @param int $paymentId ID yêu cầu thanh toán
     * @param int|null $staffId ID nhân viên phê duyệt thanh toán
     * @param string|null $note Ghi chú hóa đơn
     * @return array Thông tin hóa đơn vừa lập
     * @throws ValidationException
     */
    public function createInvoice(int $bookingId, int $paymentId, ?int $staffId = null, ?string $note = null): array
    {
        // 1. Kiểm tra tham số cơ bản
        if ($bookingId <= 0) {
            throw new ValidationException(['booking_id' => 'Mã đặt sân không hợp lệ để tạo hóa đơn.']);
        }
        if ($paymentId <= 0) {
            throw new ValidationException(['payment_id' => 'Mã thanh toán không hợp lệ để tạo hóa đơn.']);
        }

        // 2. Chống tạo trùng lặp hóa đơn (Mỗi booking chỉ có tối đa một hóa đơn)
        if ($this->model->existsByBookingId($bookingId)) {
            throw new ValidationException([
                'invoice' => "Đơn đặt sân #{$bookingId} đã có hóa đơn được lập trước đó. Không thể lập hóa đơn trùng lặp."
            ]);
        }
        if ($this->model->existsByPaymentId($paymentId)) {
            throw new ValidationException([
                'invoice' => "Giao dịch thanh toán #{$paymentId} đã được xuất hóa đơn. Không thể tạo hóa đơn lần hai."
            ]);
        }

        // 3. Đọc dữ liệu booking từ database (khóa dòng để đảm bảo tính nhất quán tuyệt đối)
        $bStmt = $this->pdo->prepare('SELECT * FROM dat_san WHERE ma_dat_san = :id FOR UPDATE');
        $bStmt->execute(['id' => $bookingId]);
        $booking = $bStmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            throw new ValidationException(['booking' => 'Không tìm thấy thông tin đơn đặt sân để lập hóa đơn.']);
        }

        // Hóa đơn chỉ được tạo khi booking đã được xác nhận (hoặc hoàn thành)
        if (!in_array($booking['trang_thai_dat_san'], ['DA_XAC_NHAN', 'HOAN_THANH'], true)) {
            throw new ValidationException([
                'booking' => 'Chỉ có thể lập hóa đơn khi đơn đặt sân đã được xác nhận thành công (DA_XAC_NHAN).'
            ]);
        }

        // 4. Đọc dữ liệu thanh toán từ database
        $pStmt = $this->pdo->prepare('SELECT * FROM thanh_toan WHERE ma_thanh_toan = :pid FOR UPDATE');
        $pStmt->execute(['pid' => $paymentId]);
        $payment = $pStmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            throw new ValidationException(['payment' => 'Không tìm thấy thông tin thanh toán tương ứng.']);
        }

        // Hóa đơn CHỈ được tạo khi thanh toán thực sự mang trạng thái THANH_CONG
        if ($payment['trang_thai'] !== 'THANH_CONG') {
            throw new ValidationException([
                'payment' => "Yêu cầu thanh toán đang ở trạng thái '{$payment['trang_thai']}', không phải THANH_CONG. Theo quy định kế toán, không thể lập hóa đơn."
            ]);
        }

        // 5. Kiểm tra tính toàn vẹn số tiền: Lấy từ CSDL, không tin tưởng frontend
        $courtFee = number_format((float) $booking['tong_tien_san'], 2, '.', '');
        $serviceFee = number_format((float) $booking['tong_tien_dich_vu'], 2, '.', '');
        $totalAmount = number_format((float) $booking['tong_tien_tam_tinh'], 2, '.', '');

        $calcTotal = number_format((float) $courtFee + (float) $serviceFee, 2, '.', '');
        if ($totalAmount !== $calcTotal) {
            throw new ValidationException([
                'amount' => 'Tổng tiền đơn đặt sân không khớp với tiền sân cộng tiền dịch vụ.'
            ]);
        }

        // Số tiền thực nhận trên payment phải khớp với tổng tiền đơn đặt
        $paidAmount = number_format((float) ($payment['so_tien_thuc_nhan'] ?? $payment['so_tien']), 2, '.', '');
        if ($paidAmount !== $totalAmount) {
            throw new ValidationException([
                'amount' => "Số tiền đã thanh toán ({$paidAmount} đ) không khớp với tổng tiền đơn đặt ({$totalAmount} đ)."
            ]);
        }

        // 6. Sinh số hóa đơn duy nhất
        $invoiceNumber = $this->model->generateInvoiceNumber($bookingId);
        $createdAt = self::now()->format('Y-m-d H:i:s');

        // 7. Tạo bản ghi hóa đơn
        $invoiceId = $this->model->create([
            'so_hoa_don' => $invoiceNumber,
            'ma_dat_san' => $bookingId,
            'ma_thanh_toan' => $paymentId,
            'ma_nhan_vien' => $staffId,
            'ngay_lap' => $createdAt,
            'tien_san' => $courtFee,
            'tien_dich_vu' => $serviceFee,
            'tong_tien' => $totalAmount,
            'trang_thai' => 'DA_LAP',
            'ghi_chu' => $note,
        ]);

        return [
            'success' => true,
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
            'booking_id' => $bookingId,
            'payment_id' => $paymentId,
            'court_fee' => $courtFee,
            'service_fee' => $serviceFee,
            'total_amount' => $totalAmount,
            'created_at' => $createdAt,
            'status' => 'DA_LAP',
        ];
    }

    /**
     * Lấy thông tin chi tiết một hóa đơn, có phân quyền và chống IDOR chặt chẽ.
     *
     * @param int $invoiceId ID hóa đơn
     * @param array|null $scope Scope phân quyền admin (nếu là nhân viên/quản lý)
     * @param int|null $customerId ID khách hàng (nếu là khách hàng đăng nhập)
     * @return array Chi tiết hóa đơn
     * @throws HttpException
     */
    public function getInvoice(int $invoiceId, ?array $scope = null, ?int $customerId = null): array
    {
        if ($invoiceId <= 0) {
            throw new HttpException(404, 'Mã hóa đơn không hợp lệ.');
        }

        $data = $this->model->details($invoiceId);
        if (!$data || empty($data['invoice'])) {
            throw new HttpException(404, 'Không tìm thấy hóa đơn.');
        }

        $invoice = $data['invoice'];

        // Kiểm tra quyền sở hữu của khách hàng (Anti-IDOR)
        if ($customerId !== null && (int) $invoice['ma_khach_hang'] !== $customerId) {
            throw new HttpException(403, 'Bạn không có quyền xem hóa đơn của khách hàng khác.');
        }

        // Kiểm tra phạm vi phân quyền nội bộ (Anti-IDOR cơ sở / công ty)
        if ($scope !== null) {
            if ($scope['facility'] !== null && (int) $invoice['ma_co_so'] !== $scope['facility']) {
                throw new HttpException(403, 'Hóa đơn không thuộc cơ sở do bạn quản lý.');
            }
            if ($scope['company'] !== null && (int) $invoice['ma_cong_ty'] !== $scope['company']) {
                throw new HttpException(403, 'Hóa đơn không thuộc công ty do bạn quản lý.');
            }
        }

        return $data;
    }

    /**
     * Lấy thông tin chi tiết hóa đơn theo mã đặt sân (ma_dat_san).
     */
    public function getInvoiceByBooking(int $bookingId, ?array $scope = null, ?int $customerId = null): ?array
    {
        if ($bookingId <= 0) {
            return null;
        }

        $invoice = $this->model->findByBookingId($bookingId);
        if (!$invoice) {
            // Tự động lập hóa đơn nếu đơn đã thanh toán thành công trước khi triển khai module hóa đơn
            $bStmt = $this->pdo->prepare('SELECT * FROM dat_san WHERE ma_dat_san = :id');
            $bStmt->execute(['id' => $bookingId]);
            $bRow = $bStmt->fetch(PDO::FETCH_ASSOC);

            if ($bRow && in_array($bRow['trang_thai_dat_san'], ['DA_XAC_NHAN', 'HOAN_THANH'], true)) {
                $pStmt = $this->pdo->prepare('SELECT * FROM thanh_toan WHERE ma_dat_san = :id AND trang_thai = "THANH_CONG"');
                $pStmt->execute(['id' => $bookingId]);
                $pRow = $pStmt->fetch(PDO::FETCH_ASSOC);

                if ($pRow) {
                    try {
                        $this->createInvoice($bookingId, (int) $pRow['ma_thanh_toan'], !empty($bRow['ma_nhan_vien']) ? (int) $bRow['ma_nhan_vien'] : null);
                        $invoice = $this->model->findByBookingId($bookingId);
                    } catch (\Throwable) {
                        // ignore
                    }
                }
            }
        }

        if (!$invoice) {
            return null;
        }

        return $this->getInvoice((int) $invoice['ma_hoa_don'], $scope, $customerId);
    }

    /**
     * Chuẩn hóa và làm sạch bộ lọc hóa đơn cho Admin.
     */
    public function validateFilters(array $query, array $scope): array
    {
        $filters = [];

        // 1. Phân trang
        $perPage = (int) ($query['per_page'] ?? 10);
        if (!in_array($perPage, [10, 20, 50], true)) {
            $perPage = 10;
        }
        $page = (int) ($query['page'] ?? 1);
        if ($page < 1) {
            $page = 1;
        }
        $filters['per_page'] = $perPage;
        $filters['page'] = $page;

        // 2. Tìm kiếm từ khóa
        $filters['q'] = trim((string) ($query['q'] ?? ''));

        // 3. Khoảng ngày
        $dateFrom = trim((string) ($query['date_from'] ?? ''));
        $filters['date_from'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ? $dateFrom : '';

        $dateTo = trim((string) ($query['date_to'] ?? ''));
        $filters['date_to'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ? $dateTo : '';

        // 4. Trạng thái
        $status = trim((string) ($query['status'] ?? ''));
        $allowedStatuses = ['DA_LAP', 'HUY'];
        $filters['status'] = in_array($status, $allowedStatuses, true) ? $status : '';

        // 5. Cơ sở
        $facilityId = (int) ($query['facility_id'] ?? 0);
        if ($scope['facility'] !== null) {
            $filters['facility_id'] = $scope['facility'];
        } else {
            $filters['facility_id'] = $facilityId > 0 ? $facilityId : '';
        }

        return $filters;
    }

    /**
     * Danh sách hóa đơn Admin có phân quyền và bộ lọc.
     */
    public function listInvoices(array $query, array $scope): array
    {
        $filters = $this->validateFilters($query, $scope);
        $data = $this->model->page($filters, $scope);

        return [
            'filters' => $filters,
            'items' => $data['items'],
            'total' => $data['total'],
            'page' => $data['page'],
            'pages' => $data['pages'],
            'per_page' => $data['per_page'],
        ];
    }

    /**
     * Danh sách hóa đơn của khách hàng.
     */
    public function listCustomerInvoices(int $customerId, array $query): array
    {
        $filters = [
            'per_page' => in_array((int)($query['per_page'] ?? 10), [10, 20], true) ? (int)$query['per_page'] : 10,
            'page' => max(1, (int)($query['page'] ?? 1)),
            'q' => trim((string)($query['q'] ?? '')),
            'date_from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($query['date_from'] ?? '')) ? $query['date_from'] : '',
            'date_to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($query['date_to'] ?? '')) ? $query['date_to'] : '',
        ];

        return $this->model->customerInvoices($customerId, $filters);
    }
}
