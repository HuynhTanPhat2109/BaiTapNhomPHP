<?php

// Dedicated isolated unit tests for PR4: Invoice Management & Invoicing within Transaction.
// Zero network calls, zero database writes to development DB.

require dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

use App\Models\HoaDon;
use App\Services\BookingManagementService;
use App\Services\InvoiceService;
use App\Services\ValidationException;
use Core\HttpException;

require_once __DIR__ . '/booking-management-unit.php';

$invoiceChecks = 0;
function assertInvoice(bool $condition, string $message): void
{
    global $invoiceChecks;
    if (!$condition) {
        throw new RuntimeException("FAIL: " . $message);
    }
    $invoiceChecks++;
}

echo "=== STARTING DEDICATED PR4 INVOICE UNIT TESTS ===" . PHP_EOL;

$db = new BookingAdminMemoryPDO();
$bookingService = new BookingManagementService($db);
$invService = new InvoiceService($db, new HoaDon($db));

$facilityMgr = [
    'ten_vai_tro' => 'QUAN_LY_CO_SO',
    'trang_thai' => 'HOAT_DONG',
    'ma_nhan_vien' => 5,
    'trang_thai_nhan_vien' => 'DANG_LAM',
    'ma_cong_ty' => 1,
    'trang_thai_cong_ty' => 'HOAT_DONG',
    'ma_co_so' => 1,
    'trang_thai_co_so' => 'HOAT_DONG',
    'cong_ty_cua_co_so' => 1,
];

// 1. Thanh toán thành công tạo đúng một hóa đơn trong cùng Transaction
$res = $bookingService->confirmBookingAndPayment(
    1,
    'FT_INV_TEST_001',
    '220000.00',
    '2026-11-02 16:00:00',
    'Khách chuyển khoản đúng',
    $facilityMgr
);
assertInvoice($res['success'] === true, 'Confirmation succeeded');
assertInvoice(isset($res['invoice_id']) && $res['invoice_id'] > 0, 'Returned invoice_id > 0');
assertInvoice(isset($res['invoice_number']) && str_starts_with($res['invoice_number'], 'INV'), 'Invoice number has standard INV prefix');
assertInvoice(count($db->invoices) === 1, 'Database contains exactly 1 invoice');

$inv = reset($db->invoices);
assertInvoice($inv['ma_dat_san'] === 1, 'Invoice ma_dat_san matches booking 1');
assertInvoice($inv['ma_thanh_toan'] === 1, 'Invoice ma_thanh_toan matches payment 1');
assertInvoice($inv['ma_nhan_vien'] === 5, 'Invoice records staff ID');
assertInvoice($inv['tong_tien'] === '220000.00', 'Invoice tong_tien is 220000.00');
assertInvoice($inv['tien_san'] === '200000.00', 'Invoice tien_san is 200000.00');
assertInvoice($inv['tien_dich_vu'] === '20000.00', 'Invoice tien_dich_vu is 20000.00');
assertInvoice($inv['trang_thai'] === 'DA_LAP', 'Invoice status is DA_LAP');

// 2. Xác nhận cùng một booking lần thứ hai không tạo hóa đơn mới
try {
    $bookingService->confirmBookingAndPayment(
        1,
        'FT_INV_TEST_001',
        '220000.00',
        null,
        null,
        $facilityMgr
    );
    assertInvoice(false, 'Double confirmation must fail');
} catch (ValidationException $e) {
    assertInvoice(isset($e->errors['booking']), 'Double confirmation triggers validation exception');
    assertInvoice(count($db->invoices) === 1, 'No new invoice created on repeat confirmation');
}

// 3. Thanh toán sai tiền không tạo hóa đơn
$dbWrongAmount = new BookingAdminMemoryPDO();
$svcWrongAmount = new BookingManagementService($dbWrongAmount);
try {
    $svcWrongAmount->confirmBookingAndPayment(
        1,
        'FT_WRONG_AMT',
        '200000.00', // Đơn là 220.000 đ
        null,
        null,
        $facilityMgr
    );
    assertInvoice(false, 'Mismatched amount must reject confirmation');
} catch (ValidationException $e) {
    assertInvoice(isset($e->errors['so_tien_thuc_nhan']), 'Mismatch triggers so_tien_thuc_nhan error');
    assertInvoice(count($dbWrongAmount->invoices) === 0, 'Zero invoices created when amount mismatches');
}

