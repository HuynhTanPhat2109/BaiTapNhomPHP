<?php
// Customer view + real quote service; exclusively fake PDO arrays, never application DB/.env.
if (PHP_SAPI !== 'cli-server' || getenv('STRYKE_OPERATIONS_TEST') !== '1') { http_response_code(404); exit; }
if (str_starts_with(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/assets/')) return false;
define('STRYKE_INVENTORY_FIXTURE', true);
require dirname(__DIR__) . '/booking-inventory-unit.php';
$_ENV['APP_BASE_PATH'] = '';
$output = dirname(__DIR__, 2) . '/storage/temp/operations-polish/stock-sessions';
if (!is_dir($output)) mkdir($output, 0777, true);
ini_set('session.save_path', $output);
Core\Session::start();
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:");
$db = new InventoryMemoryPDO();
$db->services = [5 => array_replace($db->services[5], ['don_vi_tinh' => 'cây', 'so_luong_ton' => ($_GET['stock'] ?? '6') === 'null' ? null : (int) ($_GET['stock'] ?? 6)])];
$held = (int) ($_GET['held'] ?? 0);
if ($held) inventoryPending($db, [5 => $held]);
$input = inventorySelection($held ? 2 : 1);
$extras = ['services' => [], 'note' => ''];
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Core\Csrf::verify($_POST['_token'] ?? null);
        $extras = App\Services\BookingService::extras($_POST['services'] ?? [], $_POST['note'] ?? '');
    }
    $quote = (new App\Services\BookingService($db))->quote($input, $extras);
    $error = null; $key = str_repeat('a', 64); $bookingBackUrl = '/';
    $draft = ['input' => $input, 'extras' => $extras];
    $user = ['ten_vai_tro' => 'KHACH_HANG', 'ten_khach_hang' => 'Khách fixture', 'so_dien_thoai_khach' => '0900000000'];
    Core\View::render('courts/booking-details', compact('quote', 'error', 'key', 'bookingBackUrl', 'draft', 'user') + ['title' => 'Kiểm thử tồn trong RAM']);
} catch (Core\HttpException $e) { http_response_code($e->status); echo e($e->getMessage()); }
