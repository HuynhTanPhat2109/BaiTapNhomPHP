<?php
namespace App\Models;

use PDO;

final class Booking
{
    public function __construct(private PDO $pdo) {}

    private function rows(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function byKey(string $key): ?array
    {
        return $this->rows('SELECT * FROM dat_san WHERE khoa_yeu_cau = ?', [$key])[0] ?? null;
    }

    public function find(int $id, bool $lock = false): ?array
    {
        return $this->rows('SELECT * FROM dat_san WHERE ma_dat_san = ?' . ($lock ? ' FOR UPDATE' : ''), [$id])[0] ?? null;
    }

    public function visit(int $id): array
    {
        $facility = $this->rows('SELECT c.ten_co_so, c.dia_chi FROM co_so c
            JOIN dat_san b ON b.ma_co_so=c.ma_co_so WHERE b.ma_dat_san=?', [$id])[0] ?? [];
        $lines = $this->rows('SELECT d.ten_san_snapshot, d.ngay_choi, d.gio_bat_dau, d.gio_ket_thuc,
            m.ten_bo_mon FROM chi_tiet_dat_san d JOIN san s ON s.ma_san=d.ma_san
            JOIN loai_san l ON l.ma_loai_san=s.ma_loai_san JOIN bo_mon m ON m.ma_bo_mon=l.ma_bo_mon
            WHERE d.ma_dat_san=? ORDER BY d.ngay_choi,d.ma_san,d.gio_bat_dau', [$id]);
        return ['facility' => $facility, 'lines' => $lines];
    }

    // Caller holds the booking row lock. No stock write: pending reservations are derived.
    public function cancelPending(int $id): void
    {
        $this->pdo->prepare("UPDATE dat_san SET trang_thai_dat_san =
            CASE WHEN het_han_giu_san_luc <= CURRENT_TIMESTAMP THEN 'HET_HAN' ELSE 'DA_HUY' END
            WHERE ma_dat_san=? AND trang_thai_dat_san='CHO_THANH_TOAN'
            AND NOT EXISTS (SELECT 1 FROM thanh_toan WHERE ma_dat_san=? AND trang_thai='THANH_CONG')")
            ->execute([$id, $id]);
    }

    public function prices(int $facility, string $date, string $day): array
    {
        return $this->rows("SELECT * FROM bang_gia WHERE ma_co_so = ? AND loai_ngay = ?
            AND trang_thai = 'DANG_AP_DUNG' AND ngay_bat_dau <= ?
            AND (ngay_ket_thuc IS NULL OR ngay_ket_thuc >= ?) ORDER BY ma_bang_gia", [$facility, $day, $date, $date]);
    }

    public function services(int $facility): array
    {
        return $this->rows("SELECT * FROM dich_vu WHERE ma_co_so = ? AND trang_thai = 'DANG_CUNG_CAP' ORDER BY ma_dich_vu", [$facility]);
    }

    public function reserved(int $service, string $now, bool $lock = false): int
    {
        $rows = $this->rows("SELECT c.so_luong FROM chi_tiet_dich_vu_dat c
            JOIN dat_san b ON b.ma_dat_san = c.ma_dat_san WHERE c.ma_dich_vu = ?
            AND b.trang_thai_dat_san = 'CHO_THANH_TOAN' AND b.het_han_giu_san_luc > ? ORDER BY b.ma_dat_san" . ($lock ? ' FOR UPDATE' : ''), [$service, $now]);
        return (int) array_sum(array_column($rows, 'so_luong'));
    }

    public function customer(int $id): ?array
    {
        return $this->rows("SELECT k.*, t.trang_thai AS tai_khoan_trang_thai, v.ten_vai_tro FROM khach_hang k
            JOIN tai_khoan t ON t.ma_tai_khoan=k.ma_tai_khoan JOIN vai_tro v ON v.ma_vai_tro=t.ma_vai_tro
            WHERE k.ma_khach_hang=?", [$id])[0] ?? null;
    }

    // Identifiers are internal constants only; request values are always bound.
    public function lock(string $table, string $column, int $id): ?array
    {
        $allowed = ['khach_hang'=>'ma_khach_hang','tai_khoan'=>'ma_tai_khoan',
            'cong_ty_nhuong_quyen'=>'ma_cong_ty','co_so'=>'ma_co_so','bo_mon'=>'ma_bo_mon',
            'loai_san'=>'ma_loai_san','san'=>'ma_san','dich_vu'=>'ma_dich_vu'];
        if (($allowed[$table] ?? null) !== $column) throw new \LogicException('Invalid lock target');
        return $this->rows("SELECT * FROM $table WHERE $column = ? FOR UPDATE", [$id])[0] ?? null;
    }

    public function insert(array $booking, array $courts, array $services): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO dat_san
            (khoa_yeu_cau,dau_van_tay_yeu_cau,ma_co_so,ma_khach_hang,ngay_tao,bat_dau_giu_san_luc,het_han_giu_san_luc,
             tong_tien_san,tong_tien_dich_vu,tong_tien_tam_tinh,trang_thai_dat_san,ghi_chu)
            VALUES (?,?,?,?,?,?,?,?,?,?,'CHO_THANH_TOAN',?)");
        $stmt->execute(array_values($booking));
        $id = (int) $this->pdo->lastInsertId();
        $courtStatement = $this->pdo->prepare('INSERT INTO chi_tiet_dat_san
            (ma_dat_san,ma_san,ten_san_snapshot,ngay_choi,gio_bat_dau,gio_ket_thuc,don_gia,thanh_tien) VALUES (?,?,?,?,?,?,?,?)');
        foreach ($courts as $line) $courtStatement->execute([$id,$line['court'],$line['name'],$line['date'],$line['start'],$line['end'],$line['rate'],$line['amount']]);
        $serviceStatement = $this->pdo->prepare('INSERT INTO chi_tiet_dich_vu_dat
            (ma_dat_san,ma_dich_vu,ten_dich_vu_snapshot,don_vi_tinh_snapshot,so_luong,don_gia,thanh_tien) VALUES (?,?,?,?,?,?,?)');
        foreach ($services as $line) $serviceStatement->execute([$id,$line['id'],$line['name'],$line['unit'],$line['quantity'],$line['rate'],$line['amount']]);
        // Start the six minutes only after every detail has been written, immediately before commit.
        $this->pdo->prepare('UPDATE dat_san SET ngay_tao=CURRENT_TIMESTAMP, bat_dau_giu_san_luc=CURRENT_TIMESTAMP,
            het_han_giu_san_luc=DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 6 MINUTE) WHERE ma_dat_san=?')->execute([$id]);
        return $id;
    }

    public function expire(?int $id = null): int
    {
        $stmt = $this->pdo->prepare("UPDATE dat_san SET trang_thai_dat_san='HET_HAN'
            WHERE trang_thai_dat_san='CHO_THANH_TOAN' AND het_han_giu_san_luc <= CURRENT_TIMESTAMP" . ($id === null ? '' : ' AND ma_dat_san=?'));
        $stmt->execute($id === null ? [] : [$id]);
        return $stmt->rowCount();
    }
}
