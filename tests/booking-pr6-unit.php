<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Production booking/catalog/inventory code, array PDO only. No .env/MySQL/provider.
// The payment caller below remains a CONTRACT SIMULATION, not Duy's implementation.
require __DIR__ . '/booking-inventory-unit.php';

use App\Models\Booking;
use App\Services\{BookingInventoryService, BookingService, CourtScheduleService};

$beforeChecks = $checks;
$db = new InventoryMemoryPDO(); $db->services[1]['so_luong_ton'] = 200;
$booking = new BookingService($db); $model = new Booking($db);
$a = inventoryPending($db, [1 => 2, 2 => 3]);
$snapshots = $db->serviceLines; $totals = $db->bookings[$a];
inventoryCheck(count($snapshots) === 2 && $snapshots[1]['so_luong'] === 3, 'checkout saves multiple snapshot services');
inventoryCheck($db->services[1]['so_luong_ton'] === 200 && $model->reserved(1, CourtScheduleService::now()->format('Y-m-d H:i:s')) === 2, '200 physical / 2 virtual while pending');
inventoryCheck($booking->quote(inventorySelection(2))['offers'][1]['available'] === 198, '200 physical minus 2 virtual = 198 available');
inventoryCatalogEdit($db, ['ten_dich_vu' => 'Tên catalog đã đổi', 'don_gia' => '15000.00']);
inventoryCheck($db->serviceLines === $snapshots && $db->bookings[$a] === $totals, 'catalog edit cannot rewrite existing snapshots/totals');
inventoryCheck(inventoryCaller($db, $a) === 'applied' && $db->services[1]['so_luong_ton'] === 198, 'simulated verified caller deducts snapshot quantity once');
inventoryCheck($model->reserved(1, CourtScheduleService::now()->format('Y-m-d H:i:s')) === 0 && $booking->quote(inventorySelection(2))['offers'][1]['available'] === 198, 'confirmed booking no longer double-counted as virtual hold');
inventoryCheck(inventoryCaller($db, $a) === 'duplicate' && $db->services[1]['so_luong_ton'] === 198, 'simulated caller duplicate gate prevents second deduction');
$booking->cancelHold($a, 1);
inventoryCheck($db->bookings[$a]['trang_thai_dat_san'] === 'DA_XAC_NHAN' && $db->services[1]['so_luong_ton'] === 198, 'customer cancel cannot cancel confirmed booking or restock');
inventoryCheck($db->serviceLines === $snapshots, 'confirmation/retry/cancel attempt keep saved snapshots');

$db = new InventoryMemoryPDO(); $db->services[1]['so_luong_ton'] = 200;
$model = new Booking($db); $booking = new BookingService($db);
$a = inventoryPending($db, [1 => 2]); $b = inventoryPending($db, [1 => 3], 2);
inventoryCheck($model->reserved(1, CourtScheduleService::now()->format('Y-m-d H:i:s')) === 5 && $db->services[1]['so_luong_ton'] === 200, 'several pending bookings reserve 5 without physical deduction');
$selection = inventorySelection(); $selection['date'] = CourtScheduleService::now()->modify('+2 days')->format('Y-m-d');
inventoryCheck($booking->quote($selection)['offers'][1]['available'] === 195, 'availability subtracts all pending service holds');
inventoryCaller($db, $a);
inventoryCheck($db->services[1]['so_luong_ton'] === 198 && $model->reserved(1, CourtScheduleService::now()->format('Y-m-d H:i:s')) === 3, 'payment simulation releases only its own hold');
$snapshot = $db->serviceLines; $booking->cancelHold($b, 2);
inventoryCheck($db->services[1]['so_luong_ton'] === 198 && $model->reserved(1, CourtScheduleService::now()->format('Y-m-d H:i:s')) === 0 && $db->serviceLines === $snapshot, 'cancel pending releases virtual hold without physical restock/snapshot edits');

foreach ([0, 1] as $stock) {
    $db = new InventoryMemoryPDO(); $db->services[1]['so_luong_ton'] = $stock;
    inventoryReject(fn() => inventoryPending($db, [1 => 2]), 409);
    inventoryCheck($db->services[1]['so_luong_ton'] === $stock && !$db->bookings, 'zero/insufficient stock rejects checkout without partial writes');
}
$db = new InventoryMemoryPDO(); $db->services[1]['trang_thai'] = 'NGUNG_CUNG_CAP';
inventoryCheck(!isset((new BookingService($db))->quote(inventorySelection())['offers'][1]), 'stopped service not offered');
inventoryReject(fn() => inventoryPending($db, [1 => 1]), 409);
$id = inventoryPending($db, [2 => 999999]); inventoryCaller($db, $id);
inventoryCheck($db->services[2]['so_luong_ton'] === null, 'NULL stock remains untracked after large snapshot quantity');

foreach (['THANH_CONG', 'CAN_DOI_SOAT', null] as $payment) {
    $db = new InventoryMemoryPDO(); $db->services[1]['so_luong_ton'] = 200;
    $id = inventoryPending($db, [1 => 2]); $snapshots = $db->serviceLines;
    $db->bookings[$id]['het_han_giu_san_luc'] = '2000-01-01 00:00:00';
    if ($payment !== null) $db->payments[$id] = $payment;
    $expired = (new BookingService($db))->expirePending();
    inventoryCheck($expired === ($payment === null ? 1 : 0), 'expiry excludes paid/reconciliation anomalies');
    inventoryCheck($db->bookings[$id]['trang_thai_dat_san'] === ($payment === null ? 'HET_HAN' : 'CHO_THANH_TOAN'), 'expiry preserves protected payment booking for reconciliation');
    inventoryCheck($db->services[1]['so_luong_ton'] === 200 && $db->serviceLines === $snapshots, 'expiry never writes physical stock or snapshot');
    inventoryCheck((new Booking($db))->reserved(1, CourtScheduleService::now()->format('Y-m-d H:i:s')) === 0, 'overdue protected pending does not retain virtual stock');
    $db->beginTransaction(); inventoryReject(fn() => (new BookingInventoryService($db))->deductForPayment($id), 409); $db->rollBack();
}

// Explicit limitation: the hook itself has no durable idempotency marker.
$db = new InventoryMemoryPDO(); $db->services[1]['so_luong_ton'] = 200; $id = inventoryPending($db, [1 => 2]);
$db->beginTransaction(); $inventory = new BookingInventoryService($db);
$inventory->deductForPayment($id); $inventory->deductForPayment($id);
inventoryCheck($db->services[1]['so_luong_ton'] === 196, 'demonstrate caller MUST NOT invoke hook twice while booking remains pending');
$db->rollBack(); inventoryCheck($db->services[1]['so_luong_ton'] === 200, 'caller rollback restores entire duplicate simulation');

// A simulated lock wait crosses the real server-time deadline, not a MySQL lock test.
$db = new InventoryMemoryPDO(); $id = inventoryPending($db, [1 => 2]);
$db->bookings[$id]['het_han_giu_san_luc'] = CourtScheduleService::now()->modify('+1 second')->format('Y-m-d H:i:s');
$db->serviceLockDelayMs = 2100; $db->beginTransaction();
inventoryReject(fn() => (new BookingInventoryService($db))->deductForPayment($id), 409);
inventoryCheck($db->services[1]['so_luong_ton'] === 10 && $db->inTransaction(), 'deadline rechecked after service lock wait, caller retains transaction');
$db->rollBack();
echo 'PASS: ' . ($checks - $beforeChecks) . " additional PR6 memory checks; payment/concurrency remain NOT RUN.\n";
