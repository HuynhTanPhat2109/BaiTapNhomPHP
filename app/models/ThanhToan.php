<?php

namespace App\Models;

use PDO;

final class ThanhToan
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function supportsManualReceipt(): bool
    {
        try {
            $this->pdo->query('SELECT anh_minh_chung, minh_chung_gui_luc, ma_tai_khoan_xac_nhan, xac_nhan_thu_cong_luc FROM thanh_toan WHERE 1=0');
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Tạo bản ghi yêu cầu thanh toán mới.
     *
     * @param array $data ['ma_dat_san', 'ma_tham_chieu', 'so_tien', 'phuong_thuc', 'trang_thai', 'ghi_chu']
     * @return int ID thanh toán vừa tạo (ma_thanh_toan)
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO thanh_toan (
                ma_dat_san,
                ma_tham_chieu,
                so_tien,
                phuong_thuc,
                trang_thai,
                ngay_tao,
                ghi_chu
            ) VALUES (
                :ma_dat_san,
                :ma_tham_chieu,
                :so_tien,
                :phuong_thuc,
                :trang_thai,
                :ngay_tao,
                :ghi_chu
            )'
        );

        $now = date('Y-m-d H:i:s');
        $stmt->execute([
            'ma_dat_san' => (int) $data['ma_dat_san'],
            'ma_tham_chieu' => (string) $data['ma_tham_chieu'],
            'so_tien' => $data['so_tien'],
            'phuong_thuc' => $data['phuong_thuc'] ?? 'CHUYEN_KHOAN',
            'trang_thai' => $data['trang_thai'] ?? 'CHO_THANH_TOAN',
            'ngay_tao' => $data['ngay_tao'] ?? $now,
            'ghi_chu' => $data['ghi_chu'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Tìm thông tin thanh toán theo ID (kèm thông tin đặt sân và thời hạn giữ sân).
     */
    public function find(int $id, bool $lock = false): ?array
    {
        $sql = 'SELECT t.*,
                       d.ma_khach_hang,
                       d.ma_co_so,
                       d.bat_dau_giu_san_luc,
                       d.het_han_giu_san_luc,
                       d.tong_tien_tam_tinh,
                       d.trang_thai_dat_san
                FROM thanh_toan t
                JOIN dat_san d ON d.ma_dat_san = t.ma_dat_san
                WHERE t.ma_thanh_toan = :id';

        if ($lock) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Tìm thông tin thanh toán theo mã đặt sân (ma_dat_san).
     */
    public function findByBookingId(int $bookingId, bool $lock = false): ?array
    {
        $sql = 'SELECT t.*,
                       d.ma_khach_hang,
                       d.ma_co_so,
                       d.bat_dau_giu_san_luc,
                       d.het_han_giu_san_luc,
                       d.tong_tien_tam_tinh,
                       d.trang_thai_dat_san
                FROM thanh_toan t
                JOIN dat_san d ON d.ma_dat_san = t.ma_dat_san
                WHERE t.ma_dat_san = :booking_id';

        if ($lock) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['booking_id' => $bookingId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Tìm thông tin thanh toán theo mã tham chiếu (ma_tham_chieu).
     */
    public function findByReference(string $reference, bool $lock = false): ?array
    {
        $sql = 'SELECT t.*,
                       d.ma_khach_hang,
                       d.ma_co_so,
                       d.bat_dau_giu_san_luc,
                       d.het_han_giu_san_luc,
                       d.tong_tien_tam_tinh,
                       d.trang_thai_dat_san
                FROM thanh_toan t
                JOIN dat_san d ON d.ma_dat_san = t.ma_dat_san
                WHERE t.ma_tham_chieu = :reference';

        if ($lock) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['reference' => $reference]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Lấy thông tin booking từ bảng dat_san để phục vụ kiểm tra điều kiện thanh toán.
     */
    public function findBooking(int $bookingId, bool $lock = false): ?array
    {
        $sql = 'SELECT ma_dat_san,
                       ma_co_so,
                       ma_khach_hang,
                       ma_nhan_vien,
                       ngay_tao,
                       bat_dau_giu_san_luc,
                       het_han_giu_san_luc,
                       tong_tien_san,
                       tong_tien_dich_vu,
                       tong_tien_tam_tinh,
                       trang_thai_dat_san,
                       ghi_chu
                FROM dat_san
                WHERE ma_dat_san = :booking_id';

        if ($lock) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['booking_id' => $bookingId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Cập nhật thông tin thanh toán (trạng thái, mã giao dịch, số tiền thực nhận...).
     */
    public function updateStatus(int $id, string $status, array $extra = []): bool
    {
        $fields = ['trang_thai = :status'];
        $params = ['id' => $id, 'status' => $status];

        if (array_key_exists('ma_giao_dich', $extra)) {
            $fields[] = 'ma_giao_dich = :ma_giao_dich';
            $params['ma_giao_dich'] = $extra['ma_giao_dich'];
        }

        if (array_key_exists('so_tien_thuc_nhan', $extra)) {
            $fields[] = 'so_tien_thuc_nhan = :so_tien_thuc_nhan';
            $params['so_tien_thuc_nhan'] = $extra['so_tien_thuc_nhan'];
        }

        if (array_key_exists('ngay_thanh_toan', $extra)) {
            $fields[] = 'ngay_thanh_toan = :ngay_thanh_toan';
            $params['ngay_thanh_toan'] = $extra['ngay_thanh_toan'];
        }

        if (array_key_exists('ghi_chu', $extra)) {
            $fields[] = 'ghi_chu = :ghi_chu';
            $params['ghi_chu'] = $extra['ghi_chu'];
        }

        $sql = 'UPDATE thanh_toan SET ' . implode(', ', $fields) . ' WHERE ma_thanh_toan = :id';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Cập nhật mã tham chiếu và tái kích hoạt yêu cầu thanh toán (nếu yêu cầu cũ đã bị hủy hoặc hết hạn).
     */
    public function refreshPaymentRequest(int $id, string $newReference, string|float $amount, ?string $note = null): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE thanh_toan
             SET ma_tham_chieu = :new_reference,
                 so_tien = :amount,
                 trang_thai = :status,
                 ngay_tao = :now,
                 ngay_thanh_toan = NULL,
                 ma_giao_dich = NULL,
                 so_tien_thuc_nhan = NULL,
                 ghi_chu = :note
             WHERE ma_thanh_toan = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'new_reference' => $newReference,
            'amount' => $amount,
            'status' => 'CHO_THANH_TOAN',
            'now' => date('Y-m-d H:i:s'),
            'note' => $note,
        ]);
    }

    /**
     * Kiểm tra mã tham chiếu đã tồn tại hay chưa.
     */
    public function existsReference(string $reference): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM thanh_toan WHERE ma_tham_chieu = :reference');
        $stmt->execute(['reference' => $reference]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
