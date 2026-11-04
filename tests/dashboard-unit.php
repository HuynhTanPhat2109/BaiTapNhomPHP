<?php
define('STRYKE_DASHBOARD_UNIT', true);
$_SERVER['SCRIPT_NAME'] = '/index.php'; $_GET = $_POST = [];
require __DIR__ . '/fixtures/dashboard.php';

use App\Services\DashboardService;
use Core\HttpException;

$checks = 0;
function dashboardCheck(bool $ok, string $label): void
{
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
function dashboardReject(callable $call, int $status, string $label): void
{
    try { $call(); dashboardCheck(false, $label); }
    catch (HttpException $e) { dashboardCheck($e->status === $status, $label); }
}
$service = new DashboardService($pdo);
$admin = dashboardIdentity('QUAN_TRI_TAP_DOAN');
$company = dashboardIdentity('QUAN_LY_CONG_TY');
$facility = dashboardIdentity('QUAN_LY_CO_SO');
$employee = dashboardIdentity('NHAN_VIEN');
$query = ['period' => 'today'];
$report = $service->data($admin, $query);
dashboardCheck((string) $pdo->query('SELECT ROUND(SUM(thanh_tien), 2) FROM chi_tiet_dat_san WHERE ma_dat_san = 14')->fetchColumn() === '100.25', 'fractional fixture snapshot lines reconcile exactly to booking total');
dashboardCheck((string) $report['totals']['total'] === '830100.75', 'only valid successful payments counted');
dashboardCheck((string) $report['totals']['courts'] === '750100.25', 'court snapshot totals');
dashboardCheck((string) $report['totals']['services'] === '80000.5', 'service snapshot totals including fractional dong');
dashboardCheck((int) $report['totals']['bookings'] === 5, 'pending/cancelled/expired/reconciliation/invalid payments excluded');
dashboardCheck(array_sum($report['states']) === 13, 'booking counts use creation date, not paid date');
dashboardCheck($report['states']['CHO_THANH_TOAN'] === 1 && $report['states']['HET_HAN'] === 1, 'pending and expired booking states remain visible');
dashboardCheck(count($report['groups']) === 3 && $report['assets']['companies'] === 3, 'all companies including zero-data company');
dashboardCheck(count($report['companyOptions']) === 3 && count($report['facilityOptions']) === 4, 'Admin sees company/facility filter options globally');
$a = $service->data($company, $query);
$b = $service->data(dashboardIdentity('QUAN_LY_CONG_TY', ['ma_cong_ty' => 2, 'ma_co_so' => null]), $query);
$a1 = $service->data($facility, $query);
$a2 = $service->data(dashboardIdentity('QUAN_LY_CO_SO', ['ma_co_so' => 12]), $query);
dashboardCheck((string) $a['totals']['total'] === '520100.75', 'company A excludes company B');
dashboardCheck((string) $b['totals']['total'] === '310000', 'company B own revenue');
dashboardCheck((float) $a['totals']['total'] + (float) $b['totals']['total'] === (float) $report['totals']['total'], 'group total equals sum of companies');
dashboardCheck((float) $a1['totals']['total'] + (float) $a2['totals']['total'] === (float) $a['totals']['total'], 'company total equals sum of facilities');
dashboardCheck((float) $report['totals']['courts'] + (float) $report['totals']['services'] === (float) $report['totals']['total'], 'snapshot split reconciles to actual payment total');
dashboardCheck((string) $a1['totals']['total'] === '270100.75', 'facility manager does not see sibling facility revenue');
$staff = $service->data($employee, $query);
dashboardCheck($staff['totals'] === $a1['totals'], 'staff revenue matches assigned facility');
dashboardCheck(count($staff['facilityOptions']) === 1 && (int) $staff['facilityOptions'][0]['ma_co_so'] === 11, 'staff receives no sibling names or filter data');
dashboardCheck(count($a['companyOptions']) === 1 && count($a['facilityOptions']) === 3, 'company options remain in company scope');
dashboardCheck($a1['assets']['courts'] === 1 && $a['assets']['courts'] === 2 && $report['assets']['courts'] === 3, 'active courts respect scope and state');
dashboardCheck(str_contains($a1['popular_courts'][0]['name'], 'snapshot') && (int) $a1['popular_courts'][0]['quantity'] === 3, 'court ranking counts distinct paid bookings despite multiple price lines');
dashboardCheck(str_contains($a1['popular_services'][0]['name'], 'snapshot'), 'popular services show historical snapshot name');
dashboardCheck(!str_contains(json_encode($a), 'Bình Minh'), 'company data includes no foreign names');
foreach ([[$company, ['company_id' => 2]], [$company, ['facility_id' => 21]], [$facility, ['facility_id' => 12]], [$employee, ['facility_id' => 12]], [$employee, ['company_id' => 2]]] as [$user, $override]) {
    dashboardReject(fn() => $service->data($user, $query + $override), 403, 'forged Dashboard scope is rejected');
}
dashboardReject(fn() => $service->data($admin, $query + ['company_id' => 1, 'facility_id' => 21]), 403, 'Admin company/facility mismatch rejected');
dashboardReject(fn() => $service->data(dashboardIdentity('KHACH_HANG'), $query), 403, 'customer rejected by Dashboard service');
dashboardReject(fn() => $service->data(dashboardIdentity('NHAN_VIEN', ['trang_thai_nhan_vien' => 'NGHI_VIEC']), $query), 403, 'inactive staff rejected');
foreach ([['facility_id' => ['11']], ['company_id' => '1 OR 1=1'], ['company_id' => '-1'], ['period' => []], ['group_by' => 'invalid'],
    ['period' => 'custom', 'from' => '2026-02-30', 'to' => '2026-03-01'], ['period' => 'custom', 'from' => '2026-12-01', 'to' => '2026-01-01'],
    ['period' => 'custom', 'from' => '2000-01-01', 'to' => '2026-01-01']] as $invalid) {
    dashboardReject(fn() => $service->data($admin, $invalid), 422, 'malformed filter rejected');
}
$clock = new DateTimeImmutable('2026-11-04 12:00:00', new DateTimeZone('Asia/Ho_Chi_Minh'));
foreach (['today' => ['2026-11-04', '2026-11-04'], 'week' => ['2026-10-29', '2026-11-04'],
    'month' => ['2026-11-01', '2026-11-04'], 'last_month' => ['2026-10-01', '2026-10-31']] as $period => [$from, $to]) {
    $filter = DashboardService::filters(['period' => $period], $clock);
    dashboardCheck($filter['from'] === $from && $filter['to'] === $to, 'preset date boundaries ' . $period);
}
$utc = new DateTimeImmutable('2026-11-03 18:00:00', new DateTimeZone('UTC'));
dashboardCheck(DashboardService::filters(['period' => 'today'], $utc)['from'] === '2026-11-04', 'timezone converts UTC to Vietnamese calendar day');
dashboardCheck(DashboardService::filters(['period' => 'custom', 'from' => '2024-01-01', 'to' => '2024-12-31'])['group_by'] === 'month', 'long periods automatically group by month');
dashboardCheck(DashboardService::filters(['period' => 'today'])['group_mode'] === 'auto', 'auto grouping remains selectable when dates change');
$custom = $service->data($admin, ['period' => 'custom', 'from' => $date, 'to' => $date, 'group_by' => 'month']);
dashboardCheck($custom['totals'] === $report['totals'] && count($custom['timeline']) === 1, 'custom date and monthly view use identical financial definition');
$lastMonth = $service->data($admin, ['period' => 'last_month']);
dashboardCheck((string) $lastMonth['totals']['total'] === '500000', 'last month includes 23:59:59 on last day');
dashboardCheck($report['timeline'][0]['value'] === '830100.75', 'daily timeline includes midnight, excludes next midnight');
$empty = $service->data($company, $query + ['facility_id' => 13]);
dashboardCheck((float) $empty['totals']['total'] === 0.0 && count($empty['recent']) === 0, 'empty facility returns true zero and empty lists');
dashboardCheck(DashboardService::money('123456789012345.67') === '123.456.789.012.345,67 đ', 'large aggregates format without float or per-row money limit');

// Same stored payment updated twice by retry: the read model never adds a second event.
$pdo->exec("UPDATE thanh_toan SET trang_thai = 'THANH_CONG' WHERE ma_thanh_toan = 1");
$pdo->exec("UPDATE thanh_toan SET trang_thai = 'THANH_CONG' WHERE ma_thanh_toan = 1");
dashboardCheck($service->data($admin, $query)['totals'] === $report['totals'], 'repeated success update does not double revenue');
foreach (['ma_dat_san' => 1, 'ma_giao_dich' => 'MEMORY-TRANSACTION-1'] as $key => $duplicate) {
    try {
        dashboardInsert($pdo, 'thanh_toan', ['ma_thanh_toan' => 99, 'ma_dat_san' => 99, $key => $duplicate]);
        dashboardCheck(false, 'duplicate payment should violate schema uniqueness');
    } catch (PDOException $e) { dashboardCheck($e->getCode() === '23000', 'schema uniqueness prevents duplicate booking/transaction'); }
}
$pdo->exec('PRAGMA query_only=ON');
$pdo->exec('PRAGMA query_only=OFF');
$pdo->beginTransaction();
$pdo->prepare("UPDATE thanh_toan SET so_tien_thuc_nhan='3000', ngay_thanh_toan=? WHERE ma_dat_san=5")->execute([$date . ' 12:00:00']);
$cancelledCash = $service->data($admin, $query, null, true);
dashboardCheck((string) array_column($cancelledCash['reviewFunds'], null, 'status')['HUY']['total'] === '3000', 'recorded money on cancelled payment stays in review amounts, not revenue');
dashboardCheck($cancelledCash['totals'] === $report['totals'], 'cancelled recorded money does not inflate settled revenue');
$pdo->rollBack();
$pdo->exec('PRAGMA query_only=ON');
$revenue = $service->data($admin, $query, null, true);
dashboardCheck($revenue['totals'] === $report['totals'], 'Revenue page and Dashboard share exactly one revenue definition');
dashboardCheck(count($revenue['transactions']) === (int) $report['totals']['bookings'], 'paid transaction table includes each valid booking once');
dashboardCheck(count($revenue['facilityGroups']) === 4, 'Admin has one report row per actual facility');
dashboardCheck(count($revenue['reviewFunds']) === 2, 'reconciliation and paid cancelled amounts are separate from valid revenue');
foreach (['QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO', 'NHAN_VIEN'] as $role) {
    $scoped = $service->data(dashboardIdentity($role), $query, null, true);
    dashboardCheck(!in_array('MEMORY-TRANSACTION-3', array_column($scoped['transactions'], 'ma_giao_dich'), true), "$role report SQL excludes foreign transactions");
}
foreach (['QUAN_TRI_TAP_DOAN' => '/admin', 'QUAN_LY_CONG_TY' => '/company', 'QUAN_LY_CO_SO' => '/facility', 'NHAN_VIEN' => '/staff', 'KHACH_HANG' => '/'] as $role => $home) {
    $app = dashboardFixtureApp($pdo, dashboardIdentity($role));
    $router = (static function ($app) {
        $router = new Core\Router(); require dirname(__DIR__) . '/routes/web.php'; return $router;
    })($app);
    foreach (['/admin', '/company', '/facility', '/staff'] as $route) {
        $_GET = ['route' => $route, 'period' => 'today'];
        if ($route !== $home) { dashboardReject(fn() => $router->dispatch('GET', $route), 403, "$role cannot access $route"); continue; }
        ob_start(); $router->dispatch('GET', $route); $html = ob_get_clean();
        dashboardCheck(str_contains($html, 'data-dashboard'), "$role real route renders Dashboard");
        dashboardCheck(!str_contains($html, 'data-dashboard-filters') && str_contains($html, 'Tháng này'), "$role Dashboard has default month and no report filter card");
        $dom = new DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8" ?>' . $html); $xpath = new DOMXPath($dom);
        dashboardCheck($xpath->query('//aside//a[@aria-current="page"]')->length === 1, "$role exactly one active Dashboard menu");
        dashboardCheck($xpath->query('//section[@data-dashboard]//form[@method="post"]')->length === 0, "$role Dashboard makes no write requests");
        if (in_array($role, ['QUAN_LY_CO_SO', 'NHAN_VIEN'], true)) {
            dashboardCheck($xpath->query('//select[@name="company_id" or @name="facility_id"]')->length === 0, "$role no organization filters");
            dashboardCheck(!str_contains($html, 'Phía Đông') && !str_contains($html, 'Bình Minh'), "$role rendered HTML contains no sibling/foreign data");
        }
    }
}
$app = dashboardFixtureApp($pdo, dashboardIdentity('QUAN_LY_CONG_TY'));
$router = (static function ($app) { $router = new Core\Router(); require dirname(__DIR__) . '/routes/web.php'; return $router; })($app);
$_GET = ['route' => '/company/profile'];
ob_start(); $router->dispatch('GET', '/company/profile'); $profile = ob_get_clean();
dashboardCheck(str_contains($profile, 'Thông tin công ty') && str_contains($profile, 'Hợp đồng nhượng quyền'), 'original company information/contracts remain accessible');
$pdo->exec('PRAGMA query_only=OFF');
foreach ($definitions as $definition) $pdo->exec('DELETE FROM ' . $definition[1]); // RAM fixture only
$pdo->exec('PRAGMA query_only=ON');
$nothing = $service->data($admin, $query);
dashboardCheck((float) $nothing['totals']['total'] === 0.0 && $nothing['assets']['companies'] === 0 && $nothing['assets']['facilities'] === 0, 'fully empty database returns zero without invented tenants');
dashboardCheck($nothing['groups'] === [] && $nothing['recent'] === [] && array_sum($nothing['states']) === 0, 'fully empty Dashboard has empty reports');
echo "PASS: $checks Dashboard revenue, scope, date, schema uniqueness and real-route checks; SQLite RAM only.\n";
