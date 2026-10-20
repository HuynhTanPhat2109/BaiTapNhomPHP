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
            'SELECT tk.ma_tai_khoan, tk.ten_dang_nhap, tk.trang_thai, tk.ngay_tao AS ngay_tao_tai_khoan, vt.ten_vai_tro,
                kh.ma_khach_hang, kh.ho_ten AS ten_khach_hang, kh.so_dien_thoai AS so_dien_thoai_khach,
                kh.email AS email_khach, kh.ngay_sinh AS ngay_sinh_khach, kh.gioi_tinh AS gioi_tinh_khach,
                kh.ngay_tao AS ngay_tao_khach, kh.trang_thai AS trang_thai_khach,
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

    public function passwordHash(int $accountId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT mat_khau FROM tai_khoan WHERE ma_tai_khoan = :id AND trang_thai = :active');
        $stmt->execute(['id' => $accountId, 'active' => 'HOAT_DONG']);
        $hash = $stmt->fetchColumn();
        return is_string($hash) ? $hash : null;
    }

    public function replacePassword(int $accountId, string $oldHash, string $newHash): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tai_khoan SET mat_khau = :new_hash
             WHERE ma_tai_khoan = :id AND BINARY mat_khau = BINARY :old_hash AND trang_thai = :active'
        );
        $stmt->execute(['new_hash' => $newHash, 'id' => $accountId,
            'old_hash' => $oldHash, 'active' => 'HOAT_DONG']);
        return $stmt->rowCount() === 1;
    }
}
