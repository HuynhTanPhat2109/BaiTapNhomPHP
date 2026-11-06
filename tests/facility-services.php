<?php
define('STRYKE_FACILITY_SERVICES_TEST',true);
$_GET=$_POST=[];
require __DIR__.'/fixtures/facility-services.php';
$checks=0;
$check=static function(bool $ok,string $label) use (&$checks): void { if (!$ok) throw new RuntimeException('FAIL: '.$label); $checks++; };
$model=new App\Models\CourtBrowser($pdo);
$services=$model->publicServices(1);
$check(count($services)===3,'only active own-facility services');
$check(array_keys($services[0])===['ten_dich_vu','loai_dich_vu','don_gia','don_vi_tinh'],'public service data has no inventory/internal fields');
$check($model->publicServices(2)===[] && $model->publicServices(4)===[] && $model->publicServices(5)===[],'empty/inactive facility or company excluded');
$render=static function(int $id,string $action='panel') use($controller): string {
    $_GET=['id'=>(string)$id]; ob_start(); try { $controller->$action(); return ob_get_contents(); } finally { ob_end_clean(); }
};
$html=$render(1);
$document=new DOMDocument(); @$document->loadHTML('<?xml encoding="UTF-8">'.$html); $xpath=new DOMXPath($document);
$panel='//section[@data-facility-panel="services"]';
$check($xpath->query($panel.'//table')->length===3,'two sports tables and one services table');
$check($xpath->query($panel.'//table/thead')->length===3 && $xpath->query($panel.'//table/tbody')->length>=3,'semantic tables');
$check(str_contains($html,'Standard') && str_contains($html,'VIP') && str_contains($html,'Five-a-side'),'all valid court types kept');
$check(str_contains($html,'50.000') && str_contains($html,'110.000') && str_contains($html,'90.000'),'different type prices retained');
$check(str_contains($html,'T2–CN') && str_contains($html,'T2–T6') && str_contains($html,'T7–CN'),'merge equal days only; different day prices retained');
$check(str_contains($html,'70.000') && str_contains($html,'80.000'),'different weekday/weekend prices retained');
foreach (['999.999','777.777','666.666','555.555','333.333','Foreign service','Stopped service'] as $hidden) $check(!str_contains($html,$hidden),'no foreign/inactive/future/expired data: '.$hidden);
foreach (App\Services\DichVuService::TYPES as $label) {
    if ($label==='Khác') continue;
    $check(str_contains($html,$label),'real service category: '.$label);
}
$check(str_contains($html,'&lt;script&gt;attack()&lt;/script&gt;') && !str_contains($html,'<script>attack()'),'service names escaped');
$check(!preg_match('/so_luong_ton|reserved|777|888|data-service-id|name="quantity"/',$html),'no inventory or purchase controls');
$check(str_contains($html,'đ/giờ') && str_contains($html,'Bottle'),'real units and hourly price label');
$check($xpath->query('//section[@data-facility-panel="info"]')->length===1 && str_contains($html,'Existing facility information.'),'information tab retained');
$check($xpath->query('//section[@data-facility-panel="images"]')->length===1 && $xpath->query('//section[@data-facility-panel="rules"]')->length===1 && $xpath->query('//section[@data-facility-panel="reviews"]')->length===1,'other tabs retained');
$check(str_contains($html,'route=%2Fcourts%2Fschedule&amp;id=1'),'booking link retained for correct facility');
$empty=$render(2);
$check(str_contains($empty,'Cơ sở chưa cập nhật bảng giá.') && str_contains($empty,'Cơ sở chưa cập nhật dịch vụ.'),'exact empty states');
$single=$render(6);
$singleDoc=new DOMDocument(); @$singleDoc->loadHTML('<?xml encoding="UTF-8">'.$single); $singleXp=new DOMXPath($singleDoc);
$check($singleXp->query($panel.'//table[contains(@class,"facility-service-prices")]')->length===1 && !str_contains($single,'VIP'),'one court type case');
foreach ([4,5] as $id) {
    try { $render($id); throw new RuntimeException('Inactive facility accepted'); }
    catch(Core\HttpException $error) { $check($error->status===404,'inactive facility/company endpoint denied'); }
}
$full=$render(1,'show');
$check(str_contains($full,'facility-service-tables') && str_contains($full,'50.000'),'direct facility URL receives same catalog data');
echo "PASS: $checks facility services/public pricing/scope/view checks; SQLite memory only.\n";
