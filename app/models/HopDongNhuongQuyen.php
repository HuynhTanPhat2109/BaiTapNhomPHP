<?php

namespace App\Models;

use PDO;

class HopDongNhuongQuyen
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT hop_dong_nhuong_quyen.*, cong_ty_nhuong_quyen.ten_cong_ty
             FROM hop_dong_nhuong_quyen
             INNER JOIN cong_ty_nhuong_quyen
                 ON cong_ty_nhuong_quyen.ma_cong_ty = hop_dong_nhuong_quyen.ma_cong_ty
             ORDER BY hop_dong_nhuong_quyen.ma_hop_dong DESC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM hop_dong_nhuong_quyen WHERE ma_hop_dong = :id'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO hop_dong_nhuong_quyen (
                ma_cong_ty, so_hop_dong, ngay_ky, ngay_bat_dau,
                ngay_ket_thuc, phi_nhuong_quyen, ty_le_phi, noi_dung, trang_thai
             ) VALUES (
                :ma_cong_ty, :so_hop_dong, :ngay_ky, :ngay_bat_dau,
                :ngay_ket_thuc, :phi_nhuong_quyen, :ty_le_phi, :noi_dung, :trang_thai
             )'
        );
        $stmt->execute([
            'ma_cong_ty' => $data['ma_cong_ty'],
            'so_hop_dong' => $data['so_hop_dong'],
            'ngay_ky' => $data['ngay_ky'],
            'ngay_bat_dau' => $data['ngay_bat_dau'],
            'ngay_ket_thuc' => $data['ngay_ket_thuc'],
            'phi_nhuong_quyen' => $data['phi_nhuong_quyen'] ?? 0,
            'ty_le_phi' => $data['ty_le_phi'] ?? 0,
            'noi_dung' => $data['noi_dung'] ?? null,
            'trang_thai' => $data['trang_thai'] ?? 'HIEU_LUC',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE hop_dong_nhuong_quyen SET
                ma_cong_ty = :ma_cong_ty,
                so_hop_dong = :so_hop_dong,
                ngay_ky = :ngay_ky,
                ngay_bat_dau = :ngay_bat_dau,
                ngay_ket_thuc = :ngay_ket_thuc,
                phi_nhuong_quyen = :phi_nhuong_quyen,
                ty_le_phi = :ty_le_phi,
                noi_dung = :noi_dung,
                trang_thai = :trang_thai
             WHERE ma_hop_dong = :id'
        );
        $stmt->execute([
            'ma_cong_ty' => $data['ma_cong_ty'],
            'so_hop_dong' => $data['so_hop_dong'],
            'ngay_ky' => $data['ngay_ky'],
            'ngay_bat_dau' => $data['ngay_bat_dau'],
            'ngay_ket_thuc' => $data['ngay_ket_thuc'],
            'phi_nhuong_quyen' => $data['phi_nhuong_quyen'] ?? 0,
            'ty_le_phi' => $data['ty_le_phi'] ?? 0,
            'noi_dung' => $data['noi_dung'] ?? null,
            'trang_thai' => $data['trang_thai'],
            'id' => $id,
        ]);
        return $stmt->rowCount() === 1;
    }

    public function changeStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE hop_dong_nhuong_quyen SET trang_thai = :status WHERE ma_hop_dong = :id'
        );
        $stmt->execute(['status' => $status, 'id' => $id]);
        return $stmt->rowCount() === 1;
    }

    public function existsByContractNumber(string $soHopDong, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM hop_dong_nhuong_quyen WHERE so_hop_dong = :so_hop_dong';
        $params = ['so_hop_dong' => $soHopDong];

        if ($excludeId !== null) {
            $sql .= ' AND ma_hop_dong <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }
}
