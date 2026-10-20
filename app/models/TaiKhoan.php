<?php

namespace App\Models;

use PDO;

class TaiKhoan
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tai_khoan WHERE ten_dang_nhap = :username');
        $stmt->execute(['username' => $username]);
        return $stmt->fetch() ?: null;
    }

    public function identity(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT tk.ma_tai_khoan, tk.ten_dang_nhap, tk.trang_thai, vt.ten_vai_tro,
                kh.ma_khach_hang, kh.ho_ten AS ten_khach_hang, kh.trang_thai AS trang_thai_khach,
                nv.ma_nhan_vien, nv.ho_ten AS ten_nhan_vien, nv.trang_thai AS trang_thai_nhan_vien,
                nv.ma_cong_ty, nv.ma_co_so, cs.ma_cong_ty AS cong_ty_cua_co_so,
                ct.trang_thai AS trang_thai_cong_ty, cs.trang_thai AS trang_thai_co_so
             FROM tai_khoan tk
             JOIN vai_tro vt ON vt.ma_vai_tro = tk.ma_vai_tro
             LEFT JOIN khach_hang kh ON kh.ma_tai_khoan = tk.ma_tai_khoan
             LEFT JOIN nhan_vien nv ON nv.ma_tai_khoan = tk.ma_tai_khoan
             LEFT JOIN cong_ty_nhuong_quyen ct ON ct.ma_cong_ty = nv.ma_cong_ty
             LEFT JOIN co_so cs ON cs.ma_co_so = nv.ma_co_so
             WHERE tk.ma_tai_khoan = :id'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $username, string $hash, int $roleId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tai_khoan (ten_dang_nhap, mat_khau, ma_vai_tro)
             VALUES (:username, :hash, :role)'
        );
        $stmt->execute(['username' => $username, 'hash' => $hash, 'role' => $roleId]);
        return (int) $this->pdo->lastInsertId();
    }
}
