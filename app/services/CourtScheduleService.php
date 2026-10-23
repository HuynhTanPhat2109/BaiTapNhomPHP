<?php
namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use Core\HttpException;

final class CourtScheduleService
{
    public static function now(): DateTimeImmutable { return new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh')); }

    public static function validateDate(string $date, DateTimeImmutable $now): void
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $now->getTimezone());
        if (!$parsed || $parsed->format('Y-m-d') !== $date || $date < $now->format('Y-m-d')) {
            throw new HttpException(422, 'Vui lòng chọn ngày hợp lệ từ hôm nay trở đi.');
        }
    }

    public static function build(array $facility, array $courts, array $intervals, string $date, DateTimeImmutable $now): array
    {
        self::validateDate($date, $now);
        $slots = [];
        for ($minute = 0; $minute < 1440; $minute += 30) {
            $start = sprintf('%02d:%02d:00', intdiv($minute, 60), $minute % 60);
            $end = sprintf('%02d:%02d:00', intdiv($minute + 30, 60), ($minute + 30) % 60);
            if ($start >= $facility['gio_mo_cua'] && $end <= $facility['gio_dong_cua']) $slots[] = ['start' => $start, 'end' => $end];
        }
        $rows = [];
        foreach ($courts as $court) {
            $cells = [];
            foreach ($slots as $slot) {
                $state = 'free';
                foreach ($intervals as $interval) {
                    if ((int) $interval['ma_san'] !== (int) $court['ma_san'] || $slot['start'] >= $interval['gio_ket_thuc'] || $slot['end'] <= $interval['gio_bat_dau']) continue;
                    if (in_array($interval['trang_thai_dat_san'], ['DA_XAC_NHAN', 'HOAN_THANH'], true)) { $state = 'booked'; break; }
                    if ($interval['trang_thai_dat_san'] === 'CHO_THANH_TOAN' && $interval['het_han_giu_san_luc'] > $now->format('Y-m-d H:i:s')) $state = 'held';
                }
                if ($court['trang_thai'] !== 'HOAT_DONG' || $court['trang_thai_loai_san'] !== 'HOAT_DONG' || $court['trang_thai_bo_mon'] !== 'HOAT_DONG'
                    || new DateTimeImmutable($date . ' ' . $slot['start'], $now->getTimezone()) <= $now) $state = 'unavailable';
                $cells[] = $slot + ['state' => $state];
            }
            $rows[] = ['court' => $court, 'cells' => $cells];
        }
        return ['slots' => $slots, 'rows' => $rows];
    }

    public static function selection(array $schedule, string $courtId, string $start, string $end): array
    {
        if (!preg_match('/\A(?:[01][0-9]|2[0-3]):(?:00|30)\z/', $start) || !preg_match('/\A(?:[01][0-9]|2[0-3]):(?:00|30)\z/', $end) || $start >= $end) {
            throw new HttpException(422, 'Chọn giờ bắt đầu/kết thúc theo bước 30 phút.');
        }
        foreach ($schedule['rows'] as $row) {
            if ((string) $row['court']['ma_san'] !== $courtId) continue;
            $selected = array_values(array_filter($row['cells'], fn($cell) => $cell['start'] >= $start . ':00' && $cell['end'] <= $end . ':00'));
            if (!$selected || $selected[0]['start'] !== $start . ':00' || end($selected)['end'] !== $end . ':00') break;
            foreach ($selected as $cell) if ($cell['state'] !== 'free') throw new HttpException(422, 'Khoảng giờ đã chọn không còn khả dụng. Vui lòng tải lại lịch.');
            return ['court' => $row['court']['ten_san'], 'start' => $start, 'end' => $end];
        }
        throw new HttpException(422, 'Sân hoặc khoảng giờ không thuộc lịch cơ sở này.');
    }
}
