<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Real manual-payment service + InvoiceService + SQLite RAM. No .env/MySQL/provider.
// A statement guard models native PDO's unique named bindings; FOR UPDATE is not tested.
define('STRYKE_PR6_CONTRACT', true);
require __DIR__ . '/fixtures/booking-pr6.php';

use App\Services\{BookingManagementService, ValidationException};
use Core\HttpException;

final class NativePaymentStatement extends PDOStatement
{
    public static array $stockUpdates = [];
    protected function __construct() {}
    public function execute(?array $params = null): bool
    {
        preg_match_all('/(?<!:):([A-Za-z_][A-Za-z0-9_]*)/', $this->queryString, $matches);
        $names = $matches[1];
        $keys = array_map(static fn($key) => ltrim((string) $key, ':'), array_keys($params ?? []));
        if ($names && (count($names) !== count(array_unique($names)) || array_diff($names, $keys) || array_diff($keys, $names))) {
            $error = new PDOException('SQLSTATE[HY093]: Invalid parameter number');
            $error->errorInfo = ['HY093']; throw $error;
        }
        $ok = parent::execute($params);
        if (preg_match('/\A\s*UPDATE dich_vu\b/', $this->queryString)) self::$stockUpdates[] = (int) $params['service_id'];
        return $ok;
    }
}
$pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [NativePaymentStatement::class]);
$tables = ['dat_san', 'thanh_toan', 'dich_vu', 'chi_tiet_dich_vu_dat', 'hoa_don'];
$snapshot = static function () use ($pdo, $tables): array {
    $rows = []; foreach ($tables as $table) $rows[$table] = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY 1')->fetchAll();
    return $rows;
};
$seed = $snapshot(); $checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void { if (!$ok) throw new RuntimeException($label); $checks++; };
$reset = static function () use ($pdo, $seed, $check): void {
    $check(!$pdo->inTransaction(), 'service must close its own transaction before the next test');
    foreach ($seed as $table => $rows) {
        $pdo->exec('DELETE FROM ' . $table);
        foreach ($rows as $row) {
            $s = $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(',', array_keys($row)) . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')');
            $s->execute(array_values($row));
        }
    }
};
$admin = ['ten_vai_tro' => 'QUAN_TRI_TAP_DOAN', 'trang_thai' => 'HOAT_DONG', 'ma_nhan_vien' => null];
$identity = ['trang_thai' => 'HOAT_DONG', 'ma_nhan_vien' => 11, 'trang_thai_nhan_vien' => 'DANG_LAM',
    'ma_cong_ty' => 1, 'trang_thai_cong_ty' => 'HOAT_DONG', 'ma_co_so' => 1, 'cong_ty_cua_co_so' => 1, 'trang_thai_co_so' => 'HOAT_DONG'];
