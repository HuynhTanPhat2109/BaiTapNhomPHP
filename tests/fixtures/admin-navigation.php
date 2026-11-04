<?php
// Chrome test server only. Real routes/controllers/views; disposable SQLite in RAM.
// Never load .env/Core\Database or open a file/MySQL database.
$navigationUnit = PHP_SAPI === 'cli' && defined('STRYKE_ADMIN_MANAGEMENT_UNIT');
if (!$navigationUnit && (PHP_SAPI !== 'cli-server' || getenv('STRYKE_NAVIGATION_TEST') !== '1')) {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 2);
$asset = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (str_starts_with($asset, '/assets/')) return false;
require $root . '/vendor/autoload.php';
$_ENV['APP_BASE_PATH'] = '';
$sessionDirectory = getenv('STRYKE_NAVIGATION_SESSIONS') ?: $root . '/storage/temp/admin-standardization/sessions';
if (!is_dir($sessionDirectory)) mkdir($sessionDirectory, 0777, true);
ini_set('session.save_path', $sessionDirectory);
Core\Session::start();

final class NavigationMemoryPDO extends PDO
{
    public array $preparedQueries = [];
    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $this->sqliteCreateFunction('NOW', static fn() => date('Y-m-d H:i:s'));
        $this->sqliteCreateFunction('TIMESTAMP', static fn($date, $time) => "$date $time");
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->preparedQueries[] = $query;
        return parent::prepare(preg_replace('/\s+FOR UPDATE\b/i', '', $query), $options);
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $query = preg_replace('/\s+FOR UPDATE\b/i', '', $query);
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}

// Read only column names/types from the source schema; never execute its MySQL DDL.
$pdo = new NavigationMemoryPDO();
preg_match_all('/CREATE TABLE (\w+) \((.*?)\) ENGINE=InnoDB;/s', file_get_contents($root . '/database/quanly_chuoi_san_the_thao.sql'), $definitions, PREG_SET_ORDER);
$tables = [];
foreach ($definitions as $definition) {
    preg_match_all('/^    (\w+) (INT|VARCHAR|CHAR|DECIMAL|TEXT|DATETIME|TIME|DATE|ENUM)\b([^\r\n]*)/m', $definition[2], $columns, PREG_SET_ORDER);
    $fields = [];
    foreach ($columns as $column) $fields[] = $column[1] . (str_contains($column[3], 'AUTO_INCREMENT PRIMARY KEY') ? ' INTEGER PRIMARY KEY' : ($column[2] === 'INT' ? ' INTEGER' : ' TEXT'));
    $pdo->exec('CREATE TABLE ' . $definition[1] . ' (' . implode(',', $fields) . ')');
    $tables[] = $definition[1];
}
$common = ['ma_cong_ty' => 1, 'ma_co_so' => 2, 'ma_bo_mon' => 2, 'ma_loai_san' => 2,
    'mo_ta' => 'Dữ liệu UX chỉ trong bộ nhớ', 'dia_chi' => 'Địa chỉ kiểm thử', 'so_dien_thoai' => '0900000000',
    'email' => 'ux@example.test', 'gio_mo_cua' => '06:00:00', 'gio_dong_cua' => '23:00:00', 'trang_thai' => 'HOAT_DONG'];