// 4. Thanh toán CAN_DOI_SOAT không tạo hóa đơn
$resReconcile = $svcWrongAmount->markNeedsReconciliation(
    1,
    '200000.00',
    'FT_WRONG_AMT',
    'Khách chuyển thiếu tiền',
    $facilityMgr
);
assertInvoice($resReconcile['success'] === true, 'Reconciliation marked');
assertInvoice($dbWrongAmount->payments[1]['trang_thai'] === 'CAN_DOI_SOAT', 'Payment is CAN_DOI_SOAT');
assertInvoice(count($dbWrongAmount->invoices) === 0, 'Zero invoices created for CAN_DOI_SOAT');

// 5. Booking hết hạn không tạo hóa đơn
$dbExpired = new BookingAdminMemoryPDO();
$svcExpired = new BookingManagementService($dbExpired);
try {
    $svcExpired->confirmBookingAndPayment(
        2, // Booking 2 đã hết hạn
        'FT_EXP',
        '150000.00',
        null,
        null,
        $facilityMgr
    );
    assertInvoice(false, 'Expired booking confirmation must fail');
} catch (ValidationException $e) {
    assertInvoice(isset($e->errors['booking']), 'Expired booking triggers error');
    assertInvoice(count($dbExpired->invoices) === 0, 'Zero invoices created for expired booking');
}

// 6. Booking đã hủy không tạo hóa đơn
$dbCancelled = new BookingAdminMemoryPDO();
$dbCancelled->bookings[3] = [
    'ma_dat_san' => 3,
    'ma_co_so' => 1,
    'ma_khach_hang' => 12,
    'ma_nhan_vien' => null,
    'ngay_tao' => date('Y-m-d H:i:s', time() - 300),
    'bat_dau_giu_san_luc' => date('Y-m-d H:i:s', time() - 300),
    'het_han_giu_san_luc' => date('Y-m-d H:i:s', time() + 300),
    'tong_tien_san' => '100000.00',
    'tong_tien_dich_vu' => '0.00',
    'tong_tien_tam_tinh' => '100000.00',
    'trang_thai_dat_san' => 'DA_HUY',
    'ghi_chu' => null,
];
$dbCancelled->payments[3] = [
    'ma_thanh_toan' => 3,
    'ma_dat_san' => 3,
    'ma_tham_chieu' => 'PAY261102B0003HUY',
    'so_tien' => '100000.00',
    'so_tien_thuc_nhan' => null,
    'ma_giao_dich' => null,
    'phuong_thuc' => 'CHUYEN_KHOAN',
    'trang_thai' => 'HUY',
    'ngay_tao' => date('Y-m-d H:i:s', time() - 300),
    'ngay_thanh_toan' => null,
    'ghi_chu' => null,
];
$svcCancelled = new BookingManagementService($dbCancelled);
try {
    $svcCancelled->confirmBookingAndPayment(3, 'FT_CANCELLED', '100000.00', null, null, $facilityMgr);
    assertInvoice(false, 'Cancelled booking must fail');
} catch (ValidationException $e) {
    assertInvoice(isset($e->errors['booking']), 'Cancelled booking triggers error');
    assertInvoice(count($dbCancelled->invoices) === 0, 'Zero invoices created for cancelled booking');
}

// 7. Tạo hóa đơn thất bại phải rollback toàn bộ thanh toán và booking
$dbInvErr = new BookingAdminMemoryPDO();
$dbInvErr->simulateInvoiceCreationFailure = true;
$svcInvErr = new BookingManagementService($dbInvErr);
try {
    $svcInvErr->confirmBookingAndPayment(1, 'FT_INV_ERR', '220000.00', null, null, $facilityMgr);
    assertInvoice(false, 'Simulated invoice failure must throw');
} catch (PDOException $e) {
    assertInvoice($dbInvErr->inTransaction() === false, 'Transaction was rolled back');
    assertInvoice($dbInvErr->payments[1]['trang_thai'] === 'CHO_THANH_TOAN', 'Payment rolled back to CHO_THANH_TOAN');
    assertInvoice($dbInvErr->bookings[1]['trang_thai_dat_san'] === 'CHO_THANH_TOAN', 'Booking rolled back to CHO_THANH_TOAN');
    assertInvoice($dbInvErr->services[101]['so_luong_ton'] === 10, 'Service stock untouched at 10');
    assertInvoice(count($dbInvErr->invoices) === 0, 'Zero invoices present in DB');
}

// 8. Trừ tồn kho thất bại phải rollback toàn bộ (không tạo hóa đơn)
$dbStockErr = new BookingAdminMemoryPDO();
$dbStockErr->services[101]['so_luong_ton'] = 1; // Thiếu hàng
$svcStockErr = new BookingManagementService($dbStockErr);
try {
    $svcStockErr->confirmBookingAndPayment(1, 'FT_STOCK_ERR', '220000.00', null, null, $facilityMgr);
    assertInvoice(false, 'Insufficient stock must throw');
} catch (ValidationException $e) {
    assertInvoice(isset($e->errors['inventory']), 'Inventory error triggered');
    assertInvoice($dbStockErr->inTransaction() === false, 'Transaction was rolled back');
    assertInvoice($dbStockErr->payments[1]['trang_thai'] === 'CHO_THANH_TOAN', 'Payment is still CHO_THANH_TOAN');
    assertInvoice($dbStockErr->bookings[1]['trang_thai_dat_san'] === 'CHO_THANH_TOAN', 'Booking is still CHO_THANH_TOAN');
    assertInvoice(count($dbStockErr->invoices) === 0, 'Zero invoices created when stock fails');
}

