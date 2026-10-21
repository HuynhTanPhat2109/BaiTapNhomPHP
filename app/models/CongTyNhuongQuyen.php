<?php

namespace App\Models;

use PDO;

class CongTyNhuongQuyen
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM cong_ty_nhuong_quyen ORDER BY ma_cong_ty DESC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM cong_ty_nhuong_quyen WHERE ma_cong_ty = :id'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO cong_ty_nhuong_quyen (
                ten_cong_ty, ma_so_thue, nguoi_dai_dien, dia_chi,
                so_dien_thoai, email, ngay_tham_gia, trang_thai
             ) VALUES (
                :ten_cong_ty, :ma_so_thue, :nguoi_dai_dien, :dia_chi,
                :so_dien_thoai, :email, :ngay_tham_gia, :trang_thai
             )'
        );
        $stmt->execute([
            'ten_cong_ty' => $data['ten_cong_ty'],
            'ma_so_thue' => $data['ma_so_thue'] ?? null,
            'nguoi_dai_dien' => $data['nguoi_dai_dien'] ?? null,
            'dia_chi' => $data['dia_chi'],
            'so_dien_thoai' => $data['so_dien_thoai'] ?? null,
            'email' => $data['email'] ?? null,
            'ngay_tham_gia' => $data['ngay_tham_gia'] ?? null,
            'trang_thai' => $data['trang_thai'] ?? 'HOAT_DONG',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE cong_ty_nhuong_quyen SET
                ten_cong_ty = :ten_cong_ty,
                ma_so_thue = :ma_so_thue,
                nguoi_dai_dien = :nguoi_dai_dien,
                dia_chi = :dia_chi,
                so_dien_thoai = :so_dien_thoai,
                email = :email,
                ngay_tham_gia = :ngay_tham_gia,
                trang_thai = :trang_thai
             WHERE ma_cong_ty = :id'
        );
        $stmt->execute([
            'ten_cong_ty' => $data['ten_cong_ty'],
            'ma_so_thue' => $data['ma_so_thue'] ?? null,
            'nguoi_dai_dien' => $data['nguoi_dai_dien'] ?? null,
            'dia_chi' => $data['dia_chi'],
            'so_dien_thoai' => $data['so_dien_thoai'] ?? null,
            'email' => $data['email'] ?? null,
            'ngay_tham_gia' => $data['ngay_tham_gia'] ?? null,
            'trang_thai' => $data['trang_thai'],
            'id' => $id,
        ]);
        return $stmt->rowCount() === 1;
    }

    public function changeStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE cong_ty_nhuong_quyen SET trang_thai = :status WHERE ma_cong_ty = :id'
        );
        $stmt->execute(['status' => $status, 'id' => $id]);
        return $stmt->rowCount() === 1;
    }
}
