<?php
namespace App\Services;

use Core\HttpException;

final class BookingPricingService
{
    public const MAX_CENTS = 999999999999; // DECIMAL(12,2)

    public static function cents(string $value): int
    {
        if (!preg_match('/\A[0-9]{1,10}(?:\.[0-9]{1,2})?\z/', $value)) throw new HttpException(409, 'Đơn giá không hợp lệ.');
        $parts = explode('.', $value);
        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    public static function decimal(int $cents): string
    {
        if ($cents < 0 || $cents > self::MAX_CENTS) throw new HttpException(422, 'Tổng tiền vượt giới hạn cho phép.');
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function display(string $amount): string
    {
        [$whole,$fraction] = explode('.', self::decimal(self::cents($amount)));
        return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole) . ($fraction === '00' ? '' : ',' . $fraction) . ' ₫';
    }

    // Input slots are server-validated 30-minute cells; group only identical rates.
    public static function courtLines(array $slots, array $courts, array $prices, string $date): array
    {
        $lines = [];
        foreach ($slots as $slot) {
            $court = $courts[$slot['court']];
            $matches = array_values(array_filter($prices, static fn($price) =>
                (int) $price['ma_loai_san'] === (int) $court['ma_loai_san']
                && $price['gio_bat_dau'] <= $slot['start'] . ':00' && $price['gio_ket_thuc'] >= $slot['end'] . ':00'));
            if (count($matches) !== 1) throw new HttpException(409, 'Chưa có bảng giá duy nhất cho ' . $court['ten_san'] . ' lúc ' . $slot['start'] . '. Vui lòng chọn giờ khác hoặc liên hệ cơ sở.');
            $rate = self::decimal(self::cents($matches[0]['don_gia']));
            $last = count($lines) - 1;
            if ($last >= 0 && $lines[$last]['court'] === $slot['court'] && $lines[$last]['end'] === $slot['start'] && $lines[$last]['rate'] === $rate) {
                $lines[$last]['end'] = $slot['end'];
                $lines[$last]['minutes'] += 30;
            } else {
                $lines[] = ['court'=>$slot['court'],'name'=>$court['ten_san'],'sport'=>$court['ten_bo_mon'],
                    'date'=>$date,'start'=>$slot['start'],'end'=>$slot['end'],'minutes'=>30,'rate'=>$rate];
            }
        }
        foreach ($lines as &$line) {
            // Round half up to 0.01 dong once per contiguous same-rate line.
            $line['amount'] = self::decimal(intdiv(self::cents($line['rate']) * $line['minutes'] + 30, 60));
        }
        unset($line);
        return $lines;
    }
}
