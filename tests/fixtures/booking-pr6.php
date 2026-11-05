<?php
// HTTP fixture only. SQLite RAM schema; no .env, configured MySQL or real payments.
$pr6Unit = PHP_SAPI === 'cli' && defined('STRYKE_PR6_CONTRACT');
if (!$pr6Unit && (PHP_SAPI !== 'cli-server' || getenv('STRYKE_PR6_UI') !== '1')) { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
if (str_starts_with(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/assets/')) return false;
require $root . '/vendor/autoload.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');
ini_set('display_errors', '0');
if (!$pr6Unit) { ini_set('session.save_path', getenv('STRYKE_PR6_SESSIONS')); Core\Session::start(); }
$_ENV['APP_BASE_PATH'] = '';

final class Pr6ReadPDO extends PDO
{
    public function __construct() { parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]); }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        // Only the list's MySQL GROUP_CONCAT spelling differs. Not a MySQL locking test.
        $query = str_replace("GROUP_CONCAT(DISTINCT ct.ten_san_snapshot ORDER BY ct.ten_san_snapshot SEPARATOR ', ')", 'GROUP_CONCAT(DISTINCT ct.ten_san_snapshot)', $query);
        if (defined('STRYKE_PR6_CONTRACT')) $query = preg_replace('/\s+FOR UPDATE\b/i', '', $query);
        if (defined('STRYKE_BOOKING_ACTIONS_FIXTURE')) {
            $query = str_replace('DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 6 MINUTE)', "datetime(CURRENT_TIMESTAMP, '+6 minutes')", $query);
            $query = str_replace("UPDATE dat_san b SET b.trang_thai_dat_san=", "UPDATE dat_san AS b SET trang_thai_dat_san=", $query);
            if (str_starts_with($query, 'UPDATE thanh_toan t JOIN dat_san b')) $query = "UPDATE thanh_toan
                SET trang_thai=(SELECT CASE WHEN b.trang_thai_dat_san='DA_HUY' THEN 'HUY' ELSE 'HET_HAN' END FROM dat_san b WHERE b.ma_dat_san=thanh_toan.ma_dat_san)
                WHERE ma_dat_san=? AND trang_thai='CHO_THANH_TOAN' AND EXISTS (SELECT 1 FROM dat_san b WHERE b.ma_dat_san=thanh_toan.ma_dat_san AND b.trang_thai_dat_san IN ('DA_HUY','HET_HAN'))";
        }
        return parent::prepare($query, $options);
    }
    public function exec(string $statement): int|false
    {
        if (defined('STRYKE_BOOKING_ACTIONS_FIXTURE') && str_starts_with($statement, 'SET TRANSACTION ISOLATION')) return 0;
        return parent::exec($statement);
    }
}
$pdo = new Pr6ReadPDO();
$pdo->sqliteCreateFunction('NOW', static fn() => date('Y-m-d H:i:s'));
$schema = file_get_contents($root . '/database/quanly_chuoi_san_the_thao.sql') . "\n" . file_get_contents($root . '/database/migrations/20261101_tai_khoan_ngan_hang_co_so.sql');
preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?(\w+) \((.*?)\) ENGINE=InnoDB/s', $schema, $definitions, PREG_SET_ORDER);
foreach ($definitions as $definition) {
    preg_match_all('/^    (\w+) (INT|VARCHAR|CHAR|DECIMAL|TEXT|DATETIME|TIME|DATE|ENUM|TINYINT)\b([^\r\n]*)/m', $definition[2], $columns, PREG_SET_ORDER);
    $fields = [];
    foreach ($columns as $column) $fields[] = $column[1] . (str_contains($column[3], 'AUTO_INCREMENT PRIMARY KEY') ? ' INTEGER PRIMARY KEY' : ($column[2] === 'INT' ? ' INTEGER' :
        (defined('STRYKE_RECEIPT_NUMERIC') && $column[2] === 'DECIMAL' && in_array($definition[1], ['dat_san','thanh_toan'], true) ? ' NUMERIC' : ' TEXT')));
    $pdo->exec('CREATE TABLE ' . $definition[1] . ' (' . implode(',', $fields) . ')');
}
$insert = static function (string $table, array $row) use ($pdo): void {
    $stmt = $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(',', array_keys($row)) . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')');
    $stmt->execute(array_values($row));
};
foreach ([1, 2] as $id) $insert('cong_ty_nhuong_quyen', ['ma_cong_ty' => $id, 'ten_cong_ty' => 'Công ty PR6 ' . $id, 'trang_thai' => 'HOAT_DONG']);
foreach ([1 => 1, 2 => 1, 3 => 2] as $id => $company) $insert('co_so', ['ma_co_so' => $id, 'ma_cong_ty' => $company, 'ten_co_so' => 'Cơ sở PR6 ' . $id, 'trang_thai' => 'HOAT_DONG', 'dia_chi' => 'Địa chỉ fixture', 'so_dien_thoai' => '0900000000']);
$insert('khach_hang', ['ma_khach_hang' => 10, 'ho_ten' => 'Khách fixture PR6', 'so_dien_thoai' => '0900000000']);
$insert('bo_mon', ['ma_bo_mon' => 1, 'ten_bo_mon' => 'Cầu lông']);
$insert('loai_san', ['ma_loai_san' => 1, 'ma_bo_mon' => 1, 'ten_loai_san' => 'Sân đôi']);
$insert('san', ['ma_san' => 1, 'ma_co_so' => 1, 'ma_loai_san' => 1, 'ten_san' => 'Sân PR6', 'vi_tri' => null]);
$insert('dich_vu', ['ma_dich_vu' => 1, 'ma_co_so' => 1, 'ten_dich_vu' => 'Tên catalog hiện tại', 'don_vi_tinh' => 'chai', 'don_gia' => '15000.00', 'so_luong_ton' => 200, 'loai_dich_vu' => 'DO_UONG']);
$states = [1 => 'CHO_THANH_TOAN', 2 => 'DA_HUY', 3 => 'HET_HAN', 4 => 'DA_XAC_NHAN', 5 => 'CHO_THANH_TOAN', 6 => 'DA_HUY', 7 => 'CHO_THANH_TOAN', 8 => 'CHO_XAC_NHAN', 9 => 'DA_HUY'];
foreach ($states as $id => $state) {
    $insert('dat_san', ['ma_dat_san' => $id, 'ma_co_so' => $id === 7 ? 3 : ($id === 6 ? 2 : ($id === 9 ? 999 : 1)), 'ma_khach_hang' => 10,
        'trang_thai_dat_san' => $state, 'ngay_tao' => date('Y-m-d H:i:s'), 'bat_dau_giu_san_luc' => date('Y-m-d H:i:s'),
        'het_han_giu_san_luc' => date('Y-m-d H:i:s', time() + ($state === 'HET_HAN' ? -60 : 360)), 'tong_tien_san' => '40000.00', 'tong_tien_dich_vu' => in_array($id, [1, 4], true) ? '20000.00' : '0.00', 'tong_tien_tam_tinh' => in_array($id, [1, 4], true) ? '60000.00' : '40000.00']);
    $insert('chi_tiet_dat_san', ['ma_chi_tiet' => $id, 'ma_dat_san' => $id, 'ma_san' => 1, 'ten_san_snapshot' => 'Sân snapshot PR6', 'ngay_choi' => date('Y-m-d', time() + 86400), 'gio_bat_dau' => '10:00:00', 'gio_ket_thuc' => '10:30:00', 'don_gia' => '80000.00', 'thanh_tien' => '40000.00']);
    if ($id !== 5) $insert('thanh_toan', ['ma_thanh_toan' => $id, 'ma_dat_san' => $id, 'ma_tham_chieu' => 'PR6-' . $id, 'so_tien' => in_array($id, [1, 4], true) ? '60000.00' : '40000.00',
        'trang_thai' => $id === 4 ? 'THANH_CONG' : ($id === 3 ? 'HET_HAN' : 'CHO_THANH_TOAN'), 'phuong_thuc' => 'CHUYEN_KHOAN', 'ngay_tao' => date('Y-m-d H:i:s'), 'so_tien_thuc_nhan' => $id === 4 ? '60000.00' : null]);
}
foreach ([1, 4] as $id) $insert('chi_tiet_dich_vu_dat', ['ma_chi_tiet_dv' => $id, 'ma_dat_san' => $id, 'ma_dich_vu' => 1, 'ten_dich_vu_snapshot' => 'Nước snapshot PR6', 'don_vi_tinh_snapshot' => 'chai', 'so_luong' => 2, 'don_gia' => '10000.00', 'thanh_tien' => '20000.00']);
if ($pr6Unit || defined('STRYKE_BOOKING_ACTIONS_FIXTURE')) {
    // Mirror 20261102_hoa_don_fields.sql in this RAM-only fixture; never apply its data backfill.
    $pdo->exec('ALTER TABLE hoa_don ADD COLUMN so_hoa_don TEXT');
    $pdo->exec('ALTER TABLE hoa_don ADD COLUMN ma_thanh_toan INTEGER');
    return; // The contract test may write only this RAM PDO, never Core\Database.
}
if (($_GET['missing_bank'] ?? '') === '1') $pdo->exec('DROP TABLE tai_khoan_ngan_hang_co_so'); // RAM only; reproduce local missing migration.
if (getenv('STRYKE_OPERATIONS_TEST') === '1' && ($_GET['operations_reconcile'] ?? '') === '1') {
    $pdo->exec("UPDATE thanh_toan SET trang_thai='CAN_DOI_SOAT' WHERE ma_dat_san=1");
}
$pdo->exec('PRAGMA query_only=ON'); // Any accidental application write on GET fails the test.

