<?php
// Real controller/read models with the existing SQLite :memory: fixture only.
define('STRYKE_ADMIN_MANAGEMENT_UNIT', true);
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_GET = ['fixture' => 'reset', 'count' => '20']; $_POST = [];
require __DIR__ . '/fixtures/admin-navigation.php';

use App\Controllers\EmployeeController;
use App\Models\TaiKhoan;
use App\Services\AuthService;
use Core\{Application, HttpException, Session};

$checks = 0;
function employeeCheck(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
$actor = new class extends TaiKhoan {
    public string $role = 'QUAN_TRI_TAP_DOAN';
    public function __construct() {}
    public function identity(int $id): ?array
    {
        return ['ma_tai_khoan' => 1, 'ten_dang_nhap' => 'review.actor', 'trang_thai' => 'HOAT_DONG',
            'ten_vai_tro' => $this->role, 'ma_cong_ty' => 1, 'ma_co_so' => 2, 'cong_ty_cua_co_so' => 1,
            'ma_nhan_vien' => 1, 'trang_thai_nhan_vien' => 'DANG_LAM', 'trang_thai_cong_ty' => 'HOAT_DONG',
            'trang_thai_co_so' => 'HOAT_DONG', 'ma_khach_hang' => 1, 'trang_thai_khach' => 'HOAT_DONG'];
    }
};
Session::authenticate(1);
$app = new Application($pdo);
(new ReflectionProperty(Application::class, 'auth'))->setValue($app, new AuthService($actor));
$controller = new EmployeeController($app);
$render = static function (string $role, mixed $id) use ($actor, $controller, $pdo): string {
    $actor->role = $role;
    $_GET = ['route' => match ($role) { 'QUAN_TRI_TAP_DOAN' => '/admin', 'QUAN_LY_CO_SO' => '/facility', default => '/company' } . '/employees/show', 'id' => $id];
    $pdo->preparedQueries = [];
    ob_start();
    try { $controller->show(); return ob_get_contents(); }
    finally { ob_end_clean(); }
};
foreach ([['QUAN_TRI_TAP_DOAN', 1, 'memory.1', 'UX Company 02'], ['QUAN_LY_CONG_TY', 1001, 'memory.1001', 'UX Company 01'],
    ['QUAN_LY_CO_SO', 1001, 'memory.1001', 'UX Company 01']] as [$role, $id, $username, $company]) {
    $html = $render($role, $id);
    foreach (['Thông tin nhân viên', 'Tài khoản đăng nhập', 'Phân quyền', $username, $company] as $text) {
        employeeCheck(str_contains($html, $text), "$role: scoped detail displays $text");
    }
    employeeCheck(!str_contains($html, 'FIXTURE_SECRET_NEVER_RENDER') && !str_contains($html, 'name="password"'), 'no secret or password field rendered');
    $document = new DOMDocument();
    @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($document);
    employeeCheck($xpath->query('//section[contains(@class, "detail-page")]//form | //section[contains(@class, "detail-page")]//*[contains(@class, "detail-edit")]')->length === 0, 'detail exposes no account/employee mutation; existing logout remains available');
    foreach ($pdo->preparedQueries as $sql) {
        employeeCheck(preg_match('/\ASELECT\b/i', trim($sql)) === 1, 'detail database calls are read-only');
        employeeCheck(!preg_match('/\bmat_khau\b|\bpassword\b|\btk\.\*/i', $sql), 'actual detail SQL never selects password/hash or account wildcard');
    }
}
employeeCheck(str_contains($render('QUAN_LY_CONG_TY', 9000), 'Chưa có tài khoản đăng nhập'), 'own employee without account remains accessible');
employeeCheck(str_contains($render('QUAN_TRI_TAP_DOAN', 1001), 'memory.1001'), 'approved Admin scope now includes facility managers');
employeeCheck(str_contains($render('QUAN_TRI_TAP_DOAN', 2001), 'memory.2001'), 'approved Admin scope includes staff from another company');
employeeCheck(str_contains($render('QUAN_TRI_TAP_DOAN', 9000), 'Chưa có tài khoản đăng nhập'), 'approved Admin scope includes personnel without an account');
employeeCheck(str_contains($render('QUAN_LY_CO_SO', 1001), 'memory.1001'), 'facility manager may read an employee in the assigned facility');
$pdo->exec("UPDATE tai_khoan SET trang_thai='KHOA' WHERE ma_tai_khoan=1001");
employeeCheck(str_contains($render('QUAN_LY_CONG_TY', 1001), 'Bị khóa'), 'locked employee account uses the real schema status');
$pdo->exec("UPDATE nhan_vien SET ho_ten='<script>secretAttack()</script>' WHERE ma_nhan_vien=1001");
$html = $render('QUAN_LY_CONG_TY', 1001);
employeeCheck(!str_contains($html, '<script>secretAttack()') && str_contains($html, '&lt;script&gt;secretAttack()'), 'employee fields are HTML escaped');
foreach ([['QUAN_LY_CONG_TY', 2001, 404], ['QUAN_LY_CONG_TY', 1, 404],
    ['QUAN_TRI_TAP_DOAN', 999999, 404], ['QUAN_TRI_TAP_DOAN', [], 404], ['QUAN_TRI_TAP_DOAN', '0', 404],
    ['QUAN_TRI_TAP_DOAN', '99999999999999999999999999999', 404], ['KHACH_HANG', 1, 403], ['QUAN_LY_CO_SO', 2001, 404], ['NHAN_VIEN', 1001, 403]] as [$role, $id, $status]) {
    try { $render($role, $id); employeeCheck(false, 'expected scoped rejection'); }
    catch (HttpException $e) { employeeCheck($e->status === $status, "$role: invalid/out-of-scope detail rejected"); }
}
$pdo->exec('UPDATE nhan_vien SET ma_co_so=3 WHERE ma_nhan_vien=1001');
try { $render('QUAN_LY_CONG_TY', 1001); employeeCheck(false, 'expected inconsistent facility rejection'); }
catch (HttpException $e) { employeeCheck($e->status === 404, 'inconsistent employee/facility company cannot leak a foreign facility'); }
employeeCheck(!$pdo->inTransaction(), 'detail starts no transaction');
session_write_close();
echo "PASS: $checks employee detail/scope/read-only/no-secret checks; SQLite :memory: only.\n";