$manager = new BookingManagementService($pdo);
$confirm = static fn(int $id = 1, string $txn = 'SQL-REGRESSION', string $amount = '60000.00', ?array $user = null) => $manager->confirmBookingAndPayment($id, $txn, $amount, null, 'Fixture only', $user ?? $admin);
$reject = static function (callable $action, string|int $expected) use ($check): void {
    try { $action(); }
    catch (ValidationException $e) { $check(is_string($expected) && isset($e->errors[$expected]), 'expected validation field ' . $expected); return; }
    catch (HttpException $e) { $check($e->status === $expected, 'expected permission HTTP ' . $expected); return; }
    throw new RuntimeException('Expected rejection: ' . $expected);
};
$output = dirname(__DIR__) . '/storage/temp/manual-payment-sql'; if (!is_dir($output)) mkdir($output, 0777, true);
try {
    // Prove the fixture rejects the exact original binding pattern, unlike plain SQLite.
    try { $pdo->prepare('SELECT :qty + :service_id + :qty')->execute(['qty' => 2, 'service_id' => 1]); throw new RuntimeException('Old HY093 pattern accepted'); }
    catch (PDOException $e) { $check(($e->errorInfo[0] ?? '') === 'HY093', 'duplicate named binding is rejected'); }

    $saved = $pdo->query('SELECT * FROM chi_tiet_dich_vu_dat ORDER BY 1')->fetchAll();
    $result = $confirm();
    $check($result['success'] && !$pdo->inTransaction(), 'manual confirmation commits under strict binding checks');
    $check((int) $pdo->query('SELECT so_luong_ton FROM dich_vu WHERE ma_dich_vu=1')->fetchColumn() === 198, '200 physical minus snapshot quantity 2 = 198');
    $check($pdo->query('SELECT trang_thai_dat_san FROM dat_san WHERE ma_dat_san=1')->fetchColumn() === 'DA_XAC_NHAN', 'booking confirmed');
    $check($pdo->query('SELECT trang_thai FROM thanh_toan WHERE ma_dat_san=1')->fetchColumn() === 'THANH_CONG', 'payment successful in same transaction');
    $check((int) $pdo->query('SELECT COUNT(*) FROM hoa_don')->fetchColumn() === 1, 'one invoice');
    $check($pdo->query('SELECT * FROM chi_tiet_dich_vu_dat ORDER BY 1')->fetchAll() === $saved, 'snapshots not repriced or changed');
    $committed = $snapshot();
    foreach (['SQL-REGRESSION', 'OTHER-TRANSACTION'] as $txn) {
        $reject(fn() => $confirm(1, $txn), 'booking');
        $check($snapshot() === $committed, 'repeat click cannot change stock/payment/booking/invoice');
    }

    // Receipt-waiting state from the reported example: SQL compatibility only.
    // Receipt normalization must preserve the original unexpired hold.
    $reset(); $pdo->exec("UPDATE dat_san SET trang_thai_dat_san='CHO_XAC_NHAN' WHERE ma_dat_san=1");
    $confirm(); $receiptCommitted = $snapshot();
    $check((int) $pdo->query('SELECT so_luong_ton FROM dich_vu WHERE ma_dich_vu=1')->fetchColumn() === 198, 'CHO_XAC_NHAN path deducts exactly two with strict SQL bindings');
    $reject(fn() => $confirm(), 'booking'); $check($snapshot() === $receiptCommitted, 'repeated receipt confirmation has no second deduction');

    // Eligibility checks already supported; do not redefine reconciliation semantics.
    foreach ([['', '60000.00', 'ma_giao_dich'], ['OK-TXN', '1.00', 'so_tien_thuc_nhan']] as [$txn, $amount, $field]) {
        $reset(); $before = $snapshot(); $reject(fn() => $confirm(1, $txn, $amount), $field); $check($snapshot() === $before, 'invalid amount/transaction never persists a partial confirmation');
    }
    foreach (['DA_HUY', 'HET_HAN'] as $state) {
        $reset(); $pdo->prepare('UPDATE dat_san SET trang_thai_dat_san=? WHERE ma_dat_san=1')->execute([$state]);
        $before = $snapshot(); $reject(fn() => $confirm(), 'booking'); $check($snapshot() === $before, 'cancelled/expired without reconciliation is rejected');
    }
    $reset(); $pdo->exec("UPDATE dat_san SET het_han_giu_san_luc='2000-01-01 00:00:00' WHERE ma_dat_san=1");
    $before = $snapshot(); $reject(fn() => $confirm(), 'booking'); $check($snapshot() === $before, 'overdue pending without reconciliation is rejected and rolled back');
    $reset(); $pdo->exec('DELETE FROM thanh_toan WHERE ma_dat_san=1');
    $before = $snapshot(); $reject(fn() => $confirm(), 'payment'); $check($snapshot() === $before, 'missing payment cannot persist confirmation');
    $reset(); $pdo->exec("UPDATE thanh_toan SET trang_thai='THANH_CONG' WHERE ma_dat_san=1");
    $before = $snapshot(); $reject(fn() => $confirm(), 'booking'); $check($snapshot() === $before, 'paid-but-pending cannot trigger a compensating stock deduction');
    $reset(); $pdo->exec("UPDATE thanh_toan SET ma_giao_dich='ALREADY-USED' WHERE ma_dat_san=4");
    $before = $snapshot(); $reject(fn() => $confirm(1, 'ALREADY-USED'), 'ma_giao_dich'); $check($snapshot() === $before, 'transaction used by another payment cannot confirm');
    $reset(); $pdo->exec('UPDATE dich_vu SET so_luong_ton=0 WHERE ma_dich_vu=1');
    $before = $snapshot(); $reject(fn() => $confirm(), 'inventory'); $check($snapshot() === $before, 'zero physical stock rolls back payment/booking');
    $reset(); $pdo->exec('UPDATE dich_vu SET so_luong_ton=NULL WHERE ma_dich_vu=1'); $confirm();
    $check($pdo->query('SELECT so_luong_ton FROM dich_vu WHERE ma_dich_vu=1')->fetchColumn() === null, 'untracked NULL stock is never decremented');

    foreach (['QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO', 'NHAN_VIEN'] as $role) {
        $reset(); $confirm(1, 'ROLE-' . $role, '60000.00', ['ten_vai_tro' => $role] + $identity);
        $check((int) $pdo->query('SELECT so_luong_ton FROM dich_vu WHERE ma_dich_vu=1')->fetchColumn() === 198, 'authorized in-scope ' . $role . ' can confirm');
    }
    foreach ([['ten_vai_tro' => 'KHACH_HANG', 'trang_thai' => 'HOAT_DONG'],
        ['ten_vai_tro' => 'NHAN_VIEN', 'ma_co_so' => 3, 'ma_cong_ty' => 2, 'cong_ty_cua_co_so' => 2] + $identity] as $user) {
        $reset(); $before = $snapshot(); $reject(fn() => $confirm(1, 'FORBIDDEN', '60000.00', $user), 403); $check($snapshot() === $before, 'customer/foreign staff cannot mutate payment/inventory');
    }
    $reset(); $_SESSION['_csrf'] = 'fixture-valid-token'; $_POST = ['id' => '1', '_token' => 'invalid'];
    $app = new Core\Application($pdo); $router = new Core\Router(); require dirname(__DIR__) . '/routes/bookings.php';
    $before = $snapshot(); $reject(fn() => $router->dispatch('POST', '/admin/bookings/confirm'), 403); $check($snapshot() === $before, 'real router CSRF rejection precedes payment writes');

    // A real SQLite error on the second stock line must roll back the first line too.
    $reset();
    $pdo->exec("INSERT INTO dich_vu (ma_dich_vu,ma_co_so,ten_dich_vu,don_vi_tinh,loai_dich_vu,so_luong_ton,don_gia) VALUES (2,1,'Second fixture service','chai','DO_UONG',20,'3000.00');
        INSERT INTO chi_tiet_dich_vu_dat (ma_chi_tiet_dv,ma_dat_san,ma_dich_vu,ten_dich_vu_snapshot,don_vi_tinh_snapshot,so_luong,don_gia,thanh_tien) VALUES (2,1,2,'Second snapshot','chai',1,'3000.00','3000.00');
        UPDATE dat_san SET tong_tien_dich_vu='23000.00',tong_tien_tam_tinh='63000.00' WHERE ma_dat_san=1;
        UPDATE thanh_toan SET so_tien='63000.00' WHERE ma_dat_san=1;
        CREATE TRIGGER fail_second_stock BEFORE UPDATE OF so_luong_ton ON dich_vu WHEN NEW.ma_dich_vu=2 BEGIN SELECT RAISE(ABORT, 'Injected stock SQL failure'); END");
    $before = $snapshot();
    NativePaymentStatement::$stockUpdates = [];
    try { $confirm(1, 'ROLLBACK-STOCK', '63000.00'); throw new RuntimeException('Injected SQL error not raised'); }
    catch (PDOException $e) { $check(str_contains($e->getMessage(), 'Injected stock SQL failure'), 'SQL exception is propagated'); }
    $check(NativePaymentStatement::$stockUpdates === [1], 'first stock update executed before second-line SQL failure');
    $check($snapshot() === $before && !$pdo->inTransaction(), 'second stock SQL failure rolls back both stocks/payment/booking/invoice');
    $pdo->exec('DROP TRIGGER fail_second_stock');
    $confirm(1, 'ROLLBACK-STOCK', '63000.00'); $check((int) $pdo->query('SELECT so_luong_ton FROM dich_vu WHERE ma_dich_vu=1')->fetchColumn() === 198, 'retry after rollback deducts once');

    $reset(); $pdo->exec("CREATE TRIGGER fail_invoice BEFORE INSERT ON hoa_don BEGIN SELECT RAISE(ABORT, 'Injected invoice failure'); END");
    $before = $snapshot();
    try { $confirm(); throw new RuntimeException('Invoice failure not raised'); }
    catch (PDOException $e) { $check(str_contains($e->getMessage(), 'Injected invoice failure'), 'downstream invoice failure propagated'); }
    $check($snapshot() === $before && !$pdo->inTransaction(), 'downstream failure rolls back stock/payment/booking');
    $pdo->exec('DROP TRIGGER fail_invoice');
    $reset();
    $pdo->exec("UPDATE dich_vu SET loai_dich_vu='CHO_THUE',don_vi_tinh='cay',so_luong_ton=6 WHERE ma_dich_vu=1;
        UPDATE chi_tiet_dich_vu_dat SET ten_dich_vu_snapshot='Rental snapshot',don_vi_tinh_snapshot='cay' WHERE ma_dich_vu=1");
    $rentalSnapshot = $pdo->query('SELECT * FROM chi_tiet_dich_vu_dat ORDER BY 1')->fetchAll();
    $confirm(1, 'RENTAL-ONCE');
    $check((int) $pdo->query('SELECT so_luong_ton FROM dich_vu WHERE ma_dich_vu=1')->fetchColumn() === 4, 'existing manual payment writer deducts rental 6 - 2 = 4');
    $paidRental = $snapshot();
    $reject(fn() => $confirm(1, 'RENTAL-ONCE'), 'booking');
    $check($snapshot() === $paidRental, 'sequential repeated rental confirmation leaves stock/payment/booking unchanged');
    $check($pdo->query('SELECT * FROM chi_tiet_dich_vu_dat ORDER BY 1')->fetchAll() === $rentalSnapshot, 'rental payment preserves saved service snapshot');
    $report = ['status' => 'PASS', 'checks' => $checks, 'mode' => 'SQLite RAM + native-binding guard; real service code', 'not_run' => ['MySQL DML/row locks', 'Concurrent confirmation clicks', 'Bank provider callback'], 'state_guards' => 'Cancelled/expired bookings and terminal payments are rejected; receipt normalization preserves expiry'];
} catch (Throwable $e) {
    $report = ['status' => 'FAIL', 'checks' => $checks, 'exception' => get_class($e), 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()];
}
file_put_contents($output . '/unit-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
exit($report['status'] === 'PASS' ? 0 : 1);