$seed = ['cong_ty_nhuong_quyen' => [array_replace($common, ['ten_cong_ty' => 'Công ty UX', 'ngay_tham_gia' => '2020-01-01'])]];
$modules = [
    'bo_mon' => ['ma_bo_mon', 'ten_bo_mon'], 'loai_san' => ['ma_loai_san', 'ten_loai_san'],
    'co_so' => ['ma_co_so', 'ten_co_so'], 'san' => ['ma_san', 'ten_san'],
    'bang_gia' => ['ma_bang_gia', 'ten_bang_gia'], 'dich_vu' => ['ma_dich_vu', 'ten_dich_vu'],
];
$count = ($_GET['fixture'] ?? '') === 'reset' && in_array($_GET['count'] ?? '', ['11', '20', '120'], true) ? (int) $_GET['count'] : 20;
foreach ($modules as $table => [$key, $name]) {
    for ($id = 1; $id <= $count; $id++) {
        $seed[$table][] = array_replace($common, [$key => $id, $name => sprintf('UX %s %02d', $table, $id),
            'vi_tri' => 'Khu A', 'loai_dich_vu' => 'DO_UONG', 'don_vi_tinh' => 'chai', 'don_gia' => '10000.00', 'so_luong_ton' => 10,
            'loai_ngay' => 'NGAY_THUONG', 'gio_bat_dau' => '08:00:00', 'gio_ket_thuc' => '09:00:00',
            'ngay_bat_dau' => '2020-01-01', 'ngay_ket_thuc' => null,
            'trang_thai' => $table === 'bang_gia' ? 'NGUNG' : ($table === 'dich_vu' ? 'DANG_CUNG_CAP' : 'HOAT_DONG')]);
    }
}
$seed['cong_ty_nhuong_quyen'] = [];
$seed['vai_tro'] = [
    ['ma_vai_tro' => 2, 'ten_vai_tro' => 'QUAN_LY_CONG_TY'], ['ma_vai_tro' => 3, 'ten_vai_tro' => 'QUAN_LY_CO_SO'],
    ['ma_vai_tro' => 4, 'ten_vai_tro' => 'NHAN_VIEN'],
];
for ($id = 1; $id <= $count; $id++) {
    $seed['cong_ty_nhuong_quyen'][] = ['ma_cong_ty' => $id, 'ten_cong_ty' => sprintf('UX Company %02d', $id),
        'dia_chi' => 'Địa chỉ UX', 'trang_thai' => 'HOAT_DONG', 'ngay_tham_gia' => '2020-01-01'];
    $seed['hop_dong_nhuong_quyen'][] = ['ma_hop_dong' => $id, 'ma_cong_ty' => $id % 2 + 1, 'so_hop_dong' => sprintf('UX/2026/%03d', $id),
        'ngay_ky' => '2020-01-01', 'ngay_bat_dau' => ($id % 2 ? '2020' : '2022') . '-01-01', 'ngay_ket_thuc' => '2030-12-31',
        'phi_nhuong_quyen' => '60000.00', 'ty_le_phi' => '5.00', 'trang_thai' => 'HIEU_LUC'];
    foreach ([$id => [2, $id % 2 + 1, null], 1000 + $id => [$id % 2 ? 3 : 4, 1, $id % 2 ? 2 : 4], 2000 + $id => [4, 2, 3]] as $employeeId => [$roleId, $companyId, $facilityId]) {
        $seed['tai_khoan'][] = ['ma_tai_khoan' => $employeeId, 'ma_vai_tro' => $roleId, 'ten_dang_nhap' => 'memory.' . $employeeId, 'mat_khau' => 'FIXTURE_SECRET_NEVER_RENDER', 'trang_thai' => 'HOAT_DONG'];
        $seed['nhan_vien'][] = ['ma_nhan_vien' => $employeeId, 'ma_tai_khoan' => $employeeId, 'ma_cong_ty' => $companyId, 'ma_co_so' => $facilityId,
            'ho_ten' => sprintf('UX Employee %03d', $employeeId), 'email' => "ux$employeeId@example.test", 'so_dien_thoai' => sprintf('09%08d', $employeeId),
            'trang_thai' => 'DANG_LAM', 'ngay_vao_lam' => '2020-01-01'];
    }
}
$seed['co_so'][2]['ma_cong_ty'] = 2; // Facility 3 belongs to the other company.
if (getenv('STRYKE_AUTHORIZATION_TEST') === '1') {
    // Give the authorization suite explicit foreign court/price IDs, in RAM only.
    $seed['san'][2]['ma_co_so'] = 3;
    $seed['bang_gia'][2]['ma_co_so'] = 3;
}
$seed['nhan_vien'][] = ['ma_nhan_vien' => 9000, 'ma_tai_khoan' => null, 'ma_cong_ty' => 1, 'ma_co_so' => null,
    'ho_ten' => 'UX No account', 'email' => 'noaccount@example.test', 'trang_thai' => 'DANG_LAM'];
if (($_GET['fixture'] ?? '') === 'reset') unset($_SESSION['_navigation_tables']);
foreach ($_SESSION['_navigation_tables'] ?? $seed as $table => $rows) {
    $known = array_column($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(), 'name');
    foreach ($rows as $row) {
        $row = array_intersect_key($row, array_flip($known));
        $stmt = $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(',', array_keys($row)) . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')');
        $stmt->execute(array_values($row));
    }
}
if ($navigationUnit) return;
register_shutdown_function(static function () use ($pdo, $tables): void {
    foreach ($tables as $table) $_SESSION['_navigation_tables'][$table] = $pdo->query('SELECT * FROM ' . $table)->fetchAll();
});

$accounts = new class extends App\Models\TaiKhoan {
    public function __construct() {}
    public function identity(int $id): ?array
    {
        return ['ma_tai_khoan' => 1, 'ten_dang_nhap' => 'ux.memory', 'trang_thai' => 'HOAT_DONG',
            'ten_vai_tro' => $_GET['fixture_role'] ?? $_SERVER['HTTP_X_STRYKE_TEST_ROLE'] ?? 'QUAN_TRI_TAP_DOAN', 'ma_nhan_vien' => 1,
            'trang_thai_nhan_vien' => 'DANG_LAM', 'ma_cong_ty' => 1, 'ma_co_so' => 1, 'cong_ty_cua_co_so' => 1,
            'trang_thai_cong_ty' => 'HOAT_DONG', 'trang_thai_co_so' => 'HOAT_DONG', 'ma_khach_hang' => 1, 'trang_thai_khach' => 'HOAT_DONG'];
    }
};
if (!Core\Session::accountId()) Core\Session::authenticate(1);
$app = new Core\Application($pdo);
(new ReflectionProperty(Core\Application::class, 'auth'))->setValue($app, new App\Services\AuthService($accounts));
if (($_GET['fixture'] ?? '') === 'reset') {
    header('Content-Type: application/json');
    echo json_encode(['mode' => 'SQLite :memory: only', 'count' => $count]);
    exit;
}
try {
    $navigationRoute = request_path();
    // Fixture exposes only Admin UI routes. No booking, payment or account writes.
    if (!str_starts_with($navigationRoute, '/admin')
        && !preg_match('~\A/company/employees(?:/(?:show|create|edit|update|status))?\z~', $navigationRoute)
        && !preg_match('~\A/(?:company|facility|staff)/(?:co-so|san|bang-gia)(?:/(?:show|create|store|edit|update|delete|status))?\z~', $navigationRoute)) throw new Core\HttpException(404, 'Not a navigation test route');
    $router = new Core\Router();
    require $root . '/routes/web.php';
    $router->dispatch($_SERVER['REQUEST_METHOD'], $navigationRoute);
} catch (Core\HttpException $e) {
    http_response_code($e->status);
    echo e($e->getMessage());
} catch (Throwable $e) {
    http_response_code(500);
    echo e(get_class($e) . ': ' . $e->getMessage());
}
