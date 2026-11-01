<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$facility = ['ma_co_so'=>1,'ten_co_so'=>'<Test facility>','ten_cong_ty'=>'Test company','dia_chi'=>'Test address','ten_phuong_xa'=>null,'ten_tinh'=>null,'gio_mo_cua'=>'05:00:00','gio_dong_cua'=>'23:00:00','so_dien_thoai'=>null,'email'=>null,'mo_ta'=>null,'anh_dai_dien'=>null,'logo_clb'=>null];
foreach ([false, true] as $signedIn) {
    $_SESSION = $signedIn ? ['account_id'=>1,'authenticated_at'=>time(),'last_active_at'=>time()] : [];
    ob_start();
    Core\View::render('courts/show', ['facility'=>$facility,'courts'=>[],'title'=>'Test']);
    $html = ob_get_clean();
    foreach (['court-flow-bar', 'data-facility-tabs', '&lt;Test facility&gt;', $signedIn ? 'route=%2Faccount' : 'route=%2Flogin'] as $expected) {
        if (!str_contains($html, $expected)) throw new RuntimeException('Missing: ' . $expected);
    }
    if (str_contains($html, 'class="navbar"') || str_contains($html, '<footer')) throw new RuntimeException('Main layout leaked');
    if (substr_count($html, 'data-facility-tab=') !== 5) throw new RuntimeException('Missing tabs');
}
echo "PASS: guest/member court layouts, five tabs, escaped data, no main header/footer; no database.\n";
