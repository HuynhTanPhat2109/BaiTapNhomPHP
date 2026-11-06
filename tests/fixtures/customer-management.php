<?php
// Test-only server/CLI fixture. All records live in SQLite memory, never in the application database.
$unit = PHP_SAPI === 'cli' && defined('STRYKE_CUSTOMER_TEST');
if (!$unit && (PHP_SAPI !== 'cli-server' || getenv('STRYKE_CUSTOMER_TEST') !== '1')) {
    http_response_code(404);
    exit;
}
if (!$unit && str_starts_with(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/assets/')) return false;
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$_ENV['APP_BASE_PATH'] = '';
$_SERVER['SCRIPT_NAME'] = '/index.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');
$sessionPath = dirname(__DIR__, 2) . '/storage/temp/customer-management/sessions';
if (!is_dir($sessionPath)) mkdir($sessionPath, 0700, true);
ini_set('session.save_path', $sessionPath);
if (session_status() !== PHP_SESSION_ACTIVE) Core\Session::start();

final class CustomerMemoryPDO extends PDO
{
    public array $queries = [];
    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->queries[] = $query;
        return parent::prepare($query, $options);
    }
}
$pdo = new CustomerMemoryPDO();
$pdo->exec('CREATE TABLE co_so (ma_co_so INTEGER PRIMARY KEY, ma_cong_ty INTEGER, ten_co_so TEXT);
    CREATE TABLE khach_hang (ma_khach_hang INTEGER PRIMARY KEY, ho_ten TEXT, so_dien_thoai TEXT, email TEXT);
    CREATE TABLE dat_san (ma_dat_san INTEGER PRIMARY KEY, ma_khach_hang INTEGER, ma_co_so INTEGER,
        ngay_tao TEXT, trang_thai_dat_san TEXT, het_han_giu_san_luc TEXT, tong_tien_tam_tinh TEXT);
    CREATE TABLE thanh_toan (ma_dat_san INTEGER, trang_thai TEXT, ghi_chu TEXT, anh_minh_chung TEXT);
    CREATE TABLE chi_tiet_dat_san (ma_chi_tiet INTEGER PRIMARY KEY, ma_dat_san INTEGER, ma_san INTEGER,
        ten_san_snapshot TEXT, ngay_choi TEXT, gio_bat_dau TEXT, gio_ket_thuc TEXT);
    INSERT INTO co_so VALUES (1,1,"Facility A1"),(2,1,"Facility A2"),(3,2,"Facility B1");
    INSERT INTO khach_hang VALUES (1,"Shared Customer","0900000001","shared@example.test"),
        (2,"Only A1","0900000002",NULL),(3,"Only B1","0900000003","b@example.test"),
        (4,"No bookings","0900000004",NULL),(5,"Literal %_ customer","0900000005","literal@example.test"),
        (6,"<script>customerAttack()</script>","0900000006","<img src=x onerror=attack()>");');
$addBooking = static function(int $id, int $customer, int $facility, string $date, string $status) use ($pdo): void {
    $pdo->prepare('INSERT INTO dat_san VALUES (?,?,?,?,?,?,?)')->execute([$id,$customer,$facility,$date,$status,
        $status === 'CHO_THANH_TOAN' && $id !== 32 ? '2099-01-01 00:00:00' : '2000-01-01 00:00:00','120000.00']);
    // Multiple separate court/time lines must never multiply booking/customer statistics.
    foreach ([[1,'Court 1','08:00:00','08:30:00'],[1,'Court 1','10:00:00','10:30:00'],[2,'Court 2','11:00:00','12:00:00']] as [$court,$name,$start,$end]) {
        $pdo->prepare('INSERT INTO chi_tiet_dat_san (ma_dat_san,ma_san,ten_san_snapshot,ngay_choi,gio_bat_dau,gio_ket_thuc)
            VALUES (?,?,?,?,?,?)')->execute([$id,$court,$name,'2026-12-01',$start,$end]);
    }
};
$addBooking(1,1,1,'2026-11-01 10:00:00','DA_XAC_NHAN');
$addBooking(2,1,1,'2026-11-02 10:00:00','CHO_XAC_NHAN');
$addBooking(3,1,2,'2026-11-03 10:00:00','HOAN_THANH');
$addBooking(4,1,3,'2026-11-04 10:00:00','DA_XAC_NHAN');
$addBooking(5,2,1,'2026-11-01 11:00:00','DA_HUY');
$addBooking(6,3,3,'2026-11-05 10:00:00','DA_XAC_NHAN');
$addBooking(7,5,2,'2026-11-02 11:00:00','CHO_THANH_TOAN');
$addBooking(8,6,1,'2026-11-02 12:00:00','DA_HUY');
for ($id=10;$id<=23;$id++) {
    $pdo->prepare('INSERT INTO khach_hang VALUES (?,?,?,?)')->execute([$id,'Paged Customer ' . $id,'09000000' . $id,null]);
    $addBooking($id,$id,2,'2026-10-' . $id . ' 10:00:00','HOAN_THANH');
}
$addBooking(32,2,1,'2026-11-01 12:00:00','CHO_THANH_TOAN');
$pdo->exec('PRAGMA query_only=ON');
$pdo->queries = [];
$identity = static function(string $role, int $company=1, ?int $facility=1): array {
    return ['ma_tai_khoan'=>1,'ten_dang_nhap'=>'customer.test.operator','ten_vai_tro'=>$role,'trang_thai'=>'HOAT_DONG',
        'ma_nhan_vien'=>1,'trang_thai_nhan_vien'=>'DANG_LAM','ma_cong_ty'=>$company,
        'trang_thai_cong_ty'=>'HOAT_DONG','ma_co_so'=>$facility,'cong_ty_cua_co_so'=>$company,
        'trang_thai_co_so'=>'HOAT_DONG','ma_khach_hang'=>1,'trang_thai_khach'=>'HOAT_DONG'];
};
$actor = new class extends App\Models\TaiKhoan {
    public array $user;
    public function __construct() {}
    public function identity(int $id): ?array { return $this->user; }
};
$actor->user = match ($_GET['actor'] ?? '') {
    'company-b' => $identity('QUAN_LY_CONG_TY',2,null),
    'facility' => $identity('QUAN_LY_CO_SO'),
    'staff' => $identity('NHAN_VIEN'),
    'customer' => $identity('KHACH_HANG'),
    'admin' => $identity('QUAN_TRI_TAP_DOAN'),
    default => $identity('QUAN_LY_CONG_TY',1,null),
};
$_SESSION = ['account_id'=>1,'authenticated_at'=>time(),'last_active_at'=>time()];
$app = new Core\Application($pdo);
(new ReflectionProperty($app, 'auth'))->setValue($app,new App\Services\AuthService($actor));
$router = new Core\Router();
require dirname(__DIR__, 2) . '/routes/customers.php';
if (!$unit) {
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
    try { $router->dispatch($_SERVER['REQUEST_METHOD'], $_GET['route'] ?? '/admin/customers'); }
    catch (Core\HttpException $error) { http_response_code($error->status); echo e($error->getMessage()); }
}
