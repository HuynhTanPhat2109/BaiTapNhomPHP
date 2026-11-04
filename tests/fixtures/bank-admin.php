<?php
// Isolated bank UI/permission fixture. No .env, application database or real bank data.
$bankUnit = PHP_SAPI === 'cli' && defined('STRYKE_BANK_ADMIN_UNIT');
if (!$bankUnit && (PHP_SAPI !== 'cli-server' || getenv('STRYKE_BANK_ADMIN_TEST') !== '1')) {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 2);
if (str_starts_with(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/assets/')) return false;
require $root . '/vendor/autoload.php';
$_ENV['APP_BASE_PATH'] = '';
try {
    $fixturePath = request_path();
} catch (Core\HttpException $e) {
    http_response_code($e->status);
    exit;
}
$artifacts = $root . '/storage/temp/bank-admin-ui';
if (!is_dir($artifacts . '/sessions')) mkdir($artifacts . '/sessions', 0777, true);
ini_set('session.save_path', $artifacts . '/sessions');
Core\Session::start();
if (!Core\Session::accountId()) Core\Session::authenticate(1);

final class BankMemoryPDO extends PDO
{
    public function __construct() { parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]); }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(preg_replace('/\s+FOR UPDATE\b/i', '', $query), $options);
    }
}
$pdo = new BankMemoryPDO();
$pdo->exec('CREATE TABLE cong_ty_nhuong_quyen (ma_cong_ty INTEGER PRIMARY KEY, ten_cong_ty TEXT, trang_thai TEXT);
    CREATE TABLE co_so (ma_co_so INTEGER PRIMARY KEY, ma_cong_ty INTEGER, ten_co_so TEXT, trang_thai TEXT);
    CREATE TABLE san (ma_san INTEGER PRIMARY KEY, ma_co_so INTEGER);
    CREATE TABLE tai_khoan_ngan_hang_co_so (ma_tk_ngan_hang INTEGER PRIMARY KEY, ma_co_so INTEGER,
        ten_ngan_hang TEXT, ma_bin TEXT, so_tai_khoan TEXT, ten_chu_tai_khoan TEXT,
        la_mac_dinh INTEGER, trang_thai TEXT, ngay_tao TEXT, UNIQUE(ma_co_so, ma_bin, so_tai_khoan));');
$pdo->exec("INSERT INTO cong_ty_nhuong_quyen VALUES (1, 'Công ty kiểm thử A', 'HOAT_DONG'), (2, 'Công ty kiểm thử B', 'HOAT_DONG');
    INSERT INTO co_so VALUES (1, 1, 'Cơ sở Trung tâm · Dữ liệu kiểm thử', 'HOAT_DONG'),
    (2, 1, 'Cơ sở Phía Đông · Dữ liệu kiểm thử', 'HOAT_DONG'), (3, 2, 'Cơ sở công ty khác', 'HOAT_DONG'),
    (4, 1, 'Cơ sở chưa cấu hình ngân hàng', 'HOAT_DONG');");
$seed = [
    [1, 1, 'Vietcombank', '970436', 'TESTACCOUNT001', 'TAI KHOAN KIEM THU TRUNG TAM', 1, 'HOAT_DONG', '2026-11-03 08:00:00'],
    [2, 1, 'Ngân hàng TMCP Quân đội (MBBank)', '970422', 'TESTACCOUNT002', 'TAI KHOAN KIEM THU DU PHONG', 0, 'NGUNG', '2026-11-03 08:00:00'],
    [3, 2, 'Vietcombank', '970436', 'TESTACCOUNT003', 'TAI KHOAN KIEM THU PHIA DONG', 1, 'HOAT_DONG', '2026-11-03 08:00:00'],
    [4, 3, 'Vietcombank', '970436', 'FOREIGNACCOUNT', 'TAI KHOAN NGOAI PHAM VI', 1, 'HOAT_DONG', '2026-11-03 08:00:00'],
];
// Persist only fixture rows across HTTP requests in a separate test session directory.
if ($fixturePath === '/__bank_fixture/reset') unset($_SESSION['_bank_fixture_rows']);
$insert = $pdo->prepare('INSERT INTO tai_khoan_ngan_hang_co_so VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
foreach ($_SESSION['_bank_fixture_rows'] ?? $seed as $row) $insert->execute($row);

function bankFixtureApp(PDO $pdo, array $override = []): Core\Application
{
    $accounts = new class($override) extends App\Models\TaiKhoan {
        public function __construct(private array $override) {}
        public function identity(int $id): ?array
        {
            $role = $_SERVER['HTTP_X_STRYKE_TEST_ROLE'] ?? 'QUAN_LY_CO_SO';
            $facilityId = (int) ($_SERVER['HTTP_X_STRYKE_TEST_FACILITY'] ?? 1);
            return array_replace(['ma_tai_khoan' => 1, 'ten_dang_nhap' => 'bank.fixture', 'trang_thai' => 'HOAT_DONG',
                'ten_vai_tro' => $role,
                'ma_nhan_vien' => 1, 'trang_thai_nhan_vien' => 'DANG_LAM', 'ma_cong_ty' => 1,
                'ma_co_so' => $role === 'QUAN_LY_CONG_TY' ? null : $facilityId,
                'cong_ty_cua_co_so' => 1, 'trang_thai_cong_ty' => 'HOAT_DONG',
                'trang_thai_co_so' => 'HOAT_DONG', 'ma_khach_hang' => 1, 'trang_thai_khach' => 'HOAT_DONG'], $this->override);
        }
    };
    $app = new Core\Application($pdo);
    (new ReflectionProperty(Core\Application::class, 'auth'))->setValue($app, new App\Services\AuthService($accounts));
    return $app;
}
$bankCache = $artifacts . '/banks.fixture.json';
file_put_contents($bankCache, json_encode([
    ['bin' => '970436', 'code' => 'VCB', 'shortName' => 'Vietcombank', 'name' => 'Ngân hàng TMCP Ngoại Thương Việt Nam'],
    ['bin' => '970422', 'code' => 'MB', 'shortName' => 'MBBank', 'name' => 'Ngân hàng TMCP Quân đội'],
], JSON_UNESCAPED_UNICODE));
function bankFixtureRouter(Core\Application $app): Core\Router
{
    global $bankCache;
    $router = new Core\Router();
    require dirname(__DIR__, 2) . '/routes/payment.php';
    (new ReflectionProperty(App\Controllers\BankController::class, 'vietQrService'))->setValue($bankController, new App\Services\VietQRService($bankCache));
    return $router;
}
if ($bankUnit) return;
register_shutdown_function(static function () use ($pdo): void {
    $_SESSION['_bank_fixture_rows'] = $pdo->query('SELECT * FROM tai_khoan_ngan_hang_co_so ORDER BY ma_tk_ngan_hang')->fetchAll(PDO::FETCH_NUM);
});
if ($fixturePath === '/__bank_fixture/reset') {
    header('Content-Type: application/json');
    echo json_encode(['database' => 'sqlite::memory: only']);
    exit;
}
$app = bankFixtureApp($pdo);
try {
    $path = $fixturePath;
    if (!preg_match('~\A/facility/bank(?:/(?:create|store|edit|update|set-default|toggle-status))?\z~', $path)) {
        throw new Core\HttpException(404, 'Only isolated bank routes are available.');
    }
    bankFixtureRouter($app)->dispatch($_SERVER['REQUEST_METHOD'], $path);
} catch (Core\HttpException $e) {
    http_response_code($e->status);
    echo e($e->getMessage());
} catch (Throwable $e) {
    http_response_code(500);
    echo e(get_class($e) . ': ' . $e->getMessage());
}
