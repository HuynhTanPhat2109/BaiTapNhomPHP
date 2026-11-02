<?php

namespace App\Services;

use App\Models\AdminBooking;
use Core\HttpException;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class BookingManagementService
{
    private AdminBooking $model;
    private BookingService $bookingService;
    private InvoiceService $invoiceService;

    public function __construct(
        private PDO $pdo,
        ?BookingService $bookingService = null,
        ?InvoiceService $invoiceService = null
    ) {
        $this->model = new AdminBooking($pdo);
        $this->bookingService = $bookingService ?? new BookingService($pdo);
        $this->invoiceService = $invoiceService ?? new InvoiceService($pdo);
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

    /**
     * Xác nhận thanh toán và booking thủ công.
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
    public function confirmBookingAndPayment(
        int $bookingId,
        string $bankTxnId,
        float|string $actualAmount,
        ?string $paidAt,
        ?string $note,
        array $user
    ): array {
        $scope = self::resolveScope($user);
        if (!$scope['can_confirm']) {
            throw new HttpException(403, 'Bạn không có quyền xác nhận booking.');
        }

        // 1. Kiểm tra tính hợp lệ của dữ liệu đầu vào
        $cleanTxnId = trim($bankTxnId);
        if ($cleanTxnId === '') {
            throw new ValidationException(['ma_giao_dich' => 'Vui lòng nhập mã giao dịch ngân hàng từ biên lai/biến động số dư.']);
        }
        if (mb_strlen($cleanTxnId, 'UTF-8') < 3 || mb_strlen($cleanTxnId, 'UTF-8') > 150) {
            throw new ValidationException(['ma_giao_dich' => 'Mã giao dịch ngân hàng phải từ 3 đến 150 ký tự.']);
        }

        if (!is_numeric($actualAmount) || (float) $actualAmount <= 0) {
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
            $facLock = $this->pdo->prepare('SELECT ma_co_so FROM co_so WHERE ma_co_so = :fid FOR UPDATE');
            $facLock->execute(['fid' => $facilityId]);

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

            // 7) Gọi BookingService::lockPayable($bookingId) để khóa và kiểm tra booking
            // Lưu ý: Nếu khách hàng đã nộp biên lai hoặc đơn đang ở CẦN ĐỐI SOÁT, trạng thái booking là CHO_XAC_NHAN hoặc hết hạn.
            // Để lockPayable() kiểm tra an toàn theo contract của Phát, tạm thời chuẩn hóa về CHO_THANH_TOAN trong transaction.
            $bPre = $this->pdo->prepare('SELECT trang_thai_dat_san, het_han_giu_san_luc FROM dat_san WHERE ma_dat_san = :id FOR UPDATE');
            $bPre->execute(['id' => $bookingId]);
            $preRow = $bPre->fetch(PDO::FETCH_ASSOC);

            // Kiểm tra xem đơn này có đang ở trạng thái CẦN ĐỐI SOÁT hay không
            $checkP = $this->pdo->prepare('SELECT trang_thai FROM thanh_toan WHERE ma_dat_san = :id');
            $checkP->execute(['id' => $bookingId]);
            $currentPaymentStatus = $checkP->fetchColumn();
            $isReconcile = ($currentPaymentStatus === 'CAN_DOI_SOAT');

            $nowStr = self::now()->format('Y-m-d H:i:s');
            $wasNormalized = false;
            $originalStatus = $preRow['trang_thai_dat_san'] ?? 'CHO_THANH_TOAN';
            $originalExpires = $preRow['het_han_giu_san_luc'] ?? null;

            if ($preRow && ($preRow['trang_thai_dat_san'] === 'CHO_XAC_NHAN' || $isReconcile)) {
                $futureExpires = date('Y-m-d H:i:s', strtotime('+30 minutes', strtotime($nowStr)));
                $this->pdo->prepare("UPDATE dat_san SET trang_thai_dat_san = 'CHO_THANH_TOAN', het_han_giu_san_luc = :exp WHERE ma_dat_san = :id")
                    ->execute(['exp' => $futureExpires, 'id' => $bookingId]);
                $wasNormalized = true;
            }

            try {
                $booking = $this->bookingService->lockPayable($bookingId);
            } catch (HttpException $e) {
                if ($wasNormalized) {
                    $this->pdo->prepare("UPDATE dat_san SET trang_thai_dat_san = :st, het_han_giu_san_luc = :exp WHERE ma_dat_san = :id")
                        ->execute(['st' => $originalStatus, 'exp' => $originalExpires, 'id' => $bookingId]);
                }

                // Tự động chuyển booking sang HET_HAN nếu đã hết hạn giữ chỗ (CHỈ KHI KHÔNG PHẢI CAN_DOI_SOAT)
                if (!$isReconcile) {
                    $bCheck = $this->pdo->prepare('SELECT het_han_giu_san_luc, trang_thai_dat_san FROM dat_san WHERE ma_dat_san = :id');
                    $bCheck->execute(['id' => $bookingId]);
                    $bRow = $bCheck->fetch(PDO::FETCH_ASSOC);

                    if ($bRow && !empty($bRow['het_han_giu_san_luc']) && $bRow['het_han_giu_san_luc'] <= $nowStr) {
                        $this->pdo->prepare("UPDATE dat_san SET trang_thai_dat_san = 'HET_HAN' WHERE ma_dat_san = :id AND trang_thai_dat_san IN ('CHO_THANH_TOAN', 'CHO_XAC_NHAN')")->execute(['id' => $bookingId]);
                        $this->pdo->prepare("UPDATE thanh_toan SET trang_thai = 'HET_HAN' WHERE ma_dat_san = :id AND trang_thai = 'CHO_THANH_TOAN'")->execute(['id' => $bookingId]);
                    }
                }

                throw new ValidationException([
                    'booking' => $e->getMessage() ?: 'Đơn đặt sân đã hết hạn giữ chỗ hoặc không đủ điều kiện để xác nhận.'
                ]);
            }

            // C. Khóa và kiểm tra thông tin thanh toán tương ứng
            $pStmt = $this->pdo->prepare('SELECT * FROM thanh_toan WHERE ma_dat_san = :id FOR UPDATE');
            $pStmt->execute(['id' => $bookingId]);
            $payment = $pStmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                throw new ValidationException(['payment' => 'Đơn đặt sân chưa có yêu cầu thanh toán hợp lệ.']);
            }

            // D. Chống xác nhận trùng lặp
            if ($booking['trang_thai_dat_san'] === 'DA_XAC_NHAN' || $payment['trang_thai'] === 'THANH_CONG') {
                throw new ValidationException(['booking' => 'Đơn đặt sân này đã được xác nhận thanh toán trước đó.']);
            }
            if ($booking['trang_thai_dat_san'] === 'HOAN_THANH') {
                throw new ValidationException(['booking' => 'Đơn đặt sân này đã hoàn thành.']);
            }

            // E. Kiểm tra booking đã bị hủy hoặc hết hạn
            if ($booking['trang_thai_dat_san'] === 'DA_HUY') {
                throw new ValidationException(['booking' => 'Đơn đặt sân đã bị hủy. Không thể xác nhận booking.']);
            }
            if ($booking['trang_thai_dat_san'] === 'HET_HAN') {
                throw new ValidationException([
                    'booking' => 'Đơn đặt sân đã hết hạn giữ chỗ. Sân có thể đã được giải phóng cho khách khác. Vui lòng sử dụng tính năng "Đánh dấu Cần đối soát" để xử lý số tiền đã nhận.'
                ]);
            }

            // F. Kiểm tra thời hạn giữ sân tại thời điểm bấm xác nhận
            // Tuyệt đối không kiểm tra hết hạn nếu đang trong tiến trình CẦN ĐỐI SOÁT (vì trong lúc đối soát phải giữ sân đó)
            $nowStr = self::now()->format('Y-m-d H:i:s');
            if (!$isReconcile && !empty($booking['het_han_giu_san_luc']) && $booking['het_han_giu_san_luc'] <= $nowStr) {
                // Tự động chuyển booking sang HET_HAN nếu đã quá hạn
                $this->pdo->prepare("UPDATE dat_san SET trang_thai_dat_san = 'HET_HAN' WHERE ma_dat_san = :id AND trang_thai_dat_san = 'CHO_THANH_TOAN'")->execute(['id' => $bookingId]);
                $this->pdo->prepare("UPDATE thanh_toan SET trang_thai = 'HET_HAN' WHERE ma_thanh_toan = :id AND trang_thai = 'CHO_THANH_TOAN'")->execute(['id' => $payment['ma_thanh_toan']]);
                throw new ValidationException([
                    'booking' => "Đơn đặt sân đã hết hạn giữ chỗ lúc {$booking['het_han_giu_san_luc']}. Không thể xác nhận đặt sân. Sân có thể đã được giải phóng cho khách khác. Vui lòng chuyển sang 'Cần đối soát' để xử lý số tiền đã nhận."
                ]);
            }

            // G. Kiểm tra tính duy nhất của mã giao dịch ngân hàng (Chống xử lý lặp mã ngân hàng)
            $checkTxnStmt = $this->pdo->prepare('SELECT ma_thanh_toan FROM thanh_toan WHERE ma_giao_dich = :txn_id AND ma_thanh_toan != :payment_id');
            $checkTxnStmt->execute(['txn_id' => $cleanTxnId, 'payment_id' => $payment['ma_thanh_toan']]);
            if ($checkTxnStmt->fetchColumn()) {
                throw new ValidationException([
                    'ma_giao_dich' => "Mã giao dịch ngân hàng '$cleanTxnId' đã được ghi nhận cho một đơn thanh toán khác. Vui lòng kiểm tra lại biên lai."
                ]);
            }

            // H. Kiểm tra số tiền thực nhận có khớp chính xác số tiền cần thanh toán không
            $expectedAmount = number_format((float) $payment['so_tien'], 2, '.', '');
            if ($formattedActual !== $expectedAmount) {
                throw new ValidationException([
                    'so_tien_thuc_nhan' => "Số tiền thực nhận ({$formattedActual} đ) KHÔNG KHỚP với số tiền đơn đặt ({$expectedAmount} đ). Theo quy định, không thể xác nhận booking khi sai tiền. Vui lòng chuyển sang 'Cần đối soát'."
                ]);
            }

            // 3. Thực thi cập nhật bảng thanh_toan -> THANH_CONG
            $existingReceipt = ReceiptImage::findForBooking($bookingId, $payment['ghi_chu'] ?? '');
            $finalPaymentNote = $cleanNote;
            if ($existingReceipt) {
                $finalPaymentNote = ReceiptImage::appendReceiptToNote($cleanNote, $existingReceipt);
            }

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

            // 4. Thực thi cập nhật bảng dat_san -> DA_XAC_NHAN
            $staffParam = $scope['staff_id'];
            $updateBookingStmt = $this->pdo->prepare(
                'UPDATE dat_san
                 SET trang_thai_dat_san = :status,
                     ma_nhan_vien = :staff_id
                 WHERE ma_dat_san = :id'
            );
            $updateBookingStmt->execute([
                'status' => 'DA_XAC_NHAN',
                'staff_id' => $staffParam,
                'id' => $bookingId,
            ]);

            // 5. Trừ tồn kho dịch vụ thực tế (Chỉ với dịch vụ có quản lý số lượng tồn)
            $serviceStmt = $this->pdo->prepare(
                'SELECT c.ma_dich_vu, c.so_luong, d.ten_dich_vu, d.so_luong_ton
                 FROM chi_tiet_dich_vu_dat c
                 JOIN dich_vu d ON d.ma_dich_vu = c.ma_dich_vu
                 WHERE c.ma_dat_san = :id'
            );
            $serviceStmt->execute(['id' => $bookingId]);
            $services = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);

            $deductStmt = $this->pdo->prepare(
                'UPDATE dich_vu
                 SET so_luong_ton = so_luong_ton - :qty
                 WHERE ma_dich_vu = :service_id
                   AND so_luong_ton IS NOT NULL
                   AND so_luong_ton >= :qty'
            );

            foreach ($services as $srv) {
                $qty = (int) $srv['so_luong'];
                if ($qty <= 0) {
                    continue;
                }

                // Dịch vụ không quản lý tồn kho (so_luong_ton === null) -> Bỏ qua, không trừ kho
                if ($srv['so_luong_ton'] === null) {
                    continue;
                }

                $deductStmt->execute([
                    'qty' => $qty,
                    'service_id' => (int) $srv['ma_dich_vu'],
                ]);

                // Kiểm tra kết quả rowCount() sau UPDATE trừ tồn kho
                if ($deductStmt->rowCount() === 0) {
                    $svcName = $srv['ten_dich_vu'] ?? ('#' . $srv['ma_dich_vu']);
                    $curStock = (int) $srv['so_luong_ton'];
                    throw new ValidationException([
                        'inventory' => "Dịch vụ '{$svcName}' không đủ số lượng tồn kho để hoàn tất đơn (cần {$qty}, hiện còn {$curStock}). Đã hủy thao tác xác nhận."
                    ]);
                }
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

        $this->pdo->beginTransaction();

        try {
            $pStmt = $this->pdo->prepare('SELECT * FROM thanh_toan WHERE ma_dat_san = :id FOR UPDATE');
            $pStmt->execute(['id' => $bookingId]);
            $payment = $pStmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                throw new ValidationException(['payment' => 'Không tìm thấy yêu cầu thanh toán cho đơn này.']);
            }

            // Kiểm tra phân quyền cơ sở / công ty (Anti-IDOR)
            $preStmt = $this->pdo->prepare('SELECT ma_co_so FROM dat_san WHERE ma_dat_san = :id');
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

            if ($payment['trang_thai'] === 'THANH_CONG') {
                throw new ValidationException(['payment' => 'Giao dịch đã xác nhận thành công trước đó, không thể chuyển sang Cần đối soát.']);
            }

            // Cập nhật trạng thái thanh toán sang CAN_DOI_SOAT
            $existingReceipt = ReceiptImage::findForBooking($bookingId, $payment['ghi_chu'] ?? '');
            $finalReconcileNote = $cleanReason;
            if ($existingReceipt) {
                $finalReconcileNote = ReceiptImage::appendReceiptToNote($cleanReason, $existingReceipt);
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
            $cleanReason = 'Hủy sau khi đối soát không thành công / Khách không chuyển phần tiền còn lại';
        }
        if (mb_strlen($cleanReason, 'UTF-8') > 500) {
            throw new ValidationException(['ly_do_huy' => 'Lý do không vượt quá 500 ký tự.']);
        }

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
                    $finalPayNote = ReceiptImage::appendReceiptToNote($cleanReason, $existingReceipt);
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
