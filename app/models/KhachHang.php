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
}
