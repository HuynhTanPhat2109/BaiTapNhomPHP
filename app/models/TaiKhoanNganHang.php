<?php

namespace App\Models;

use PDO;

final class TaiKhoanNganHang
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Lấy toàn bộ tài khoản ngân hàng của một cơ sở cụ thể.
     */
    public function allByFacility(int $facilityId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tai_khoan_ngan_hang_co_so
             WHERE ma_co_so = :facility_id
             ORDER BY la_mac_dinh DESC, ma_tk_ngan_hang DESC'
        );
        $stmt->execute(['facility_id' => $facilityId]);
        return $stmt->fetchAll();
    }

    /**
     * Tìm thông tin tài khoản ngân hàng theo ID.
     */
    public function find(int $id, bool $lock = false): ?array
    {
        $sql = 'SELECT tk.*, cs.ten_co_so, cs.ma_cong_ty
                FROM tai_khoan_ngan_hang_co_so tk
                JOIN co_so cs ON cs.ma_co_so = tk.ma_co_so
                WHERE tk.ma_tk_ngan_hang = :id';

        if ($lock) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Lấy tài khoản ngân hàng mặc định đang hoạt động của cơ sở để nhận tiền đặt sân.
     */
    public function getDefaultByFacility(int $facilityId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM tai_khoan_ngan_hang_co_so
             WHERE ma_co_so = :facility_id
               AND trang_thai = 'HOAT_DONG'
             ORDER BY la_mac_dinh DESC, ma_tk_ngan_hang DESC
             LIMIT 1"
        );
        $stmt->execute(['facility_id' => $facilityId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Thêm mới tài khoản ngân hàng cho cơ sở.
     * Nếu là mặc định, các tài khoản khác của cơ sở sẽ được hủy mặc định.
     */
    public function create(array $data): int
    {
        $facilityId = (int) $data['ma_co_so'];
        $isDefault = !empty($data['la_mac_dinh']) ? 1 : 0;

        $inTx = $this->pdo->inTransaction();
        if (!$inTx) {
            $this->pdo->beginTransaction();
        }

        try {
            // Kiểm tra xem cơ sở đã có tài khoản nào chưa. Nếu là tài khoản đầu tiên thì tự động đặt làm mặc định
            $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM tai_khoan_ngan_hang_co_so WHERE ma_co_so = :facility_id');
            $countStmt->execute(['facility_id' => $facilityId]);
            $total = (int) $countStmt->fetchColumn();

            if ($total === 0) {
                $isDefault = 1;
            }

            if ($isDefault === 1) {
                $this->clearDefaults($facilityId);
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO tai_khoan_ngan_hang_co_so (
                    ma_co_so,
                    ten_ngan_hang,
                    ma_bin,
                    so_tai_khoan,
                    ten_chu_tai_khoan,
                    la_mac_dinh,
                    trang_thai,
                    ngay_tao
                ) VALUES (
                    :ma_co_so,
                    :ten_ngan_hang,
                    :ma_bin,
                    :so_tai_khoan,
                    :ten_chu_tai_khoan,
                    :la_mac_dinh,
                    :trang_thai,
                    :ngay_tao
                )'
            );

            $stmt->execute([
                'ma_co_so' => $facilityId,
                'ten_ngan_hang' => trim($data['ten_ngan_hang']),
                'ma_bin' => trim($data['ma_bin']),
                'so_tai_khoan' => trim($data['so_tai_khoan']),
                'ten_chu_tai_khoan' => mb_strtoupper(trim($data['ten_chu_tai_khoan']), 'UTF-8'),
                'la_mac_dinh' => $isDefault,
                'trang_thai' => $data['trang_thai'] ?? 'HOAT_DONG',
                'ngay_tao' => $data['ngay_tao'] ?? date('Y-m-d H:i:s'),
            ]);

            $newId = (int) $this->pdo->lastInsertId();

            if (!$inTx) {
                $this->pdo->commit();
            }

            return $newId;
        } catch (\Throwable $e) {
            if (!$inTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cập nhật thông tin tài khoản ngân hàng.
     */
    public function update(int $id, array $data): bool
    {
        $existing = $this->find($id);
        if (!$existing) {
            return false;
        }

        $facilityId = (int) $existing['ma_co_so'];
        $isDefault = isset($data['la_mac_dinh']) ? ((int) $data['la_mac_dinh']) : (int) $existing['la_mac_dinh'];

        $inTx = $this->pdo->inTransaction();
        if (!$inTx) {
            $this->pdo->beginTransaction();
        }

        try {
            if ($isDefault === 1) {
                $this->clearDefaults($facilityId, $id);
            }

            $stmt = $this->pdo->prepare(
                'UPDATE tai_khoan_ngan_hang_co_so
                 SET ten_ngan_hang = :ten_ngan_hang,
                     ma_bin = :ma_bin,
                     so_tai_khoan = :so_tai_khoan,
                     ten_chu_tai_khoan = :ten_chu_tai_khoan,
                     la_mac_dinh = :la_mac_dinh,
                     trang_thai = :trang_thai
                 WHERE ma_tk_ngan_hang = :id'
            );

            $result = $stmt->execute([
                'id' => $id,
                'ten_ngan_hang' => trim($data['ten_ngan_hang']),
                'ma_bin' => trim($data['ma_bin']),
                'so_tai_khoan' => trim($data['so_tai_khoan']),
                'ten_chu_tai_khoan' => mb_strtoupper(trim($data['ten_chu_tai_khoan']), 'UTF-8'),
                'la_mac_dinh' => $isDefault,
                'trang_thai' => $data['trang_thai'] ?? $existing['trang_thai'],
            ]);

            if (!$inTx) {
                $this->pdo->commit();
            }

            return $result;
        } catch (\Throwable $e) {
            if (!$inTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Đặt một tài khoản làm mặc định cho cơ sở.
     */
    public function setDefault(int $id, int $facilityId): bool
    {
        $inTx = $this->pdo->inTransaction();
        if (!$inTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $this->clearDefaults($facilityId);

            $stmt = $this->pdo->prepare(
                "UPDATE tai_khoan_ngan_hang_co_so
                 SET la_mac_dinh = 1, trang_thai = 'HOAT_DONG'
                 WHERE ma_tk_ngan_hang = :id AND ma_co_so = :facility_id"
            );
            $res = $stmt->execute(['id' => $id, 'facility_id' => $facilityId]);

            if (!$inTx) {
                $this->pdo->commit();
            }

            return $res;
        } catch (\Throwable $e) {
            if (!$inTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Thay đổi trạng thái tài khoản ngân hàng (HOAT_DONG <-> NGUNG).
     */
    public function toggleStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tai_khoan_ngan_hang_co_so
             SET trang_thai = :status
             WHERE ma_tk_ngan_hang = :id'
        );
        return $stmt->execute(['id' => $id, 'status' => $status]);
    }

    /**
     * Hủy trạng thái mặc định của các tài khoản khác thuộc cùng cơ sở.
     */
    private function clearDefaults(int $facilityId, ?int $exceptId = null): void
    {
        $sql = 'UPDATE tai_khoan_ngan_hang_co_so SET la_mac_dinh = 0 WHERE ma_co_so = :facility_id';
        $params = ['facility_id' => $facilityId];

        if ($exceptId !== null) {
            $sql .= ' AND ma_tk_ngan_hang <> :except_id';
            $params['except_id'] = $exceptId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }
}
