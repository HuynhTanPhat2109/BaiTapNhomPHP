<?php

namespace App\Models;

use PDO;

final class CoSo
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(): array
    {
        return $this->pdo->query(
            'SELECT f.*, c.ten_cong_ty, c.trang_thai AS trang_thai_cong_ty,
                (SELECT COUNT(*) FROM san s WHERE s.ma_co_so = f.ma_co_so) AS so_san
             FROM co_so f JOIN cong_ty_nhuong_quyen c ON c.ma_cong_ty = f.ma_cong_ty ORDER BY f.ma_co_so DESC'
        )->fetchAll();
    }

    public function find(int $id, bool $lock = false): ?array
    {
        $stmt = $this->pdo->prepare($lock
            ? 'SELECT * FROM co_so WHERE ma_co_so = :id FOR UPDATE'
            : 'SELECT f.*, c.ten_cong_ty, c.trang_thai AS trang_thai_cong_ty FROM co_so f
               JOIN cong_ty_nhuong_quyen c ON c.ma_cong_ty = f.ma_cong_ty WHERE f.ma_co_so = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function company(int $id, bool $lock = false): ?array
    {
        $stmt = $this->pdo->prepare('SELECT ma_cong_ty, ten_cong_ty, trang_thai FROM cong_ty_nhuong_quyen WHERE ma_cong_ty = :id' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function companies(?int $currentId = null): array
    {
        $stmt = $this->pdo->prepare("SELECT ma_cong_ty, ten_cong_ty, trang_thai FROM cong_ty_nhuong_quyen
            WHERE trang_thai = 'HOAT_DONG' OR ma_cong_ty = :current ORDER BY ten_cong_ty");
        $stmt->execute(['current' => $currentId ?? 0]);
        return $stmt->fetchAll();
    }

    public function options(?int $currentId = null): array
    {
        $stmt = $this->pdo->prepare("SELECT f.ma_co_so, f.ten_co_so, f.trang_thai, c.ten_cong_ty,
                c.trang_thai AS trang_thai_cong_ty FROM co_so f
            JOIN cong_ty_nhuong_quyen c ON c.ma_cong_ty = f.ma_cong_ty
            WHERE (f.trang_thai = 'HOAT_DONG' AND c.trang_thai = 'HOAT_DONG') OR f.ma_co_so = :current
            ORDER BY c.ten_cong_ty, f.ten_co_so");
        $stmt->execute(['current' => $currentId ?? 0]);
        return $stmt->fetchAll();
    }

    public function lockReferences(int $id): void
    {
        // Fixed identifiers, never request input. Lock courts before booking headers.
        foreach (['san' => 'ma_san', 'nhan_vien' => 'ma_nhan_vien', 'bang_gia' => 'ma_bang_gia',
            'dat_san' => 'ma_dat_san', 'dich_vu' => 'ma_dich_vu'] as $table => $key) {
            $stmt = $this->pdo->prepare("SELECT $key FROM $table WHERE ma_co_so = :id ORDER BY $key FOR UPDATE");
            $stmt->execute(['id' => $id]);
            $stmt->fetchAll();
        }
    }

    public function related(int $id): array
    {
        $counts = [];
        foreach (['san', 'nhan_vien', 'bang_gia', 'dat_san', 'dich_vu'] as $table) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM $table WHERE ma_co_so = :id");
            $stmt->execute(['id' => $id]);
            $counts[$table] = (int) $stmt->fetchColumn();
        }
        foreach ([
            'san_dang_dung' => ['san', "trang_thai IN ('HOAT_DONG', 'BAO_TRI')"],
            'nhan_vien_dang_lam' => ['nhan_vien', "trang_thai = 'DANG_LAM'"],
            'bang_gia_dang_dung' => ['bang_gia', "trang_thai = 'DANG_AP_DUNG'"],
            'dich_vu_dang_dung' => ['dich_vu', "trang_thai = 'DANG_CUNG_CAP'"],
        ] as $name => [$table, $condition]) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM $table WHERE ma_co_so = :id AND $condition");
            $stmt->execute(['id' => $id]);
            $counts[$name] = (int) $stmt->fetchColumn();
        }
        $counts['booking_hieu_luc'] = $this->activeBookings($id);
        return $counts;
    }

    public function activeBookings(int $id, ?array $hours = null): int
    {
        // Occupancy follows the database contract: drafts/expired holds do not reserve a court.
        $sql = "SELECT COUNT(DISTINCT d.ma_dat_san) FROM dat_san d
            JOIN chi_tiet_dat_san ct ON ct.ma_dat_san = d.ma_dat_san
            WHERE d.ma_co_so = :id AND TIMESTAMP(ct.ngay_choi, ct.gio_ket_thuc) > NOW()
            AND (d.trang_thai_dat_san = 'DA_XAC_NHAN'
                OR (d.trang_thai_dat_san = 'CHO_THANH_TOAN' AND d.het_han_giu_san_luc > NOW()))";
        $params = ['id' => $id];
        if ($hours !== null) {
            $sql .= ' AND (ct.gio_bat_dau < :opening OR ct.gio_ket_thuc > :closing)';
            $params += ['opening' => $hours[0], 'closing' => $hours[1]];
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function hoursConflict(int $id, string $opening, string $closing): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM bang_gia WHERE ma_co_so = :id AND trang_thai = 'DANG_AP_DUNG'
            AND (gio_bat_dau < :opening OR gio_ket_thuc > :closing) LIMIT 1");
        $stmt->execute(['id' => $id, 'opening' => $opening, 'closing' => $closing]);
        return $stmt->fetchColumn() !== false || $this->activeBookings($id, [$opening, $closing]) > 0;
    }

    public function save(array $data, ?int $id): int
    {
        if ($id === null) {
            $sql = 'INSERT INTO co_so (ma_tinh, ten_tinh, ma_phuong_xa, ten_phuong_xa, anh_dai_dien, logo_clb, ma_cong_ty, ten_co_so, dia_chi, so_dien_thoai, email, gio_mo_cua, gio_dong_cua, mo_ta, trang_thai)
                VALUES (:ma_tinh, :ten_tinh, :ma_phuong_xa, :ten_phuong_xa, :anh_dai_dien, :logo_clb, :ma_cong_ty, :ten_co_so, :dia_chi, :so_dien_thoai, :email, :gio_mo_cua, :gio_dong_cua, :mo_ta, :trang_thai)';
        } else {
            $sql = 'UPDATE co_so SET ma_tinh = :ma_tinh, ten_tinh = :ten_tinh, ma_phuong_xa = :ma_phuong_xa, ten_phuong_xa = :ten_phuong_xa, anh_dai_dien = :anh_dai_dien, logo_clb = :logo_clb, ma_cong_ty = :ma_cong_ty, ten_co_so = :ten_co_so, dia_chi = :dia_chi,
                so_dien_thoai = :so_dien_thoai, email = :email, gio_mo_cua = :gio_mo_cua,
                gio_dong_cua = :gio_dong_cua, mo_ta = :mo_ta, trang_thai = :trang_thai WHERE ma_co_so = :id';
            $data['id'] = $id;
        }
        $this->pdo->prepare($sql)->execute($data);
        return $id ?? (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM co_so WHERE ma_co_so = :id')->execute(['id' => $id]);
    }
}
