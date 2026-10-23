<?php

// Real account controller/views, in-memory identities; no database queries or password writes.
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Controllers\AccountController;
use App\Models\TaiKhoan;
use App\Services\AuthService;
use Core\Application;
use Core\Session;

$checks = 0;
function checkAccountLayout(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    $checks++;
}

$accounts = new class extends TaiKhoan {
    public array $user = [];
    public function __construct() {}
    public function identity(int $id): ?array { return $id === 1 ? $this->user : null; }
};
$noDatabase = new class extends PDO { public function __construct() {} };
$app = new Application($noDatabase);
(new ReflectionProperty(Application::class, 'auth'))->setValue($app, new AuthService($accounts));
$controller = new AccountController($app);
$identity = ['ma_tai_khoan' => 1, 'ten_dang_nhap' => '<test.user>', 'trang_thai' => 'HOAT_DONG',
    'ma_khach_hang' => 1, 'trang_thai_khach' => 'HOAT_DONG', 'ten_khach_hang' => 'Khách hàng',
    'ma_nhan_vien' => 1, 'trang_thai_nhan_vien' => 'DANG_LAM', 'ten_nhan_vien' => 'Nhân viên',
    'ma_cong_ty' => 1, 'ma_co_so' => 1, 'cong_ty_cua_co_so' => 1,
    'trang_thai_cong_ty' => 'HOAT_DONG', 'trang_thai_co_so' => 'HOAT_DONG'];
$_SERVER['SCRIPT_NAME'] = '/index.php';
ini_set('session.save_path', dirname(__DIR__) . '/storage/temp');
Session::start();
try {
    foreach (['QUAN_TRI_TAP_DOAN' => '/admin', 'KHACH_HANG' => '/',
        'QUAN_LY_CONG_TY' => '/company', 'QUAN_LY_CO_SO' => '/facility', 'NHAN_VIEN' => '/staff'] as $role => $home) {
        $accounts->user = $identity + ['ten_vai_tro' => $role];
        Session::authenticate(1);
        foreach (['show' => 200, 'settings' => 200, 'changePassword' => 422] as $method => $status) {
            $_POST = ['current_password' => '', 'new_password' => '<secret>', 'new_password_confirmation' => ''];
            ob_start();
            $controller->$method();
            $html = ob_get_clean();
            checkAccountLayout(http_response_code() === $status, "$role $method response");
            checkAccountLayout(str_contains($html, 'class="admin-sidebar"') === ($role === 'QUAN_TRI_TAP_DOAN'), "$role $method correct layout");
            checkAccountLayout(str_contains($html, '/assets/css/admin.css') === ($role === 'QUAN_TRI_TAP_DOAN'), "$role $method scoped styles");
            checkAccountLayout(str_contains($html, 'href="' . e(url($home)) . '"'), "$role $method return to role area");
            checkAccountLayout(!str_contains($html, '<test.user>') && !str_contains($html, 'value="&lt;secret&gt;"'), 'escape identity and never echo passwords');
            checkAccountLayout(!str_contains($html, 'Thông tin cá nhân khách hàng') || $role === 'KHACH_HANG', 'no customer heading for staff/Admin');
            checkAccountLayout(Session::accountId() === 1, 'navigation preserves login');
        }
    }
} finally {
    Session::forgetAuthentication();
    session_write_close();
}
echo "OK: $checks account layout checks across five roles; no database used.\n";
