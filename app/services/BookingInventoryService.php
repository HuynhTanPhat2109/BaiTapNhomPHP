<?php
namespace App\Services;

use App\Models\Booking;
use Core\HttpException;
use PDO;

final class BookingInventoryService
{
    public function __construct(private PDO $pdo) {}

    private function rows(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Internal payment hook, NOT independently idempotent.
     * Duy verifies payment and locks the pending booking BEFORE calling this ONCE.
     * Deduction, verified payment and conditional confirmation MUST commit together.
     * No booking/payment writes or transaction management here; caller rolls back ANY failure.
     */
    public function deductForPayment(int $id): array
    {
        if (!$this->pdo->inTransaction()) throw new \LogicException('Inventory requires the payment transaction');
        if ($id <= 0) throw new HttpException(404, 'Không tìm thấy booking.');
        $model = new Booking($this->pdo);
        $booking = $model->find($id, true);
        if (!$booking) throw new HttpException(404, 'Không tìm thấy booking.');
        $this->requirePayable($booking);
        // Snapshot quantities/units only; never reprice or modify the saved details.
        $lines = $this->rows('SELECT ma_dich_vu, don_vi_tinh_snapshot, so_luong FROM chi_tiet_dich_vu_dat
            WHERE ma_dat_san=? ORDER BY ma_dich_vu', [$id]);
        $deducted = []; $untracked = [];
        foreach ($lines as $line) {
            $serviceId = (int) $line['ma_dich_vu'];
            $quantity = filter_var($line['so_luong'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
            if ($quantity === false) throw new HttpException(409, 'Số lượng snapshot không hợp lệ.');
            $service = $model->lock('dich_vu', 'ma_dich_vu', $serviceId);
            if (!$service || (int) $service['ma_co_so'] !== (int) $booking['ma_co_so']) throw new HttpException(409, 'Dịch vụ không thuộc cơ sở của booking.');
            if ($service['don_vi_tinh'] !== $line['don_vi_tinh_snapshot']) {
                throw new HttpException(409, 'Loại hoặc đơn vị dịch vụ cần được kiểm tra trước khi xuất tồn.');
            }
            if ($service['so_luong_ton'] === null) { $untracked[] = $serviceId; continue; }
            $stock = (int) $service['so_luong_ton'];
            if ($stock < $quantity) throw new HttpException(409, 'Không đủ tồn dịch vụ để xác nhận thanh toán.');
            $deducted[] = ['service_id' => $serviceId, 'quantity' => $quantity, 'stock_before' => $stock,
                'stock_after' => $stock - $quantity];
        }

        // Locks may have waited past the hold deadline. Duy must also recheck at confirmation.
        $this->requirePayable($booking);
        // Validate all lines before the first write; an SQL failure still requires caller rollback.
        $stmt = $this->pdo->prepare('UPDATE dich_vu SET so_luong_ton=so_luong_ton-?
            WHERE ma_dich_vu=? AND ma_co_so=? AND so_luong_ton IS NOT NULL AND so_luong_ton>=?');
        foreach ($deducted as $line) {
            $stmt->execute([$line['quantity'], $line['service_id'], (int) $booking['ma_co_so'], $line['quantity']]);
            if ($stmt->rowCount() !== 1) throw new HttpException(409, 'Tồn dịch vụ vừa thay đổi. Cần rollback thanh toán.');
        }
        return ['booking_id' => $id, 'deducted' => $deducted, 'untracked' => $untracked];
    }

    private function requirePayable(array $booking): void
    {
        if ($booking['trang_thai_dat_san'] !== BookingService::PENDING
            || empty($booking['het_han_giu_san_luc'])
            || $booking['het_han_giu_san_luc'] <= CourtScheduleService::now()->format('Y-m-d H:i:s')) {
            throw new HttpException(409, 'Booking hết hạn hoặc không còn chờ thanh toán. Không được xuất tồn.');
        }
    }
}
