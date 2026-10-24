<?php

// Validation and real-template checks only; no database connection.
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\CoSoService;
use App\Services\SanService;
use Core\Session;
use Core\View;

$checks = 0;
function checkFacilityUnit(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    $checks++;
}

$facility = ['ma_cong_ty' => '1', 'ten_co_so' => ' Cơ sở Thủ Đức ', 'dia_chi' => 'Địa chỉ',
    'gio_mo_cua' => '06:00', 'gio_dong_cua' => '23:00:00', 'trang_thai' => 'HOAT_DONG'];
$court = ['ma_co_so' => '1', 'ma_loai_san' => '2', 'ten_san' => 'Sân 01', 'trang_thai' => 'HOAT_DONG'];
checkFacilityUnit(CoSoService::validate($facility) === [], 'valid facility');
checkFacilityUnit(SanService::validate($court) === [], 'valid court');
foreach (['', '0', '-1', '01', '1.0', '1e2', ' 1 ', '2147483648', ['1'], null, 1] as $id) {
    checkFacilityUnit(!CoSoService::validId($id), 'invalid ID rejected');
    foreach (['ma_co_so', 'ma_loai_san'] as $field) {
        checkFacilityUnit(isset(SanService::validate(array_replace($court, [$field => $id]))[$field]), 'invalid court parent ID');
    }
}
checkFacilityUnit(CoSoService::validId('2147483647'), 'schema INT upper bound');
foreach (['', '24:00', '12:60', '6:00', '06:00:60', '06:00bad', ['06:00']] as $time) {
    checkFacilityUnit(isset(CoSoService::validate(array_replace($facility, ['gio_mo_cua' => $time]))['gio_mo_cua']), 'invalid time');
}
foreach (['06:00', '05:59:59'] as $closing) {
    checkFacilityUnit(isset(CoSoService::validate(array_replace($facility, ['gio_dong_cua' => $closing]))['gio_dong_cua']), 'closing must follow opening');
}
foreach (['ten_co_so' => 200, 'dia_chi' => 255, 'so_dien_thoai' => 20] as $field => $limit) {
    checkFacilityUnit(!isset(CoSoService::validate(array_replace($facility, [$field => str_repeat('á', $limit)]))[$field]), 'Unicode VARCHAR boundary');
    checkFacilityUnit(isset(CoSoService::validate(array_replace($facility, [$field => str_repeat('á', $limit + 1)]))[$field]), 'VARCHAR too long');
}
foreach (['', ['bad'], "bad\0name", "bad\nname", "\xff", str_repeat('á', 151)] as $name) {
    checkFacilityUnit(isset(SanService::validate(array_replace($court, ['ten_san' => $name]))['ten_san']), 'invalid court name');
}
checkFacilityUnit(SanService::validate($court + ['mo_ta' => str_repeat('á', 32767) . 'a']) === [], 'TEXT 65535 bytes');
checkFacilityUnit(isset(SanService::validate($court + ['mo_ta' => str_repeat('á', 32768)])['mo_ta']), 'TEXT byte overflow');
checkFacilityUnit(SanService::validate($court + ['mo_ta' => "0\n\tMô tả"]) === [], 'multiline description');
checkFacilityUnit(isset(CoSoService::validate($facility + ['email' => 'invalid'])['email']), 'invalid email');
foreach (['ACTIVE', '', [], null] as $state) {
    checkFacilityUnit(isset(CoSoService::validate(array_replace($facility, ['trang_thai' => $state]))['trang_thai']), 'facility state allowlist');
    checkFacilityUnit(isset(SanService::validate(array_replace($court, ['trang_thai' => $state]))['trang_thai']), 'court state allowlist');
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
ini_set('session.save_path', dirname(__DIR__) . '/storage/temp');
Session::start();
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
try {
    foreach (['co-so', 'san'] as $module) {
        foreach (['index', 'form', 'show', 'delete'] as $page) {
            $item = $facility + $court + ['ma_co_so' => 1, 'ma_san' => 1, 'ma_bo_mon' => 1,
                'ma_loai_san' => 2, 'ten_cong_ty' => '<script>company</script>', 'ten_bo_mon' => 'Bộ môn',
                'ten_loai_san' => 'Loại sân', 'ten_co_so' => 'Cơ sở', 'so_san' => 0, 'trang_thai_cong_ty' => 'HOAT_DONG',
                'trang_thai_co_so' => 'HOAT_DONG', 'trang_thai_bo_mon' => 'HOAT_DONG', 'trang_thai_loai_san' => 'HOAT_DONG'];
            $related = array_fill_keys(['san', 'nhan_vien', 'bang_gia', 'dat_san', 'dich_vu',
                'nhan_vien_dang_lam', 'booking_hieu_luc', 'chi_tiet_dat_san'], 0);
            $data = ['title' => 'Kiểm thử', 'user' => ['ten_dang_nhap' => '<Admin>'], 'item' => $item,
                'items' => [$item], 'old' => $item, 'errors' => [], 'related' => $related, 'courts' => [],
                'companies' => [], 'facilities' => [], 'types' => [], 'page' => 1, 'pages' => 1, 'total' => 1,
                'filters' => ['q' => '', 'ma_cong_ty' => '', 'ma_co_so' => '', 'ma_loai_san' => '', 'trang_thai' => '', 'per_page' => 10, 'page' => 1]];
            ob_start();
            View::render('admin/' . $module . '/' . $page, $data);
            $html = ob_get_clean();
            checkFacilityUnit(str_contains($html, 'class="admin-sidebar"'), 'shared Sidebar on ' . $module . '/' . $page);
            checkFacilityUnit(str_contains($html, 'href="' . e(url('/admin/' . $module)) . '" aria-current="page"'), 'active module');
            checkFacilityUnit(!str_contains($html, '<script>company</script>'), 'HTML escaped');
            if ($page === 'form') {
                checkFacilityUnit(str_contains($html, 'name="_token"'), 'CSRF field');
                checkFacilityUnit((bool) preg_match('/type="submit"\s+disabled/', $html), 'no active parent disables save');
            }
        }
    }
} finally {
    restore_error_handler();
    Session::forgetAuthentication();
    session_write_close();
}
echo "OK: $checks facility unit checks; no database used.\n";
