<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('STRYKE_PR6_CONTRACT', true);
require __DIR__ . '/fixtures/booking-pr6.php';
use App\Services\BookingManagementService as Management;
use App\Services\ValidationException;
$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException($message);
    $checks++;
};
$clock = Management::now();
foreach (['CHO_THANH_TOAN', 'CHO_XAC_NHAN', 'DA_XAC_NHAN', 'HOAN_THANH', 'DA_HUY', 'HET_HAN'] as $bookingState) {
    foreach (['CHO_THANH_TOAN', 'CHO_XAC_NHAN', 'CAN_DOI_SOAT', 'THANH_CONG', 'HUY', 'HET_HAN', ''] as $paymentState) {
        foreach ([-1, 0, 360] as $seconds) {
            $row = ['trang_thai_dat_san' => $bookingState, 'trang_thai_thanh_toan' => $paymentState,
                'het_han_giu_san_luc' => $clock->modify("$seconds seconds")->format('Y-m-d H:i:s')];
            $actions = Management::actions($row, $clock);
            $eligible = $seconds > 0 && in_array($bookingState, ['CHO_THANH_TOAN', 'CHO_XAC_NHAN'], true)
                && in_array($paymentState, ['CHO_THANH_TOAN', 'CHO_XAC_NHAN', 'CAN_DOI_SOAT'], true);
            $check($actions['confirm'] === $eligible && $actions['cancel'] === $eligible, "$bookingState/$paymentState/$seconds confirmation and cancel eligibility");
            if ($paymentState === 'THANH_CONG') $check(!$actions['reconcile'], 'successful payment is view-only');
        }
    }
}
$user = ['ten_vai_tro' => 'QUAN_TRI_TAP_DOAN', 'trang_thai' => 'HOAT_DONG'];
$manager = new Management($pdo);
$snapshot = static fn() => array_map(static fn($table) => $pdo->query("SELECT * FROM $table ORDER BY 1")->fetchAll(), ['dat_san', 'thanh_toan', 'dich_vu', 'hoa_don', 'chi_tiet_dich_vu_dat']);
$reject = static function (callable $action, string $label) use ($check, $snapshot): void {
    $before = $snapshot();
    try { $action(); } catch (ValidationException $e) {
        $check($snapshot() === $before, $label . ': no writes, inventory or invoice side effects');
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $label);
};
$reject(fn() => $manager->cancelBookingByAdmin(4, 'Cannot cancel paid booking', $user), 'paid booking cancellation');
$reject(fn() => $manager->cancelBookingByAdmin(1, '', $user), 'cancellation reason required');
$reject(fn() => $manager->markNeedsReconciliation(4, '60000', 'PAID-RETRY', 'Cannot overwrite paid transaction', $user), 'paid reconciliation');
$reject(fn() => $manager->markNeedsReconciliation(3, '0', 'LATE', 'No money received', $user), 'late transfer requires positive recorded amount');
$stock = $pdo->query('SELECT * FROM dich_vu ORDER BY 1')->fetchAll();
$manager->markNeedsReconciliation(3, '1000', 'LATE', 'Recorded late money only', $user);
$check($pdo->query('SELECT trang_thai_dat_san FROM dat_san WHERE ma_dat_san=3')->fetchColumn() === 'HET_HAN', 'late transfer never revives the booking');
$check($pdo->query('SELECT * FROM dich_vu ORDER BY 1')->fetchAll() === $stock, 'late reconciliation never deducts stock');
$reject(fn() => $manager->markNeedsReconciliation(3, '1000', 'LATE', 'Repeated request', $user), 'reconciliation repeat cannot overwrite recorded transaction');
$reject(fn() => $manager->confirmBookingAndPayment(3, 'LATE', '40000', null, null, $user), 'reconciled expired booking confirmation');
$pdo->exec("UPDATE dat_san SET trang_thai_dat_san='CHO_XAC_NHAN', het_han_giu_san_luc='2000-01-01 00:00:00' WHERE ma_dat_san=1;
    UPDATE thanh_toan SET trang_thai='CAN_DOI_SOAT' WHERE ma_dat_san=1");
$reject(fn() => $manager->confirmBookingAndPayment(1, 'RECEIPT-EXPIRED', '60000', null, null, $user), 'receipt/reconciliation never extends expired hold');
$check(!$pdo->inTransaction(), 'every failed write closes its transaction');
echo "PASS: $checks booking action eligibility and real-service RAM regression checks.\n";
