<?php
namespace App\Models;

use PDO;

final class Booking
{
    private array $expiryReport = [];

    public function __construct(private PDO $pdo) {}

    /** Internal SQL fragment: b is always the booking alias; the caller binds server time. */
    public static function effectiveStatusSql(): string
    {
        return "CASE WHEN b.trang_thai_dat_san='CHO_THANH_TOAN'
            AND b.het_han_giu_san_luc <= ? AND " . self::unprotectedSql() . "
            THEN 'HET_HAN' ELSE b.trang_thai_dat_san END";
    }

    /** Read boundary only: tt is the payment alias; use the same cutoff as the booking. */
    public static function effectivePaymentStatusSql(): string
    {
        return "CASE WHEN tt.trang_thai='CHO_THANH_TOAN'
            AND (" . self::effectiveStatusSql() . ")='HET_HAN' AND " . self::unprotectedSql() . "
            THEN 'HET_HAN' ELSE tt.trang_thai END";
    }

    /** A single SELECT/cutoff keeps both display states consistent without writing on GET. */
    public function effectiveStates(int $id): ?array
    {
        $now = $this->serverTime();
        return $this->rows('SELECT ' . self::effectiveStatusSql() . ' AS booking_status, '
            . self::effectivePaymentStatusSql() . ' AS payment_status
            FROM dat_san b LEFT JOIN thanh_toan tt ON tt.ma_dat_san=b.ma_dat_san
            WHERE b.ma_dat_san=?', [$now, $now, $id])[0] ?? null;
    }

    private static function unprotectedSql(): string
    {
        // Legacy pending rows with a receipt link require manual review, not automatic expiry.
        // This is only a conservative guard; it never confirms payment or restores a slot.
        return "NOT EXISTS (SELECT 1 FROM thanh_toan p WHERE p.ma_dat_san=b.ma_dat_san
            AND (p.trang_thai='THANH_CONG' OR p.ghi_chu LIKE '%[BIEN_LAI:%'
                OR NULLIF(TRIM(p.anh_minh_chung),'') IS NOT NULL))";
    }

