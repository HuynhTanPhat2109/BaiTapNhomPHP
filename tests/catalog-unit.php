<?php

// Validation and in-memory login/navigation tests; no real database or account changes.
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Controllers\AuthController;
use App\Middleware\RoleMiddleware;
use App\Models\TaiKhoan;
use App\Services\AuthService;
use App\Services\BoMonService;
use App\Services\LoaiSanService;
use Core\Application;
use Core\Csrf;
use Core\HttpException;
use Core\Session;
use Core\View;

$checks = 0;
function checkCatalogUnit(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    $checks++;
}

$sport = ['ten_bo_mon' => ' Cầu lông ', 'mo_ta' => "Mô tả\nhai dòng", 'trang_thai' => 'HOAT_DONG'];
checkCatalogUnit(BoMonService::validate($sport) === [], 'valid Unicode and multiline description');
checkCatalogUnit(BoMonService::normalize($sport)['ten_bo_mon'] === 'Cầu lông', 'trim name');
checkCatalogUnit(BoMonService::normalize($sport + ['ignored' => 'value']) === BoMonService::normalize($sport), 'only allowed fields');
checkCatalogUnit(BoMonService::normalize(array_replace($sport, ['mo_ta' => '0']))['mo_ta'] === '0', 'preserve zero description');
checkCatalogUnit(BoMonService::normalize(array_replace($sport, ['mo_ta' => '  ']))['mo_ta'] === null, 'empty description is null');
foreach (['', '  ', ['bad'], "Bad\0Name", "Bad\nName", "\xff", str_repeat('á', 101)] as $name) {
    checkCatalogUnit(isset(BoMonService::validate(array_replace($sport, ['ten_bo_mon' => $name]))['ten_bo_mon']), 'reject invalid sport name');
}
checkCatalogUnit(BoMonService::validate(array_replace($sport, ['ten_bo_mon' => str_repeat('á', 100)])) === [], '100 Unicode characters');
foreach ([['bad'], "\xff", "Bad\0text", str_repeat('á', 256)] as $description) {
    checkCatalogUnit(isset(BoMonService::validate(array_replace($sport, ['mo_ta' => $description]))['mo_ta']), 'reject invalid description');
}
checkCatalogUnit(BoMonService::validate(array_replace($sport, ['mo_ta' => str_repeat('á', 255)])) === [], '255 character description');
foreach (['', 'ACTIVE', ['HOAT_DONG'], null] as $state) {
    checkCatalogUnit(isset(BoMonService::validate(array_replace($sport, ['trang_thai' => $state]))['trang_thai']), 'state allowlist');
}
checkCatalogUnit(count(BoMonService::validate([])) === 2, 'missing required values');
$type = ['ten_loai_san' => str_repeat('á', 150), 'ma_bo_mon' => '1', 'trang_thai' => 'NGUNG'];
checkCatalogUnit(LoaiSanService::validate($type) === [], 'valid type with 150 Unicode characters');
checkCatalogUnit(isset(LoaiSanService::validate(array_replace($type, ['ten_loai_san' => str_repeat('á', 151)]))['ten_loai_san']), 'type name limit');
foreach (['', '0', '-1', '01', '1.0', '1e2', '1bad', ' 1 ', '2147483648', str_repeat('9', 30), ['1'], null] as $id) {
    checkCatalogUnit(isset(LoaiSanService::validate(array_replace($type, ['ma_bo_mon' => $id]))['ma_bo_mon']), 'reject invalid parent ID');
}
checkCatalogUnit(LoaiSanService::validate(array_replace($type, ['ma_bo_mon' => '2147483647'])) === [], 'schema integer upper bound');
$password = 'Aa1!' . bin2hex(random_bytes(6));
$accounts = new class($password) extends TaiKhoan {
    public ?array $user = null;
    private string $hash;

    public function __construct(string $password)
    {
        $this->hash = password_hash($password, PASSWORD_DEFAULT);
    }

    public function findByUsername(string $username): ?array
    {
        return $username === 'role.test' && $this->user !== null
            ? ['ma_tai_khoan' => 1, 'mat_khau' => $this->hash] : null;
    }

    public function identity(int $id): ?array
    {
        return $id === 1 ? $this->user : null;
    }
};
$auth = new AuthService($accounts);
$application = new Application();
(new ReflectionProperty(Application::class, 'auth'))->setValue($application, $auth);
$controller = new AuthController($application);
$roleHome = new ReflectionMethod(AuthController::class, 'roleHome');
$identity = [
    'ma_tai_khoan' => 1, 'ten_dang_nhap' => 'role.test', 'trang_thai' => 'HOAT_DONG',
    'ma_khach_hang' => 1, 'trang_thai_khach' => 'HOAT_DONG',
    'ma_nhan_vien' => 1, 'trang_thai_nhan_vien' => 'DANG_LAM',
    'ma_cong_ty' => 1, 'ma_co_so' => 1, 'cong_ty_cua_co_so' => 1,
    'trang_thai_cong_ty' => 'HOAT_DONG', 'trang_thai_co_so' => 'HOAT_DONG',
];
$_SERVER['SCRIPT_NAME'] = '/BaiTapNhomPHP/public/index.php';
ini_set('session.save_path', dirname(__DIR__) . '/storage/temp');
Session::start();
try {
    foreach ([
        'QUAN_TRI_TAP_DOAN' => '/admin', 'KHACH_HANG' => '/',
        'QUAN_LY_CONG_TY' => '/company', 'QUAN_LY_CO_SO' => '/facility', 'NHAN_VIEN' => '/staff',
    ] as $role => $destination) {
        Session::forgetAuthentication();
        $accounts->user = $identity + ['ten_vai_tro' => $role];
        checkCatalogUnit(!$auth->attempt('role.test', 'incorrect'), $role . ': wrong password rejected');
        checkCatalogUnit(Session::accountId() === null, $role . ': failed login stays anonymous');
        $sessionId = session_id();
        Csrf::token();
        checkCatalogUnit($auth->attempt('role.test', $password), $role . ': login succeeds');
        checkCatalogUnit(session_id() !== $sessionId && !isset($_SESSION['_csrf']), $role . ': session and CSRF rotate');
        $user = $auth->user();
        checkCatalogUnit($user['ten_vai_tro'] === $role, $role . ': identity keeps the correct role');
        checkCatalogUnit($roleHome->invoke($controller, $user['ten_vai_tro']) === $destination, $role . ': correct login destination');
        (new RoleMiddleware($application, [$role]))();
        checkCatalogUnit(true, $role . ': own area remains accessible');
        if ($role !== 'QUAN_TRI_TAP_DOAN') {
            try {
                (new RoleMiddleware($application, ['QUAN_TRI_TAP_DOAN']))();
                throw new RuntimeException('Non-Admin entered the Admin area.');
            } catch (HttpException $e) {
                checkCatalogUnit($e->status === 403, $role . ': Admin area remains forbidden');
            }
        }
    }
    foreach ([null, '', 'UNKNOWN', [], ['QUAN_TRI_TAP_DOAN'], 1, true] as $invalidRole) {
        checkCatalogUnit($roleHome->invoke($controller, $invalidRole) === '/account', 'invalid/missing role uses protected fallback');
    }
    $accounts->user = null;
    $user = $auth->user();
    checkCatalogUnit($user === null && Session::accountId() === null, 'removed identity invalidates authentication');
    checkCatalogUnit($roleHome->invoke($controller, $user['ten_vai_tro'] ?? null) === '/account', 'missing user uses protected fallback');
    $accounts->user = array_replace($identity, ['ten_vai_tro' => 'QUAN_TRI_TAP_DOAN', 'trang_thai' => 'KHOA']);
    checkCatalogUnit(!$auth->attempt('role.test', $password), 'locked Admin remains unable to log in');
    $accounts->user = $identity + ['ten_vai_tro' => 'UNKNOWN'];
    checkCatalogUnit(!$auth->attempt('role.test', $password), 'unknown role remains unable to log in');

    // Render the real views with in-memory values; never create database fixtures.
    $viewData = [
        'title' => 'Kiểm tra quản trị', 'user' => ['ten_dang_nhap' => '<Admin>'],
        'stats' => ['bo_mon' => 7, 'loai_san' => 12],
        'companies' => [], 'company' => ['ma_cong_ty' => 3, 'ten_cong_ty' => '<Công ty>'],
        'items' => [], 'types' => [], 'sports' => [], 'errors' => [], 'old' => [],
        'filters' => ['q' => '', 'ma_bo_mon' => '', 'trang_thai' => '', 'per_page' => 10, 'page' => 1],
        'page' => 1, 'pages' => 1, 'total' => 0,
        'item' => ['ma_bo_mon' => 1, 'ma_loai_san' => 2, 'ten_bo_mon' => 'Bộ môn mẫu',
            'ten_loai_san' => 'Loại sân mẫu', 'trang_thai' => 'NGUNG', 'trang_thai_bo_mon' => 'NGUNG'],
        'related' => ['loai_san' => 0, 'loai_san_dang_dung' => 0, 'san' => 0,
            'san_dang_dung' => 0, 'bang_gia' => 0, 'bang_gia_dang_dung' => 0],
    ];
    $templates = ['admin/dashboard', 'admin/companies/index', 'admin/companies/create', 'admin/companies/edit'];
    foreach (['bo-mon', 'loai-san'] as $module) {
        foreach (['index', 'form', 'show', 'delete'] as $page) {
            $templates[] = 'admin/' . $module . '/' . $page;
        }
    }
    $menuGroups = require dirname(__DIR__) . '/config/admin-menu.php';
    $upcoming = 0;
    foreach ($menuGroups as $group) {
        foreach ($group as $entry) {
            $upcoming += $entry['route'] === null ? 1 : 0;
        }
    }
    foreach ($templates as $template) {
        ob_start();
        View::render($template, $viewData);
        $html = ob_get_clean();
        preg_match('/<aside class="admin-sidebar".*?<\/aside>/s', $html, $match);
        $sidebar = $match[0] ?? '';
        foreach (['/admin', '/admin/companies', '/admin/bo-mon', '/admin/loai-san', '/admin/co-so', '/admin/san'] as $path) {
            checkCatalogUnit(strpos($sidebar, 'href="' . e(url($path)) . '"') !== false, $template . ': Admin navigation ' . $path);
        }
        checkCatalogUnit(substr_count($sidebar, 'aria-current="page"') === 1, $template . ': exactly one active menu');
        $expectedPath = str_starts_with($template, 'admin/companies/') ? '/admin/companies'
            : (str_starts_with($template, 'admin/bo-mon/') ? '/admin/bo-mon'
                : (str_starts_with($template, 'admin/loai-san/') ? '/admin/loai-san' : '/admin'));
        checkCatalogUnit(strpos($sidebar, 'href="' . e(url($expectedPath)) . '" aria-current="page"') !== false, $template . ': correct active module');
        checkCatalogUnit(substr_count($sidebar, 'aria-disabled="true"') === $upcoming, $template . ': upcoming items');
        checkCatalogUnit(substr_count($sidebar, '<small>Sắp phát triển</small>') === $upcoming, $template . ': upcoming labels');
        checkCatalogUnit(strpos($sidebar, '<a class="admin-menu__item is-disabled"') === false, $template . ': upcoming items have no links');
        checkCatalogUnit(strpos($sidebar, '>Trang chủ</a>') === false, $template . ': no customer navigation');
        checkCatalogUnit(strpos($html, '/assets/css/admin.css') !== false, $template . ': isolated Admin styles');
        checkCatalogUnit(strpos($html, 'role-notice') === false, $template . ': dedicated Admin content');
        if ($template === 'admin/dashboard') {
            checkCatalogUnit(strpos($html, 'data-stat="bo_mon">7</p>') !== false, 'dashboard uses supplied sport count');
            checkCatalogUnit(strpos($html, 'data-stat="loai_san">12</p>') !== false, 'dashboard uses supplied type count');
            checkCatalogUnit(strpos($html, '<strong>&lt;Admin&gt;</strong>') !== false, 'dashboard escapes username');
        }
    }
    ob_start();
    View::render('home', ['template' => 'admin/dashboard', 'isAdminArea' => true]);
    $homeHtml = ob_get_clean();
    checkCatalogUnit(strpos($homeHtml, 'hero-section') !== false, 'original customer homepage still renders');
    checkCatalogUnit(strpos($homeHtml, '>Trang chủ</a>') !== false, 'customer navbar preserved');
    checkCatalogUnit(strpos($homeHtml, '>Tổng quan</a>') === false, 'template cannot be overridden by view data');
    checkCatalogUnit(strpos($homeHtml, 'admin-sidebar') === false && strpos($homeHtml, '/assets/css/admin.css') === false, 'customer homepage has no Admin UI or styles');
} finally {
    Session::logout();
}
echo "PASS: $checks catalog/login/navigation checks. All 5 roles tested; no real database connected.\n";
