<?php
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Services\CourtScheduleService as Schedule;
use Core\HttpException;
$checks = 0;
function courtCheck(bool $ok): void { global $checks; if (!$ok) throw new RuntimeException('Court assertion failed'); $checks++; }
function courtReject(callable $fn): void { try { $fn(); } catch (HttpException $e) { courtCheck($e->status === 422); return; } throw new RuntimeException('Invalid selection accepted'); }
$now = new DateTimeImmutable('2026-10-23 16:30:00', new DateTimeZone('Asia/Ho_Chi_Minh'));
$facility = ['gio_mo_cua' => '16:00:00', 'gio_dong_cua' => '19:00:00'];
$court = ['ma_san' => 1, 'ten_san' => '<Test court>', 'trang_thai' => 'HOAT_DONG', 'trang_thai_loai_san' => 'HOAT_DONG', 'trang_thai_bo_mon' => 'HOAT_DONG'];
$build = fn($intervals = [], $c = null) => Schedule::build($facility, [$c ?? $court], $intervals, '2026-10-23', $now);
$grid = $build();
courtCheck(count($grid['slots']) === 6);
courtCheck(array_column($grid['rows'][0]['cells'], 'state') === ['unavailable', 'unavailable', 'free', 'free', 'free', 'free']);
courtCheck(Schedule::selection($grid, '1', '17:00', '18:30')['end'] === '18:30');
foreach ([['1','16:30','17:00'], ['1','17:15','18:00'], ['2','17:00','18:00'], ['1','18:00','17:00'], ['1','18:00','20:00']] as $selection) courtReject(fn() => Schedule::selection($grid, ...$selection));
foreach (['2026-10-22', '2026-02-30', 'bad', '2026-1-1'] as $date) courtReject(fn() => Schedule::validateDate($date, $now));
foreach (['CHO_XAC_NHAN' => 'free', 'DA_HUY' => 'free', 'HET_HAN' => 'free', 'DA_XAC_NHAN' => 'booked', 'HOAN_THANH' => 'booked', 'CHO_THANH_TOAN' => 'held'] as $state => $expected) {
    $interval = ['ma_san' => 1, 'gio_bat_dau' => '17:00:00', 'gio_ket_thuc' => '17:30:00', 'trang_thai_dat_san' => $state, 'het_han_giu_san_luc' => '2026-10-23 16:36:00'];
    $result = $build([$interval]);
    courtCheck($result['rows'][0]['cells'][2]['state'] === $expected);
    courtCheck($result['rows'][0]['cells'][3]['state'] === 'free');
}
$interval['het_han_giu_san_luc'] = '2026-10-23 16:30:00';
courtCheck($build([$interval])['rows'][0]['cells'][2]['state'] === 'free');
$interval['trang_thai_dat_san'] = 'DA_XAC_NHAN';
courtReject(fn() => Schedule::selection($build([$interval]), '1', '17:00', '18:00'));
foreach (['trang_thai', 'trang_thai_loai_san', 'trang_thai_bo_mon'] as $field) courtCheck($build([], array_replace($court, [$field => 'NGUNG']))['rows'][0]['cells'][2]['state'] === 'unavailable');
$odd = Schedule::build(['gio_mo_cua'=>'05:45:00','gio_dong_cua'=>'07:15:00'], [$court], [], '2026-10-24', $now);
courtCheck(array_column($odd['slots'], 'start') === ['06:00:00','06:30:00']);
$morning = new DateTimeImmutable('2026-10-23 08:31:00', new DateTimeZone('Asia/Ho_Chi_Minh'));
$hours = ['gio_mo_cua' => '08:00:00', 'gio_dong_cua' => '10:00:00'];
$today = Schedule::build($hours, [$court], [], '2026-10-23', $morning);
courtCheck(array_column($today['rows'][0]['cells'], 'state') === ['unavailable', 'unavailable', 'free', 'free']);
$future = Schedule::build($hours, [$court], [], '2026-10-24', $morning);
courtCheck(array_column($future['rows'][0]['cells'], 'state') === ['free', 'free', 'free', 'free']);
foreach ($future['slots'] as $slot) courtCheck(strtotime($slot['end']) - strtotime($slot['start']) === 1800);
echo "PASS: $checks schedule checks; no database.\n";