    public function findEffective(int $id): ?array
    {
        return $this->rows('SELECT b.*, ' . self::effectiveStatusSql() . ' AS trang_thai_dat_san
            FROM dat_san b WHERE b.ma_dat_san=?', [$this->serverTime(), $id])[0] ?? null;
    }

    private function serverTime(): string
    {
        return (string) $this->pdo->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();
    }

    public function expiryReport(): array { return $this->expiryReport; }

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

    /** Read-only effective status; never persist expiry, visit checkout or recalculate prices here. */
    public function history(int $customer, string $status = '', int $page = 1, string $from = '', string $to = '', string $sort = 'newest'): array
    {
        if ($customer < 1) throw new \InvalidArgumentException('Customer required.');
        $now = $this->serverTime();
        // One cutoff for cards, counts, filters and pages; no expiry writes on GET.
        $source = '(SELECT b.ma_dat_san,b.ma_khach_hang,b.ma_co_so,b.ngay_tao,b.ghi_chu,
            b.tong_tien_san,b.tong_tien_dich_vu,b.tong_tien_tam_tinh,' . self::effectiveStatusSql() . ' AS trang_thai_dat_san FROM dat_san b) h';
        $dateWhere = '';
        $dateParams = [];
        if ($from !== '') { $dateWhere .= ' AND h.ngay_tao>=?'; $dateParams[] = $from . ' 00:00:00'; }
        // ngay_tao is DATETIME without fractional seconds: include the entire last day.
        if ($to !== '') { $dateWhere .= ' AND h.ngay_tao<=?'; $dateParams[] = $to . ' 23:59:59'; }
        $counts = [];
        foreach ($this->rows('SELECT h.trang_thai_dat_san, COUNT(*) AS total FROM ' . $source . '
            WHERE h.ma_khach_hang=?' . $dateWhere . ' GROUP BY h.trang_thai_dat_san', [$now, $customer, ...$dateParams]) as $row) {
            $counts[$row['trang_thai_dat_san']] = (int) $row['total'];
        }
        // Overview is lifetime totals; tab counts reflect the selected creation-date range.
        $stats = $counts;
        if ($dateWhere !== '') {
            $stats = [];
            foreach ($this->rows('SELECT h.trang_thai_dat_san,COUNT(*) AS total FROM ' . $source . '
                WHERE h.ma_khach_hang=? GROUP BY h.trang_thai_dat_san', [$now, $customer]) as $row) {
                $stats[$row['trang_thai_dat_san']] = (int)$row['total'];
            }
        }
        $total = $status === '' ? array_sum($counts) : ($counts[$status] ?? 0);
        $perPage = 6;
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;
        $params = [$now, $customer, ...$dateParams];
        $where = 'h.ma_khach_hang=?' . $dateWhere;
        if ($status !== '') { $where .= ' AND h.trang_thai_dat_san=?'; $params[] = $status; }
        // LIMIT/OFFSET are internal integers; all request values are bound parameters.
        $direction = $sort === 'oldest' ? 'ASC' : 'DESC';
        $items = $this->rows("SELECT h.ma_dat_san, h.ngay_tao, h.trang_thai_dat_san,
            h.tong_tien_san, h.tong_tien_dich_vu, h.tong_tien_tam_tinh, c.ten_co_so, c.anh_dai_dien,
            h.ghi_chu, p.ghi_chu AS payment_note,
            p.anh_minh_chung AS receipt_image, p.minh_chung_gui_luc AS receipt_sent_at
            FROM $source JOIN co_so c ON c.ma_co_so=h.ma_co_so
            LEFT JOIN thanh_toan p ON p.ma_dat_san=h.ma_dat_san
            WHERE $where ORDER BY h.ngay_tao $direction, h.ma_dat_san $direction LIMIT $perPage OFFSET $offset", $params);
        $lines = [];
        $serviceLines = [];
        if ($items) {
            $ids = array_column($items, 'ma_dat_san');
            $marks = implode(',', array_fill(0, count($ids), '?'));
            foreach ($this->rows("SELECT d.ma_dat_san, d.ma_san, d.ten_san_snapshot,
                d.ngay_choi, d.gio_bat_dau, d.gio_ket_thuc
                FROM chi_tiet_dat_san d JOIN dat_san b ON b.ma_dat_san=d.ma_dat_san
                WHERE b.ma_khach_hang=? AND d.ma_dat_san IN ($marks)
                ORDER BY d.ngay_choi, d.ma_san, d.gio_bat_dau, d.ma_chi_tiet", [$customer, ...$ids]) as $line) {
                $lines[$line['ma_dat_san']][] = $line;
            }
            // One batch for the current page, independent of court rows (no fan-out / N+1).
            // Only stored service snapshots; never join current catalog prices.
            foreach ($this->rows("SELECT d.ma_dat_san, d.ten_dich_vu_snapshot,
                d.don_vi_tinh_snapshot, d.so_luong, d.don_gia, d.thanh_tien
                FROM chi_tiet_dich_vu_dat d JOIN dat_san b ON b.ma_dat_san=d.ma_dat_san
                WHERE b.ma_khach_hang=? AND d.ma_dat_san IN ($marks)
                ORDER BY d.ma_dat_san,d.ma_chi_tiet_dv", [$customer, ...$ids]) as $line) {
                $serviceLines[$line['ma_dat_san']][] = $line;
            }
        }
        foreach ($items as &$item) {
            $item['lines'] = $lines[$item['ma_dat_san']] ?? [];
            $item['service_lines'] = $serviceLines[$item['ma_dat_san']] ?? [];
        }
        unset($item);
        return compact('items', 'counts', 'stats', 'total', 'page', 'pages', 'perPage');
    }

    public function find(int $id, bool $lock = false): ?array
    {
        return $this->rows('SELECT * FROM dat_san WHERE ma_dat_san = ?' . ($lock ? ' FOR UPDATE' : ''), [$id])[0] ?? null;
    }

    public function historyReceipt(int $id, int $customer): ?array
    {
        return $this->rows('SELECT p.* FROM dat_san b
            JOIN thanh_toan p ON p.ma_dat_san=b.ma_dat_san
            WHERE b.ma_dat_san=? AND b.ma_khach_hang=?', [$id,$customer])[0] ?? null;
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
        if (!$this->pdo->inTransaction()) throw new \LogicException('Cancellation requires transaction');
        $this->rows('SELECT ma_thanh_toan FROM thanh_toan WHERE ma_dat_san=? FOR UPDATE', [$id]);
        $this->pdo->prepare("UPDATE dat_san SET trang_thai_dat_san =
            CASE WHEN het_han_giu_san_luc <= CURRENT_TIMESTAMP THEN 'HET_HAN' ELSE 'DA_HUY' END
            WHERE ma_dat_san=? AND trang_thai_dat_san='CHO_THANH_TOAN'
            AND NOT EXISTS (SELECT 1 FROM thanh_toan WHERE ma_dat_san=?
                AND (trang_thai='THANH_CONG' OR ghi_chu LIKE '%[BIEN_LAI:%'
                    OR NULLIF(TRIM(anh_minh_chung),'') IS NOT NULL))")
            ->execute([$id, $id]);
        $this->syncEndedPayment($id);
    }

    private function syncEndedPayment(int $id): void
    {
        // Caller holds booking then payment locks. Never overwrite received/reconciliation money.
        $this->pdo->prepare("UPDATE thanh_toan t JOIN dat_san b ON b.ma_dat_san=t.ma_dat_san
            SET t.trang_thai=CASE WHEN b.trang_thai_dat_san='DA_HUY' THEN 'HUY' ELSE 'HET_HAN' END
            WHERE b.ma_dat_san=? AND b.trang_thai_dat_san IN ('DA_HUY','HET_HAN')
            AND t.trang_thai='CHO_THANH_TOAN'")->execute([$id]);
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
            AND (b.trang_thai_dat_san='CHO_XAC_NHAN'
                OR (b.trang_thai_dat_san='CHO_THANH_TOAN' AND b.het_han_giu_san_luc > ?)) ORDER BY b.ma_dat_san" . ($lock ? ' FOR UPDATE' : ''), [$service, $now]);
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
        if ($id !== null) return $this->expireOne($id);
        if ($this->pdo->inTransaction()) throw new \LogicException('Bulk expiry requires independent transactions');
        $this->expiryReport = ['expired'=>0, 'batches'=>0, 'protected_sample'=>[], 'overlap_skipped'=>false, 'more_pending'=>false];
        // Database-scoped lock works across CLI instances / hosts connected to this MySQL.
        $lock = 'stryke_expiry_' . hash('sha1', (string)$this->pdo->query('SELECT DATABASE()')->fetchColumn());
        $acquired = $this->rows('SELECT GET_LOCK(?,0) AS acquired', [$lock])[0]['acquired'];
        if ($acquired === null) throw new \RuntimeException('Cannot acquire expiry job lock');
        if ((int)$acquired !== 1) {
            $this->expiryReport['overlap_skipped'] = true;
            return 0;
        }
        $eligible = "b.trang_thai_dat_san='CHO_THANH_TOAN' AND b.het_han_giu_san_luc<=CURRENT_TIMESTAMP AND " . self::unprotectedSql();
        $deadline = microtime(true) + 45;
        try {
            $this->expiryReport['protected_sample'] = array_column($this->rows("SELECT b.ma_dat_san FROM dat_san b
                WHERE b.trang_thai_dat_san='CHO_THANH_TOAN' AND b.het_han_giu_san_luc<=CURRENT_TIMESTAMP
                AND NOT (" . self::unprotectedSql() . ") ORDER BY b.het_han_giu_san_luc,b.ma_dat_san LIMIT 100"), 'ma_dat_san');
            if ($this->expiryReport['protected_sample']) {
                error_log('Booking expiry reconciliation required; booking_ids=' . implode(',', $this->expiryReport['protected_sample']));
            }
            // Indexed deadline range, capped memory and runtime; next invocation picks up remaining rows.
            for ($batch = 0; $batch < 10 && microtime(true) < $deadline; $batch++) {
                $ids = array_column($this->rows("SELECT b.ma_dat_san FROM dat_san b WHERE $eligible
                    ORDER BY b.het_han_giu_san_luc,b.ma_dat_san LIMIT 200"), 'ma_dat_san');
                if (!$ids) break;
                $this->expiryReport['batches']++;
                foreach ($ids as $bookingId) {
                    if (microtime(true) >= $deadline) break;
                    $this->expiryReport['expired'] += $this->expireOne((int)$bookingId);
                }
            }
            $this->expiryReport['more_pending'] = (bool)$this->rows("SELECT b.ma_dat_san FROM dat_san b WHERE $eligible LIMIT 1");
            return $this->expiryReport['expired'];
        } finally {
            $this->rows('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }

    private function expireOne(int $bookingId): int
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) $this->pdo->beginTransaction();
        try {
            $booking = $this->find($bookingId, true);
            $payment = $this->rows('SELECT trang_thai FROM thanh_toan WHERE ma_dat_san=? FOR UPDATE', [$bookingId])[0] ?? null;
            if ($booking && $booking['trang_thai_dat_san']==='CHO_THANH_TOAN'
                && ($payment['trang_thai'] ?? '')==='THANH_CONG') {
                error_log('Booking expiry skipped for reconciliation; booking_id=' . $bookingId);
                if ($ownsTransaction) $this->pdo->commit();
                return 0;
            }
            $stmt = $this->pdo->prepare("UPDATE dat_san b SET b.trang_thai_dat_san='HET_HAN'
                WHERE b.ma_dat_san=? AND b.trang_thai_dat_san='CHO_THANH_TOAN'
                AND b.het_han_giu_san_luc <= CURRENT_TIMESTAMP AND " . self::unprotectedSql());
            $stmt->execute([$bookingId]);
            $count = $stmt->rowCount();
            $this->syncEndedPayment($bookingId);
            if ($ownsTransaction) $this->pdo->commit();
            return $count;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
