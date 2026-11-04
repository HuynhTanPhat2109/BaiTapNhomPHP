<?php
// Isolated in-memory fixture only. Never connect to the configured application database.
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Controllers\BookingHistoryController;
use App\Models\Booking;
use Core\Application;
use Core\HttpException;

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE co_so (ma_co_so INTEGER PRIMARY KEY, ten_co_so TEXT, anh_dai_dien TEXT DEFAULT NULL);
    CREATE TABLE dat_san (ma_dat_san INTEGER PRIMARY KEY, ma_khach_hang INTEGER, ma_co_so INTEGER,
        ngay_tao TEXT, trang_thai_dat_san TEXT, tong_tien_tam_tinh TEXT, het_han_giu_san_luc TEXT DEFAULT NULL,
        tong_tien_san TEXT DEFAULT \'185000.00\', tong_tien_dich_vu TEXT DEFAULT \'0.00\');
    CREATE TABLE thanh_toan (ma_dat_san INTEGER, trang_thai TEXT);
    CREATE TABLE chi_tiet_dat_san (ma_chi_tiet INTEGER PRIMARY KEY, ma_dat_san INTEGER, ma_san INTEGER,
        ten_san_snapshot TEXT, ngay_choi TEXT, gio_bat_dau TEXT, gio_ket_thuc TEXT);
    CREATE TABLE chi_tiet_dich_vu_dat (ma_chi_tiet_dv INTEGER PRIMARY KEY, ma_dat_san INTEGER,
        ten_dich_vu_snapshot TEXT, don_vi_tinh_snapshot TEXT, so_luong INTEGER, don_gia TEXT, thanh_tien TEXT)');
$pdo->prepare('INSERT INTO co_so (ma_co_so,ten_co_so) VALUES (1,?)')->execute(['STRYKE · Cơ sở kiểm thử']);
$statuses = BookingHistoryController::STATUSES;
$states = array_keys($statuses);
for ($i = 1; $i <= 14; $i++) {
    $pdo->prepare("INSERT INTO dat_san (ma_dat_san,ma_khach_hang,ma_co_so,ngay_tao,trang_thai_dat_san,tong_tien_tam_tinh,het_han_giu_san_luc)
        VALUES (?,7,1,?,?,?,'2099-01-01 00:00:00')")->execute([$i, '2026-10-' . sprintf('%02d', $i) . ' 09:00:00', $states[($i-1)%6], '185000.00']);
    foreach ([[1,'Sân 1','08:00:00','08:30:00'], [1,'Sân 1','10:00:00','11:00:00'], [2,'Sân 2','09:00:00','10:00:00']] as [$court,$name,$start,$end]) {
        $pdo->prepare('INSERT INTO chi_tiet_dat_san (ma_dat_san,ma_san,ten_san_snapshot,ngay_choi,gio_bat_dau,gio_ket_thuc) VALUES (?,?,?,?,?,?)')->execute([$i,$court,$name,'2026-11-15',$start,$end]);
    }
}
$pdo->exec("INSERT INTO dat_san (ma_dat_san,ma_khach_hang,ma_co_so,ngay_tao,trang_thai_dat_san,tong_tien_tam_tinh)
    VALUES (99,8,1,'2027-01-01 00:00:00','DA_XAC_NHAN','999999.00')");
$pdo->exec("INSERT INTO chi_tiet_dich_vu_dat VALUES (1,14,'Nước snapshot PR6','chai',2,'10000.00','20000.00'),
    (2,99,'Dịch vụ chủ khác','chai',1,'999999.00','999999.00');
    UPDATE dat_san SET tong_tien_san='165000.00',tong_tien_dich_vu='20000.00' WHERE ma_dat_san=14");
$pdo->exec("UPDATE dat_san SET ngay_tao='2026-10-02 00:00:00' WHERE ma_dat_san=2;
    UPDATE dat_san SET ngay_tao='2026-10-04 23:59:59' WHERE ma_dat_san=4");
$pdo->exec('PRAGMA query_only=ON'); // Any accidental UPDATE/INSERT/DELETE now fails.
$model = new Booking($pdo);
$checks = 0;
$check = static function(bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException($label);
    $checks++;
};
$history = $model->history(7);
$check($history['total'] === 14 && count($history['items']) === 6, 'Counts and pagination');
$check(array_column($history['items'], 'ma_dat_san') === [14,13,12,11,10,9], 'Newest first, owner scope');
$check(count($history['items'][0]['lines']) === 3, 'Multiple courts and separated slots');
$check($history['items'][0]['lines'][0]['ten_san_snapshot'] === 'Sân 1', 'Snapshot');
$check($history['items'][0]['tong_tien_tam_tinh'] === '185000.00', 'Stored total');
$check($history['items'][0]['tong_tien_san'] === '165000.00' && $history['items'][0]['tong_tien_dich_vu'] === '20000.00', 'Stored court/service totals, no repricing');
$check(count($history['items'][0]['service_lines']) === 1 && $history['items'][0]['service_lines'][0]['ten_dich_vu_snapshot'] === 'Nước snapshot PR6', 'History reads stored service snapshots without catalog');
$check($history['items'][1]['service_lines'] === [], 'History supports booking without services');
$check(!str_contains(json_encode($history), 'Dịch vụ chủ khác'), 'Service snapshot owner scope');
$check($model->history(7, '', 999)['page'] === 3, 'Clamp page');
$check($model->history(7, '', 3)['items'][1]['ma_dat_san'] === 1, 'Oldest still accessible');
$check($model->history(123)['total'] === 0, 'New customer empty');
$check($model->history(7, "' OR 1=1 --")['total'] === 0, 'Bound status cannot inject');
$range = $model->history(7, '', 1, '2026-10-02', '2026-10-04', 'oldest');
$check(array_column($range['items'],'ma_dat_san') === [2,3,4], 'Inclusive start midnight and end 23:59:59, oldest first');
$check(array_sum($range['counts']) === 3 && array_sum($range['stats']) === 14, 'Date-scoped tab counts and lifetime overview');
$check($model->history(7,'DA_XAC_NHAN',1,'2026-10-02','2026-10-04','oldest')['total'] === 1, 'Combined date/status filter');
$check($model->history(7,'',1,'2026-11-15','2026-11-15')['total'] === 0, 'Filter uses creation date, not play date');
$check($model->history(7,'',1,'','2026-10-02')['total'] === 2, 'Open-ended start');
$check($model->history(7,'',1,'2026-10-14','')['total'] === 1, 'Open-ended end');
$check(array_column($model->history(7,'',2,'2026-10-01','2026-10-14','oldest')['items'],'ma_dat_san') === [7,8,9,10,11,12], 'Filtered page two retains ordering');
foreach ($states as $state) {
    $filtered = $model->history(7, $state);
    $check($filtered['total'] > 0 && count(array_unique(array_column($filtered['items'], 'trang_thai_dat_san'))) === 1
        && $filtered['items'][0]['trang_thai_dat_san'] === $state, 'Effective status ' . $state);
}
// Real controller and middleware, with identity stub only; the history SQL remains real.
$_SESSION = ['account_id'=>7, 'authenticated_at'=>time(), 'last_active_at'=>time()];
$accounts = new class($pdo) extends App\Models\TaiKhoan {
    public string $role = 'KHACH_HANG';
    public function identity(int $id): ?array {
        return ['ma_tai_khoan'=>7,'ten_dang_nhap'=>'history.test','ten_vai_tro'=>$this->role,
            'trang_thai'=>'HOAT_DONG','ma_khach_hang'=>7,'trang_thai_khach'=>'HOAT_DONG'];
    }
};
$app = new Application($pdo);
(new ReflectionProperty($app, 'auth'))->setValue($app, new App\Services\AuthService($accounts));
$router = new Core\Router();
require dirname(__DIR__) . '/routes/courts.php';
$_ENV['APP_BASE_PATH'] = '/BaiTapNhomPHP/public';
$_GET = ['ma_khach_hang'=>'8'];
ob_start(); $router->dispatch('GET', '/booking/history'); $html = ob_get_clean();
$check(str_contains($html, '185.000') && !str_contains($html, '#99'), 'Controller ignores URL customer');
$check(!str_contains($html, 'booking%2Fwait') && !str_contains($html, 'booking%2Fdetails'), 'No payment/draft links');
$check(str_contains($html, '08:00–08:30') && str_contains($html, '10:00–11:00'), 'No invented continuous range');
$check(str_contains($html, 'Chờ thanh toán'), 'Pending booking with future deadline remains pending on GET');
$_GET = ['status'=>'DA_XAC_NHAN'];
ob_start(); $router->dispatch('GET', '/booking/history'); $filteredHtml = ob_get_clean();
$check(str_contains($filteredHtml, 'status=DA_XAC_NHAN'), 'Filter retained in URLs');
$_GET = ['from'=>'2026-10-01','to'=>'2026-10-14','sort'=>'oldest'];
ob_start(); $router->dispatch('GET','/booking/history'); $dateHtml = ob_get_clean();
$check(str_contains($dateHtml,'from=2026-10-01&amp;to=2026-10-14&amp;sort=oldest&amp;page=2'), 'Pagination preserves date and sort');
foreach ([['page'=>'0'],['page'=>[]],['status'=>[]],['status'=>"' OR 1=1 --"],['from'=>'2026-02-30'],['from'=>'2026-10-03','to'=>'2026-10-02'],['to'=>[]],['sort'=>'oldest; DROP TABLE dat_san'],['from'=>'bad'],['from'=>"2026-01-01\0"]] as $_GET) {
    try { ob_start(); $router->dispatch('GET','/booking/history'); throw new RuntimeException('Bad query accepted'); }
    catch (HttpException $e) { $check($e->status === 422, 'Invalid filter/page rejected'); }
    finally { ob_end_clean(); }
}
$accounts->role = 'QUAN_TRI_TAP_DOAN'; $_GET = [];
try { $router->dispatch('GET','/booking/history'); throw new RuntimeException('Admin accepted'); }
catch (HttpException $e) { $check($e->status === 403, 'Customer role required'); }
// Escape both facility and snapshot labels, and render empty/filter-empty states.
$history['items'][0]['ten_co_so'] = '<script>alert(1)</script>';
foreach ($history['items'][0]['lines'] as &$line) $line['ten_san_snapshot'] = '<img src=x onerror=alert(1)>';
unset($line);
$status = '';
ob_start(); require dirname(__DIR__).'/app/views/courts/history.php'; $escaped = ob_get_clean();
$check(str_contains($escaped, '&lt;script&gt;') && str_contains($escaped, '&lt;img src=x') && !str_contains($escaped, '<img src=x'), 'XSS escaped');
$history = $model->history(123);
ob_start(); require dirname(__DIR__).'/app/views/courts/history.php'; $empty = ob_get_clean();
$check(str_contains($empty, 'Bạn chưa có lịch đặt sân nào'), 'First booking empty state');
$history['counts'] = ['DA_HUY'=>1]; $history['stats'] = ['DA_HUY'=>1]; $status='DA_XAC_NHAN';
ob_start(); require dirname(__DIR__).'/app/views/courts/history.php'; $empty = ob_get_clean();
$check(str_contains($empty, 'Không có lịch đặt phù hợp bộ lọc'), 'Filter empty state');
if (in_array('--render', $argv, true)) { echo $html; } else { echo "PASS: $checks history checks; read-only SQLite fixture, no application DB.\n"; }
