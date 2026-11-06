<?php
define('STRYKE_CUSTOMER_TEST', true);
$_GET = $_POST = [];
require __DIR__ . '/fixtures/customer-management.php';

use App\Services\CustomerManagementService;
use Core\HttpException;

$checks = 0;
$check = static function(bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
};
$reject = static function(callable $call, int $status, string $label) use ($check): void {
    try { $call(); throw new RuntimeException('Expected rejection: ' . $label); }
    catch (HttpException $error) { $check($error->status === $status,$label); }
};
$service = new CustomerManagementService($pdo);
$companyA = $identity('QUAN_LY_CONG_TY',1,null);
$companyB = $identity('QUAN_LY_CONG_TY',2,null);
$facility = $identity('QUAN_LY_CO_SO');
$staff = $identity('NHAN_VIEN');
$a = $service->listing($companyA, ['per_page'=>'50']);
$b = $service->listing($companyB, []);
$check($a['total']===18 && $b['total']===2,'company-scoped unique customer totals');
$check(array_column($b['items'],'ma_khach_hang')===[3,1],'company B cannot see A-only customers; latest booking sort');
$check(!in_array(3,array_column($a['items'],'ma_khach_hang')) && !in_array(4,array_column($a['items'],'ma_khach_hang')),'foreign and never-booked customers excluded');
$shared = array_values(array_filter($a['items'],static fn($row)=>(int)$row['ma_khach_hang']===1));
$check(count($shared)===1 && (int)$shared[0]['booking_count']===3 && $shared[0]['last_booking_at']==='2026-11-03 10:00:00','shared customer counted once, scoped count and latest timestamp');
$detailA = $service->detail($companyA,'1',[]);
$detailB = $service->detail($companyB,'1',[]);
$check(array_column($detailA['items'],'ma_dat_san')===[3,2,1] && array_column($detailB['items'],'ma_dat_san')===[4],'shared customer history never crosses company');
$check((int)$detailA['customer']['confirmed_count']===1 && (int)$detailA['customer']['awaiting_count']===1,'statistics count bookings rather than detail lines and exclude foreign bookings');
$check(count($detailA['items'][0]['lines'])===3 && $detailA['items'][0]['lines'][1]['gio_bat_dau']==='10:00:00','multiple courts and noncontinuous time ranges remain exact');
foreach ([$facility,$staff] as $user) {
    $list=$service->listing($user,[]);
    $check(array_column($list['items'],'ma_khach_hang')===[6,1,2],$user['ten_vai_tro'] . ' only assigned facility');
    $check(array_column($service->detail($user,'1',[])['items'],'ma_dat_san')===[2,1],'facility-specific history');
    $reject(fn()=>$service->detail($user,'5',[]),404,'other facility customer denied');
}
$reject(fn()=>$service->detail($companyA,'3',[]),404,'foreign company detail denied');
$reject(fn()=>$service->detail($companyB,'2',[]),404,'reverse foreign company detail denied');
$reject(fn()=>$service->detail($companyA,'4',[]),404,'customer without in-scope booking denied');
foreach (['KHACH_HANG','QUAN_TRI_TAP_DOAN','UNKNOWN'] as $role) {
    $reject(fn()=>$service->listing($identity($role),[]),403,'unsupported list role rejected');
    $reject(fn()=>$service->detail($identity($role),'1',[]),403,'unsupported detail role rejected');
}
$reject(fn()=>$service->listing($identity('NHAN_VIEN',1,null),[]),403,'unassigned staff fails closed');
$reject(fn()=>$service->listing(array_replace($staff,['trang_thai_nhan_vien'=>'DA_NGHI']),[]),403,'inactive staff rejected');
$reject(fn()=>$service->listing(array_replace($staff,['cong_ty_cua_co_so'=>2]),[]),403,'inconsistent assignment rejected');
foreach (['Shared Customer','0900000001','shared@example.test'] as $keyword) {
    $check(array_column($service->listing($companyA,['q'=>$keyword])['items'],'ma_khach_hang')===[1],'search by name/phone/email');
}
$check($service->listing($companyA,['q'=>'%_'])['total']===1,'LIKE wildcards treated literally');
$check($service->listing($companyA,['q'=>"' OR 1=1 --"])['total']===0,'SQL injection is a bound search string');
$forged=$service->listing($staff,['company_id'=>'2','facility_id'=>'3','ma_co_so'=>'3','ma_khach_hang'=>'3']);
$check(array_column($forged['items'],'ma_khach_hang')===[6,1,2],'forged request ownership parameters cannot widen scope');
$page1=$service->listing($companyA,[]); $page2=$service->listing($companyA,['page'=>'2']);
$check(count($page1['items'])===10 && count($page2['items'])===8 && !array_intersect(array_column($page1['items'],'ma_khach_hang'),array_column($page2['items'],'ma_khach_hang')),'pagination has no missing/duplicate customers');
$check($service->listing($companyA,['page'=>'999'])['page']===2,'out-of-range page clamped');
$check($service->listing($companyA,['q'=>'Paged','page'=>'2'])['total']===14,'filtered pagination');
foreach ([['q'=>[]],['page'=>'0'],['per_page'=>'1000']] as $query) $reject(fn()=>$service->listing($companyA,$query),422,'invalid list filters');
foreach ([null,[],"1 OR 1=1",'0','2147483648'] as $id) $reject(fn()=>$service->detail($companyA,$id,[]),404,'invalid customer ID');
$pdo->queries=[]; $service->detail($companyA,'1',[]);
$check(count($pdo->queries)===3,'one statistics, one history, one batched-lines query, no N+1');
$check($service->detail($facility,'2',[])['items'][0]['trang_thai_dat_san']==='HET_HAN','reuse effective expiry display without writing status');
$check($service->detail($companyA,'1',[])['items'][1]['trang_thai_dat_san']==='CHO_XAC_NHAN','review remains review after deadline');

