<?php
namespace App\Services;

/** Presentation grouping only; booking price calculation remains unchanged. */
final class CourtPriceList
{
    public static function group(array $rows): array
    {
        $sports = [];
        foreach ($rows as $row) {
            $sport = $row['ma_bo_mon'];
            $type = $row['ma_loai_san'];
            $sports[$sport]['name'] = $row['ten_bo_mon'];
            $sports[$sport]['types'][$type]['name'] = $row['ten_loai_san'];
            $sports[$sport]['types'][$type]['rates'] ??= [];
            if ($row['ma_bang_gia'] === null) continue;
            $key = json_encode([$row['gio_bat_dau'], $row['gio_ket_thuc'],
                BookingPricingService::cents((string)$row['don_gia']), $row['ngay_bat_dau'], $row['ngay_ket_thuc']]);
            $rates = &$sports[$sport]['types'][$type]['rates'];
            $rates[$key] ??= $row + ['days'=>[]];
            $rates[$key]['days'][$row['loai_ngay']] = true;
            unset($rates);
        }
        foreach ($sports as &$sport) {
            foreach ($sport['types'] as &$type) {
                $type['rates'] = array_values($type['rates']);
                foreach ($type['rates'] as &$rate) {
                    $rate['day_label'] = isset($rate['days']['NGAY_THUONG'], $rate['days']['CUOI_TUAN'])
                        ? 'T2–CN' : (isset($rate['days']['NGAY_THUONG']) ? 'T2–T6' : 'T7–CN');
                }
                unset($rate);
                $dayOrder = ['T2–CN'=>0, 'T2–T6'=>1, 'T7–CN'=>2];
                usort($type['rates'], static fn($a,$b) =>
                    [$dayOrder[$a['day_label']], $a['gio_bat_dau'], $a['gio_ket_thuc'], $a['ma_bang_gia']]
                    <=> [$dayOrder[$b['day_label']], $b['gio_bat_dau'], $b['gio_ket_thuc'], $b['ma_bang_gia']]);
                // Span only consecutive rows within this court type, without removing any rates.
                for ($i=0, $count=count($type['rates']); $i<$count;) {
                    $end=$i+1;
                    while ($end<$count && $type['rates'][$end]['day_label']===$type['rates'][$i]['day_label']) $end++;
                    $type['rates'][$i]['day_span']=$end-$i;
                    for ($j=$i+1; $j<$end; $j++) $type['rates'][$j]['day_span']=0;
                    $i=$end;
                }
            }
            unset($type);
        }
        unset($sport);
        return array_values($sports);
    }
}
