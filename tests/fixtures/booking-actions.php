<?php
// Production controller/service/routes on SQLite RAM, persisted only in a fixture PHP session.
if (PHP_SAPI !== 'cli-server' || getenv('STRYKE_BOOKING_ACTIONS_UI') !== '1') { http_response_code(404); exit; }
if (str_starts_with(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/assets/')) return false;
define('STRYKE_PR6_CONTRACT', true); // Strip MySQL row locks in this isolated fixture; not a lock-concurrency test.
define('STRYKE_BOOKING_ACTIONS_FIXTURE', true);
define('STRYKE_RECEIPT_NUMERIC', true);
require __DIR__ . '/booking-pr6.php';
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'");
$tables = ['dat_san', 'thanh_toan', 'dich_vu', 'hoa_don', 'chi_tiet_dat_san', 'chi_tiet_dich_vu_dat'];
if (($_GET['reset'] ?? '') === '1') unset($_SESSION['_booking_action_fixture'], $_SESSION['_bank_source_mode']);

if (isset($_SESSION['_booking_action_fixture'])) {
    foreach ($tables as $table) {
        $pdo->exec('DELETE FROM ' . $table);
        foreach ($_SESSION['_booking_action_fixture'][$table] as $row) $insert($table, $row);
    }
}
register_shutdown_function(static function () use ($pdo, $tables): void {
    if ($pdo->inTransaction()) $pdo->rollBack();
    foreach ($tables as $table) $_SESSION['_booking_action_fixture'][$table] = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY 1')->fetchAll();
});
if (($_GET['seed_recorded'] ?? '') === '1') $pdo->exec("UPDATE thanh_toan SET trang_thai='CAN_DOI_SOAT', so_tien_thuc_nhan='55000', ma_giao_dich='RAM-RECORDED', ngay_thanh_toan='2026-11-04 09:00:00' WHERE ma_dat_san=1");
if (($_GET['invoice_error'] ?? '') === '1') $pdo->exec("CREATE TRIGGER fail_action_invoice BEFORE INSERT ON hoa_don BEGIN SELECT RAISE(ABORT, 'RAM invoice failure'); END");
$insert('tai_khoan_ngan_hang_co_so', ['ma_tk_ngan_hang' => 1, 'ma_co_so' => 1, 'ma_bin' => '970422',
    'so_tai_khoan' => 'RAM-ACCOUNT', 'ten_ngan_hang' => 'Ngân hàng fixture', 'trang_thai' => 'HOAT_DONG', 'la_mac_dinh' => 1, 'so_tai_khoan' => '12345678', 'ten_chu_tai_khoan' => 'TEST FIXTURE']);
$receiptRoot = getenv('STRYKE_RECEIPT_ROOT') ?: $root . '/storage/temp/receipt-confirmation-review/private';
if (!is_dir($receiptRoot . '/receipts')) mkdir($receiptRoot . '/receipts', 0700, true);
$fixturePng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aLX0AAAAASUVORK5CYII=');
if (!is_file($receiptRoot . '/qr.png')) file_put_contents($receiptRoot . '/qr.png', $fixturePng);
$insert('tai_khoan', ['ma_tai_khoan' => 1, 'ten_dang_nhap' => 'Operator fixture', 'trang_thai' => 'HOAT_DONG']);
$pdo->sqliteCreateFunction('current_timestamp', static fn() => date('Y-m-d H:i:s'));
$insert('vai_tro', ['ma_vai_tro' => 5, 'ten_vai_tro' => 'KHACH_HANG']);
$insert('tai_khoan', ['ma_tai_khoan' => 2, 'ten_dang_nhap' => 'Customer fixture', 'trang_thai' => 'HOAT_DONG', 'ma_vai_tro' => 5]);
$pdo->exec("UPDATE khach_hang SET ma_tai_khoan=2, trang_thai='HOAT_DONG' WHERE ma_khach_hang=10;
    UPDATE bo_mon SET trang_thai='HOAT_DONG'; UPDATE loai_san SET trang_thai='HOAT_DONG';
    UPDATE san SET trang_thai='HOAT_DONG'; UPDATE dich_vu SET trang_thai='DANG_CUNG_CAP';
    UPDATE co_so SET gio_mo_cua='06:00:00', gio_dong_cua='23:00:00'");
foreach (['NGAY_THUONG','CUOI_TUAN'] as $dayType) $insert('bang_gia', ['ma_co_so' => 1, 'ma_loai_san' => 1,
    'ten_bang_gia' => 'Fixture price', 'loai_ngay' => $dayType, 'gio_bat_dau' => '06:00:00', 'gio_ket_thuc' => '23:00:00',
    'don_gia' => '80000.00', 'ngay_bat_dau' => '2000-01-01', 'trang_thai' => 'DANG_AP_DUNG']);
if (($_GET['seed_receipt'] ?? '') === '1') {
    $image = 'receipts/receipt_1_' . substr(hash('sha256', session_id()), 0, 16) . '.png';
    file_put_contents($receiptRoot . '/' . $image, is_file($receiptRoot . '/sample-proof.png') ? file_get_contents($receiptRoot . '/sample-proof.png') : $fixturePng);
    $pdo->prepare('UPDATE thanh_toan SET anh_minh_chung=?, minh_chung_gui_luc=? WHERE ma_dat_san=1')->execute([$image, date('Y-m-d H:i:s')]);
}
if (($_GET['expire_fixture'] ?? '') === '1') $pdo->exec("UPDATE dat_san SET het_han_giu_san_luc='2000-01-01 00:00:00' WHERE ma_dat_san=1");
$accounts = new class extends App\Models\TaiKhoan {
    public function __construct() {}
    public function identity(int $id): ?array {
        if (($_SERVER['HTTP_X_STRYKE_TEST_ROLE'] ?? '') === 'GUEST') return null;
        return ['ma_tai_khoan' => 1, 'ten_dang_nhap' => 'actions.fixture', 'trang_thai' => 'HOAT_DONG',
            'ten_vai_tro' => $_SERVER['HTTP_X_STRYKE_TEST_ROLE'] ?? 'QUAN_TRI_TAP_DOAN', 'ma_nhan_vien' => 1,
            'trang_thai_nhan_vien' => 'DANG_LAM', 'ma_cong_ty' => (int) ($_SERVER['HTTP_X_STRYKE_COMPANY'] ?? 1), 'ma_co_so' => (int) ($_SERVER['HTTP_X_STRYKE_FACILITY'] ?? 1), 'cong_ty_cua_co_so' => (int) ($_SERVER['HTTP_X_STRYKE_COMPANY'] ?? 1),
            'trang_thai_cong_ty' => 'HOAT_DONG', 'trang_thai_co_so' => 'HOAT_DONG',
            'ma_khach_hang' => (int) ($_SERVER['HTTP_X_STRYKE_CUSTOMER'] ?? 10), 'trang_thai_khach' => 'HOAT_DONG',
            'ten_khach_hang' => 'Customer fixture', 'so_dien_thoai_khach' => '0900000000'];
    }
};
if (!Core\Session::accountId()) Core\Session::authenticate(1);
$app = new Core\Application($pdo);
(new ReflectionProperty(Core\Application::class, 'auth'))->setValue($app, new App\Services\AuthService($accounts));
try {
    if (($_GET['route'] ?? '') === '/__booking_actions/meta') {
        header('Content-Type: application/json');
        echo json_encode(['token' => Core\Csrf::token(), 'stock' => $pdo->query('SELECT so_luong_ton FROM dich_vu WHERE ma_dich_vu=1')->fetchColumn(),
            'bookings' => $pdo->query('SELECT ma_dat_san, trang_thai_dat_san, het_han_giu_san_luc FROM dat_san ORDER BY 1')->fetchAll(),
            'payments' => $pdo->query('SELECT * FROM thanh_toan ORDER BY 1')->fetchAll(),
            'invoices' => $pdo->query('SELECT COUNT(*) FROM hoa_don')->fetchColumn()]);
        exit;
    }
    if (!in_array(request_path(), ['/admin/bookings', '/admin/bookings/show', '/admin/bookings/confirm', '/admin/bookings/cancel', '/admin/bookings/reconcile', '/booking/wait', '/booking/receipt', '/booking/start', '/booking/details', '/booking/review', '/booking/confirm', '/payment/qr'], true)) throw new Core\HttpException(404, 'Fixture route not exposed');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') $pdo->exec('PRAGMA query_only=ON');
    $router = new Core\Router(); require $root . '/routes/bookings.php';
    $receipts = new App\Services\BookingReceiptService($pdo, $receiptRoot);
    (new ReflectionProperty($adminBooking, 'receipts'))->setValue($adminBooking, $receipts);
    (new ReflectionProperty($adminBooking, 'service'))->setValue($adminBooking, new App\Services\BookingManagementService($pdo, receipts: $receipts));
    require $root . '/routes/courts.php';
    (new ReflectionProperty($booking, 'receipts'))->setValue($booking, $receipts);
    if (request_path() === '/payment/qr') { header('Content-Type: image/png'); readfile($receiptRoot . '/qr.png'); exit; } // Fixture QR only: never calls external banks.
    $router->dispatch($_SERVER['REQUEST_METHOD'], request_path());
} catch (Core\HttpException $e) { http_response_code($e->status); echo e($e->getMessage()); }
catch (Throwable $e) { http_response_code(500); error_log((string) $e); echo 'Booking action fixture failed'; }