$accounts = new class extends App\Models\TaiKhoan {
    public function __construct() {}
    public function identity(int $id): ?array
    {
        return ['ma_tai_khoan' => 1, 'ten_dang_nhap' => 'pr6.fixture', 'trang_thai' => 'HOAT_DONG', 'ten_vai_tro' => $_GET['fixture_role'] ?? 'QUAN_TRI_TAP_DOAN',
            'ma_nhan_vien' => 1, 'trang_thai_nhan_vien' => 'DANG_LAM', 'ma_cong_ty' => 1, 'ma_co_so' => 1, 'cong_ty_cua_co_so' => 1,
            'trang_thai_cong_ty' => 'HOAT_DONG', 'trang_thai_co_so' => 'HOAT_DONG', 'ma_khach_hang' => 10, 'trang_thai_khach' => 'HOAT_DONG'];
    }
};
if (!Core\Session::accountId()) Core\Session::authenticate(1);
$app = new Core\Application($pdo);
(new ReflectionProperty(Core\Application::class, 'auth'))->setValue($app, new App\Services\AuthService($accounts));
try {
    $route = request_path();
    if (!in_array($route, ['/admin/bookings', '/admin/bookings/show', '/admin/bookings/confirm'], true)) throw new Core\HttpException(404, 'Fixture route not exposed');
    // Only invalid-CSRF POST is exposed; a valid token cannot run the payment writer.
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' && hash_equals(Core\Csrf::token(), (string) ($_POST['_token'] ?? ''))) throw new Core\HttpException(405, 'No payment writes in PR6 UI fixture');
    $router = new Core\Router(); require $root . '/routes/bookings.php';
    $router->dispatch($_SERVER['REQUEST_METHOD'], $route);
} catch (Core\HttpException $e) {
    http_response_code($e->status); echo e($e->getMessage());
} catch (Throwable $e) {
    error_log('PR6 fixture failure: ' . get_class($e) . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . ' ' . $e->getMessage());
    error_log($e->getTraceAsString()); // Fixture-local log only; never returned in HTTP.
    http_response_code(500); echo 'Một chút gián đoạn';
}
