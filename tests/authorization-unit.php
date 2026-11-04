<?php
// Production routes/controllers/services on disposable SQLite RAM, never Core\Database/.env.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('STRYKE_PR6_CONTRACT', true);
$_GET = $_POST = [];
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/fixtures/booking-pr6.php';

use App\Models\TaiKhoan;
use App\Services\AuthService;
use Core\{Application, Csrf, HttpException, Router, Session};

$output = dirname(__DIR__) . '/storage/temp/authorization-review';
if (!is_dir($output . '/sessions')) mkdir($output . '/sessions', 0777, true);
ini_set('session.save_path', $output . '/sessions');
Session::start(); Session::authenticate(1);
$insert('san', ['ma_san' => 3, 'ma_co_so' => 3, 'ma_loai_san' => 1, 'ten_san' => 'FOREIGN_COMPANY_COURT_SECRET', 'trang_thai' => 'HOAT_DONG']);
$pdo->sqliteCreateFunction('TIMESTAMP', static fn($date, $time) => "$date $time");
$pdo->exec("UPDATE co_so SET gio_mo_cua='06:00:00',gio_dong_cua='23:00:00';
    UPDATE bo_mon SET trang_thai='HOAT_DONG'; UPDATE loai_san SET trang_thai='HOAT_DONG';
    UPDATE san SET trang_thai='HOAT_DONG'; UPDATE dich_vu SET trang_thai='DANG_CUNG_CAP';");
$insert('san', ['ma_san' => 2, 'ma_co_so' => 2, 'ma_loai_san' => 1, 'ten_san' => 'SIBLING_FACILITY_COURT_SECRET', 'trang_thai' => 'HOAT_DONG']);
foreach ([1 => 'QUAN_TRI_TAP_DOAN', 2 => 'QUAN_LY_CONG_TY', 3 => 'QUAN_LY_CO_SO', 4 => 'NHAN_VIEN', 5 => 'KHACH_HANG'] as $id => $code) {
    $insert('vai_tro', ['ma_vai_tro' => $id, 'ten_vai_tro' => $code]);
}
foreach ([10 => [2, 1, null], 11 => [3, 1, 1], 12 => [4, 1, 1], 13 => [4, 1, 2], 14 => [4, 2, 3], 15 => [null, 1, null]] as $id => [$roleId, $companyId, $facilityId]) {
    if ($roleId !== null) $insert('tai_khoan', ['ma_tai_khoan' => $id, 'ma_vai_tro' => $roleId, 'ten_dang_nhap' => 'audit.user.' . $id, 'mat_khau' => 'RAM_SECRET_NEVER_RENDER', 'trang_thai' => 'HOAT_DONG']);
    $insert('nhan_vien', ['ma_nhan_vien' => $id, 'ma_cong_ty' => $companyId, 'ma_co_so' => $facilityId,
        'ma_tai_khoan' => $roleId === null ? null : $id, 'ho_ten' => 'Audit employee ' . $id, 'trang_thai' => 'DANG_LAM']);
}
foreach ([1, 2, 3] as $id) $insert('bang_gia', ['ma_bang_gia' => $id, 'ma_co_so' => $id, 'ma_loai_san' => 1,
    'ten_bang_gia' => 'Audit price ' . $id, 'loai_ngay' => 'NGAY_THUONG', 'gio_bat_dau' => '08:00:00', 'gio_ket_thuc' => '09:00:00',
    'don_gia' => '10000.00', 'ngay_bat_dau' => '2025-01-01', 'ngay_ket_thuc' => null, 'trang_thai' => 'DANG_AP_DUNG']);
$actor = new class extends TaiKhoan {
    public string $role = 'QUAN_TRI_TAP_DOAN';
    public function __construct() {}
    public function identity(int $id): ?array
    {
        return ['ma_tai_khoan' => 1, 'ten_dang_nhap' => 'audit.memory', 'trang_thai' => 'HOAT_DONG', 'ten_vai_tro' => $this->role,
            'ma_nhan_vien' => 1, 'ma_cong_ty' => 1, 'ma_co_so' => $this->role === 'QUAN_LY_CONG_TY' ? null : 1, 'cong_ty_cua_co_so' => 1,
            'trang_thai_nhan_vien' => 'DANG_LAM', 'trang_thai_cong_ty' => 'HOAT_DONG', 'trang_thai_co_so' => 'HOAT_DONG',
            'ma_khach_hang' => 10, 'trang_thai_khach' => 'HOAT_DONG'];
    }
};
$app = new Application($pdo);
(new ReflectionProperty(Application::class, 'auth'))->setValue($app, new AuthService($actor));
$router = new Router(); require dirname(__DIR__) . '/routes/web.php';
$results = [];
$check = static function (bool $ok, string $label) use (&$results): void {
    $results[] = ['pass' => $ok, 'label' => $label];
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
};
$request = static function (string $role, string $method, string $route, array $query = [], array $post = []) use ($actor, $router): array {
    $actor->role = $role;
    $_SERVER['REQUEST_METHOD'] = $method;
    $_GET = ['route' => $route] + $query;
    $_POST = $post;
    http_response_code(200);
    ob_start();
    try { $router->dispatch($method, $route); return [http_response_code(), ob_get_contents()]; }
    catch (HttpException $e) { return [$e->status, ob_get_contents()]; }
    finally { ob_end_clean(); }
};

try {
    [$status, $html] = $request('QUAN_LY_CONG_TY', 'GET', '/admin/bookings', ['facility_id' => '3']);
    file_put_contents($output . '/booking-filter-probe.json', json_encode(['status' => $status, 'foreign_court_leaked' => str_contains($html, 'FOREIGN_COMPANY_COURT_SECRET'), 'mode' => 'SQLite :memory: only'], JSON_PRETTY_PRINT));
    $check($status === 403 && !str_contains($html, 'FOREIGN_COMPANY_COURT_SECRET'), 'company cannot load another company court dropdown via booking filter');
    $reject = static function (callable $call, string $label) use ($check): void {
        try { $call(); $check(false, $label); }
        catch (HttpException $e) { $check(in_array($e->status, [403, 404], true), $label); }
    };
    $roles = ['QUAN_TRI_TAP_DOAN' => '/admin', 'QUAN_LY_CONG_TY' => '/company', 'QUAN_LY_CO_SO' => '/facility', 'NHAN_VIEN' => '/staff'];
    $changes = (int) $pdo->query('SELECT total_changes()')->fetchColumn();
    foreach ($roles as $role => $root) {
        foreach (['co-so', 'san', 'bang-gia'] as $module) {
            [$status, $html] = $request($role, 'GET', "$root/$module");
            $check($status === 200, "$role: own $module list works");
            if ($role !== 'QUAN_TRI_TAP_DOAN') $check(!str_contains($html, 'FOREIGN_COMPANY_COURT_SECRET') && !str_contains($html, 'Cơ sở PR6 3'), "$role: foreign company absent from $module rows and filter options");
            if (in_array($role, ['QUAN_LY_CO_SO', 'NHAN_VIEN'], true)) $check(!str_contains($html, 'SIBLING_FACILITY_COURT_SECRET') && !str_contains($html, 'Cơ sở PR6 2'), "$role: sibling facility absent from $module");
            $dom = new DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">' . $html); $xpath = new DOMXPath($dom);
            if ($role === 'NHAN_VIEN') {
                $check($xpath->query('//div[contains(@class,"admin-workspace")]//a[contains(@class,"list-action--edit") or contains(@class,"list-action--delete") or contains(@class,"list-action--stop")]')->length === 0, "staff $module: no write controls");
                $check($xpath->query('//header[contains(@class,"module-heading")]//a[contains(@href,"create")]')->length === 0, "staff $module: no create button");
            }
            $check($xpath->query('//aside//a[@aria-current="page"]')->length === 1, "$role $module: exactly one active sidebar item");
            [$status, $detail] = $request($role, 'GET', "$root/$module/show", ['id' => '1']);
            $check($status === 200 && !str_contains($detail, 'RAM_SECRET_NEVER_RENDER'), "$role: owned $module detail works without secrets");
            if ($role === 'NHAN_VIEN') {
                $dom = new DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">' . $detail); $xpath = new DOMXPath($dom);
                $check($xpath->query('//div[contains(@class,"admin-workspace")]//a[contains(@href,"/create") or contains(@href,"/edit") or contains(@href,"/delete")]')->length === 0, "staff $module detail: no create/edit/delete links");
            }
            if ($role !== 'QUAN_TRI_TAP_DOAN') {
                foreach (['show', 'edit'] as $action) {
                    [$status] = $request($role, 'GET', "$root/$module/$action", ['id' => '3']);
                    $check(in_array($status, [403, 404], true), "$role: direct foreign $module/$action URL denied");
                }
                foreach (['GET', 'POST'] as $verb) {
                    [$status] = $request($role, $verb, "$root/$module/delete", ['id' => '1'], ['id' => '1', '_token' => Csrf::token()]);
                    $check(in_array($status, [403, 404], true), "$role: $verb hard delete $module denied");
                }
            }
            foreach ($roles as $otherRole => $otherRoot) {
                if ($otherRole === $role) continue;
                [$status] = $request($role, 'GET', "$otherRoot/$module");
                $check($status === 403, "$role: cannot enter $otherRoot/$module");
            }
            if ($role === 'NHAN_VIEN') {
                foreach (['store', 'update', 'status'] as $action) {
                    [$status] = $request($role, 'POST', "$root/$module/$action", [], ['_token' => Csrf::token(), 'id' => '1', 'company_id' => '1', 'facility_id' => '1']);
                    $check(in_array($status, [403, 404], true), "staff forged $module/$action POST denied");
                }
            } else {
                [$status] = $request($role, 'POST', "$root/$module/update", [], ['_token' => 'invalid', 'id' => '1']);
                $check($status === 403, "$role $module: CSRF remains required");
                if ($role !== 'QUAN_TRI_TAP_DOAN') {
                    [$status] = $request($role, 'POST', "$root/$module/update", [], ['_token' => Csrf::token(), 'id' => '3', 'company_id' => '1', 'facility_id' => '1']);
                    $check(in_array($status, [403, 404], true), "$role: valid-CSRF foreign $module update denied");
                }
            }
        }
        foreach ($roles as $otherRole => $home) {
            [$status] = $request($role, 'GET', $home, ['period' => 'today']);
            $check($status === ($otherRole === $role ? 200 : 403), "$role: Dashboard $home respects role");
        }
        foreach (['/facility/bank', '/admin/bookings', '/admin/invoices'] as $route) {
            [$status] = $request($role, 'GET', $route);
            $check($status === 200, "$role: existing scoped $route works");
        }
        if ($role !== 'QUAN_TRI_TAP_DOAN') {
            [$status] = $request($role, 'GET', $root . '/revenue', ['period' => 'today', 'company_id' => '2', 'facility_id' => '3']);
            $check($status === 403, "$role: forged revenue organization rejected");
        }
    }
    [$status] = $request('NHAN_VIEN', 'POST', '/facility/bank/store', [], ['_token' => Csrf::token(), 'facility_id' => '1']);
    $check($status === 403, 'staff cannot add bank account even with valid CSRF');
    foreach (['/admin/companies/edit', '/admin/contracts/edit', '/admin/employees/edit'] as $route) {
        [$status] = $request('QUAN_LY_CONG_TY', 'GET', $route, ['id' => '2']);
        $check($status === 403, 'company manager cannot enter global management ' . $route);
    }
    foreach (['/admin', '/company', '/facility', '/staff', '/admin/co-so', '/company/co-so', '/staff/bang-gia', '/facility/bank', '/admin/invoices'] as $route) {
        [$status] = $request('KHACH_HANG', 'GET', $route);
        $check($status === 403, 'customer cannot enter ' . $route);
    }
    $check((int) $pdo->query('SELECT total_changes()')->fetchColumn() === $changes, 'GET/security probes performed no database mutations');
    $scopes = [];
    foreach ($roles as $role => $root) { $actor->role = $role; $scopes[$role] = App\Services\FacilityScope::forUser($actor->identity(1)); }
    $companyScope = $scopes['QUAN_LY_CONG_TY']; $facilityScope = $scopes['QUAN_LY_CO_SO']; $staffScope = $scopes['NHAN_VIEN'];
    $facilityData = ['ma_cong_ty' => '1', 'ten_co_so' => 'Allowed facility update', 'dia_chi' => 'Address only in RAM',
        'so_dien_thoai' => '', 'email' => '', 'gio_mo_cua' => '06:00', 'gio_dong_cua' => '23:00', 'trang_thai' => 'HOAT_DONG'];
    $courtData = ['ma_co_so' => '1', 'ma_loai_san' => '1', 'ten_san' => 'Allowed court update', 'trang_thai' => 'HOAT_DONG'];
    $priceData = ['ma_co_so' => '1', 'ma_loai_san' => '1', 'ten_bang_gia' => 'Allowed price update', 'loai_ngay' => 'NGAY_THUONG',
        'gio_bat_dau' => '08:00', 'gio_ket_thuc' => '09:00', 'don_gia' => '12000', 'ngay_bat_dau' => '2025-01-01', 'ngay_ket_thuc' => '', 'trang_thai' => 'DANG_AP_DUNG'];
    $beforeDenied = (int) $pdo->query('SELECT total_changes()')->fetchColumn();
    $reject(fn() => (new App\Services\CoSoService($pdo, $companyScope))->save($facilityData, 3), 'company cannot POST an owned company ID while editing a foreign facility');
    $reject(fn() => (new App\Services\CoSoService($pdo, $companyScope))->save(array_replace($facilityData, ['ma_cong_ty' => '2']), 1), 'company cannot change hidden owner ID');
    $reject(fn() => (new App\Services\CoSoService($pdo, $facilityScope))->save($facilityData), 'facility manager cannot create a new facility');
    $reject(fn() => (new App\Services\SanService($pdo, $facilityScope))->save(array_replace($courtData, ['ma_co_so' => '2']), 1), 'facility manager cannot move a court into a sibling facility');
    $reject(fn() => (new App\Services\SanService($pdo, $companyScope))->save($courtData, 3), 'company cannot update a foreign court with an owned hidden facility ID');
    $reject(fn() => (new App\Services\BangGiaService($pdo, $facilityScope))->save($priceData, 2), 'facility manager cannot update sibling facility price');
    $reject(fn() => (new App\Services\BangGiaService($pdo, $companyScope))->save(array_replace($priceData, ['ma_co_so' => '3']), 1), 'company cannot move price into another company');
    foreach ([App\Services\CoSoService::class, App\Services\SanService::class, App\Services\BangGiaService::class] as $service) {
        $reject(fn() => (new $service($pdo, $staffScope))->save([]), 'staff cannot bypass routes through ' . $service);
    }
    $reject(fn() => (new App\Services\BangGiaService($pdo, $staffScope))->changeStatus(1, 'NGUNG'), 'staff cannot stop a price through the service API');
    foreach ([App\Services\CoSoService::class, App\Services\SanService::class] as $service) $reject(fn() => (new $service($pdo, $companyScope))->delete(1), 'company cannot hard delete through ' . $service);
    $check((int) $pdo->query('SELECT total_changes()')->fetchColumn() === $beforeDenied && !$pdo->inTransaction(), 'all denied service writes preserve data and close transactions');
    (new App\Services\CoSoService($pdo, $facilityScope))->save($facilityData, 1);
    $check($pdo->query('SELECT ten_co_so FROM co_so WHERE ma_co_so=1')->fetchColumn() === $facilityData['ten_co_so'], 'facility manager can update own operational metadata');
    $newFacility = (new App\Services\CoSoService($pdo, $companyScope))->save(array_replace($facilityData, ['ten_co_so' => 'Company created in RAM']));
    $check((int) $pdo->query('SELECT ma_cong_ty FROM co_so WHERE ma_co_so=' . $newFacility)->fetchColumn() === 1, 'company creates facility only under its own owner');
    (new App\Services\SanService($pdo, $facilityScope))->save($courtData, 1);
    $newCourt = (new App\Services\SanService($pdo, $companyScope))->save(array_replace($courtData, ['ten_san' => 'Company created court', 'ma_co_so' => '2']));
    $check((int) $pdo->query('SELECT ma_co_so FROM san WHERE ma_san=' . $newCourt)->fetchColumn() === 2, 'company can create court in a second owned facility');
    (new App\Services\BangGiaService($pdo, $facilityScope))->save($priceData, 1);
    $newPrice = (new App\Services\BangGiaService($pdo, $companyScope))->save(array_replace($priceData, ['ma_co_so' => (string) $newFacility]));
    $check((int) $pdo->query('SELECT ma_co_so FROM bang_gia WHERE ma_bang_gia=' . $newPrice)->fetchColumn() === $newFacility, 'company may configure price before the owned facility has courts');
    (new App\Services\BangGiaService($pdo, $facilityScope))->changeStatus(1, 'NGUNG');
    (new App\Services\BangGiaService($pdo, $facilityScope))->changeStatus(1, 'DANG_AP_DUNG');
    $check($pdo->query('SELECT trang_thai FROM bang_gia WHERE ma_bang_gia=1')->fetchColumn() === 'DANG_AP_DUNG', 'owned price stop/reactivate still works with overlap validation');
    $employees = new App\Services\EmployeeService($pdo);
    foreach ([10, 11, 12, 13, 14, 15] as $id) $check((int) $employees->getEmployee('QUAN_TRI_TAP_DOAN', null, $id)['ma_nhan_vien'] === $id, 'Admin may read personnel across all roles and companies: ' . $id);
    $reject(fn() => $employees->getEmployee('QUAN_LY_CONG_TY', 1, 14), 'company cannot read foreign personnel directly');
    $employeeData = ['ho_ten' => 'Admin updated staff', 'ma_cong_ty' => '2', 'ma_co_so' => '3', 'trang_thai' => 'DANG_LAM', 'role' => 'QUAN_TRI_TAP_DOAN'];
    $employees->updateEmployee('QUAN_TRI_TAP_DOAN', null, 14, $employeeData);
    $check($pdo->query('SELECT ho_ten FROM nhan_vien WHERE ma_nhan_vien=14')->fetchColumn() === 'Admin updated staff'
        && (int) $pdo->query('SELECT ma_vai_tro FROM tai_khoan WHERE ma_tai_khoan=14')->fetchColumn() === 4, 'Admin can edit foreign-company staff without changing account role');
    foreach ([10 => '', 11 => '1', 15 => ''] as $id => $facilityId) {
        $employees->updateEmployee('QUAN_TRI_TAP_DOAN', null, $id, array_replace($employeeData, ['ma_cong_ty' => '1', 'ma_co_so' => $facilityId]));
        $employees->changeStatus('QUAN_TRI_TAP_DOAN', null, $id, 'DA_NGHI');
        $check($pdo->query('SELECT trang_thai FROM nhan_vien WHERE ma_nhan_vien=' . $id)->fetchColumn() === 'DA_NGHI', 'Admin edits and changes status of manager/unlinked personnel: ' . $id);
    }
    $reject(fn() => $employees->updateEmployee('QUAN_LY_CONG_TY', 1, 14, $employeeData), 'company cannot POST changes to foreign personnel');
    [$status, $html] = $request('QUAN_LY_CONG_TY', 'POST', '/company/employees', [], ['_token' => Csrf::token(),
        'ho_ten' => 'Escalation attempt', 'ma_co_so' => '1', 'cap_tai_khoan' => '1', 'role' => 'QUAN_TRI_TAP_DOAN',
        'username' => 'audit.attack', 'password' => bin2hex(random_bytes(5)) . 'Aa1!']);
    $check($status === 422 && !$pdo->inTransaction(), 'real company account creation rejects attempted Admin role escalation');
    $check((int) $pdo->query("SELECT COUNT(*) FROM tai_khoan WHERE ten_dang_nhap='audit.attack'")->fetchColumn() === 0, 'role escalation creates no account');
    $bookingModel = new App\Models\AdminBooking($pdo);
    $check($bookingModel->courtsByFacility(3, $companyScope) === [], 'court dropdown SQL itself excludes foreign company');
    $check($bookingModel->courtsByFacility(2, $facilityScope) === [], 'court dropdown SQL itself excludes sibling facility');
    $check(count($bookingModel->courtsByFacility(3, $scopes['QUAN_TRI_TAP_DOAN'])) === 1, 'Admin may load a court dropdown in any company');
    foreach ($scopes as $role => $scope) {
        foreach (['co-so' => App\Models\CoSo::class, 'san' => App\Models\San::class, 'bang-gia' => App\Models\BangGia::class] as $module => $modelClass) {
            $filters = $module === 'bang-gia' ? App\Services\BangGiaService::filters([]) : App\Services\AdminListService::filters([], $module === 'co-so' ? ['ma_cong_ty'] : ['ma_co_so', 'ma_loai_san'], ['trang_thai' => ['HOAT_DONG', 'TAM_NGUNG', 'BAO_TRI']]);
            $model = new $modelClass($pdo);
            $all = $model->page($filters, $scope);
            $clamped = $model->page(array_replace($filters, ['page' => 999]), $scope);
            $check($all['total'] === count($all['items']) && $clamped['page'] === 1, "$role $module: count and page clamp use the scoped result");
            $empty = $model->page(array_replace($filters, ['q' => "' OR 1=1 --"]), $scope);
            $check($empty['total'] === 0 && $empty['items'] === [], "$role $module: search stays parameterized and scoped");
        }
    }
} finally {
    file_put_contents($output . '/authorization-results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    session_write_close();
}
echo 'PASS: ' . count($results) . " authorization checks; disposable SQLite RAM only.\n";
