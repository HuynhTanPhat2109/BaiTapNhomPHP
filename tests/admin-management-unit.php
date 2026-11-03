<?php
// Real read models on SQLite :memory:. No .env, MySQL or database files.
define('STRYKE_ADMIN_MANAGEMENT_UNIT', true);
$_GET = ['fixture' => 'reset', 'count' => '120']; $_POST = [];
require __DIR__ . '/fixtures/admin-navigation.php';

use App\Models\{CongTyNhuongQuyen, HopDongNhuongQuyen, NhanVien};
use App\Services\AdminListService;
use Core\HttpException;

$checks = 0;
function managementCheck(bool $ok, string $label): void
{
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
$company = new CongTyNhuongQuyen($pdo); $contract = new HopDongNhuongQuyen($pdo); $employee = new NhanVien($pdo);
$models = ['companies' => $company, 'contracts' => $contract, 'employees' => $employee];
foreach ($models as $module => $model) {
    $path = '/admin/' . $module;
    $parse = static fn($query) => AdminListService::managementFilters($path, $query);
    foreach ([10, 20, 50] as $size) {
        $ids = [];
        $key = ['companies' => 'ma_cong_ty', 'contracts' => 'ma_hop_dong', 'employees' => 'ma_nhan_vien'][$module];
        for ($number = 1; $number <= (int) ceil(120 / $size); $number++) {
            $result = $model->page($parse(['page' => (string) $number, 'per_page' => (string) $size]));
            managementCheck($result['total'] === 120 && $result['page'] === $number, "$module exact count/page with size $size");
            $ids = array_merge($ids, array_column($result['items'], $key));
        }
        managementCheck(array_map('intval', $ids) === range(120, 1), "$module stable unique order without duplicate/missing rows");
    }
    foreach ([['page' => '-1'], ['page' => '0'], ['page' => []], ['page' => '2147483648'], ['per_page' => '1000000'],
        ['per_page' => []], ['sort' => 'name; DROP TABLE'], ['q' => []], ['q' => str_repeat('a', 151)], ['trang_thai' => 'UNKNOWN']] as $bad) {
        try { $parse($bad); throw new RuntimeException('Expected validation error'); }
        catch (HttpException $e) { managementCheck($e->status === 422, "$module rejects invalid list query"); }
    }
    $result = $model->page($parse(['q' => 'NO_SUCH_RESULT', 'page' => '999']));
    managementCheck($result['total'] === 0 && $result['page'] === 1 && $result['pages'] === 1 && !$result['items'], "$module empty filter clamps to page 1");
    $result = $model->page($parse(['q' => "%' OR 1=1 --"]));
    managementCheck($result['total'] === 0, "$module LIKE metacharacters/SQL stay literal");
    $_GET = ['route' => $path, 'page' => '2', 'per_page' => '20', 'q' => 'UX', 'sort' => $module === 'contracts' ? 'start_asc' : 'name_asc'];
    $action = admin_context_url($path . '/edit');
    parse_str(parse_url($action, PHP_URL_QUERY), $actionQuery);
    $_GET = ['route' => $path . '/edit', 'return_to' => $actionQuery['return_to']];
    parse_str(parse_url(admin_context_url($path), PHP_URL_QUERY), $returnQuery);
    managementCheck($returnQuery['page'] === '2' && $returnQuery['per_page'] === '20' && $returnQuery['sort'] !== 'newest', "$module action/back preserves page/size/sort");
}
$pdo->exec("UPDATE cong_ty_nhuong_quyen SET ten_cong_ty='UX Tied'");
$a = $company->page(AdminListService::managementFilters('/admin/companies', ['sort' => 'name_asc']));
$b = $company->page(AdminListService::managementFilters('/admin/companies', ['sort' => 'name_asc', 'page' => '2']));
managementCheck(array_column($a['items'], 'ma_cong_ty') === range(120, 111) && array_column($b['items'], 'ma_cong_ty') === range(110, 101), 'equal sort labels use unique descending ID tie-breaker');
$byId = $company->page(AdminListService::managementFilters('/admin/companies', ['q' => '17']));
managementCheck(array_column($byId['items'], 'ma_cong_ty') === [117, 17], 'company search finds numeric ID independently of name');
$pdo->exec("UPDATE cong_ty_nhuong_quyen SET trang_thai='NGUNG_HOAT_DONG' WHERE ma_cong_ty<=12");
$filtered = $company->page(AdminListService::managementFilters('/admin/companies', ['trang_thai' => 'NGUNG_HOAT_DONG', 'page' => '2']));
managementCheck($filtered['total'] === 12 && count($filtered['items']) === 2, 'company status uses matching COUNT and list predicates');
foreach ([['den_ngay' => '2021-12-31'], ['tu_ngay' => '2030-12-31', 'den_ngay' => '2030-12-31'], ['tu_ngay' => '2031-01-01']] as $index => $query) {
    $result = $contract->page(AdminListService::managementFilters('/admin/contracts', $query));
    managementCheck($result['total'] === [60, 120, 0][$index], 'contract date-window intersection, inclusive bounds');
}
$result = $contract->page(AdminListService::managementFilters('/admin/contracts', ['q' => 'UX/', 'ma_cong_ty' => '1', 'trang_thai' => 'HIEU_LUC', 'den_ngay' => '2023-01-01', 'page' => '2']));
managementCheck($result['total'] === 60 && count($result['items']) === 10 && !array_filter($result['items'], fn($row) => $row['ma_cong_ty'] !== 1), 'combined contract filters/page/count agree');
foreach ([['tu_ngay' => []], ['den_ngay' => '2026-02-30'], ['tu_ngay' => '2026-11-02', 'den_ngay' => '2020-01-01']] as $bad) {
    try { AdminListService::managementFilters('/admin/contracts', $bad); throw new RuntimeException('Expected date validation'); }
    catch (HttpException $e) { managementCheck($e->status === 422, 'contract invalid date filter rejected'); }
}
$companyFilters = static fn($query) => AdminListService::managementFilters('/company/employees', $query);
$result = $employee->page($companyFilters(['ma_cong_ty' => '2']), 1);
managementCheck($result['total'] === 121 && !array_filter($result['items'], fn($row) => $row['ma_cong_ty'] !== 1 || ($row['ten_vai_tro'] ?? '') === 'QUAN_LY_CONG_TY'), 'company scope excludes foreign employees/managers; client cannot override actor company');
$result = $employee->page($companyFilters(['ma_co_so' => '2', 'vai_tro' => 'QUAN_LY_CO_SO', 'trang_thai' => 'DANG_LAM']), 1);
managementCheck($result['total'] === 60, 'employee facility/role/status combined filter');
foreach (['ux1001@example.test', '0900001001'] as $search) {
    $result = $employee->page($companyFilters(['q' => $search]), 1);
    managementCheck($result['total'] === 1 && $result['items'][0]['ma_nhan_vien'] === 1001, 'employee searches email/phone in authorized company');
}
$result = $employee->page($companyFilters(['q' => 'ux2001@example.test']), 1);
managementCheck($result['total'] === 0, 'OR search cannot escape company scope');
$result = $employee->page($companyFilters(['q' => 'No account']), 1);
managementCheck($result['total'] === 1, 'existing access to own employees without account retained');
$formatMoney = require dirname(__DIR__) . '/app/views/admin/partials/format-money.php';
foreach ([['60000.00', '60.000 đ'], ['150000', '150.000 đ'], ['1200000', '1.200.000 đ'], ['9999999999999.99', '9.999.999.999.999,99 đ'], ['0', '0 đ'], ['0.01', '0,01 đ'], ['', '—'], ['-1', '—'], ['1.001', '—']] as [$raw, $expected]) {
    managementCheck($formatMoney($raw) === $expected, 'shared money display preserves decimal precision');
}
session_write_close();
echo "PASS: $checks management pagination/scope/money checks; SQLite :memory: only.\n";
