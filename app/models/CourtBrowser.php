<?php
namespace App\Models;

use PDO;

final class CourtBrowser
{
    public function __construct(private PDO $pdo) {}

    public function facilities(array $filters = []): array
    {
        $sql = "SELECT f.*, c.ten_cong_ty FROM co_so f JOIN cong_ty_nhuong_quyen c ON c.ma_cong_ty=f.ma_cong_ty
            WHERE f.trang_thai='HOAT_DONG' AND c.trang_thai='HOAT_DONG'";
        $params = [];
        if (($filters['ma_bo_mon'] ?? '') !== '') {
            $sql .= " AND EXISTS (SELECT 1 FROM san s JOIN loai_san l ON l.ma_loai_san=s.ma_loai_san
                JOIN bo_mon b ON b.ma_bo_mon=l.ma_bo_mon WHERE s.ma_co_so=f.ma_co_so
                AND s.trang_thai='HOAT_DONG' AND l.trang_thai='HOAT_DONG' AND b.trang_thai='HOAT_DONG' AND b.ma_bo_mon=:sport)";
            $params['sport'] = $filters['ma_bo_mon'];
        }
        if (($filters['q'] ?? '') !== '') {
            $sql .= " AND f.ten_co_so LIKE :q ESCAPE '!'";
            $params['q'] = '%' . strtr($filters['q'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        }
        foreach (['ma_tinh', 'ma_phuong_xa', 'ma_co_so'] as $field) {
            if (($filters[$field] ?? '') !== '') {
                $sql .= " AND f.$field = :$field";
                $params[$field] = $filters[$field];
            }
        }
        $stmt = $this->pdo->prepare($sql . ' ORDER BY f.ten_co_so, f.ma_co_so');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function courts(int $facility): array
    {
        $stmt = $this->pdo->prepare('SELECT s.ma_san, s.ten_san, s.vi_tri, s.mo_ta, s.trang_thai,
            l.ten_loai_san, l.trang_thai AS trang_thai_loai_san, b.ten_bo_mon, b.trang_thai AS trang_thai_bo_mon
            FROM san s JOIN loai_san l ON l.ma_loai_san=s.ma_loai_san JOIN bo_mon b ON b.ma_bo_mon=l.ma_bo_mon
            WHERE s.ma_co_so=:id ORDER BY b.ten_bo_mon, s.ten_san, s.ma_san');
        $stmt->execute(['id' => $facility]);
        return $stmt->fetchAll();
    }

    public function sports(): array
    {
        return $this->pdo->query("SELECT ma_bo_mon, ten_bo_mon FROM bo_mon WHERE trang_thai='HOAT_DONG' ORDER BY ten_bo_mon")->fetchAll();
    }

    public function priceList(int $facility, string $date): array
    {
        $stmt = $this->pdo->prepare("SELECT DISTINCT b.ma_bo_mon, b.ten_bo_mon, l.ma_loai_san, l.ten_loai_san,
            g.ma_bang_gia, g.loai_ngay, g.gio_bat_dau, g.gio_ket_thuc, g.don_gia, g.ngay_bat_dau, g.ngay_ket_thuc
            FROM san s JOIN loai_san l ON l.ma_loai_san=s.ma_loai_san
            JOIN bo_mon b ON b.ma_bo_mon=l.ma_bo_mon
            JOIN co_so f ON f.ma_co_so=s.ma_co_so
            JOIN cong_ty_nhuong_quyen c ON c.ma_cong_ty=f.ma_cong_ty
            LEFT JOIN bang_gia g ON g.ma_co_so=s.ma_co_so AND g.ma_loai_san=l.ma_loai_san
                AND g.trang_thai='DANG_AP_DUNG' AND g.ngay_bat_dau<=:first
                AND (g.ngay_ket_thuc IS NULL OR g.ngay_ket_thuc>=:last)
            WHERE s.ma_co_so=:facility AND s.trang_thai='HOAT_DONG'
                AND l.trang_thai='HOAT_DONG' AND b.trang_thai='HOAT_DONG'
                AND f.trang_thai='HOAT_DONG' AND c.trang_thai='HOAT_DONG'
            ORDER BY b.ten_bo_mon, l.ten_loai_san, g.gio_bat_dau, g.gio_ket_thuc, g.loai_ngay");
        $stmt->execute(['facility'=>$facility, 'first'=>$date, 'last'=>$date]);
        return $stmt->fetchAll();
    }

    /** Public catalog only: stock and internal booking reservations are not selected. */
    public function publicServices(int $facility): array
    {
        $stmt = $this->pdo->prepare("SELECT d.ten_dich_vu, d.loai_dich_vu, d.don_gia, d.don_vi_tinh
            FROM dich_vu d JOIN co_so f ON f.ma_co_so=d.ma_co_so
            JOIN cong_ty_nhuong_quyen c ON c.ma_cong_ty=f.ma_cong_ty
            WHERE d.ma_co_so=:facility AND d.trang_thai='DANG_CUNG_CAP'
                AND f.trang_thai='HOAT_DONG' AND c.trang_thai='HOAT_DONG'
            ORDER BY d.loai_dich_vu, d.ten_dich_vu, d.ma_dich_vu");
        $stmt->execute(['facility' => $facility]);
        return $stmt->fetchAll();
    }

    public function intervals(int $facility, string $date): array
    {
        // Public schedule deliberately excludes identities, contact information and money.
        $stmt = $this->pdo->prepare('SELECT ct.ma_san, ct.gio_bat_dau, ct.gio_ket_thuc,
            d.trang_thai_dat_san, d.het_han_giu_san_luc FROM chi_tiet_dat_san ct
            JOIN dat_san d ON d.ma_dat_san=ct.ma_dat_san JOIN san s ON s.ma_san=ct.ma_san
            WHERE s.ma_co_so=:facility AND d.ma_co_so=:booking_facility AND ct.ngay_choi=:date');
        $stmt->execute(['facility' => $facility, 'booking_facility' => $facility, 'date' => $date]);
        return $stmt->fetchAll();
    }
}
