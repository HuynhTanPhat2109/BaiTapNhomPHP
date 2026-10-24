<?php

namespace App\Models;

use PDO;

final class San
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(?int $facilityId = null): array
    {
        $stmt = $this->pdo->prepare('SELECT s.*, f.ten_co_so, l.ten_loai_san, b.ten_bo_mon FROM san s
            JOIN co_so f ON f.ma_co_so = s.ma_co_so JOIN loai_san l ON l.ma_loai_san = s.ma_loai_san
            JOIN bo_mon b ON b.ma_bo_mon = l.ma_bo_mon'
            . ($facilityId === null ? '' : ' WHERE s.ma_co_so = :facility') . ' ORDER BY s.ma_san DESC');
        $stmt->execute($facilityId === null ? [] : ['facility' => $facilityId]);
        return $stmt->fetchAll();
    }

    public function page(array $filters): array
    {
        return AdminList::page($this->pdo, ' FROM san s
            JOIN co_so f ON f.ma_co_so = s.ma_co_so JOIN loai_san l ON l.ma_loai_san = s.ma_loai_san
            JOIN bo_mon b ON b.ma_bo_mon = l.ma_bo_mon', 's.*, f.ten_co_so, l.ten_loai_san, b.ten_bo_mon', 's.ma_san',
            ['q' => 's.ten_san', 'ma_co_so' => 's.ma_co_so', 'ma_loai_san' => 's.ma_loai_san', 'trang_thai' => 's.trang_thai'], $filters);
    }

    public function find(int $id, bool $lock = false): ?array
    {
        $stmt = $this->pdo->prepare($lock ? 'SELECT * FROM san WHERE ma_san = :id FOR UPDATE'
            : 'SELECT s.*, f.ten_co_so, f.ma_cong_ty, f.trang_thai AS trang_thai_co_so,
                c.ten_cong_ty, c.trang_thai AS trang_thai_cong_ty, l.ten_loai_san, l.ma_bo_mon,
                l.trang_thai AS trang_thai_loai_san, b.ten_bo_mon, b.trang_thai AS trang_thai_bo_mon FROM san s
                JOIN co_so f ON f.ma_co_so = s.ma_co_so JOIN cong_ty_nhuong_quyen c ON c.ma_cong_ty = f.ma_cong_ty
                JOIN loai_san l ON l.ma_loai_san = s.ma_loai_san JOIN bo_mon b ON b.ma_bo_mon = l.ma_bo_mon WHERE s.ma_san = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function types(?int $currentId = null): array
    {
        $stmt = $this->pdo->prepare("SELECT l.ma_loai_san, l.ten_loai_san, l.trang_thai,
                b.ten_bo_mon, b.trang_thai AS trang_thai_bo_mon FROM loai_san l
            JOIN bo_mon b ON b.ma_bo_mon = l.ma_bo_mon
            WHERE (l.trang_thai = 'HOAT_DONG' AND b.trang_thai = 'HOAT_DONG') OR l.ma_loai_san = :current
            ORDER BY b.ten_bo_mon, l.ten_loai_san");
        $stmt->execute(['current' => $currentId ?? 0]);
        return $stmt->fetchAll();
    }

    public function nameExists(int $facilityId, string $name, ?int $exceptId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM san WHERE ma_co_so = :facility AND ten_san = :name AND ma_san <> :id LIMIT 1');
        $stmt->execute(['facility' => $facilityId, 'name' => $name, 'id' => $exceptId ?? 0]);
        return $stmt->fetchColumn() !== false;
    }

    public function lockReferences(int $id): void
    {
        $stmt = $this->pdo->prepare('SELECT ma_chi_tiet FROM chi_tiet_dat_san WHERE ma_san = :id ORDER BY ma_chi_tiet FOR UPDATE');
        $stmt->execute(['id' => $id]);
        $stmt->fetchAll();
    }

    public function related(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM chi_tiet_dat_san WHERE ma_san = :id');
        $stmt->execute(['id' => $id]);
        $references = (int) $stmt->fetchColumn();
        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT d.ma_dat_san) FROM chi_tiet_dat_san ct
            JOIN dat_san d ON d.ma_dat_san = ct.ma_dat_san
            WHERE ct.ma_san = :id AND TIMESTAMP(ct.ngay_choi, ct.gio_ket_thuc) > NOW()
            AND (d.trang_thai_dat_san = 'DA_XAC_NHAN'
                OR (d.trang_thai_dat_san = 'CHO_THANH_TOAN' AND d.het_han_giu_san_luc > NOW()))");
        $stmt->execute(['id' => $id]);
        return ['chi_tiet_dat_san' => $references, 'booking_hieu_luc' => (int) $stmt->fetchColumn()];
    }

    public function save(array $data, ?int $id): int
    {
        if ($id === null) {
            $sql = 'INSERT INTO san (ma_co_so, ma_loai_san, ten_san, vi_tri, mo_ta, trang_thai)
                VALUES (:ma_co_so, :ma_loai_san, :ten_san, :vi_tri, :mo_ta, :trang_thai)';
        } else {
            $sql = 'UPDATE san SET ma_co_so = :ma_co_so, ma_loai_san = :ma_loai_san, ten_san = :ten_san,
                vi_tri = :vi_tri, mo_ta = :mo_ta, trang_thai = :trang_thai WHERE ma_san = :id';
            $data['id'] = $id;
        }
        $this->pdo->prepare($sql)->execute($data);
        return $id ?? (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM san WHERE ma_san = :id')->execute(['id' => $id]);
    }
}
