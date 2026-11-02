<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$facility = ['ma_co_so'=>1,'ten_co_so'=>'<Test facility>','ten_cong_ty'=>'Test company','dia_chi'=>'Test address','ten_phuong_xa'=>null,'ten_tinh'=>null,'gio_mo_cua'=>'05:00:00','gio_dong_cua'=>'23:00:00','so_dien_thoai'=>null,'email'=>null,'mo_ta'=>null,'anh_dai_dien'=>null,'logo_clb'=>null];
foreach ([false, true] as $signedIn) {
    $_SESSION = $signedIn ? ['account_id'=>1,'authenticated_at'=>time(),'last_active_at'=>time()] : [];
    ob_start();
    Core\View::render('courts/show', ['facility'=>$facility,'courts'=>[],'title'=>'Test']);
    $html = ob_get_clean();
    foreach (['court-flow-bar', 'data-facility-tabs', '&lt;Test facility&gt;'] as $expected) {
        if (!str_contains($html, $expected)) throw new RuntimeException('Missing: ' . $expected);
    }
    $document = new DOMDocument();
    if (!$document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) throw new RuntimeException('Court layout did not render');
    $xpath = new DOMXPath($document);
    // The compact court header returns to the list/home; account navigation lives on home.
    if ($xpath->query('//header//a[contains(concat(" ", normalize-space(@class), " "), " court-flow-back ") and @href="' . url('/courts') . '"]')->length !== 1) throw new RuntimeException('Missing return to facility list');
    if ($xpath->query('//header//a[contains(concat(" ", normalize-space(@class), " "), " court-flow-brand ") and @href="' . url('/') . '"]')->length !== 1) throw new RuntimeException('Missing return to STRYKE home');
    if ($xpath->query('//main//a[@href="' . url('/courts/schedule') . '&id=1"]')->length !== 1) throw new RuntimeException('Booking link lost its facility ID');
    if (str_contains($html, 'class="navbar"') || str_contains($html, '<footer')) throw new RuntimeException('Main layout leaked');
    if (substr_count($html, 'data-facility-tab=') !== 5) throw new RuntimeException('Missing tabs');
    ob_start();
    Core\View::render('home');
    $homeHtml = ob_get_clean();
    $homeDocument = new DOMDocument();
    if (!$homeDocument->loadHTML($homeHtml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) throw new RuntimeException('Home layout did not render');
    $homeXPath = new DOMXPath($homeDocument);
    $accountRoute = $signedIn ? '/account' : '/login';
    if ($homeXPath->query('//header//a[@href="' . url($accountRoute) . '"]')->length !== 1) throw new RuntimeException('Home lost guest/member account navigation');
}
echo "PASS: guest/member court layouts, five tabs, escaped data, no main header/footer; no database.\n";
