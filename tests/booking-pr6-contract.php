<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// State contract regressions: cancelled/expired bookings and terminal payments remain protected.
// Runs real BookingManagementService + InvoiceService + SQL on SQLite :memory:.
// FOR UPDATE stripped: proves state transitions/rollback, NOT MySQL locking/idempotency.
define('STRYKE_PR6_CONTRACT', true);
require __DIR__ . '/fixtures/booking-pr6.php';

$results = [];
$check = static function (bool $ok, string $name) use (&$results): void { $results[] = ['status' => $ok ? 'PASS' : 'FAIL', 'name' => $name]; };
$admin = ['ten_vai_tro' => 'QUAN_TRI_TAP_DOAN', 'trang_thai' => 'HOAT_DONG', 'ma_nhan_vien' => null];
$manager = new App\Services\BookingManagementService($pdo);
$row = static function (int $id) use ($pdo): array { $s = $pdo->prepare('SELECT * FROM dat_san WHERE ma_dat_san=?'); $s->execute([$id]); return $s->fetch(); };
$stock = static fn() => (int) $pdo->query('SELECT so_luong_ton FROM dich_vu WHERE ma_dich_vu=1')->fetchColumn();
$snapshot = $pdo->query('SELECT * FROM chi_tiet_dich_vu_dat')->fetchAll();
try {
    $result = $manager->confirmBookingAndPayment(1, 'PR6-VALID', '60000.00', null, 'RAM contract only', $admin);
    $check($result['success'] && $stock() === 198 && $row(1)['trang_thai_dat_san'] === 'DA_XAC_NHAN', 'shared inventory hook deducts 2 once (SQLite binding semantics only)');
    $check($pdo->query('SELECT * FROM chi_tiet_dich_vu_dat')->fetchAll() === $snapshot, 'manual confirmation preserves snapshots and catalog price independence');
    try { $manager->confirmBookingAndPayment(1, 'PR6-VALID', '60000.00', null, null, $admin); $rejected = false; }
    catch (App\Services\ValidationException $e) { $rejected = true; }
    $check($rejected && $stock() === 198, 'sequential manual retry rejects without another stock deduction');
    try { $manager->confirmBookingAndPayment(1, 'PR6-DIFFERENT-TXN', '60000.00', null, null, $admin); $rejected = false; }
    catch (App\Services\ValidationException $e) { $rejected = true; }
    $check($rejected && $stock() === 198, 'different transaction cannot reconfirm an already confirmed booking (sequential, not callback verification)');

    $pdo->exec("INSERT INTO chi_tiet_dich_vu_dat (ma_chi_tiet_dv,ma_dat_san,ma_dich_vu,ten_dich_vu_snapshot,don_vi_tinh_snapshot,so_luong,don_gia,thanh_tien)
        VALUES (8,8,1,'Nước snapshot PR6','chai',2,'10000.00','20000.00');
        UPDATE dat_san SET tong_tien_dich_vu='20000.00',tong_tien_tam_tinh='60000.00' WHERE ma_dat_san=8;
        UPDATE thanh_toan SET so_tien='60000.00' WHERE ma_dat_san=8");
    $pdo->exec("CREATE TRIGGER fail_invoice BEFORE INSERT ON hoa_don BEGIN SELECT RAISE(ABORT, 'PR6 invoice failure'); END");
    $old = $row(8);
    try { $manager->confirmBookingAndPayment(8, 'PR6-ROLLBACK', '60000.00', null, null, $admin); $failed = false; }
    catch (PDOException $e) { $failed = str_contains($e->getMessage(), 'PR6 invoice failure'); }
    $check($failed && $row(8) === $old && $stock() === 198 && !$pdo->inTransaction(), 'invoice failure rolls back stock/booking/normalized hold');
    $check($pdo->query('SELECT trang_thai FROM thanh_toan WHERE ma_dat_san=8')->fetchColumn() === 'CHO_THANH_TOAN', 'invoice failure rolls back payment success');
    $pdo->exec('DROP TRIGGER fail_invoice');

    foreach ([2 => 'cancelled', 3 => 'expired'] as $id => $label) {
        $pdo->exec("UPDATE thanh_toan SET trang_thai='CAN_DOI_SOAT' WHERE ma_dat_san=$id");
        $original = $row($id);
        try { $manager->confirmBookingAndPayment($id, 'PR6-RECONCILE-' . $id, '40000.00', null, 'Must not revive expired/cancelled', $admin); }
        catch (App\Services\ValidationException $e) { /* Rejection is acceptable; state must remain protected. */ }
        $current = $row($id);
        $check($current['trang_thai_dat_san'] === $original['trang_thai_dat_san'] && $current['het_han_giu_san_luc'] === $original['het_han_giu_san_luc'], "$label + reconciliation must NOT be revived or have hold extended");
    }
    // A terminal payment must not be silently reactivated even if the booking is pending.
    foreach (['HUY', 'HET_HAN'] as $paymentState) {
        $pdo->exec('DELETE FROM hoa_don WHERE ma_dat_san=5');
        $pdo->prepare("UPDATE dat_san SET trang_thai_dat_san='CHO_THANH_TOAN',het_han_giu_san_luc=? WHERE ma_dat_san=5")
            ->execute([date('Y-m-d H:i:s', time() + 360)]);
        $pdo->exec('DELETE FROM thanh_toan WHERE ma_dat_san=5');
        $pdo->prepare("INSERT INTO thanh_toan (ma_thanh_toan,ma_dat_san,ma_tham_chieu,so_tien,trang_thai,phuong_thuc) VALUES (5,5,'PR6-TERMINAL','40000.00',?,'CHUYEN_KHOAN')")
            ->execute([$paymentState]);
        try { $manager->confirmBookingAndPayment(5, 'PR6-TERMINAL-' . $paymentState, '40000.00', null, null, $admin); }
        catch (App\Services\ValidationException $e) { /* A terminal payment must remain terminal. */ }
        $check($row(5)['trang_thai_dat_san'] === 'CHO_THANH_TOAN'
            && $pdo->query('SELECT trang_thai FROM thanh_toan WHERE ma_dat_san=5')->fetchColumn() === $paymentState,
            "payment $paymentState must NOT become successful through confirmation");
    }
} catch (Throwable $e) { $results[] = ['status' => 'FAIL', 'name' => 'Unexpected contract test error', 'exception' => get_class($e), 'message' => $e->getMessage()]; }
$failures = count(array_filter($results, static fn($r) => $r['status'] === 'FAIL'));
$report = ['mode' => 'SQLite :memory: only, actual manual confirmation service; not provider/MySQL concurrency', 'results' => $results,
    'blocked' => ['No provider callback verification endpoint to exercise'],
    'not_run' => ['Concurrent callbacks', 'Concurrent cancel/expire/payment', 'MySQL deadlock/retry', 'Real bank transfers']];
$out = dirname(__DIR__) . '/storage/temp/pr6-review'; if (!is_dir($out)) mkdir($out, 0777, true);
file_put_contents($out . '/contract-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
foreach ($results as $r) echo $r['status'] . ': ' . $r['name'] . PHP_EOL;
echo 'Result: ' . (count($results) - $failures) . " PASS, $failures FAIL; payment hook connected through BookingManagementService, concurrency NOT RUN.\n";
exit($failures ? 1 : 0);
