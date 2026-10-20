<?php

namespace App\Models;

use PDO;

final class KhachHang
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(int $accountId, string $name, string $phone, ?string $email): void
    {
        // Email is nullable and NOT UNIQUE in the current schema.
        $stmt = $this->pdo->prepare(
            'INSERT INTO khach_hang (ma_tai_khoan, ho_ten, so_dien_thoai, email)
             VALUES (:account, :name, :phone, :email)'
        );
        $stmt->execute(['account' => $accountId, 'name' => $name, 'phone' => $phone, 'email' => $email]);
    }

    public function updateProfile(int $accountId, string $name, string $phone, ?string $email,
        ?string $birthDate, ?string $gender): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE khach_hang SET ho_ten = :name, so_dien_thoai = :phone, email = :email,
                ngay_sinh = :birth_date, gioi_tinh = :gender
             WHERE ma_tai_khoan = :account AND trang_thai = :active'
        );
        $stmt->execute(['name' => $name, 'phone' => $phone, 'email' => $email,
            'birth_date' => $birthDate, 'gender' => $gender,
            'account' => $accountId, 'active' => 'HOAT_DONG']);
    }
}