$render = static function(string $path,array $query=[]) use($router): string {
    $_GET=['route'=>$path]+$query;
    ob_start(); try { $router->dispatch('GET',$path); return ob_get_contents(); } finally { ob_end_clean(); }
};
foreach ([$companyA,$companyB,$facility,$staff] as $user) {
    $actor->user=$user;
    $html=$render('/admin/customers');
    $check(str_contains($html,'Quản lý khách hàng') && str_contains($html,'admin-sidebar'),'real controller uses admin layout for allowed roles');
    $check(str_contains($html,'aria-current="page"') && !str_contains($html,'Thêm khách hàng'),'active navigation and no create action');
    $detailHtml=$render('/admin/customers/show',['id'=>'1']);
    $check(str_contains($detailHtml,'Shared Customer') && !str_contains($detailHtml,'name="confirmation_mode"'),'read-only detail view');
    $check(!str_contains($detailHtml,$user['ma_cong_ty']===1?'Facility B1':'Facility A1'),'rendered history excludes foreign facility');
}
$actor->user=$companyA;
$html=$render('/admin/customers');
$check(str_contains($html,'&lt;script&gt;customerAttack()&lt;/script&gt;') && !str_contains($html,'<script>customerAttack()'),'customer names escaped');
$detailHtml=$render('/admin/customers/show',['id'=>'6']);
$check(str_contains($detailHtml,'&lt;img src=x onerror=attack()&gt;') && !str_contains($detailHtml,'<img src=x'),'customer email escaped');
$check(str_contains($render('/admin/customers',['q'=>'Paged','page'=>'2']),'q=Paged&amp;per_page=10&amp;page=1'),'pagination keeps validated search');
$check(str_contains($render('/admin/customers',['q'=>'No match']),'Không tìm thấy khách hàng phù hợp'),'search empty state');
$actor->user=$identity('QUAN_LY_CONG_TY',99,null);
$check(str_contains($render('/admin/customers'),'Chưa có khách hàng đặt sân'),'scope empty state');
$actor->user=$companyB;
$reject(fn()=>$render('/admin/customers/show',['id'=>'2']),404,'direct GET detail cannot bypass tenant scope');
foreach (['KHACH_HANG','QUAN_TRI_TAP_DOAN'] as $role) {
    $actor->user=$identity($role);
    $reject(fn()=>$render('/admin/customers'),403,'route middleware list rejects disallowed role');
    $reject(fn()=>$render('/admin/customers/show',['id'=>'1']),403,'route middleware detail rejects disallowed role');
}
$routes=(new ReflectionProperty($router,'routes'))->getValue($router);
$check(count($routes['GET'])===2 && empty($routes['POST']),'only two GET routes, no mutation endpoints');
foreach (['/admin/customers/create','/admin/customers/edit','/admin/customers/delete'] as $path) {
    $reject(fn()=>$router->dispatch('GET',$path),404,'mutation URL does not exist');
}
$reject(fn()=>$router->dispatch('POST','/admin/customers'),405,'POST method not allowed');
foreach ($pdo->queries as $sql) {
    $check(preg_match('/\ASELECT\b/i',trim($sql))===1,'every module query read-only');
    $check(!preg_match('/\bmat_khau\b|\btoken\b|\btai_khoan\b/i',$sql),'no account/hash/token query');
}
$menu=require dirname(__DIR__) . '/config/admin-menu.php';
foreach (CustomerManagementService::ROLES as $role) {
    $check(count(array_filter($menu[$role]['CÔNG TY'],static fn($item)=>$item['route']==='/admin/customers'))===1,'sidebar entry only once in company group');
}
$check(!str_contains(json_encode($menu['QUAN_TRI_TAP_DOAN']),'/admin/customers'),'no unintended root-admin access added');
session_write_close();
echo "PASS: $checks scoped SQL/controller/middleware/view/read-only checks; SQLite memory only.\n";