// 9. Hai yêu cầu xác nhận đồng thời không tạo hai hóa đơn
$dbConcurrent = new BookingAdminMemoryPDO();
$svcConcurrent = new BookingManagementService($dbConcurrent);
$resA = $svcConcurrent->confirmBookingAndPayment(1, 'FT_CONC_A', '220000.00', null, null, $facilityMgr);
assertInvoice($resA['success'] === true, 'Request A succeeded');
assertInvoice(count($dbConcurrent->invoices) === 1, 'Request A created invoice');
try {
    $svcConcurrent->confirmBookingAndPayment(1, 'FT_CONC_B', '220000.00', null, null, $facilityMgr);
    assertInvoice(false, 'Request B on same booking must fail');
} catch (ValidationException $e) {
    assertInvoice(isset($e->errors['booking']), 'Request B rejected because booking already confirmed');
    assertInvoice(count($dbConcurrent->invoices) === 1, 'Database strictly retains only 1 invoice');
}

// 10. Khách hàng không thể xem hóa đơn của người khác (Anti-IDOR)
$invServiceTest = new InvoiceService($db, new HoaDon($db));
$invoiceId = (int) $res['invoice_id'];

// Chủ sở hữu (khách hàng 10) truy cập được
$detailOwner = $invServiceTest->getInvoice($invoiceId, null, 10);
assertInvoice($detailOwner['invoice']['ma_hoa_don'] === $invoiceId, 'Invoice owner accesses invoice successfully');

// Khách hàng lạ (khách hàng 99) truy cập bị 403
try {
    $invServiceTest->getInvoice($invoiceId, null, 99);
    assertInvoice(false, 'Cross-customer access must throw 403');
} catch (HttpException $e) {
    assertInvoice($e->status === 403, 'Customer IDOR prevented with 403 Forbidden');
}

// 11. Nhân viên không thể xem hóa đơn ngoài phạm vi được phân quyền (Anti-IDOR)
$scopeOtherFacility = ['company' => 2, 'facility' => 2];
try {
    $invServiceTest->getInvoice($invoiceId, $scopeOtherFacility, null);
    assertInvoice(false, 'Cross-facility access must throw 403');
} catch (HttpException $e) {
    assertInvoice($e->status === 403, 'Facility IDOR prevented with 403 Forbidden');
}

// 12. Quản lý cơ sở 1 (đúng phạm vi) truy cập được
$scopeCorrectFacility = ['company' => 1, 'facility' => 1];
$detailFac1 = $invServiceTest->getInvoice($invoiceId, $scopeCorrectFacility, null);
assertInvoice($detailFac1['invoice']['ma_hoa_don'] === $invoiceId, 'Facility manager accesses invoice in scope');

// 13. Nhân viên cơ sở 1 (NHAN_VIEN) truy cập và in hóa đơn đúng phạm vi
$scopeStaffF1 = ['role' => 'NHAN_VIEN', 'company' => 1, 'facility' => 1, 'staff_id' => 22];
$detailStaffF1 = $invServiceTest->getInvoice($invoiceId, $scopeStaffF1, null);
assertInvoice($detailStaffF1['invoice']['ma_hoa_don'] === $invoiceId, 'Staff accesses invoice in scope');
assertInvoice(!empty($detailStaffF1['facility']['ten_co_so']), 'Facility details present for POS printing');

// 14. Nhân viên cơ sở 2 (NHAN_VIEN) truy cập hóa đơn cơ sở 1 -> Bị chặn 403
$scopeStaffF2 = ['role' => 'NHAN_VIEN', 'company' => 2, 'facility' => 2, 'staff_id' => 25];
try {
    $invServiceTest->getInvoice($invoiceId, $scopeStaffF2, null);
    assertInvoice(false, 'Cross-facility invoice print/view by staff must throw 403');
} catch (HttpException $e) {
    assertInvoice($e->status === 403, 'Cross-facility staff access prevented with 403 Forbidden');
}

echo "PASS: Tất cả {$invoiceChecks} bài kiểm thử độc lập chuyên sâu cho Hóa đơn (PR4) đều đạt 100%!" . PHP_EOL;
