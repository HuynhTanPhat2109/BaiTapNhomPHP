<?php
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Services\AdministrativeAddress;
use App\Services\FacilityImage;
use App\Services\ValidationException;
$checks = 0;
function verifyAddress(bool $ok): void { global $checks; if (!$ok) throw new RuntimeException('Assertion failed'); $checks++; }
$provinces = AdministrativeAddress::provinces();
verifyAddress(count($provinces) === 34);
$count = 0;
foreach ($provinces as $p) {
    foreach ($p['Wards'] as $w) {
        $address = AdministrativeAddress::resolve($p['Code'], $w['Code']);
        verifyAddress($address['ten_phuong_xa'] === $w['FullName'] && $w['ProvinceCode'] === $p['Code']);
        $count++;
    }
}
verifyAddress($count === 3321);
verifyAddress(AdministrativeAddress::resolve('01', '00004')['ten_tinh'] === 'Thành phố Hà Nội');
foreach ([['01', 'invalid'], ['invalid', '00004'], [[], []], ['79', '00004']] as [$p, $w]) {
    try { AdministrativeAddress::resolve($p, $w); throw new RuntimeException('Invalid address accepted'); }
    catch (ValidationException $e) { verifyAddress(isset($e->errors['ma_phuong_xa'])); }
}
verifyAddress(FacilityImage::store(null) === null);
verifyAddress(FacilityImage::store(['error' => UPLOAD_ERR_NO_FILE]) === null);
verifyAddress(FacilityImage::validPath('uploads/co-so/' . str_repeat('a', 32) . '.jpg'));
foreach (['../.env', 'uploads/co-so/a.php', 'https://example.com/a.jpg', null] as $path) verifyAddress(!FacilityImage::validPath($path));
foreach ([['error' => UPLOAD_ERR_INI_SIZE], ['error' => UPLOAD_ERR_OK, 'tmp_name' => __FILE__]] as $file) {
    try { FacilityImage::store($file); throw new RuntimeException('Invalid upload accepted'); }
    catch (ValidationException $e) { verifyAddress(isset($e->errors['anh_dai_dien'])); }
}
echo "PASS: $checks address/image checks. No database used.\n";
