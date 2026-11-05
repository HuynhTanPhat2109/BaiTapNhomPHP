<?php

namespace App\Services;

use App\Models\AdminBooking;
use Core\HttpException;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class BookingManagementService
{
    /** UI hints only; writers re-evaluate the same state under row locks. */
    public static function actions(array $booking, ?\DateTimeImmutable $now = null): array
    {
        $status = $booking['trang_thai_dat_san'] ?? '';
        $payment = $booking['trang_thai_thanh_toan'] ?? '';
        $expires = $booking['het_han_giu_san_luc'] ?? '';
        $pending = in_array($status, ['CHO_THANH_TOAN', 'CHO_XAC_NHAN'], true);
        $live = $pending && $expires !== '' && $expires > ($now ?? self::now())->format('Y-m-d H:i:s');
        $payable = in_array($payment, ['CHO_THANH_TOAN', 'CHO_XAC_NHAN', 'CAN_DOI_SOAT'], true);
        $recordable = in_array($status, ['CHO_THANH_TOAN', 'CHO_XAC_NHAN', 'DA_HUY', 'HET_HAN'], true)
            && in_array($payment, ['CHO_THANH_TOAN', 'CHO_XAC_NHAN', 'HUY', 'HET_HAN'], true);
        return ['confirm' => $live && $payable, 'cancel' => $live && $payable,
            'reconcile' => $recordable, 'late' => !$live,
            'view_reconciliation' => in_array($payment, ['THANH_CONG', 'CAN_DOI_SOAT'], true)];
    }

    private AdminBooking $model;
    private BookingService $bookingService;
    private InvoiceService $invoiceService;
    private PaymentVerificationService $verification;

    public function __construct(
        private PDO $pdo,
        ?BookingService $bookingService = null,
        ?InvoiceService $invoiceService = null,
        ?PaymentVerificationService $verification = null,
        private ?BookingReceiptService $receipts = null
    ) {
        $this->model = new AdminBooking($pdo);
        $this->bookingService = $bookingService ?? new BookingService($pdo);
        $this->invoiceService = $invoiceService ?? new InvoiceService($pdo);
        $this->verification = $verification ?? new PaymentVerificationService($pdo);
        $this->receipts ??= new BookingReceiptService($pdo);
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
    }

    /**
     * Xác thực và phân quyền phạm vi dữ liệu dựa trên danh tính tài khoản đăng nhập.
     * TUYỆT ĐỐI không tin tưởng mã cơ sở/công ty do client gửi lên.
     *
     * @param array $user Thông tin tài khoản người dùng từ Session
     * @return array [
     *     'role' => string,
     *     'company' => ?int,
     *     'facility' => ?int,
     *     'staff_id' => ?int,
     *     'can_confirm' => bool
     * ]
     * @throws HttpException
     */
    public static function resolveScope(array $user): array
    {
        $role = $user['ten_vai_tro'] ?? '';
        $allowedRoles = ['QUAN_TRI_TAP_DOAN', 'QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO', 'NHAN_VIEN'];

        if (!in_array($role, $allowedRoles, true)) {
            throw new HttpException(403, 'Bạn không có quyền truy cập khu vực quản lý đơn đặt sân.');
        }

        // Kiểm tra tài khoản và hồ sơ nhân viên còn đang hoạt động
        if (!AuthService::isActive($user)) {
            throw new HttpException(403, 'Tài khoản hoặc phạm vi phân công của bạn đã bị khóa hoặc ngừng hoạt động.');
        }

        $staffId = !empty($user['ma_nhan_vien']) ? (int) $user['ma_nhan_vien'] : null;

        if ($role === 'QUAN_TRI_TAP_DOAN') {
            return [
                'role' => $role,
                'company' => null,
                'facility' => null,
                'staff_id' => $staffId,
                'can_confirm' => true,
            ];
        }

        if ($role === 'QUAN_LY_CONG_TY') {
            $companyId = (int) ($user['ma_cong_ty'] ?? 0);
            if ($companyId <= 0) {
                throw new HttpException(403, 'Tài khoản quản lý chưa được gán công ty hợp lệ.');
            }
            return [
                'role' => $role,
                'company' => $companyId,
                'facility' => null,
                'staff_id' => $staffId,
                'can_confirm' => true,
            ];
        }

        if ($role === 'QUAN_LY_CO_SO' || $role === 'NHAN_VIEN') {
            $facilityId = (int) ($user['ma_co_so'] ?? 0);
            $companyId = (int) ($user['ma_cong_ty'] ?? 0);
            if ($facilityId <= 0) {
                throw new HttpException(403, 'Tài khoản chưa được phân công cơ sở hoạt động.');
            }
            return [
                'role' => $role,
                'company' => $companyId > 0 ? $companyId : null,
                'facility' => $facilityId,
                'staff_id' => $staffId,
                'can_confirm' => true,
            ];
        }

        throw new HttpException(403, 'Phân quyền không xác định.');
    }

    /**
     * Chuẩn hóa và làm sạch bộ lọc đầu vào.
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

        // 2. Tìm kiếm
        $filters['q'] = trim((string) ($query['q'] ?? ''));

        // 3. Loại ngày & khoảng ngày
        $dateType = trim((string) ($query['date_type'] ?? 'ngay_choi'));
        $filters['date_type'] = in_array($dateType, ['ngay_choi', 'ngay_tao'], true) ? $dateType : 'ngay_choi';

        $dateFrom = trim((string) ($query['date_from'] ?? ''));
        $filters['date_from'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ? $dateFrom : '';

        $dateTo = trim((string) ($query['date_to'] ?? ''));
        $filters['date_to'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ? $dateTo : '';

        // 4. Trạng thái booking
        $bookingStatus = trim((string) ($query['booking_status'] ?? ''));
        $allowedBookingStatuses = ['CHO_THANH_TOAN', 'CHO_XAC_NHAN', 'DA_XAC_NHAN', 'HOAN_THANH', 'HET_HAN', 'DA_HUY'];
        $filters['booking_status'] = in_array($bookingStatus, $allowedBookingStatuses, true) ? $bookingStatus : '';

        // 5. Trạng thái thanh toán
        $paymentStatus = trim((string) ($query['payment_status'] ?? ''));
        $allowedPaymentStatuses = ['CHO_THANH_TOAN', 'THANH_CONG', 'HET_HAN', 'HUY', 'CAN_DOI_SOAT'];
        $filters['payment_status'] = in_array($paymentStatus, $allowedPaymentStatuses, true) ? $paymentStatus : '';

        // 6. Cơ sở (nếu scope cho phép chọn)
        $facilityId = (int) ($query['facility_id'] ?? 0);
        if ($scope['facility'] !== null) {
            $filters['facility_id'] = $scope['facility'];
        } else {
            $filters['facility_id'] = $facilityId > 0 ? $facilityId : '';
        }

        // 7. Sân
        $courtId = (int) ($query['court_id'] ?? 0);
        $filters['court_id'] = $courtId > 0 ? $courtId : '';

        return $filters;
    }

    public function confirmSyncedBookingAndPayment(int $bookingId, array $user): array
    {
        $scope = self::resolveScope($user);
        $booking = $this->model->find($bookingId, $scope);
        if (!$booking) throw new HttpException(404, 'Không tìm thấy đơn đặt sân trong phạm vi của bạn.');
        $review = $this->verification->review($booking + ['so_tien' => $booking['so_tien_can_thanh_toan']]);
        if (!$review['can_confirm']) throw new ValidationException(['bank_transaction' => $review['message']]);
        $transaction = $review['transaction'];
        return $this->finalizeBookingAndPayment($bookingId, $transaction['transaction_id'], $transaction['amount'],
            $transaction['occurred_at'], null, $user, $transaction);
    }

    /** The final POST itself attests that this operator checked the receiving account. */
    public function confirmReceiptPayment(int $bookingId, array $user, ?string $receiptVersion = null): array
    {
        return $this->finalizeBookingAndPayment($bookingId, '', '0', null, null, $user, receiptConfirmation: true, receiptVersion: $receiptVersion);
    }

    /** Existing exception workflow, restricted to a linked receipt and CAN_DOI_SOAT. */
    public function confirmReconciledPayment(int $id, string $transaction, string $amount, string $paidAt, ?string $note, array $user, ?string $receiptVersion = null): array
    {
        return $this->finalizeBookingAndPayment($id, $transaction, $amount, $paidAt, $note, $user, reconciledReceipt: true, receiptVersion: $receiptVersion);
    }

    /** Existing, explicitly manual reconciliation entry point. */
    public function confirmBookingAndPayment(int $bookingId, string $bankTxnId, float|string $actualAmount,
        ?string $paidAt, ?string $note, array $user): array
    {
        return $this->finalizeBookingAndPayment($bookingId, $bankTxnId, $actualAmount, $paidAt, $note, $user);
    }

    /**
     * Xác nhận thanh toán và booking trong cùng transaction.
     * Chạy toàn bộ kiểm tra và cập nhật trong 1 TRANSACTION nguyên tử duy nhất.
     *
     * @param int $bookingId ID đơn đặt sân
     * @param string $bankTxnId Mã giao dịch ngân hàng thực tế
     * @param float|string $actualAmount Số tiền thực tế nhận được trong tài khoản
     * @param string|null $paidAt Thời gian chuyển tiền thành công
     * @param string|null $note Ghi chú đối soát của nhân viên
     * @param array $user Thông tin tài khoản người xác nhận
     * @return array Kết quả xử lý
     * @throws ValidationException
     * @throws HttpException
     */
    private function finalizeBookingAndPayment(
        int $bookingId,
        string $bankTxnId,
        float|string $actualAmount,
        ?string $paidAt,
        ?string $note,
        array $user,
        ?array $verifiedTransaction = null,
        bool $receiptConfirmation = false,
        bool $reconciledReceipt = false,
        ?string $receiptVersion = null
    ): array {
        $scope = self::resolveScope($user);
        if (!$scope['can_confirm']) {
            throw new HttpException(403, 'Bạn không có quyền xác nhận booking.');
        }

        // 1. Kiểm tra tính hợp lệ của dữ liệu đầu vào
        $cleanTxnId = trim($bankTxnId);
        if (!$receiptConfirmation && $cleanTxnId === '') {
            throw new ValidationException(['ma_giao_dich' => 'Vui lòng nhập mã giao dịch ngân hàng từ biên lai/biến động số dư.']);
        }
        if (!$receiptConfirmation && (mb_strlen($cleanTxnId, 'UTF-8') < 3 || mb_strlen($cleanTxnId, 'UTF-8') > 150)) {
            throw new ValidationException(['ma_giao_dich' => 'Mã giao dịch ngân hàng phải từ 3 đến 150 ký tự.']);
        }

        if (!$receiptConfirmation && (!is_numeric($actualAmount) || (float) $actualAmount <= 0)) {
            throw new ValidationException(['so_tien_thuc_nhan' => 'Số tiền thực nhận không hợp lệ (phải lớn hơn 0).']);
        }
        $formattedActual = number_format((float) $actualAmount, 2, '.', '');

        $paymentTime = trim((string) $paidAt);
        if ($paymentTime === '' || !strtotime($paymentTime)) {
            $paymentTime = self::now()->format('Y-m-d H:i:s');
        } else {
            $paymentTime = date('Y-m-d H:i:s', strtotime($paymentTime));
        }

        $cleanNote = $note !== null ? trim($note) : null;
        if ($cleanNote !== null && mb_strlen($cleanNote, 'UTF-8') > 500) {
            throw new ValidationException(['ghi_chu' => 'Ghi chú không được vượt quá 500 ký tự.']);
        }

        // 2. Bắt đầu Transaction nguyên tử với Locking Hierarchy chặt chẽ theo Contract của Phát
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $this->pdo->beginTransaction();

        try {
            // A. Khóa phân cấp dữ liệu theo contract:
            // Đọc trước các ID liên quan để xác định thứ tự khóa (không khóa ở bước đọc)
            $preStmt = $this->pdo->prepare('SELECT ma_co_so, ma_khach_hang FROM dat_san WHERE ma_dat_san = :id');
            $preStmt->execute(['id' => $bookingId]);
            $preBooking = $preStmt->fetch(PDO::FETCH_ASSOC);

            if (!$preBooking) {
                throw new ValidationException(['booking' => 'Không tìm thấy đơn đặt sân.']);
            }

            $facilityId = (int) $preBooking['ma_co_so'];

            // B. Kiểm tra quyền sở hữu phạm vi cơ sở/công ty
            if ($scope['facility'] !== null && $facilityId !== $scope['facility']) {
                throw new HttpException(403, 'Bạn không có quyền quản lý booking của cơ sở khác.');
            }

            $fStmt = $this->pdo->prepare('SELECT ma_cong_ty FROM co_so WHERE ma_co_so = :co_so');
            $fStmt->execute(['co_so' => $facilityId]);
            $companyId = (int) $fStmt->fetchColumn();

            if ($scope['company'] !== null && $companyId !== $scope['company']) {
                throw new HttpException(403, 'Booking không thuộc phạm vi công ty của bạn.');
            }

            // Đọc danh sách sân, loại sân, bộ môn liên quan
            $courtRowsStmt = $this->pdo->prepare(
                'SELECT d.ma_san, s.ma_loai_san, ls.ma_bo_mon
                 FROM chi_tiet_dat_san d
                 JOIN san s ON s.ma_san = d.ma_san
                 JOIN loai_san ls ON ls.ma_loai_san = s.ma_loai_san
                 WHERE d.ma_dat_san = :id'
            );
            $courtRowsStmt->execute(['id' => $bookingId]);
            $courtRows = $courtRowsStmt->fetchAll(PDO::FETCH_ASSOC);

            $sports = [];
            $types = [];
            $courts = [];
            foreach ($courtRows as $cr) {
                $sports[(int) $cr['ma_bo_mon']] = true;
                $types[(int) $cr['ma_loai_san']] = true;
                $courts[(int) $cr['ma_san']] = true;
            }
            ksort($sports);
            ksort($types);
            ksort($courts);

            // Đọc danh sách dịch vụ liên quan
            $svcRowsStmt = $this->pdo->prepare('SELECT ma_dich_vu FROM chi_tiet_dich_vu_dat WHERE ma_dat_san = :id');
            $svcRowsStmt->execute(['id' => $bookingId]);
            $svcIds = array_unique(array_map('intval', $svcRowsStmt->fetchAll(PDO::FETCH_COLUMN)));
            sort($svcIds);

            // Khóa phân cấp cha -> con:
            // 1) company
            if ($companyId > 0) {
                $cLock = $this->pdo->prepare('SELECT ma_cong_ty FROM cong_ty_nhuong_quyen WHERE ma_cong_ty = :cid FOR UPDATE');
                $cLock->execute(['cid' => $companyId]);
            }

            // 2) facility
            $facLock = $this->pdo->prepare('SELECT ma_co_so, ma_cong_ty FROM co_so WHERE ma_co_so = :fid FOR UPDATE');
            $facLock->execute(['fid' => $facilityId]);
            $lockedFacility = $facLock->fetch(PDO::FETCH_ASSOC);
            if (!$lockedFacility || (int)$lockedFacility['ma_cong_ty'] !== $companyId) {
                throw new ValidationException(['booking'=>'Phạm vi cơ sở vừa thay đổi. Vui lòng tải lại đơn và kiểm tra quyền quản lý.']);
            }

            // 3) sports ascending
            $spLock = $this->pdo->prepare('SELECT ma_bo_mon FROM bo_mon WHERE ma_bo_mon = :id FOR UPDATE');
            foreach (array_keys($sports) as $spId) {
                if ($spId > 0) $spLock->execute(['id' => $spId]);
            }

            // 4) court types ascending
            $tpLock = $this->pdo->prepare('SELECT ma_loai_san FROM loai_san WHERE ma_loai_san = :id FOR UPDATE');
            foreach (array_keys($types) as $tpId) {
                if ($tpId > 0) $tpLock->execute(['id' => $tpId]);
            }

            // 5) courts ascending
            $ctLock = $this->pdo->prepare('SELECT ma_san FROM san WHERE ma_san = :id FOR UPDATE');
            foreach (array_keys($courts) as $ctId) {
                if ($ctId > 0) $ctLock->execute(['id' => $ctId]);
            }

            // 6) services ascending
            $svLock = $this->pdo->prepare('SELECT ma_dich_vu FROM dich_vu WHERE ma_dich_vu = :id FOR UPDATE');
            foreach ($svcIds as $svId) {
                if ($svId > 0) $svLock->execute(['id' => $svId]);
            }

            // Keep the original deadline. Receipt review is an explicit state, not a new hold.
            $booking = $this->bookingService->lockPayable($bookingId, true);
            $conflict=$this->pdo->prepare("SELECT other.ma_dat_san FROM chi_tiet_dat_san own
                JOIN chi_tiet_dat_san other ON other.ma_san=own.ma_san AND other.ngay_choi=own.ngay_choi
                    AND other.gio_bat_dau<own.gio_ket_thuc AND other.gio_ket_thuc>own.gio_bat_dau
                JOIN dat_san b ON b.ma_dat_san=other.ma_dat_san
                WHERE own.ma_dat_san=? AND other.ma_dat_san<>?
                    AND (b.trang_thai_dat_san IN ('CHO_XAC_NHAN','DA_XAC_NHAN','HOAN_THANH')
                        OR (b.trang_thai_dat_san='CHO_THANH_TOAN' AND b.het_han_giu_san_luc>CURRENT_TIMESTAMP))
                LIMIT 1 FOR UPDATE");
            $conflict->execute([$bookingId,$bookingId]);
            if ($conflict->fetchColumn()) throw new ValidationException(['booking'=>'Khung giờ bị trùng với đơn khác. Cần đối soát trước khi xác nhận.']);

            // C. Khóa và kiểm tra thông tin thanh toán tương ứng
            $pStmt = $this->pdo->prepare('SELECT * FROM thanh_toan WHERE ma_dat_san = :id FOR UPDATE');
            $pStmt->execute(['id' => $bookingId]);
            $payment = $pStmt->fetch(PDO::FETCH_ASSOC);
            if (!$preRow) throw new ValidationException(['booking' => 'Không tìm thấy booking.']);
            if (!$payment) throw new ValidationException(['payment' => 'Không tìm thấy yêu cầu thanh toán.']);
            if (!self::actions($preRow + ['trang_thai_thanh_toan' => $payment['trang_thai']])['confirm']) {
                throw new ValidationException(['booking' => 'Booking đã hủy, hết hạn, đã xử lý hoặc thanh toán không còn đủ điều kiện xác nhận.']);
            }
            // lockPayable accepts CHO_THANH_TOAN only; never extend the original expiry.
            if ($preRow['trang_thai_dat_san'] === 'CHO_XAC_NHAN') {
                $this->pdo->prepare("UPDATE dat_san SET trang_thai_dat_san = 'CHO_THANH_TOAN' WHERE ma_dat_san = :id")->execute(['id' => $bookingId]);
            }

            if (!in_array($payment['trang_thai'], ['CHO_THANH_TOAN','CAN_DOI_SOAT'], true)) {
                throw new ValidationException(['payment'=>'Giao dịch không còn được phép xác nhận.']);
            }

            $receipt = null;
            if ($reconciledReceipt) {
                if (($payment['trang_thai'] ?? '') !== 'CAN_DOI_SOAT'
                    || !array_key_exists('xac_nhan_thu_cong_luc', $payment)
                    || (int) ($user['ma_tai_khoan'] ?? 0) < 1
                    || !($receipt = $this->receipts->info($payment))) {
                    throw new ValidationException(['receipt' => 'Chỉ xử lý đối soát cho payment cần đối soát có minh chứng liên kết hợp lệ.']);
                }
                if ($receiptVersion !== null && !hash_equals($receipt['version'], $receiptVersion)) throw new ValidationException(['receipt' => 'Minh chứng đã thay đổi. Kiểm tra lại ảnh mới.']);
            }
            if ($receiptConfirmation) {
                if ((int) ($user['ma_tai_khoan'] ?? 0) < 1) throw new HttpException(403, 'Không xác định được tài khoản xác nhận.');
                $receipt = $this->receipts->requireConfirmation($payment + $booking);
                if ($receiptVersion !== null && !hash_equals($receipt['version'], $receiptVersion)) {
                    throw new ValidationException(['receipt' => 'Khách vừa thay minh chứng. Tải lại và kiểm tra ảnh mới trước khi xác nhận.']);
                }
                // A human attestation of full receipt, not data from a bank API.
                $formattedActual = number_format((float) $payment['so_tien'], 2, '.', '');
                if ($formattedActual !== number_format((float) $booking['tong_tien_tam_tinh'], 2, '.', '')) {
                    throw new ValidationException(['payment' => 'Số tiền payment và booking không khớp. Vui lòng xem đối soát.']);
                }
            }

            if ($verifiedTransaction !== null) {
                $this->verification->requireMatch($verifiedTransaction, $payment + $booking
                    + ['trang_thai_thanh_toan' => $payment['trang_thai']]);
            }

            // G. Kiểm tra tính duy nhất của mã giao dịch ngân hàng (Chống xử lý lặp mã ngân hàng)
            if (!$receiptConfirmation) {
                $checkTxnStmt = $this->pdo->prepare('SELECT ma_thanh_toan FROM thanh_toan WHERE ma_giao_dich = :txn_id AND ma_thanh_toan != :payment_id');
                $checkTxnStmt->execute(['txn_id' => $cleanTxnId, 'payment_id' => $payment['ma_thanh_toan']]);
                if ($checkTxnStmt->fetchColumn()) {
                    throw new ValidationException([
                        'ma_giao_dich' => "Mã giao dịch ngân hàng '$cleanTxnId' đã được ghi nhận cho một đơn thanh toán khác. Vui lòng kiểm tra lại biên lai."
                    ]);
                }

            }

            // H. Kiểm tra số tiền thực nhận có khớp chính xác số tiền cần thanh toán không
            $expectedAmount = number_format((float) $payment['so_tien'], 2, '.', '');
            if ($formattedActual !== $expectedAmount) {
                throw new ValidationException([
                    'so_tien_thuc_nhan' => "Số tiền thực nhận ({$formattedActual} đ) KHÔNG KHỚP với số tiền đơn đặt ({$expectedAmount} đ). Theo quy định, không thể xác nhận booking khi sai tiền. Vui lòng chuyển sang 'Cần đối soát'."
                ]);
            }

            // One stock writer, while the locked booking is still pending. Caller owns rollback.
            try {
                (new BookingInventoryService($this->pdo))->deductForPayment($bookingId);
            } catch (HttpException $e) {
                throw new ValidationException(['inventory' => $e->getMessage()]);
            }

            // 3. Thực thi cập nhật bảng thanh_toan -> THANH_CONG
            $existingReceipt = ReceiptImage::findForBooking($bookingId, $payment['ghi_chu'] ?? '');
            $finalPaymentNote = $verifiedTransaction !== null || $receiptConfirmation ? ($payment['ghi_chu'] ?? null) : $cleanNote;
            if ($existingReceipt) {
                $finalPaymentNote = ReceiptImage::appendReceiptToNote($finalPaymentNote, $existingReceipt, ReceiptImage::sentAt($payment['ghi_chu'] ?? null));
            }

            if ($receiptConfirmation) {
                $updatePaymentStmt = $this->pdo->prepare("UPDATE thanh_toan SET trang_thai='THANH_CONG',
                    so_tien_thuc_nhan=:amount, anh_minh_chung=:receipt,
                    ma_tai_khoan_xac_nhan=:actor, xac_nhan_thu_cong_luc=:confirmed_at
                    WHERE ma_thanh_toan=:id AND trang_thai IN ('CHO_THANH_TOAN','CHO_XAC_NHAN')");
                $updatePaymentStmt->execute(['amount' => $formattedActual, 'receipt' => $receipt['path'],
                    'actor' => (int) $user['ma_tai_khoan'], 'confirmed_at' => self::now()->format('Y-m-d H:i:s'), 'id' => $payment['ma_thanh_toan']]);
                if ($updatePaymentStmt->rowCount() !== 1) throw new ValidationException(['payment' => 'Payment đã được xử lý.']);
            } else {
                $updatePaymentStmt = $this->pdo->prepare(
                'UPDATE thanh_toan
                 SET trang_thai = :status,
                     ngay_thanh_toan = :paid_at,
                     so_tien_thuc_nhan = :actual_amount,
                     ma_giao_dich = :bank_txn,
                     ghi_chu = :note
                 WHERE ma_thanh_toan = :id'
            );
            $updatePaymentStmt->execute([
                'status' => 'THANH_CONG',
                'paid_at' => $paymentTime,
                'actual_amount' => $formattedActual,
                'bank_txn' => $cleanTxnId,
                'note' => $finalPaymentNote,
                'id' => $payment['ma_thanh_toan'],
            ]);
            if ($reconciledReceipt) {
                $this->pdo->prepare('UPDATE thanh_toan SET anh_minh_chung=:receipt, ma_tai_khoan_xac_nhan=:actor,
                    xac_nhan_thu_cong_luc=:confirmed WHERE ma_thanh_toan=:id')->execute(['receipt' => $receipt['path'],
                    'actor' => (int) $user['ma_tai_khoan'], 'confirmed' => self::now()->format('Y-m-d H:i:s'), 'id' => $payment['ma_thanh_toan']]);
            }
            }

            // 4. Thực thi cập nhật bảng dat_san -> DA_XAC_NHAN
            $staffParam = $scope['staff_id'];
            $updateBookingStmt = $this->pdo->prepare(
                'UPDATE dat_san
                 SET trang_thai_dat_san = :status,
                     ma_nhan_vien = :staff_id
                 WHERE ma_dat_san = :id AND trang_thai_dat_san = :pending
                   AND het_han_giu_san_luc > :confirmed_at'
            );
            $updateBookingStmt->execute([
                'status' => 'DA_XAC_NHAN',
                'staff_id' => $staffParam,
                'id' => $bookingId,
                'pending' => BookingService::PENDING,
                'confirmed_at' => self::now()->format('Y-m-d H:i:s'),
            ]);
            if ($updateBookingStmt->rowCount() !== 1) {
                throw new ValidationException(['booking' => 'Booking đã hết hạn hoặc thay đổi trong lúc xác nhận.']);
            }

            // 6. Lập hóa đơn chính thức (PR4) trong cùng một Transaction nguyên tử
            $invoiceResult = $this->invoiceService->createInvoice(
                $bookingId,
                (int) $payment['ma_thanh_toan'],
                $scope['staff_id'],
                $cleanNote
            );

            // 7. Commit toàn bộ thay đổi nguyên tử
            $this->pdo->commit();

            return [
                'success' => true,
                'booking_id' => $bookingId,
                'invoice_id' => $invoiceResult['invoice_id'],
                'invoice_number' => $invoiceResult['invoice_number'],
                'message' => "Đã xác nhận thanh toán, đặt sân và lập hóa đơn #{$invoiceResult['invoice_number']} thành công cho đơn #{$bookingId}.",
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Đánh dấu yêu cầu thanh toán sang trạng thái CAN_DOI_SOAT.
     * Sử dụng khi: Khách chuyển thiếu tiền, thừa tiền, chuyển muộn khi đơn đã hết hạn, hoặc nội dung không rõ ràng.
     * TUYỆT ĐỐI KHÔNG xác nhận booking và KHÔNG trừ tồn kho dịch vụ.
     *
     * @param int $bookingId
     * @param float|string $actualAmount Số tiền thực nhận được
     * @param string|null $bankTxnId Mã giao dịch ngân hàng
     * @param string $reason Lý do cần đối soát
     * @param array $user
     * @return array
     * @throws ValidationException
     * @throws HttpException
     */
    public function markNeedsReconciliation(
        int $bookingId,
        float|string $actualAmount,
        ?string $bankTxnId,
        string $reason,
        array $user
    ): array {
        $scope = self::resolveScope($user);
        if (!$scope['can_confirm']) {
            throw new HttpException(403, 'Bạn không có quyền thao tác trên booking này.');
        }

        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            throw new ValidationException(['ly_do_doi_soat' => 'Vui lòng nhập lý do chuyển sang Cần đối soát.']);
        }
        if (mb_strlen($cleanReason, 'UTF-8') > 500) {
            throw new ValidationException(['ly_do_doi_soat' => 'Lý do không vượt quá 500 ký tự.']);
        }

        if (!is_numeric($actualAmount) || (float) $actualAmount < 0) {
            throw new ValidationException(['so_tien_thuc_nhan' => 'Số tiền thực nhận không hợp lệ.']);
        }
        $formattedActual = number_format((float) $actualAmount, 2, '.', '');
        $cleanTxn = $bankTxnId !== null ? trim($bankTxnId) : null;

        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $this->pdo->beginTransaction();

        try {
            $bLock=$this->pdo->prepare('SELECT ma_dat_san FROM dat_san WHERE ma_dat_san=? FOR UPDATE');
            $bLock->execute([$bookingId]);
            $pStmt = $this->pdo->prepare('SELECT * FROM thanh_toan WHERE ma_dat_san = :id FOR UPDATE');
            $pStmt->execute(['id' => $bookingId]);
            $payment = $pStmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                throw new ValidationException(['payment' => 'Không tìm thấy yêu cầu thanh toán cho đơn này.']);
            }

            // Kiểm tra phân quyền cơ sở / công ty (Anti-IDOR)
            $preStmt = $this->pdo->prepare('SELECT ma_co_so, trang_thai_dat_san, het_han_giu_san_luc FROM dat_san WHERE ma_dat_san = :id FOR UPDATE');
            $preStmt->execute(['id' => $bookingId]);
            $preBooking = $preStmt->fetch(PDO::FETCH_ASSOC);

            if (!$preBooking) {
                throw new ValidationException(['booking' => 'Không tìm thấy đơn đặt sân.']);
            }

            $facilityId = (int) $preBooking['ma_co_so'];
            if ($scope['facility'] !== null && $facilityId !== $scope['facility']) {
                throw new HttpException(403, 'Bạn không có quyền quản lý booking của cơ sở khác.');
            }

            $fStmt = $this->pdo->prepare('SELECT ma_cong_ty FROM co_so WHERE ma_co_so = :co_so');
            $fStmt->execute(['co_so' => $facilityId]);
            $companyId = (int) $fStmt->fetchColumn();

            if ($scope['company'] !== null && $companyId !== $scope['company']) {
                throw new HttpException(403, 'Booking không thuộc phạm vi công ty của bạn.');
            }

            $pStmt = $this->pdo->prepare('SELECT * FROM thanh_toan WHERE ma_dat_san = :id FOR UPDATE');
            $pStmt->execute(['id' => $bookingId]);
            $payment = $pStmt->fetch(PDO::FETCH_ASSOC);
            $actions = self::actions($preBooking + ['trang_thai_thanh_toan' => $payment['trang_thai'] ?? '']);
            if (!$payment || !$actions['reconcile']) throw new ValidationException(['payment' => 'Giao dịch không còn đủ điều kiện ghi nhận đối soát.']);
            if ($actions['late'] && (float) $formattedActual <= 0) throw new ValidationException(['so_tien_thuc_nhan' => 'Nhập số tiền chuyển muộn thực nhận; không xác nhận giữ sân.']);

            // Cập nhật trạng thái thanh toán sang CAN_DOI_SOAT
            $existingReceipt = ReceiptImage::findForBooking($bookingId, $payment['ghi_chu'] ?? '');
            $finalReconcileNote = $cleanReason;
            if ($existingReceipt) {
                $finalReconcileNote = ReceiptImage::appendReceiptToNote($cleanReason, $existingReceipt, ReceiptImage::sentAt($payment['ghi_chu'] ?? null));
            }

            $stmt = $this->pdo->prepare(
                'UPDATE thanh_toan
                 SET trang_thai = :status,
                     so_tien_thuc_nhan = :actual,
                     ma_giao_dich = :txn,
                     ngay_thanh_toan = :now,
                     ghi_chu = :note
                 WHERE ma_thanh_toan = :id'
            );
            $stmt->execute([
                'status' => 'CAN_DOI_SOAT',
                'actual' => $formattedActual,
                'txn' => $cleanTxn !== '' ? $cleanTxn : null,
                'now' => self::now()->format('Y-m-d H:i:s'),
                'note' => $finalReconcileNote,
                'id' => $payment['ma_thanh_toan'],
            ]);

            $this->pdo->commit();

            return [
                'success' => true,
                'booking_id' => $bookingId,
                'message' => "Đã chuyển thanh toán của đơn #{$bookingId} sang trạng thái Cần đối soát.",
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Thao tác Hủy đặt sân bởi Admin/Nhân viên (khi đối soát thất bại hoặc khách yêu cầu hủy).
     * Giải phóng khung giờ đặt sân về trạng thái TRỐNG (free).
     *
     * @param int $bookingId
     * @param string $reason
     * @param array $user
     * @return array
     * @throws ValidationException
     * @throws HttpException
     */
    public function cancelBookingByAdmin(int $bookingId, string $reason, array $user): array
    {
        $scope = self::resolveScope($user);
        if (!$scope['can_confirm']) {
            throw new HttpException(403, 'Bạn không có quyền thao tác trên booking này.');
        }

        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            throw new ValidationException(['ly_do_huy' => 'Vui lòng nhập lý do từ chối / hủy booking.']);
        }
        if (mb_strlen($cleanReason, 'UTF-8') > 500) {
            throw new ValidationException(['ly_do_huy' => 'Lý do không vượt quá 500 ký tự.']);
        }

        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $this->pdo->beginTransaction();

        try {
            $bStmt = $this->pdo->prepare('SELECT * FROM dat_san WHERE ma_dat_san = :id FOR UPDATE');
            $bStmt->execute(['id' => $bookingId]);
            $booking = $bStmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                throw new ValidationException(['booking' => 'Không tìm thấy đơn đặt sân.']);
            }

            // Kiểm tra phân quyền cơ sở / công ty (Anti-IDOR)
            $facilityId = (int) $booking['ma_co_so'];
            if ($scope['facility'] !== null && $facilityId !== $scope['facility']) {
                throw new HttpException(403, 'Bạn không có quyền quản lý booking của cơ sở khác.');
            }

            $fStmt = $this->pdo->prepare('SELECT ma_cong_ty FROM co_so WHERE ma_co_so = :co_so');
            $fStmt->execute(['co_so' => $facilityId]);
            $companyId = (int) $fStmt->fetchColumn();

            if ($scope['company'] !== null && $companyId !== $scope['company']) {
                throw new HttpException(403, 'Booking không thuộc phạm vi công ty của bạn.');
            }

            if ($booking['trang_thai_dat_san'] === 'DA_HUY') {
                throw new ValidationException(['booking' => 'Đơn đặt sân này đã được hủy trước đó.']);
            }

            if ($booking['trang_thai_dat_san'] === 'HOAN_THANH') {
                throw new ValidationException(['booking' => 'Không thể hủy đơn đặt sân đã hoàn thành.']);
            }

            $pStmt = $this->pdo->prepare('SELECT * FROM thanh_toan WHERE ma_dat_san = :id FOR UPDATE');
            $pStmt->execute(['id' => $bookingId]);
            $payment = $pStmt->fetch(PDO::FETCH_ASSOC);

            if ($booking['trang_thai_dat_san']==='DA_XAC_NHAN' || ($payment['trang_thai'] ?? '')==='THANH_CONG') {
                throw new ValidationException(['booking'=>'Đơn đã xác nhận thanh toán cần xử lý hoàn tiền riêng; không thể hủy bằng thao tác từ chối biên lai.']);
            }

            // 1. Cập nhật bảng dat_san sang DA_HUY để giải phóng khung giờ sân về TRỐNG
            $existingBookingNote = trim($booking['ghi_chu'] ?? '');
            $cancelNoteTag = "[HUY_BOI_ADMIN: {$cleanReason}]";
            $updatedBookingNote = $existingBookingNote !== '' ? $existingBookingNote . ' ' . $cancelNoteTag : $cancelNoteTag;

            $updateB = $this->pdo->prepare(
                'UPDATE dat_san
                 SET trang_thai_dat_san = :status,
                     ghi_chu = :note,
                     ma_nhan_vien = :staff_id
                 WHERE ma_dat_san = :id'
            );
            $updateB->execute([
                'status' => 'DA_HUY',
                'note' => $updatedBookingNote,
                'staff_id' => $scope['staff_id'],
                'id' => $bookingId,
            ]);

            // 2. Cập nhật bảng thanh_toan sang HUY (hoặc giữ lại ghi chú đối soát)
            if ($payment) {
                $existingReceipt = ReceiptImage::findForBooking($bookingId, $payment['ghi_chu'] ?? '');
                $finalPayNote = $cleanReason;
                if ($existingReceipt) {
                    $finalPayNote = ReceiptImage::appendReceiptToNote($cleanReason, $existingReceipt, ReceiptImage::sentAt($payment['ghi_chu'] ?? null));
                }

                $updateP = $this->pdo->prepare(
                    'UPDATE thanh_toan
                     SET trang_thai = :status,
                         ghi_chu = :note
                     WHERE ma_thanh_toan = :id'
                );
                $updateP->execute([
                    'status' => 'HUY',
                    'note' => $finalPayNote,
                    'id' => $payment['ma_thanh_toan'],
                ]);
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'booking_id' => $bookingId,
                'message' => "Đã hủy đơn đặt sân #{$bookingId} thành công. Khung giờ sân đã được giải phóng về trạng thái TRỐNG.",
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof ValidationException || $e instanceof HttpException) {
                throw $e;
            }
            throw new \RuntimeException('Lỗi hệ thống khi hủy đơn đặt sân: ' . $e->getMessage(), 0, $e);
        }
    }
}
