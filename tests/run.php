<?php

// Isolated tests: no database connection, SQL import or persistent test records.
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Middleware\RoleMiddleware;
use App\Models\TaiKhoan;
use App\Services\AccountService;
use App\Services\AuthService;
use App\Services\RegistrationService;
use App\Services\ValidationException;
use App\Services\LoginThrottle;
use Core\Application;
use Core\Csrf;
use Core\HttpException;
use Core\Router;
use Core\Session;

final class MemoryPDO extends PDO
{
    public array $accounts = [];
    public array $customers = [];
    public array $identities = [];
    public bool $failProfile = false;
    public bool $duplicateOnInsert = false;
    private bool $transaction = false;
    private array $snapshot = [];

    public function __construct() {}
    public function beginTransaction(): bool
    {
        if ($this->transaction) {
            throw new RuntimeException('Nested transaction');
        }
        $this->snapshot = [$this->accounts, $this->customers];
        return $this->transaction = true;
    }
    public function commit(): bool { $this->transaction = false; return true; }
    public function rollBack(): bool
    {
        [$this->accounts, $this->customers] = $this->snapshot;
        $this->transaction = false;
        return true;
    }
    public function inTransaction(): bool { return $this->transaction; }
    public function lastInsertId(?string $name = null): string|false { return (string) count($this->accounts); }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new MemoryStatement($this, $query);
    }
}

final class MemoryStatement extends PDOStatement
{
    private MemoryPDO $db;
    private string $sql;
    private $result = false;
    public function __construct(MemoryPDO $db, string $sql) { $this->db = $db; $this->sql = $sql; }
    public function execute(?array $params = null): bool
    {
        $this->result = false;
        if (strpos($this->sql, 'SELECT ma_vai_tro') === 0) {
            // Deliberately nonstandard IDs: services must resolve role by code.
            $this->result = ['KHACH_HANG' => 81, 'NHAN_VIEN' => 92][$params['code']] ?? false;
        } elseif (strpos($this->sql, 'SELECT * FROM tai_khoan') === 0) {
            foreach ($this->db->accounts as $row) {
                if (strcasecmp($row['ten_dang_nhap'], $params['username']) === 0) {
                    $this->result = $row;
                }
            }
        } elseif (strpos($this->sql, 'SELECT tk.ma_tai_khoan') === 0) {
            $this->result = $this->db->identities[$params['id']] ?? false;
        } elseif (strpos($this->sql, 'INSERT INTO tai_khoan') === 0) {
            if ($this->db->duplicateOnInsert) {
                $exception = new PDOException('Duplicate test key');
                $exception->errorInfo = ['23000', 1062, ''];
                throw $exception;
            }
            $id = count($this->db->accounts) + 1;
            $this->db->accounts[$id] = ['ma_tai_khoan' => $id, 'ten_dang_nhap' => $params['username'],
                'mat_khau' => $params['hash'], 'ma_vai_tro' => $params['role']];
        } elseif (strpos($this->sql, 'INSERT INTO khach_hang') === 0) {
            if ($this->db->failProfile) {
                throw new RuntimeException('Simulated profile write failure');
            }
            $this->db->customers[] = $params;
        } else {
            throw new RuntimeException('Unexpected test query');
        }
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->result;
    }
    public function fetchColumn(int $column = 0): mixed { return $this->result; }
}

$checks = 0;
function check(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
    $checks++;
}
function expectException(callable $action, string $class, ?int $status = null): void
{
    try { $action(); }
    catch (Throwable $e) {
        check($e instanceof $class, 'exception type ' . $class);
        if ($status !== null) { check($e->status === $status, 'HTTP status ' . $status); }
        return;
    }
    throw new RuntimeException('Expected exception: ' . $class);
}

