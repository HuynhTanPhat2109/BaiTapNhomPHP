<?php
namespace App\Services;

use App\Models\ThanhToan;
use Core\HttpException;
use PDO;

final class BookingReceiptService
{
    public function __construct(private PDO $pdo, private ?string $storageRoot = null) {}

    public function info(array $payment): ?array
    {
        $note = $payment['ghi_chu'] ?? null;
        $path = ReceiptImage::getReceiptFromNote($note) ?? ($payment['anh_minh_chung'] ?? null);
        if (!$path) return null;
        $file = ReceiptImage::file($path, (int) $payment['ma_dat_san'], $this->storageRoot);
        $sentAt = ReceiptImage::sentAt($note) ?? ($payment['minh_chung_gui_luc'] ?? null);
        return $file ? $file + ['sent_at' => $sentAt,
            'version' => hash('sha256', $path . '|' . ($sentAt ?? ''))] : null;
    }

    public function authorized(int $id, array $user): array
    {
        if (!AuthService::isActive($user)) throw new HttpException(403, 'Vui lòng đăng nhập tài khoản hoạt động.');
        $scope = ($user['ten_vai_tro'] ?? '') === 'KHACH_HANG' ? null : BookingManagementService::resolveScope($user);
        $payment = (new ThanhToan($this->pdo))->findByBookingId($id);
        if (!$payment) throw new HttpException(404, 'Không tìm thấy minh chứng.');
        if ($scope === null) {
            if ((int) ($user['ma_khach_hang'] ?? 0) !== (int) $payment['ma_khach_hang']) throw new HttpException(404, 'Không tìm thấy minh chứng.');
        } else {
            if ($scope['facility'] !== null && $scope['facility'] !== (int) $payment['ma_co_so']) throw new HttpException(404, 'Không tìm thấy minh chứng.');
            $stmt = $this->pdo->prepare('SELECT ma_cong_ty FROM co_so WHERE ma_co_so = :id');
            $stmt->execute(['id' => $payment['ma_co_so']]);
            if ($scope['company'] !== null && $scope['company'] !== (int) $stmt->fetchColumn()) throw new HttpException(404, 'Không tìm thấy minh chứng.');
        }
        return $this->info($payment) ?? throw new HttpException(404, 'Không tìm thấy minh chứng.');
    }

    /** Called again under booking/payment locks before stock or payment writes. */
    public function requireConfirmation(array $payment): array
    {
        if (!array_key_exists('xac_nhan_thu_cong_luc', $payment)) {
            throw new ValidationException(['receipt' => 'Cần áp dụng migration 20261105_manual_receipt_confirmation.sql trước khi xác nhận bằng minh chứng.']);
        }
        if (!in_array($payment['trang_thai'] ?? '', ['CHO_THANH_TOAN', 'CHO_XAC_NHAN'], true)
            || !BookingManagementService::actions($payment + ['trang_thai_thanh_toan' => $payment['trang_thai'] ?? ''])['confirm']) {
            throw new ValidationException(['receipt' => 'Booking/payment đã xử lý, hết hạn hoặc cần đối soát riêng.']);
        }
        if ($payment['so_tien_thuc_nhan'] !== null && (float) $payment['so_tien_thuc_nhan'] !== (float) $payment['so_tien']) {
            throw new ValidationException(['receipt' => 'Số tiền đã ghi nhận không khớp. Vui lòng xem đối soát.']);
        }
        return $this->info($payment) ?? throw new ValidationException(['receipt' => 'Khách hàng chưa gửi minh chứng chuyển khoản hợp lệ.']);
    }

    public function upload(int $id, int $customerId, ?array $file): void
    {
        $saved = null;
        $this->pdo->beginTransaction();
        try {
            $model = new ThanhToan($this->pdo);
            $booking = $model->findBooking($id, true);
            if (!$booking || (int) $booking['ma_khach_hang'] !== $customerId) throw new HttpException(404, 'Không tìm thấy booking.');
            $payment = $model->findByBookingId($id, true);
            if (!$payment || !in_array($booking['trang_thai_dat_san'], ['CHO_THANH_TOAN', 'CHO_XAC_NHAN'], true)
                || empty($booking['het_han_giu_san_luc']) || $booking['het_han_giu_san_luc'] <= BookingManagementService::now()->format('Y-m-d H:i:s')
                || !in_array($payment['trang_thai'], ['CHO_THANH_TOAN', 'CHO_XAC_NHAN'], true)) {
                throw new ValidationException(['bien_lai' => 'Booking không còn đủ điều kiện gửi minh chứng. Chuyển muộn cần liên hệ cơ sở để đối soát.']);
            }
            $saved = ReceiptImage::store($file, $id, $this->storageRoot);
            $sentAt = BookingManagementService::now()->format('Y-m-d H:i:s');
            // Duy's existing schema stores the explicit image link in ghi_chu.
            // Upload must not depend on the separate manual-confirmation migration.
            $params = ['note' => ReceiptImage::appendReceiptToNote($payment['ghi_chu'] ?? null, $saved, $sentAt), 'id' => $payment['ma_thanh_toan']];
            $columns = '';
            if (array_key_exists('anh_minh_chung', $payment) && array_key_exists('minh_chung_gui_luc', $payment)) {
                $columns = ', anh_minh_chung=:path, minh_chung_gui_luc=:sent';
                $params += ['path' => $saved, 'sent' => $sentAt];
            }
            $stmt = $this->pdo->prepare('UPDATE thanh_toan SET ghi_chu=:note' . $columns . ' WHERE ma_thanh_toan=:id');
            $stmt->execute($params);
            // Receipt submission is separate from booking confirmation. Keep its original
            // pending state/deadline so existing six-minute court and stock holds still apply.
            if ($booking['het_han_giu_san_luc'] <= BookingManagementService::now()->format('Y-m-d H:i:s')) throw new ValidationException(['bien_lai' => 'Thời hạn giữ sân đã kết thúc.']);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($saved && ($image = ReceiptImage::file($saved, $id, $this->storageRoot))) unlink($image['absolute']);
            throw $e;
        }
    }
}
