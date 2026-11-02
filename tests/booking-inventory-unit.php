<?php
// All PDO, catalog, booking and payment changes below stay in arrays. No .env or real database.
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\Booking;
use App\Services\{BookingInventoryService, BookingService, CourtScheduleService, DichVuService, ValidationException};
use Core\HttpException;

final class InventoryMemoryPDO extends PDO
{
    public array $bookings = [], $courtLines = [], $serviceLines = [], $payments = [], $calls = [];
    public array $services, $facilities, $companies, $courts;
    public int $begins = 0, $commits = 0, $rollbacks = 0;
    public ?int $failServiceId = null;
    public bool $throwOnWrite = false;
    private bool $transaction = false;
    private array $snapshot = [];
    private int $lastId = 0;
    public function __construct()
    {
        $this->companies = [1 => ['ma_cong_ty' => 1, 'ten_cong_ty' => 'Công ty', 'trang_thai' => 'HOAT_DONG']];
        $f = ['ma_co_so' => 1, 'ma_cong_ty' => 1, 'ten_co_so' => 'STRYKE', 'trang_thai' => 'HOAT_DONG', 'gio_mo_cua' => '05:00:00', 'gio_dong_cua' => '23:00:00'];
        $this->facilities = [1 => $f, 2 => array_replace($f, ['ma_co_so' => 2])];
        $s = ['ma_dich_vu' => 1, 'ma_co_so' => 1, 'ten_dich_vu' => 'Nước suối', 'don_vi_tinh' => 'chai', 'loai_dich_vu' => 'DO_UONG', 'don_gia' => '10000.00', 'so_luong_ton' => 10, 'mo_ta' => null, 'trang_thai' => 'DANG_CUNG_CAP'];
        $this->services = [1 => $s, 2 => array_replace($s, ['ma_dich_vu' => 2, 'ten_dich_vu' => 'Không theo dõi', 'so_luong_ton' => null, 'don_gia' => '0.00']),
            3 => array_replace($s, ['ma_dich_vu' => 3, 'ten_dich_vu' => 'Hết hàng', 'so_luong_ton' => 0]),
            4 => array_replace($s, ['ma_dich_vu' => 4, 'ma_co_so' => 2, 'ten_dich_vu' => 'Cơ sở khác']),
            5 => array_replace($s, ['ma_dich_vu' => 5, 'ten_dich_vu' => 'Vợt thuê', 'loai_dich_vu' => 'CHO_THUE'])];
        $c = ['ma_san' => 1, 'ma_co_so' => 1, 'ma_loai_san' => 1, 'ma_bo_mon' => 1, 'ten_san' => 'Sân 1', 'ten_bo_mon' => 'Cầu lông', 'trang_thai' => 'HOAT_DONG', 'trang_thai_loai_san' => 'HOAT_DONG', 'trang_thai_bo_mon' => 'HOAT_DONG'];
        $this->courts = [1 => $c, 2 => array_replace($c, ['ma_san' => 2, 'ten_san' => 'Sân 2'])];
    }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new InventoryMemoryStatement($this, $query); }
    public function exec(string $statement): int|false { if ($statement !== 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED') throw new RuntimeException('Unexpected exec'); return 0; }
    public function lastInsertId(?string $name = null): string|false { return (string) $this->lastId; }
    public function allocateBooking(): int { return ++$this->lastId; }
    public function beginTransaction(): bool
    {
        if ($this->transaction) throw new RuntimeException('Nested transaction');
        $this->snapshot = [$this->bookings, $this->courtLines, $this->serviceLines, $this->services, $this->payments];
        $this->begins++; return $this->transaction = true;
    }
    public function inTransaction(): bool { return $this->transaction; }
    public function commit(): bool { $this->commits++; $this->transaction = false; return true; }
    public function rollBack(): bool
    {
        [$this->bookings, $this->courtLines, $this->serviceLines, $this->services, $this->payments] = $this->snapshot;
        $this->rollbacks++; $this->transaction = false; return true;
    }
}

final class InventoryMemoryStatement extends PDOStatement
{
    private array $rows = [];
    private int $affected = 0;
    public function __construct(private InventoryMemoryPDO $db, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        $p = $params ?? []; $sql = preg_replace('/\s+/', ' ', trim($this->sql));
        $this->db->calls[] = ['sql' => $sql, 'params' => $p]; $this->rows = []; $this->affected = 0;
        if (preg_match('/\A(?:INSERT|UPDATE|DELETE)\b/', $sql) && !$this->db->inTransaction()) throw new RuntimeException('Write outside transaction');
        if (str_starts_with($sql, 'INSERT INTO dat_san ')) {
            $id = $this->db->allocateBooking();
            $fields = ['khoa_yeu_cau', 'dau_van_tay_yeu_cau', 'ma_co_so', 'ma_khach_hang', 'ngay_tao', 'bat_dau_giu_san_luc', 'het_han_giu_san_luc', 'tong_tien_san', 'tong_tien_dich_vu', 'tong_tien_tam_tinh', 'ghi_chu'];
            $this->db->bookings[$id] = array_combine($fields, $p) + ['ma_dat_san' => $id, 'trang_thai_dat_san' => 'CHO_THANH_TOAN'];
        } elseif (str_starts_with($sql, 'INSERT INTO chi_tiet_dat_san ')) {
            $this->db->courtLines[] = array_combine(['ma_dat_san', 'ma_san', 'ten_san_snapshot', 'ngay_choi', 'gio_bat_dau', 'gio_ket_thuc', 'don_gia', 'thanh_tien'], $p);
        } elseif (str_starts_with($sql, 'INSERT INTO chi_tiet_dich_vu_dat ')) {
            $this->db->serviceLines[] = array_combine(['ma_dat_san', 'ma_dich_vu', 'ten_dich_vu_snapshot', 'don_vi_tinh_snapshot', 'so_luong', 'don_gia', 'thanh_tien'], $p);
        } elseif (str_starts_with($sql, 'UPDATE dat_san SET ngay_tao=')) {
            $now = CourtScheduleService::now();
            $this->db->bookings[$p[0]]['ngay_tao'] = $this->db->bookings[$p[0]]['bat_dau_giu_san_luc'] = $now->format('Y-m-d H:i:s');
            $this->db->bookings[$p[0]]['het_han_giu_san_luc'] = $now->modify('+6 minutes')->format('Y-m-d H:i:s');
        } elseif (str_starts_with($sql, 'UPDATE dich_vu SET so_luong_ton=so_luong_ton-?')) {
            [$qty, $id, $facility, $minimum] = $p; $s = &$this->db->services[$id];
            if ($id === $this->db->failServiceId && $this->db->throwOnWrite) throw new PDOException('Simulated SQL failure');
            if ($id !== $this->db->failServiceId && $s && $s['ma_co_so'] === $facility && $s['so_luong_ton'] !== null && $s['so_luong_ton'] >= $minimum) { $s['so_luong_ton'] -= $qty; $this->affected = 1; }
        } elseif (str_starts_with($sql, 'UPDATE dich_vu SET ma_co_so')) {
            $id = $p['id']; unset($p['id']); $this->db->services[$id] = $p + ['ma_dich_vu' => $id]; $this->affected = 1;
        } elseif (str_starts_with($sql, 'UPDATE dat_san SET trang_thai_dat_san')) {
            $b = &$this->db->bookings[$p[0]];
            if ($b && $b['trang_thai_dat_san'] === 'CHO_THANH_TOAN') {
                $expired = $b['het_han_giu_san_luc'] <= CourtScheduleService::now()->format('Y-m-d H:i:s');
                if (str_contains($sql, "='DA_XAC_NHAN'")) { if (!$expired) { $b['trang_thai_dat_san'] = 'DA_XAC_NHAN'; $this->affected = 1; } }
                elseif (str_contains($sql, 'CASE WHEN')) { if (($this->db->payments[$p[0]] ?? '') !== 'THANH_CONG') { $b['trang_thai_dat_san'] = $expired ? 'HET_HAN' : 'DA_HUY'; $this->affected = 1; } }
                elseif ($expired) { $b['trang_thai_dat_san'] = 'HET_HAN'; $this->affected = 1; }
            }
        } elseif (str_starts_with($sql, 'UPDATE thanh_toan t JOIN dat_san')) {
            // These fixtures have no pending payment requests to synchronize.
        } elseif (str_starts_with($sql, 'SELECT c.so_luong FROM chi_tiet_dich_vu_dat c')) {
            if (!str_contains($sql, "b.trang_thai_dat_san = 'CHO_THANH_TOAN' AND b.het_han_giu_san_luc > ?")) throw new RuntimeException('Reservation query lost state/expiry predicates');
            foreach ($this->db->serviceLines as $line) {
                $b = $this->db->bookings[$line['ma_dat_san']];
                if ($line['ma_dich_vu'] === $p[0] && $b['trang_thai_dat_san'] === 'CHO_THANH_TOAN' && $b['het_han_giu_san_luc'] > $p[1]) $this->rows[] = ['so_luong' => $line['so_luong']];
            }
        } elseif (str_contains($sql, 'FROM chi_tiet_dich_vu_dat WHERE ma_dat_san=?')) {
            $this->rows = array_values(array_filter($this->db->serviceLines, fn($r) => $r['ma_dat_san'] === $p[0]));
            usort($this->rows, fn($a, $b) => $a['ma_dich_vu'] <=> $b['ma_dich_vu']);
        } elseif (str_contains($sql, 'FROM chi_tiet_dich_vu_dat ct LEFT JOIN dat_san')) {
            foreach ($this->db->serviceLines as $i => $r) if ($r['ma_dich_vu'] === $p['id']) $this->rows[] = ['ma_chi_tiet_dv' => $i + 1, 'trang_thai_dat_san' => $this->db->bookings[$r['ma_dat_san']]['trang_thai_dat_san']];
        } elseif (str_starts_with($sql, 'SELECT * FROM dat_san WHERE khoa_yeu_cau')) {
            $this->rows = array_values(array_filter($this->db->bookings, fn($r) => $r['khoa_yeu_cau'] === $p[0]));
        } elseif (str_starts_with($sql, 'SELECT * FROM dat_san WHERE ma_dat_san')) {
            if (isset($this->db->bookings[$p[0]])) $this->rows[] = $this->db->bookings[$p[0]];
        } elseif (str_starts_with($sql, 'SELECT ma_dat_san FROM dat_san')) {
            foreach ($this->db->bookings as $b) if ($b['trang_thai_dat_san'] === 'CHO_THANH_TOAN' && $b['het_han_giu_san_luc'] <= CourtScheduleService::now()->format('Y-m-d H:i:s')) $this->rows[] = ['ma_dat_san' => $b['ma_dat_san']];
        } elseif (str_starts_with($sql, 'SELECT ma_thanh_toan FROM thanh_toan')) {
            $this->rows = [];
        } elseif (preg_match('/\ASELECT \* FROM (\w+) WHERE \w+ = \S+ FOR UPDATE\z/', $sql, $m)) {
            $id = (int) array_values($p)[0];
            $row = match ($m[1]) { 'dich_vu' => $this->db->services[$id] ?? null, 'co_so' => $this->db->facilities[$id] ?? null,
                'cong_ty_nhuong_quyen' => $this->db->companies[$id] ?? null, 'san' => $this->db->courts[$id] ?? null,
                'bo_mon' => ['ma_bo_mon' => $id], 'loai_san' => ['ma_loai_san' => $id, 'ma_bo_mon' => 1],
                'khach_hang' => ['ma_khach_hang' => $id, 'ma_tai_khoan' => $id], 'tai_khoan' => ['ma_tai_khoan' => $id], default => throw new RuntimeException('Unknown lock') };
            if ($row) $this->rows[] = $row;
        } elseif (str_contains($sql, 'FROM khach_hang k JOIN tai_khoan')) {
            $this->rows[] = ['ma_khach_hang' => $p[0], 'ma_tai_khoan' => $p[0], 'trang_thai' => 'HOAT_DONG', 'tai_khoan_trang_thai' => 'HOAT_DONG', 'ten_vai_tro' => 'KHACH_HANG', 'so_dien_thoai' => '0901234567'];
        } elseif (str_starts_with($sql, 'SELECT * FROM dich_vu WHERE ma_co_so')) {
            $this->rows = array_values(array_filter($this->db->services, fn($r) => $r['ma_co_so'] === $p[0] && $r['trang_thai'] === 'DANG_CUNG_CAP'));
        } elseif (str_contains($sql, 'FROM dich_vu d JOIN co_so f')) {
            if (isset($this->db->services[$p['id']])) $this->rows[] = $this->db->services[$p['id']];
        } elseif (str_starts_with($sql, 'SELECT 1 FROM dich_vu')) {
            foreach ($this->db->services as $r) if ($r['ma_co_so'] === $p['facility'] && $r['ten_dich_vu'] === $p['name'] && $r['ma_dich_vu'] !== $p['id']) $this->rows[] = [1];
        } elseif (str_contains($sql, 'FROM co_so f JOIN cong_ty_nhuong_quyen c')) {
            if (isset($this->db->facilities[(int) $p['ma_co_so']])) $this->rows[] = $this->db->facilities[(int) $p['ma_co_so']];
        } elseif (str_contains($sql, 'FROM co_so f WHERE')) {
            if (isset($this->db->facilities[$p['id']])) $this->rows[] = $this->db->facilities[$p['id']];
        } elseif (str_contains($sql, 'FROM cong_ty_nhuong_quyen WHERE')) {
            if (isset($this->db->companies[$p['id']])) $this->rows[] = $this->db->companies[$p['id']];
        } elseif (str_contains($sql, 'FROM san s JOIN loai_san')) {
            $this->rows = array_values(array_filter($this->db->courts, fn($r) => $r['ma_co_so'] === $p['id']));
        } elseif (str_contains($sql, 'FROM san s JOIN co_so')) {
            if (isset($this->db->courts[$p['id']])) $this->rows[] = $this->db->courts[$p['id']];
        } elseif (str_contains($sql, 'FROM chi_tiet_dat_san ct JOIN dat_san')) {
            foreach ($this->db->courtLines as $r) if ($this->db->bookings[$r['ma_dat_san']]['ma_co_so'] === $p['facility'] && $r['ngay_choi'] === $p['date']) $this->rows[] = $r + $this->db->bookings[$r['ma_dat_san']];
        } elseif (str_starts_with($sql, 'SELECT * FROM bang_gia')) {
            $this->rows[] = ['ma_loai_san' => 1, 'gio_bat_dau' => '05:00:00', 'gio_ket_thuc' => '23:00:00', 'don_gia' => '80000.00'];
        } elseif (str_contains($sql, 'FROM co_so c JOIN dat_san')) {
            $this->rows[] = ['ten_co_so' => 'STRYKE', 'dia_chi' => 'Địa chỉ'];
        } elseif (str_contains($sql, 'FROM chi_tiet_dat_san d JOIN san')) {
            foreach ($this->db->courtLines as $r) if ($r['ma_dat_san'] === $p[0]) $this->rows[] = $r + ['ten_bo_mon' => 'Cầu lông'];
        } else throw new RuntimeException('Unexpected SQL: ' . $sql);
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return array_shift($this->rows) ?: false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column = 0): mixed { return isset($this->rows[0]) ? array_values($this->rows[0])[$column] : false; }
    public function rowCount(): int { return $this->affected; }
}

$checks = 0;
function inventoryCheck(bool $ok, string $label): void { global $checks; if (!$ok) throw new RuntimeException('FAIL: ' . $label); $checks++; }
function inventoryReject(callable $action, int|string $expected): void
{
    try { $action(); }
    catch (HttpException $e) { inventoryCheck($expected === $e->status, 'HTTP ' . $expected); return; }
    catch (ValidationException $e) { inventoryCheck(is_string($expected) && isset($e->errors[$expected]), 'validation ' . $expected); return; }
    catch (LogicException $e) { inventoryCheck($expected === 'transaction', 'requires caller transaction'); return; }
    throw new RuntimeException('Expected rejection: ' . $expected);
}
function inventorySelection(int $court = 1): array
{
    return ['facility' => 1, 'date' => CourtScheduleService::now()->modify('+1 day')->format('Y-m-d'), 'slots' => [['court' => $court, 'start' => '10:00', 'end' => '10:30']]];
}
function inventoryPending(InventoryMemoryPDO $db, array $services, int $court = 1): int
{
    $booking = new BookingService($db); $input = inventorySelection($court); $extras = ['services' => $services, 'note' => ''];
    $quote = $booking->quote($input, $extras);
    return $booking->confirm($court, bin2hex(random_bytes(32)), $input, $extras, $quote['fingerprint']);
}
function inventoryCatalogEdit(InventoryMemoryPDO $db, array $changes): void
{
    $row = $db->services[1];
    $input = array_replace($row, ['ma_co_so' => (string) $row['ma_co_so'], 'theo_doi_ton' => $row['so_luong_ton'] === null ? '0' : '1',
        'so_luong_ton' => (string) $row['so_luong_ton'], 'mo_ta' => $row['mo_ta'] ?? '', 'version' => DichVuService::version($row)], $changes);
    (new DichVuService($db, ['ten_vai_tro' => 'QUAN_TRI_TAP_DOAN', 'trang_thai' => 'HOAT_DONG']))->save($input, 1);
}

/** Simulated caller contract ONLY, not a webhook or production payment implementation. */
function inventoryCaller(InventoryMemoryPDO $db, int $id, bool $failAfterDeduction = false): string
{
    $db->beginTransaction();
    try {
        $booking = (new Booking($db))->find($id, true);
        if ($booking['trang_thai_dat_san'] === 'DA_XAC_NHAN' && ($db->payments[$id] ?? '') === 'THANH_CONG') { $db->commit(); return 'duplicate'; }
        // External payment verification is deliberately outside these memory-only tests.
        (new BookingInventoryService($db))->deductForPayment($id);
        if ($failAfterDeduction) throw new RuntimeException('Simulated caller failure');
        $stmt = $db->prepare("UPDATE dat_san SET trang_thai_dat_san='DA_XAC_NHAN'
            WHERE ma_dat_san=? AND trang_thai_dat_san='CHO_THANH_TOAN' AND het_han_giu_san_luc>CURRENT_TIMESTAMP");
        $stmt->execute([$id]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('Conditional confirmation lost');
        $db->payments[$id] = 'THANH_CONG'; $db->commit(); return 'applied';
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
}

set_error_handler(static function (int $level, string $message, string $file, int $line): never { throw new ErrorException($message, 0, $level, $file, $line); });
try {
    // A: actual checkout snapshot + actual catalog writer; no reimplementation of quote/prices.
    $db = new InventoryMemoryPDO(); $id = inventoryPending($db, [1 => 2]);
    $saved = $db->serviceLines; $totals = $db->bookings[$id];
    inventoryCheck($saved[0]['ma_dich_vu'] === 1 && $saved[0]['ten_dich_vu_snapshot'] === 'Nước suối' && $saved[0]['don_vi_tinh_snapshot'] === 'chai', 'snapshot ID/name/unit');
    inventoryCheck($saved[0]['don_gia'] === '10000.00' && $saved[0]['so_luong'] === 2 && $saved[0]['thanh_tien'] === '20000.00', '2 x 10000 = 20000 snapshot');
    inventoryCheck($totals['tong_tien_san'] === '40000.00' && $totals['tong_tien_dich_vu'] === '20000.00' && $totals['tong_tien_tam_tinh'] === '60000.00', 'stored booking totals');
    inventoryCheck(strtotime($totals['het_han_giu_san_luc']) - strtotime($totals['bat_dau_giu_san_luc']) === 360, 'six-minute hold retained');
    inventoryCatalogEdit($db, ['don_gia' => '15000.00', 'ten_dich_vu' => 'Tên mới']);
    inventoryCheck($db->services[1]['don_gia'] === '15000.00' && $db->services[1]['ten_dich_vu'] === 'Tên mới', 'catalog price/name really changed in memory');
    inventoryCheck($db->serviceLines === $saved && $db->bookings[$id] === $totals, 'pending snapshots and totals immutable after catalog edit');
    inventoryReject(fn() => inventoryCatalogEdit($db, ['don_vi_tinh' => 'thùng']), 'don_vi_tinh');
    inventoryReject(fn() => inventoryCatalogEdit($db, ['so_luong_ton' => '1']), 'so_luong_ton');
    inventoryReject(fn() => inventoryCatalogEdit($db, ['theo_doi_ton' => '0']), 'so_luong_ton');
    $staleVersion = DichVuService::version($db->services[1]);
    inventoryCheck(inventoryCaller($db, $id) === 'applied' && $db->services[1]['so_luong_ton'] === 8, 'deduction uses snapshot quantity, ignores changed catalog price/name');
    inventoryReject(fn() => inventoryCatalogEdit($db, ['version' => $staleVersion, 'so_luong_ton' => '10']), 409);
    inventoryCheck($db->services[1]['so_luong_ton'] === 8, 'stale catalog form cannot overwrite newly deducted inventory');
    inventoryCheck((new BookingService($db))->quote(inventorySelection(2))['offers'][1]['available'] === 8, 'new quote sees physical stock after confirmation without counting the old hold again');
    inventoryCatalogEdit($db, ['don_gia' => '19000.00']);
    inventoryCheck($db->serviceLines === $saved && $db->bookings[$id]['tong_tien_tam_tinh'] === '60000.00', 'paid snapshots and totals unchanged');
    $db->bookings[$id]['trang_thai_dat_san'] = 'HOAN_THANH';
    inventoryCatalogEdit($db, ['so_luong_ton' => '0']); inventoryCatalogEdit($db, ['don_vi_tinh' => 'thùng']);
    inventoryCheck($db->services[1]['don_vi_tinh'] === 'thùng' && $db->serviceLines === $saved, 'allowed terminal catalog unit change does not rewrite snapshot');

    // B: pending reservations, stale quotes and cancellation/expiry through Phat's actual services.
    $db = new InventoryMemoryPDO(); $booking = new BookingService($db); $model = new Booking($db);
    $stale = $booking->quote(inventorySelection(2), ['services' => [1 => 5], 'note' => '']);
    $a = inventoryPending($db, [1 => 6]);
    inventoryCheck($db->services[1]['so_luong_ton'] === 10, 'pending creation never deducts physical stock');
    inventoryCheck($booking->quote(inventorySelection(2))['offers'][1]['available'] === 4, '10 physical minus 6 held = 4 available');
    inventoryReject(fn() => $booking->confirm(2, bin2hex(random_bytes(32)), inventorySelection(2), ['services' => [1 => 5], 'note' => ''], $stale['fingerprint']), 409);
    inventoryCheck(count($db->bookings) === 1, 'stale request rolls back without partial booking');
    foreach ([[4 => 1], [5 => 1], [3 => 1]] as $bad) inventoryReject(fn() => $booking->quote(inventorySelection(2), ['services' => $bad, 'note' => '']), 409);
    $offers = $booking->quote(inventorySelection(2))['offers'];
    inventoryCheck($offers[2]['available'] === null && $offers[3]['available'] === 0, 'NULL and zero distinct');
    $b = inventoryPending($db, [1 => 4], 2);
    $snapshots = $db->serviceLines;
    inventoryCheck($model->reserved(1, CourtScheduleService::now()->format('Y-m-d H:i:s')) === 10, 'holds reach available limit without physical write');
    $booking->cancelHold($a, 1);
    inventoryCheck($model->reserved(1, CourtScheduleService::now()->format('Y-m-d H:i:s')) === 4 && $db->services[1]['so_luong_ton'] === 10, 'cancel releases only virtual stock');
    $db->bookings[$b]['het_han_giu_san_luc'] = '2000-01-01 00:06:00';
    inventoryCheck($model->reserved(1, CourtScheduleService::now()->format('Y-m-d H:i:s')) === 0, 'overdue pending excluded before cleanup');
    inventoryCheck($booking->expirePending() === 1 && $booking->expirePending() === 0, 'expiry conditional and repeat safe');
    inventoryCheck($db->services[1]['so_luong_ton'] === 10 && $db->serviceLines === $snapshots, 'expiry never restocks or edits snapshots');

    // C: stock hook and the explicitly required caller-owned transaction/state gate.
    $db = new InventoryMemoryPDO(); $inventory = new BookingInventoryService($db);
    inventoryReject(fn() => $inventory->deductForPayment(1), 'transaction');
    $a = inventoryPending($db, [1 => 6, 2 => 2]); $b = inventoryPending($db, [1 => 4], 2);
    $db->beginTransaction(); $start = count($db->calls); $txCounts = [$db->begins, $db->commits, $db->rollbacks];
    $result = $inventory->deductForPayment($a);
    inventoryCheck($result === ['booking_id' => $a, 'deducted' => [['service_id' => 1, 'quantity' => 6, 'stock_before' => 10, 'stock_after' => 4]], 'untracked' => [2]], 'clear output for tracked/untracked');
    inventoryCheck($db->services[2]['so_luong_ton'] === null && $db->bookings[$a]['trang_thai_dat_san'] === 'CHO_THANH_TOAN', 'hook changes neither NULL stock nor booking state');
    inventoryCheck([$db->begins, $db->commits, $db->rollbacks] === $txCounts && $db->inTransaction(), 'hook does not manage caller transaction');
    foreach (array_slice($db->calls, $start) as $call) {
        inventoryCheck(!preg_match('/\A(?:INSERT|DELETE|UPDATE (?!dich_vu SET so_luong_ton))/', $call['sql']), 'hook writes stock only');
        if (str_contains($call['sql'], 'FROM chi_tiet_dich_vu_dat')) inventoryCheck(!str_contains($call['sql'], 'JOIN') && $call['params'] === [$a], 'hook reads owned booking snapshot without current catalog prices');
    }
    $db->rollBack();
    inventoryCheck(inventoryCaller($db, $a) === 'applied' && $db->services[1]['so_luong_ton'] === 4, 'physical stock deducted once in caller transaction');
    inventoryCheck((new Booking($db))->reserved(1, CourtScheduleService::now()->format('Y-m-d H:i:s')) === 4, 'confirmed booking no longer counts as virtual hold');
    $writes = count(array_filter($db->calls, fn($c) => str_starts_with($c['sql'], 'UPDATE dich_vu SET so_luong_ton=')));
    inventoryCheck(inventoryCaller($db, $a) === 'duplicate' && $db->services[1]['so_luong_ton'] === 4, 'caller detects repeat BEFORE inventory hook');
    inventoryCheck(count(array_filter($db->calls, fn($c) => str_starts_with($c['sql'], 'UPDATE dich_vu SET so_luong_ton='))) === $writes, 'duplicate generates no extra stock update');
    inventoryCheck(inventoryCaller($db, $b) === 'applied' && $db->services[1]['so_luong_ton'] === 0, 'second reservation consumes remaining stock, no negative');
    inventoryCheck((new Booking($db))->reserved(1, CourtScheduleService::now()->format('Y-m-d H:i:s')) === 0, 'no double subtraction of physical and virtual stock after commit');

    $db = new InventoryMemoryPDO(); $db->services[3]['so_luong_ton'] = 5;
    $id = inventoryPending($db, [1 => 2, 3 => 1]); $before = $db->serviceLines;
    $db->services[3]['so_luong_ton'] = 0; // Deliberately invalid external stock change, outside the catalog writer.
    $db->beginTransaction(); inventoryReject(fn() => (new BookingInventoryService($db))->deductForPayment($id), 409);
    inventoryCheck($db->services[1]['so_luong_ton'] === 10, 'validate every service before the first deduction'); $db->rollBack();
    $db->services[3]['so_luong_ton'] = 5; $db->failServiceId = 3;
    inventoryReject(fn() => inventoryCaller($db, $id), 409);
    inventoryCheck($db->services[1]['so_luong_ton'] === 10 && $db->services[3]['so_luong_ton'] === 5, 'SQL guard failure on second item rolls back first deduction');
    inventoryCheck($db->bookings[$id]['trang_thai_dat_san'] === 'CHO_THANH_TOAN' && $db->payments === [] && $db->serviceLines === $before, 'failed transaction leaves no paid state or changed snapshot');
    $db->throwOnWrite = true;
    try { inventoryCaller($db, $id); throw new LogicException('Expected PDO failure'); }
    catch (PDOException $e) { inventoryCheck($e->getMessage() === 'Simulated SQL failure', 'PDO exception is propagated to caller'); }
    inventoryCheck($db->services[1]['so_luong_ton'] === 10 && $db->services[3]['so_luong_ton'] === 5 && !$db->inTransaction(), 'caller rolls back partial deduction after SQL exception');
    $db->throwOnWrite = false;
    $db->failServiceId = null;
    try { inventoryCaller($db, $id, true); throw new LogicException('Expected caller failure'); }
    catch (RuntimeException $e) { inventoryCheck($e->getMessage() === 'Simulated caller failure', 'failure after inventory is propagated'); }
    inventoryCheck($db->services[1]['so_luong_ton'] === 10 && $db->services[3]['so_luong_ton'] === 5 && !$db->inTransaction(), 'caller rolls back a downstream failure');
    inventoryCheck(inventoryCaller($db, $id) === 'applied' && $db->services[1]['so_luong_ton'] === 8 && $db->services[3]['so_luong_ton'] === 4, 'retry after rollback applies exactly one successful transaction');

    $db = new InventoryMemoryPDO(); $id = inventoryPending($db, [1 => 1]); $inventory = new BookingInventoryService($db);
    foreach (['CHO_XAC_NHAN', 'DA_HUY', 'HET_HAN', 'DA_XAC_NHAN', 'HOAN_THANH'] as $state) {
        $db->bookings[$id]['trang_thai_dat_san'] = $state; $db->beginTransaction();
        inventoryReject(fn() => $inventory->deductForPayment($id), 409);
        inventoryCheck($db->services[1]['so_luong_ton'] === 10 && $db->inTransaction(), 'terminal/nonpending rejection never deducts or closes transaction'); $db->rollBack();
    }
    $db->bookings[$id]['trang_thai_dat_san'] = 'CHO_THANH_TOAN';
    $db->bookings[$id]['het_han_giu_san_luc'] = '2000-01-01 00:06:00'; $db->beginTransaction();
    inventoryReject(fn() => $inventory->deductForPayment($id), 409); $db->rollBack();
    $db->beginTransaction(); inventoryReject(fn() => $inventory->deductForPayment(0), 404);
    inventoryReject(fn() => $inventory->deductForPayment(999), 404); $db->rollBack();

    foreach (['quantity', 'facility', 'unit', 'rental', 'missing'] as $bad) {
        $db = new InventoryMemoryPDO(); $id = inventoryPending($db, [1 => 1]);
        if ($bad === 'quantity') $db->serviceLines[0]['so_luong'] = 0;
        if ($bad === 'facility') $db->services[1]['ma_co_so'] = 2;
        if ($bad === 'unit') $db->services[1]['don_vi_tinh'] = 'thùng';
        if ($bad === 'rental') $db->services[1]['loai_dich_vu'] = 'CHO_THUE';
        if ($bad === 'missing') unset($db->services[1]);
        $db->beginTransaction(); inventoryReject(fn() => (new BookingInventoryService($db))->deductForPayment($id), 409); $db->rollBack();
    }
    $db = new InventoryMemoryPDO(); $id = inventoryPending($db, []); $db->beginTransaction();
    inventoryCheck((new BookingInventoryService($db))->deductForPayment($id) === ['booking_id' => $id, 'deducted' => [], 'untracked' => []], 'booking without services is supported'); $db->rollBack();
    $db = new InventoryMemoryPDO(); $id = inventoryPending($db, [2 => 999999]);
    inventoryCheck(inventoryCaller($db, $id) === 'applied' && $db->services[2]['so_luong_ton'] === null, 'NULL inventory never becomes tracked or deducted');
    $db = new InventoryMemoryPDO(); $db->services[1]['don_gia'] = '0.00';
    $id = inventoryPending($db, [1 => 2, 2 => 1]); $db->serviceLines = array_reverse($db->serviceLines);
    $db->beginTransaction(); $start = count($db->calls);
    $result = (new BookingInventoryService($db))->deductForPayment($id);
    inventoryCheck($db->bookings[$id]['tong_tien_dich_vu'] === '0.00' && $result['deducted'][0]['quantity'] === 2 && $db->services[1]['so_luong_ton'] === 8, 'free tracked service still consumes physical stock');
    $locks = array_values(array_filter(array_slice($db->calls, $start), fn($c) => str_contains($c['sql'], 'FROM dich_vu WHERE') && str_contains($c['sql'], 'FOR UPDATE')));
    inventoryCheck(array_column($locks, 'params') === [[1], [2]], 'service row locks follow ascending IDs regardless of input order'); $db->rollBack();

    // Sequential stale-data simulation, NOT a MySQL concurrency/locking test.
    $db = new InventoryMemoryPDO(); $id = inventoryPending($db, [1 => 3]); $other = inventoryPending($db, [1 => 3], 2);
    $db->services[1]['so_luong_ton'] = 5; // Simulate corrupted/stale physical stock outside supported writers.
    inventoryCheck(inventoryCaller($db, $id) === 'applied' && $db->services[1]['so_luong_ton'] === 2, 'first deduction is bounded by current physical stock');
    inventoryReject(fn() => inventoryCaller($db, $other), 409);
    inventoryCheck($db->services[1]['so_luong_ton'] === 2 && $db->bookings[$other]['trang_thai_dat_san'] === 'CHO_THANH_TOAN', 'second stale request cannot produce negative stock');
    foreach ($db->calls as $call) if (str_starts_with($call['sql'], 'UPDATE dich_vu SET so_luong_ton=')) {
        inventoryCheck(str_contains($call['sql'], 'so_luong_ton IS NOT NULL AND so_luong_ton>=?') && $call['params'][0] === $call['params'][3], 'stock SQL independently enforces sufficient tracked stock');
    }
} finally { restore_error_handler(); }
echo "PASS: $checks booking/inventory memory checks; no database or .env.\n";