$_SERVER['SCRIPT_NAME'] = '/BaiTapNhomPHP/public/index.php';
ini_set('session.save_path', dirname(__DIR__) . '/storage/temp');
Session::start();
try {
    check(base_path() === '/BaiTapNhomPHP/public', 'XAMPP base path');
    check(strpos(url('/login'), '/public/index.php?route=') !== false, 'query route fallback');
    $_GET = ['route' => ['bad']];
    expectException('request_path', HttpException::class, 404);
    $_GET = [];
    $_SERVER['REQUEST_URI'] = '/BaiTapNhomPHP/public/login?x=1';
    check(request_path() === '/login', 'clean route');
    check(e('<script>"&') === '&lt;script&gt;&quot;&amp;', 'HTML escaping');
    $_POST = ['username' => ['admin']];
    check(post_string('username') === '', 'array input rejected');

    $router = new Router();
    $events = [];
    $router->get('/test', static function () use (&$events) { $events[] = 'controller'; },
        [static function () use (&$events) { $events[] = 'middleware'; }]);
    $router->post('/test', static function () use (&$events) { $events[] = 'post'; });
    $router->dispatch('GET', '/test');
    check($events === ['middleware', 'controller'], 'middleware before controller');
    expectException(static fn() => $router->dispatch('GET', '/missing'), HttpException::class, 404);
    expectException(static fn() => $router->dispatch('DELETE', '/test'), HttpException::class, 405);
    expectException(static fn() => $router->dispatch('POST', '/test'), HttpException::class, 403);
    $_POST['_token'] = Csrf::token();
    $router->dispatch('POST', '/test');
    check(end($events) === 'post', 'valid CSRF accepted');
    expectException(static fn() => Csrf::verify(['bad']), HttpException::class, 403);

    $data = ['username' => 'customer.one', 'password' => 'Test-secret-123',
        'password_confirmation' => 'Test-secret-123', 'name' => 'Khách kiểm thử',
        'phone' => '0901234567', 'email' => 'test@example.com', 'role' => 'QUAN_TRI_TAP_DOAN'];
    check(RegistrationService::validate($data) === [], 'valid registration');
    foreach (['012345678', '01234567890', '1901234567', '+901234567', '090 123456', '090123456a', '０９０１２３４５６７'] as $invalidPhone) {
        $phoneData = $data;
        $phoneData['phone'] = $invalidPhone;
        check(isset(RegistrationService::validate($phoneData)['phone']), 'reject invalid 10-digit phone');
    }
    $bad = $data;
    $bad['email'] = 'invalid'; $bad['phone'] = 'bad'; $bad['name'] = '';
    $bad['password_confirmation'] = 'different';
    check(count(RegistrationService::validate($bad)) === 4, 'field validation');
    check(count(RegistrationService::validate([])) === 5, 'missing fields produce validation, not TypeError');
    $badTypes = array_fill_keys(['username', 'password', 'password_confirmation', 'name', 'phone', 'email'], ['invalid']);
    check(count(RegistrationService::validate($badTypes)) === 6, 'array payload rejected for every field');
    foreach (["Bad\0Name", "Bad\nName", "\xff"] as $badName) {
        $invalidNameData = $data;
        $invalidNameData['name'] = $badName;
        check(isset(RegistrationService::validate($invalidNameData)['name']), 'invalid name encoding or controls rejected');
    }
    $normalizedData = $data;
    $normalizedData['phone'] = ' 0901234567 ';
    check(RegistrationService::validate($normalizedData) === [], 'trim non-password input consistently');
    foreach (['abcde', str_repeat('a', 16), 'abc de', 'abc@de'] as $username) {
        check(isset(AccountService::validateCredentials($username, 'Abcdef1!')['username']), 'invalid username');
    }
    foreach (['abcdef', str_repeat('a', 15)] as $username) {
        check(AccountService::validateCredentials($username, 'Abcdef1!') === [], 'username boundary');
    }
    foreach (['Abcd1!x', 'Abcdefghijklmno1!', 'abcdefgh1!', 'ABCDEFGH1!',
        'Abcdefgh!', 'Abcdefg1', 'Abcdef1 ', "Abcdef1!\0", "Abcdef1!\n"] as $password) {
        $errors = AccountService::validateCredentials('validuser', $password);
        check(($errors['password'] ?? null) === AccountService::PASSWORD_REQUIREMENT, 'password requirement and message');
    }
    foreach (['Abcdef1!', 'Abcdefghijklmn1!', 'Abcdefghijkl1!xy', 'Ábcdef1!', 'Ábcdefghijklm1!x'] as $password) {
        check(AccountService::validateCredentials('validuser', $password) === [], 'valid password boundary or Unicode');
    }

    $db = new MemoryPDO();
    $registration = new RegistrationService($db);
    $id = $registration->register($data);
    check($id === 1 && count($db->customers) === 1 && !$db->inTransaction(), 'atomic registration committed');
    check($db->accounts[1]['ma_vai_tro'] === 81, 'public role cannot be injected');
    check($db->accounts[1]['mat_khau'] !== $data['password']
        && password_verify($data['password'], $db->accounts[1]['mat_khau']), 'password hashed');
    expectException(static fn() => $registration->register($data), ValidationException::class);
    $data['username'] = 'customer.two';
    $registration->register($data);
    check(count($db->customers) === 2, 'duplicate email allowed by schema');
    $db->failProfile = true;
    $data['username'] = 'customer.three';
    expectException(static fn() => $registration->register($data), RuntimeException::class);
    check(count($db->accounts) === 2 && !$db->inTransaction(), 'profile failure rolls back account');
    $db->failProfile = false;
    $db->duplicateOnInsert = true;
    expectException(static fn() => $registration->register($data), ValidationException::class);
    check(count($db->accounts) === 2 && !$db->inTransaction(), 'duplicate race rolled back');
    $db->duplicateOnInsert = false;
    $db->beginTransaction();
    (new AccountService($db))->create('staff.one', 'Staff-secret-123', 'NHAN_VIEN');
    check($db->inTransaction(), 'AccountService does not commit caller transaction');
    $db->rollBack();

    $identity = ['ma_tai_khoan' => 1, 'ten_dang_nhap' => 'customer.one', 'trang_thai' => 'HOAT_DONG',
        'ten_vai_tro' => 'KHACH_HANG', 'ma_khach_hang' => 1, 'trang_thai_khach' => 'HOAT_DONG',
        'ma_nhan_vien' => null, 'trang_thai_nhan_vien' => null, 'ma_cong_ty' => null,
        'ma_co_so' => null, 'cong_ty_cua_co_so' => null, 'trang_thai_cong_ty' => null,
        'trang_thai_co_so' => null];
    $db->identities[1] = $identity;
    $auth = new AuthService(new TaiKhoan($db));
    check(!$auth->attempt('missing', 'wrong') && !$auth->attempt('customer.one', 'wrong'), 'invalid credentials');
    $oldId = session_id();
    check($auth->attempt('customer.one', 'Test-secret-123'), 'login success');
    check(session_id() !== $oldId && $_SESSION['account_id'] === 1
        && isset($_SESSION['authenticated_at'], $_SESSION['last_active_at'])
        && !isset($_SESSION['_csrf']), 'session rotates, timestamps and drops stale state');
    $_SESSION['last_active_at'] = time() - Session::IDLE_TIMEOUT;
    check($auth->user() === null && !isset($_SESSION['account_id']), 'idle timeout is server enforced');
    check($auth->attempt('customer.one', 'Test-secret-123'), 'login after idle expiry');
    $_SESSION['authenticated_at'] = time() - Session::ABSOLUTE_TIMEOUT;
    check($auth->user() === null, 'absolute timeout is server enforced');
    check($auth->attempt('customer.one', 'Test-secret-123'), 'login after absolute expiry');
    $application = new Application($db);
    (new RoleMiddleware($application, ['KHACH_HANG']))();
    expectException(static fn() => (new RoleMiddleware($application, ['QUAN_TRI_TAP_DOAN']))(), HttpException::class, 403);
    $db->identities[1]['trang_thai'] = 'KHOA';
    check($auth->user() === null && !isset($_SESSION['account_id']), 'account lock invalidates session');
    check(!$auth->attempt('customer.one', 'Test-secret-123'), 'locked login rejected');
    $db->identities[1] = $identity;
    $db->identities[1]['trang_thai_khach'] = 'KHOA';
    check(!$auth->attempt('customer.one', 'Test-secret-123'), 'customer lock rejected');
    foreach (['QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO', 'NHAN_VIEN'] as $role) {
        $staff = array_merge($identity, ['ten_vai_tro' => $role, 'ma_nhan_vien' => 7,
            'trang_thai_nhan_vien' => 'DANG_LAM', 'ma_cong_ty' => 3, 'ma_co_so' => 9,
            'cong_ty_cua_co_so' => 3, 'trang_thai_cong_ty' => 'HOAT_DONG', 'trang_thai_co_so' => 'HOAT_DONG']);
        check(AuthService::isActive($staff), 'active staff role');
        $staff['cong_ty_cua_co_so'] = 4;
        check(!AuthService::isActive($staff), 'cross-company profile rejected');
    }
    $admin = array_merge($identity, ['ten_vai_tro' => 'QUAN_TRI_TAP_DOAN', 'ma_khach_hang' => null]);
    check(AuthService::isActive($admin), 'admin needs no employee profile');
    $throttleDirectory = dirname(__DIR__) . '/storage/temp/throttle-unit-' . bin2hex(random_bytes(4));
    $throttle = new LoginThrottle($throttleDirectory);
    try {
        for ($attempt = 0; $attempt < LoginThrottle::LIMIT; $attempt++) {
            check($throttle->consume('test-client', 1000) === 0, 'allow reserved attempt');
        }
        check($throttle->consume('test-client', 1001) === LoginThrottle::WINDOW - 1, 'throttle blocks at limit');
        check((new LoginThrottle($throttleDirectory))->consume('test-client', 1002) > 0, 'throttle survives new instance/session');
        check($throttle->consume('other-client', 1002) === 0, 'clients isolated');
        check($throttle->consume('test-client', 1000 + LoginThrottle::WINDOW) === 0, 'throttle expires');
    } finally {
        foreach (['test-client', 'other-client'] as $client) {
            unlink($throttleDirectory . '/' . hash('sha256', $client) . '.json');
        }
        rmdir($throttleDirectory);
    }
    $_SESSION['account_id'] = 1;
    Session::logout();
    check($_SESSION === [] && session_status() === PHP_SESSION_NONE, 'logout destroys session');
    echo "PASS: $checks checks. No real database was connected or modified.\n";
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        Session::logout();
    }
}
