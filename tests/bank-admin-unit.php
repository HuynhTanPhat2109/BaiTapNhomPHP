<?php
// Exercise real bank routes, middleware, controller scope and views with SQLite RAM only.
define('STRYKE_BANK_ADMIN_UNIT', true);
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_GET = $_POST = [];
require __DIR__ . '/fixtures/bank-admin.php';
$checks = 0;
function bankCheck(bool $ok, string $label): void
{
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
function bankRequest(string $role, string $route, array $query = [], ?array $post = null, array $identity = []): array
{
    global $app, $pdo;
    if (!Core\Session::accountId()) Core\Session::authenticate(1);
    $app = bankFixtureApp($pdo, ['ten_vai_tro' => $role] + $identity);
    $_GET = ['route' => $route] + $query;
    $_POST = $post ?? [];
    ob_start();
    try {
        bankFixtureRouter($app)->dispatch($post === null ? 'GET' : 'POST', $route);
        return [200, ob_get_clean()];
    } catch (Core\HttpException $e) {
        ob_end_clean();
        return [$e->status, $e->getMessage()];
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
}
$snapshot = fn() => $pdo->query('SELECT * FROM tai_khoan_ngan_hang_co_so ORDER BY ma_tk_ngan_hang')->fetchAll();
$initial = $snapshot();
$managers = ['QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO'];
[$status, $html] = bankRequest('QUAN_TRI_TAP_DOAN', '/facility/bank');
bankCheck($status === 200 && str_contains($html, 'FOREIGNACCOUNT') && str_contains($html, 'TESTACCOUNT003'), 'Admin monitors every company and facility');
bankCheck(!preg_match('/action="[^"\n]*bank[^"\n]*(store|update|set-default|toggle-status)/', $html) && !str_contains($html, 'bank%2Fcreate'), 'Admin monitor contains no write controls');
[$status, $html] = bankRequest('QUAN_TRI_TAP_DOAN', '/facility/bank', ['company_id' => '1', 'status' => 'NGUNG', 'q' => 'TESTACCOUNT']);
bankCheck($status === 200 && str_contains($html, 'TESTACCOUNT002') && !str_contains($html, 'FOREIGNACCOUNT') && !str_contains($html, 'TESTACCOUNT001'), 'Admin combined bank filters');
foreach ([...$managers, 'NHAN_VIEN'] as $role) {
    [$status, $html] = bankRequest($role, '/facility/bank', ['facility_id' => 1]);
    bankCheck($status === 200 && str_contains($html, 'TESTACCOUNT001'), "$role reads own facility");
    bankCheck(!str_contains($html, 'FOREIGNACCOUNT') && !str_contains($html, 'TESTACCOUNT003'), "$role list contains only selected facility");
    bankCheck(str_contains($html, 'aria-current="page"') && str_contains($html, 'aria-label="Tài khoản ngân hàng"'), "$role has bank sidebar entry");
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    $xpath = new DOMXPath($dom);
    bankCheck($xpath->query('//table/colgroup/col')->length === ($role === 'NHAN_VIEN' ? 5 : 6), "$role has no unused columns");
    bankCheck($xpath->query('//table//form')->length === ($role === 'NHAN_VIEN' ? 0 : 2), "$role sees permitted status forms without setting inactive account as default");
    bankCheck($xpath->query('//table//details')->length === 0, "$role uses direct actions");
    if ($role === 'NHAN_VIEN') {
        bankCheck(str_contains($html, 'Nhân viên chỉ có quyền xem thông tin'), 'employee read-only explanation');
        bankCheck(!str_contains($html, 'Thêm tài khoản') && !str_contains($html, 'data-label="Thao tác"'), 'employee has no add/actions controls');
    } else {
        foreach (['create', 'edit'] as $action) {
            [$status, $html] = bankRequest($role, '/facility/bank/' . $action, ['facility_id' => 1, 'id' => 2]);
            bankCheck($status === 200, "$role can open $action");
            foreach (['bank_code', 'so_tai_khoan', 'ten_chu_tai_khoan', 'facility_id', '_token'] as $name) {
                bankCheck(str_contains($html, 'name="' . $name . '"'), "$role $action preserves $name");
            }
            bankCheck((str_contains($html, 'name="trang_thai"') && str_contains($html, 'name="la_mac_dinh"')) === ($action === 'create'), "$role $action: only creation chooses initial state/default");
            bankCheck(!str_contains($html, 'Kích hoạt sử dụng'), "$role $action: no ambiguous activation checkbox");
        }
    }
}
foreach (['create', 'edit'] as $action) {
    bankCheck(bankRequest('NHAN_VIEN', '/facility/bank/' . $action, ['facility_id' => 1, 'id' => 1])[0] === 403, "employee forbidden GET $action");
    bankCheck(bankRequest('QUAN_TRI_TAP_DOAN', '/facility/bank/' . $action, ['facility_id' => 1, 'id' => 1])[0] === 403, "Admin forbidden GET $action");
}
foreach (['QUAN_TRI_TAP_DOAN', 'NHAN_VIEN', 'KHACH_HANG'] as $role) {
    foreach (['store', 'update', 'set-default', 'toggle-status'] as $action) {
        bankCheck(bankRequest($role, '/facility/bank/' . $action, [], ['_token' => Core\Csrf::token(), 'facility_id' => 1, 'id' => 1])[0] === 403, "$role forbidden POST $action with valid CSRF");
        bankCheck($snapshot() === $initial, "$role $action did not write");
    }
}
bankCheck(bankRequest('KHACH_HANG', '/facility/bank', ['facility_id' => 1])[0] === 403, 'customer list forbidden');
foreach (['QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO', 'NHAN_VIEN'] as $role) {
    bankCheck(bankRequest($role, '/facility/bank', ['facility_id' => 3])[0] === 403, "$role rejects cross-scope list");
}
foreach (['QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO'] as $role) {
    bankCheck(bankRequest($role, '/facility/bank/edit', ['id' => 4])[0] === 403, "$role rejects foreign edit");
    foreach (['update', 'set-default', 'toggle-status'] as $action) {
        bankCheck(bankRequest($role, '/facility/bank/' . $action, [], ['_token' => Core\Csrf::token(), 'facility_id' => 1, 'id' => 4])[0] === 403, "$role rejects foreign $action despite forged facility_id");
    }
    bankCheck(bankRequest($role, '/facility/bank/store', [], ['facility_id' => 1])[0] === 403, "$role missing CSRF rejected");
    bankCheck(bankRequest($role, '/facility/bank/update', [], ['id' => 1, '_token' => 'bad'])[0] === 403, "$role wrong CSRF rejected");
}
bankCheck(bankRequest('QUAN_LY_CO_SO', '/facility/bank/edit', ['id' => 3])[0] === 403, 'facility manager cannot edit another facility in same company');
[$status, $html] = bankRequest('QUAN_LY_CONG_TY', '/facility/bank', [], null, ['ma_co_so' => null]);
bankCheck($status === 200 && !str_contains($html, '<option value="3"'), 'company picker works without assigned facility and excludes other company');
bankCheck(bankRequest('QUAN_LY_CONG_TY', '/facility/bank', ['facility_id' => 2])[0] === 200, 'company manager can read second owned facility');
[$status, $html] = bankRequest('QUAN_LY_CONG_TY', '/facility/bank/edit', ['id' => 3]);
bankCheck($status === 200 && str_contains($html, 'bank&amp;facility_id=2'), 'bank breadcrumb and cancel keep selected facility');
bankCheck(bankRequest('QUAN_TRI_TAP_DOAN', '/facility/bank', ['facility_id' => 3])[0] === 200, 'existing Admin scope retained');
foreach (['QUAN_LY_CO_SO', 'NHAN_VIEN'] as $role) {
    [$status, $html] = bankRequest($role, '/facility/bank', [], null, ['ma_co_so' => 4]);
    bankCheck($status === 200 && str_contains($html, 'Chưa có tài khoản ngân hàng'), "$role empty state works");
    bankCheck($role !== 'NHAN_VIEN' || !str_contains($html, 'Thêm tài khoản'), 'employee empty state has no add prompt');
}
// The controller also refuses readonly roles if called without router middleware.
foreach (['NHAN_VIEN', 'QUAN_TRI_TAP_DOAN'] as $readonlyRole) {
$app = bankFixtureApp($pdo, ['ten_vai_tro' => $readonlyRole]);
$controller = new App\Controllers\BankController($app);
$_GET = ['id' => 1]; $_POST = ['id' => 1];
foreach (['create', 'edit', 'store', 'update', 'setDefault', 'toggleStatus'] as $method) {
    try {
        $controller->$method();
        bankCheck(false, 'direct readonly controller ' . $readonlyRole . $method);
    } catch (Core\HttpException $e) {
        bankCheck($e->status === 403, 'controller independently rejects ' . $readonlyRole . ' ' . $method);
    }
}
}
bankCheck($snapshot() === $initial, 'permission/UI tests made no bank writes');
$model = new App\Models\TaiKhoanNganHang($pdo);
$rejectState = static function (callable $action, string $label) use ($snapshot): void {
    $before = $snapshot();
    try { $action(); } catch (App\Services\ValidationException $e) {
        bankCheck($snapshot() === $before, $label . ': rejected without changes'); return;
    }
    throw new RuntimeException('Expected bank validation: ' . $label);
};
$pdo->exec('UPDATE tai_khoan_ngan_hang_co_so SET la_mac_dinh=1 WHERE ma_tk_ngan_hang=2');
$rejectState(fn() => $model->toggleStatus(2, 'HOAT_DONG'), 'historical inactive default cannot activate beside another active default');
$pdo->exec('UPDATE tai_khoan_ngan_hang_co_so SET la_mac_dinh=0 WHERE ma_tk_ngan_hang=2');
$rejectState(fn() => $model->setDefault(2, 1), 'inactive account cannot become default or reactivate');
$rejectState(fn() => $model->setDefault(4, 1), 'default target must belong to supplied facility');
$rejectState(fn() => $model->toggleStatus(1, 'NGUNG'), 'current default cannot be stopped without an explicit replacement');
$account = $model->find(2);
$model->update(2, array_replace($account, ['ten_chu_tai_khoan' => 'SUA THONG TIN', 'trang_thai' => 'HOAT_DONG', 'la_mac_dinh' => 1]));
bankCheck($model->find(2)['trang_thai'] === 'NGUNG' && (int) $model->find(2)['la_mac_dinh'] === 0, 'editing ignores forged activation/default fields');
bankCheck((int) $model->getDefaultByFacility(1)['ma_tk_ngan_hang'] === 1, 'checkout still selects the existing active default');
$model->toggleStatus(2, 'HOAT_DONG'); $model->setDefault(2, 1); $model->toggleStatus(1, 'NGUNG');
bankCheck((int) $model->getDefaultByFacility(1)['ma_tk_ngan_hang'] === 2 && $model->find(1)['trang_thai'] === 'NGUNG', 'explicit replacement enables stopping the old default');
$newAccount = ['ma_co_so' => 4, 'ten_ngan_hang' => 'Fixture Bank', 'ma_bin' => '970436', 'so_tai_khoan' => 'MEMORYONLY', 'ten_chu_tai_khoan' => 'FIXTURE', 'la_mac_dinh' => 0, 'trang_thai' => 'NGUNG'];
$id = $model->create($newAccount);
bankCheck($model->find($id)['trang_thai'] === 'NGUNG' && (int) $model->find($id)['la_mac_dinh'] === 0 && $model->getDefaultByFacility(4) === null, 'first inactive account stays inactive and is unavailable at checkout');
$rejectState(fn() => $model->create(array_replace($newAccount, ['la_mac_dinh' => 1])), 'creating inactive default rejected');
bankCheck(!$pdo->inTransaction(), 'bank success/failure closes owned transactions');
echo "PASS: $checks bank Admin scope, routes, CSRF and view checks; SQLite RAM only.\n";
