<?php
// Disposable, read-only Dashboard fixture. Never loads .env or Core\Database.
$dashboardUnit = PHP_SAPI === 'cli' && defined('STRYKE_DASHBOARD_UNIT');
if (!$dashboardUnit && (PHP_SAPI !== 'cli-server' || getenv('STRYKE_DASHBOARD_TEST') !== '1')) { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
if (str_starts_with(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/assets/')) return false;
require_once $root . '/vendor/autoload.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');
$_ENV['APP_BASE_PATH'] = '';
$artifact = $root . '/storage/temp/dashboard-review';
if (!is_dir($artifact . '/sessions')) mkdir($artifact . '/sessions', 0777, true);
ini_set('session.save_path', $artifact . '/sessions');
Core\Session::start();
if (!Core\Session::accountId()) Core\Session::authenticate(1);
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$schema = file_get_contents($root . '/database/quanly_chuoi_san_the_thao.sql');
preg_match_all('/CREATE TABLE (\w+) \((.*?)\) ENGINE=InnoDB/s', $schema, $definitions, PREG_SET_ORDER);
foreach ($definitions as $definition) {
    preg_match_all('/^    (\w+) (INT|VARCHAR|CHAR|DECIMAL|TEXT|DATETIME|TIME|DATE|ENUM)\b([^\r\n]*)/m', $definition[2], $columns, PREG_SET_ORDER);
    $fields = [];
    foreach ($columns as $column) $fields[] = $column[1] . (str_contains($column[3], 'AUTO_INCREMENT PRIMARY KEY') ? ' INTEGER PRIMARY KEY' : ($column[2] === 'INT' ? ' INTEGER' : ($column[2] === 'DECIMAL' ? ' NUMERIC' : ' TEXT')));
    $pdo->exec('CREATE TABLE ' . $definition[1] . ' (' . implode(',', $fields) . ')');
}
$pdo->exec('CREATE UNIQUE INDEX fixture_payment_booking ON thanh_toan(ma_dat_san);
    CREATE UNIQUE INDEX fixture_payment_txn ON thanh_toan(ma_giao_dich);
    CREATE UNIQUE INDEX fixture_invoice_booking ON hoa_don(ma_dat_san);');
// Mirror existing invoice migration fields in RAM; never migrate the application database.
$pdo->exec('ALTER TABLE hoa_don ADD COLUMN so_hoa_don TEXT;
    ALTER TABLE hoa_don ADD COLUMN ma_thanh_toan INTEGER;');
function dashboardInsert(PDO $pdo, string $table, array $row): void
{
    $stmt = $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(',', array_keys($row)) . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')');
    $stmt->execute(array_values($row));
}
$today = new DateTimeImmutable('today');
$date = $today->format('Y-m-d');
$previous = $today->modify('last day of last month')->format('Y-m-d');
$tomorrow = $today->modify('+1 day')->format('Y-m-d');
foreach ([1 => 'Công ty An Phú', 2 => 'Công ty Bình Minh', 3 => 'Công ty chưa vận hành'] as $id => $name) dashboardInsert($pdo, 'cong_ty_nhuong_quyen', ['ma_cong_ty' => $id, 'ten_cong_ty' => $name, 'trang_thai' => 'HOAT_DONG']);
dashboardInsert($pdo, 'khach_hang', ['ma_khach_hang' => 1, 'ho_ten' => 'Khách hàng trong bộ nhớ', 'so_dien_thoai' => '0900000000']);
foreach ([11 => [1, 'An Phú · Trung tâm'], 12 => [1, 'An Phú · Phía Đông'], 13 => [1, 'An Phú · Chưa khai thác'], 21 => [2, 'Bình Minh · Hoàng Quân']] as $id => [$company, $name]) dashboardInsert($pdo, 'co_so', ['ma_co_so' => $id, 'ma_cong_ty' => $company, 'ten_co_so' => $name, 'trang_thai' => 'HOAT_DONG']);
foreach ([1 => 11, 2 => 11, 3 => 12, 4 => 21] as $id => $facility) {
    dashboardInsert($pdo, 'san', ['ma_san' => $id, 'ma_co_so' => $facility, 'ten_san' => 'Tên sân hiện tại ' . $id, 'trang_thai' => $id === 2 ? 'BAO_TRI' : 'HOAT_DONG']);
    dashboardInsert($pdo, 'dich_vu', ['ma_dich_vu' => $id, 'ma_co_so' => $facility, 'ten_dich_vu' => 'Tên catalog hiện tại ' . $id, 'don_gia' => '999999.00', 'trang_thai' => 'DANG_CUNG_CAP']);
}
dashboardInsert($pdo, 'bo_mon', ['ma_bo_mon' => 1, 'ten_bo_mon' => 'Cầu lông']);
dashboardInsert($pdo, 'loai_san', ['ma_loai_san' => 1, 'ten_loai_san' => 'Sân đôi']);
$cases = [
    // id => facility, court snapshot, service snapshot, booking state, payment state, paid at, created at
    1 => [11, '100000.00', '20000.00', 'DA_XAC_NHAN', 'THANH_CONG', "$date 10:00:00", "$date 08:00:00"],
    2 => [12, '200000.00', '50000.00', 'HOAN_THANH', 'THANH_CONG', "$date 11:00:00", "$date 08:00:00"],
    3 => [21, '300000.00', '10000.00', 'DA_XAC_NHAN', 'THANH_CONG', "$date 12:00:00", "$date 08:00:00"],
    4 => [11, '100000.00', '0.00', 'CHO_THANH_TOAN', 'CHO_THANH_TOAN', null, "$date 08:00:00"],
    5 => [11, '100000.00', '0.00', 'DA_HUY', 'HUY', null, "$date 08:00:00"],
    6 => [11, '100000.00', '0.00', 'HET_HAN', 'CAN_DOI_SOAT', "$date 13:00:00", "$date 08:00:00"],
    7 => [11, '100000.00', '0.00', 'DA_HUY', 'THANH_CONG', "$date 14:00:00", "$date 08:00:00"],
    8 => [11, '100000.00', '0.00', 'DA_XAC_NHAN', 'THANH_CONG', "$date 15:00:00", "$date 08:00:00"],
    9 => [11, '100000.00', '0.00', 'DA_XAC_NHAN', 'THANH_CONG', null, "$date 08:00:00"],
    10 => [11, '100000.00', '0.00', 'DA_XAC_NHAN', 'THANH_CONG', "$date 16:00:00", "$date 08:00:00"],
    11 => [11, '150000.00', '0.00', 'DA_XAC_NHAN', 'THANH_CONG', "$date 17:00:00", "$previous 08:00:00"],
    12 => [21, '400000.00', '100000.00', 'DA_XAC_NHAN', 'THANH_CONG', "$previous 23:59:59", "$previous 08:00:00"],
    13 => [11, '100000.00', '0.00', 'CHO_XAC_NHAN', 'CAN_DOI_SOAT', null, "$date 08:00:00"],
    14 => [11, '100.25', '0.50', 'DA_XAC_NHAN', 'THANH_CONG', "$date 00:00:00", "$date 00:00:00"],
    15 => [11, '10000.00', '0.00', 'DA_XAC_NHAN', 'THANH_CONG', "$tomorrow 00:00:00", "$date 08:00:00"],
];
foreach ($cases as $id => [$facility, $court, $service, $state, $payment, $paidAt, $created]) {
    $courtCents = App\Services\BookingPricingService::cents($court);
    $serviceCents = App\Services\BookingPricingService::cents($service);
    $total = App\Services\BookingPricingService::decimal($courtCents + $serviceCents);
    dashboardInsert($pdo, 'dat_san', ['ma_dat_san' => $id, 'ma_khach_hang' => 1, 'ma_co_so' => $facility, 'ngay_tao' => $created, 'trang_thai_dat_san' => $state, 'tong_tien_san' => $court, 'tong_tien_dich_vu' => $service, 'tong_tien_tam_tinh' => $total]);
    dashboardInsert($pdo, 'thanh_toan', ['ma_thanh_toan' => $id, 'ma_dat_san' => $id, 'ma_tham_chieu' => 'DASH-TEST-' . $id, 'trang_thai' => $payment, 'so_tien' => $total,
        'so_tien_thuc_nhan' => $id === 8 ? '90000.00' : $total, 'ma_giao_dich' => $id === 10 ? null : 'MEMORY-TRANSACTION-' . $id, 'ngay_thanh_toan' => $paidAt, 'ngay_tao' => $created]);
    $courtId = $facility === 11 ? 1 : ($facility === 12 ? 3 : 4);
    foreach ([intdiv($courtCents, 2), $courtCents - intdiv($courtCents, 2)] as $lineAmount) dashboardInsert($pdo, 'chi_tiet_dat_san', ['ma_dat_san' => $id, 'ma_san' => $courtId,
        'ten_san_snapshot' => 'Sân snapshot ' . $courtId, 'thanh_tien' => App\Services\BookingPricingService::decimal($lineAmount)]);
    if ((float) $service > 0) dashboardInsert($pdo, 'chi_tiet_dich_vu_dat', ['ma_dat_san' => $id, 'ma_dich_vu' => $courtId,
        'ten_dich_vu_snapshot' => 'Nước snapshot', 'don_vi_tinh_snapshot' => 'chai', 'so_luong' => 2, 'don_gia' => App\Services\BookingPricingService::decimal(intdiv($serviceCents, 2)), 'thanh_tien' => $service]);
    if ($payment === 'THANH_CONG') dashboardInsert($pdo, 'hoa_don', ['so_hoa_don' => $id === 1 ? 'STRYKE-HOADON-2026-MA-DAI-KIEM-TRA-RESPONSIVE-000001' : 'HD-MEMORY-' . $id,
        'ma_dat_san' => $id, 'ma_thanh_toan' => $id, 'ngay_lap' => $created, 'tien_san' => $court, 'tien_dich_vu' => $service, 'tong_tien' => $total, 'trang_thai' => 'DA_LAP']);
}
function dashboardIdentity(string $role, array $override = []): array
{
    return array_replace(['ma_tai_khoan' => 1, 'ten_dang_nhap' => 'dashboard.fixture', 'ten_nhan_vien' => 'Người dùng kiểm thử', 'trang_thai' => 'HOAT_DONG',
        'ten_vai_tro' => $role, 'ma_nhan_vien' => 1, 'trang_thai_nhan_vien' => 'DANG_LAM', 'ma_cong_ty' => 1,
        'ma_co_so' => $role === 'QUAN_LY_CONG_TY' ? null : 11, 'cong_ty_cua_co_so' => 1, 'trang_thai_cong_ty' => 'HOAT_DONG',
        'trang_thai_co_so' => 'HOAT_DONG', 'ma_khach_hang' => 1, 'trang_thai_khach' => 'HOAT_DONG'], $override);
}
function dashboardFixtureApp(PDO $pdo, array $identity): Core\Application
{
    $accounts = new class($identity) extends App\Models\TaiKhoan {
        public function __construct(private array $identity) {}
        public function identity(int $id): ?array { return $this->identity; }
    };
    $app = new Core\Application($pdo);
    (new ReflectionProperty(Core\Application::class, 'auth'))->setValue($app, new App\Services\AuthService($accounts));
    return $app;
}
if ($dashboardUnit) return;
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'");
try {
    $route = request_path();
    if ($route === '/__dashboard_fixture/meta') { header('Content-Type: application/json'); echo json_encode(['today' => $date, 'previous' => $previous]); exit; }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !in_array($route, ['/admin', '/company', '/facility', '/staff', '/admin/revenue', '/company/revenue', '/facility/revenue', '/staff/revenue', '/admin/invoices', '/admin/invoices/show', '/admin/invoices/print'], true)) throw new Core\HttpException(404, 'Only read-only Dashboard/revenue/invoice routes are exposed');
    if (($_GET['fixture'] ?? '') === 'empty') foreach (['chi_tiet_dat_san', 'chi_tiet_dich_vu_dat', 'hoa_don', 'thanh_toan', 'dat_san'] as $table) $pdo->exec('DELETE FROM ' . $table);
    if (($_GET['fixture'] ?? '') === 'error') $pdo->exec('DROP TABLE thanh_toan');
    $pdo->exec('PRAGMA query_only=ON'); // Fail any accidental production write on a Dashboard GET.
    $app = dashboardFixtureApp($pdo, dashboardIdentity($_SERVER['HTTP_X_STRYKE_TEST_ROLE'] ?? 'QUAN_TRI_TAP_DOAN'));
    $router = new Core\Router();
    require $root . '/routes/web.php';
    $router->dispatch($_SERVER['REQUEST_METHOD'], request_path());
} catch (Core\HttpException $e) {
    http_response_code($e->status); echo e($e->getMessage());
} catch (Throwable $e) {
    http_response_code(500); error_log((string) $e); echo 'Dashboard fixture failed';
}
