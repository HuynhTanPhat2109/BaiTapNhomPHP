<?php
// Included only by the existing isolated facility HTTP/SQL test suite.
if (!isset($pdo, $f, $s, $booking, $detail, $base)) throw new RuntimeException('Run via facility-migration-integration.php --run');
$browser = new App\Models\CourtBrowser($pdo);
$publicClient = facilityClient();
$rows = $browser->facilities(['ma_co_so' => (string) $f]);
checkFacility(count($rows) === 1, 'public active facility');
checkFacility(count($browser->facilities(['ma_co_so'=>(string)$f, 'ma_tinh'=>'01'])) === 1, 'structured province filter');
checkFacility($browser->facilities(['ma_co_so'=>(string)$f, 'ma_tinh'=>'invalid']) === [], 'wrong province excluded');
checkFacility($browser->facilities(['q'=>"' OR 1=1 --"]) === [], 'search SQL injection treated as data');
foreach (['/courts', '/courts/show', '/courts/panel', '/courts/schedule'] as $route) {
    $r = facilityRequest($publicClient, $route, null, ['id'=>(string)$f]);
    checkFacility($r['status'] === 200, 'public route ' . $route);
}
checkFacility(facilityRequest($publicClient, '/courts/show', null, ['id'=>'999999999'])['status'] === 404, 'unknown public facility');
checkFacility(facilityRequest($publicClient, '/courts/schedule', null, ['id'=>(string)$f, 'date'=>'2020-01-01'])['status'] === 422, 'past date rejected');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$pdo->prepare("UPDATE chi_tiet_dat_san SET ngay_choi=?,gio_bat_dau='08:00',gio_ket_thuc='10:00' WHERE ma_chi_tiet=?")->execute([$tomorrow,$detail]);
$pdo->prepare("UPDATE dat_san SET trang_thai_dat_san='DA_XAC_NHAN' WHERE ma_dat_san=?")->execute([$booking]);
$courts->save($court, $s);
$r = facilityRequest($publicClient, '/courts/schedule', null, ['id'=>(string)$f, 'date'=>$tomorrow, 'court'=>(string)$s, 'start'=>'08:00', 'end'=>'08:30']);
checkFacility($r['status'] === 422, 'real booking blocks selection');
$r = facilityRequest($publicClient, '/courts/schedule', null, ['id'=>(string)$f, 'date'=>$tomorrow, 'court'=>(string)$s, 'start'=>'10:00', 'end'=>'11:00']);
checkFacility($r['status'] === 200 && str_contains($r['body'], 'Chưa giữ sân'), 'adjacent free range only previews');
$previewResponse = facilityRequest($publicClient, '/courts/schedule', null, ['id'=>(string)$f, 'date'=>$tomorrow, 'court'=>(string)$s, 'start'=>'10:00', 'end'=>'11:00', 'preview'=>'1']);
$previewData = json_decode($previewResponse['body'], true, 512, JSON_THROW_ON_ERROR);
checkFacility($previewResponse['status'] === 200 && $previewData['selection']['start'] === '10:00', 'grid preview validates on backend');
checkFacility(facilityRequest($publicClient, '/courts/schedule', null, ['id'=>(string)$f, 'date'=>$tomorrow, 'court'=>(string)$s, 'start'=>'08:00', 'end'=>'11:00', 'preview'=>'1'])['status'] === 422, 'preview rejects blocked range');
checkFacility(facilityRequest($publicClient, '/courts/booking-type', null, ['id'=>(string)$f])['status'] === 404, 'obsolete booking type route removed');
$intervals = $browser->intervals($f, $tomorrow);
checkFacility(!array_key_exists('ma_khach_hang', $intervals[0]) && !array_key_exists('don_gia', $intervals[0]), 'schedule projection excludes private data');
$pdo->prepare("UPDATE co_so SET trang_thai='TAM_NGUNG' WHERE ma_co_so=?")->execute([$f]);
checkFacility(facilityRequest($publicClient, '/courts/show', null, ['id'=>(string)$f])['status'] === 404, 'inactive facility inaccessible directly');
$pdo->prepare("UPDATE co_so SET trang_thai='HOAT_DONG' WHERE ma_co_so=?")->execute([$f]);
$companyId = $facilityModel->find($f)['ma_cong_ty'];
$pdo->prepare("UPDATE cong_ty_nhuong_quyen SET trang_thai='NGUNG_HOAT_DONG' WHERE ma_cong_ty=?")->execute([$companyId]);
checkFacility($browser->facilities(['ma_co_so'=>(string)$f]) === [], 'inactive company excludes facility');
checkFacility(facilityRequest($publicClient, '/courts/panel', null, ['id'=>(string)$f])['status'] === 404, 'drawer blocks inactive company');
$pdo->prepare("UPDATE cong_ty_nhuong_quyen SET trang_thai='HOAT_DONG' WHERE ma_cong_ty=?")->execute([$companyId]);
checkFacility(count($browser->facilities(['ma_co_so'=>(string)$f, 'ma_bo_mon'=>(string)$sport])) === 1, 'sport filter matches active courts');
checkFacility($browser->facilities(['ma_co_so'=>(string)$f, 'ma_bo_mon'=>'2147483647']) === [], 'sport without courts excluded');
$originalName = $facilityModel->find($f)['ten_co_so'];
$pdo->prepare('UPDATE co_so SET ten_co_so=? WHERE ma_co_so=?')->execute(['<script>alert(1)</script>', $f]);
$panel = facilityRequest($publicClient, '/courts/panel', null, ['id'=>(string)$f]);
checkFacility(!str_contains($panel['body'], '<script>') && str_contains($panel['body'], '&lt;script&gt;'), 'drawer escapes facility content');
checkFacility(!str_contains($panel['body'], '<html') && str_contains($panel['body'], 'Đặt lịch'), 'drawer fragment has booking action without layout');
$pdo->prepare('UPDATE co_so SET ten_co_so=? WHERE ma_co_so=?')->execute([$originalName, $f]);
