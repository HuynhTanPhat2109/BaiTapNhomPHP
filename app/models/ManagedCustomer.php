<?php

namespace App\Models;

use App\Services\FacilityScope;
use PDO;

/** Read-only customer data, always constrained by the operator's facility scope. */
final class ManagedCustomer
{
    public function __construct(private PDO $pdo) {}

    private function source(array $scope): array
    {
        [$where, $params] = FacilityScope::where($scope);
        return [' FROM dat_san b JOIN co_so f ON f.ma_co_so=b.ma_co_so
            JOIN khach_hang k ON k.ma_khach_hang=b.ma_khach_hang WHERE '
            . implode(' AND ', $where ?: ['1=0']), $params];
    }

    public function page(array $scope, array $filters): array
    {
        [$source, $params] = $this->source($scope);
        if ($filters['q'] !== '') {
            $source .= " AND (k.ho_ten LIKE :q_name ESCAPE '!' OR k.so_dien_thoai LIKE :q_phone ESCAPE '!'
                OR k.email LIKE :q_email ESCAPE '!')";
            $keyword = '%' . strtr($filters['q'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $params += ['q_name' => $keyword, 'q_phone' => $keyword, 'q_email' => $keyword];
        }
        $total = (int) $this->rows('SELECT COUNT(DISTINCT k.ma_khach_hang) AS total' . $source, $params)[0]['total'];
        $paging = $this->paging($total, $filters);
        $items = $this->rows('SELECT k.ma_khach_hang, k.ho_ten, k.so_dien_thoai, k.email,
                COUNT(*) AS booking_count, MAX(b.ngay_tao) AS last_booking_at' . $source . '
            GROUP BY k.ma_khach_hang, k.ho_ten, k.so_dien_thoai, k.email
            ORDER BY last_booking_at DESC, k.ma_khach_hang DESC LIMIT ' . $paging['limit'] . ' OFFSET ' . $paging['offset'], $params);
        return ['items' => $items] + $paging;
    }

    public function find(int $id, array $scope): ?array
    {
        [$source, $params] = $this->source($scope);
        $status = str_replace('?', 'CURRENT_TIMESTAMP', Booking::effectiveStatusSql());
        return $this->rows("SELECT k.ma_khach_hang, k.ho_ten, k.so_dien_thoai, k.email,
                COUNT(*) AS booking_count, MAX(b.ngay_tao) AS last_booking_at,
                SUM(CASE WHEN ($status)='DA_XAC_NHAN' THEN 1 ELSE 0 END) AS confirmed_count,
                SUM(CASE WHEN ($status)='CHO_XAC_NHAN' THEN 1 ELSE 0 END) AS awaiting_count"
            . $source . ' AND k.ma_khach_hang=:customer
            GROUP BY k.ma_khach_hang, k.ho_ten, k.so_dien_thoai, k.email', $params + ['customer' => $id])[0] ?? null;
    }

    public function history(int $id, array $scope, array $filters, int $total): array
    {
        [$source, $params] = $this->source($scope);
        $status = str_replace('?', 'CURRENT_TIMESTAMP', Booking::effectiveStatusSql());
        $paging = $this->paging($total, $filters);
        $items = $this->rows("SELECT b.ma_dat_san, b.ngay_tao, f.ten_co_so, b.tong_tien_tam_tinh,
                ($status) AS trang_thai_dat_san" . $source . '
            AND k.ma_khach_hang=:customer ORDER BY b.ngay_tao DESC, b.ma_dat_san DESC
            LIMIT ' . $paging['limit'] . ' OFFSET ' . $paging['offset'], $params + ['customer' => $id]);
        // One batch for all lines on the page; no price recalculation or N+1 queries.
        $lines = [];
        if ($items) {
            [$where, $lineParams] = FacilityScope::where($scope);
            $where[] = 'b.ma_khach_hang=:customer';
            $lineParams['customer'] = $id;
            $holders = [];
            foreach ($items as $index => $booking) {
                $holders[] = ':booking_' . $index;
                $lineParams['booking_' . $index] = (int) $booking['ma_dat_san'];
            }
            $where[] = 'b.ma_dat_san IN (' . implode(',', $holders) . ')';
            foreach ($this->rows('SELECT ct.ma_dat_san, ct.ten_san_snapshot, ct.ngay_choi, ct.gio_bat_dau, ct.gio_ket_thuc
                FROM chi_tiet_dat_san ct JOIN dat_san b ON b.ma_dat_san=ct.ma_dat_san
                JOIN co_so f ON f.ma_co_so=b.ma_co_so WHERE ' . implode(' AND ', $where) . '
                ORDER BY ct.ngay_choi, ct.ma_san, ct.gio_bat_dau, ct.ma_chi_tiet', $lineParams) as $line) {
                $lines[(int) $line['ma_dat_san']][] = $line;
            }
        }
        foreach ($items as &$booking) $booking['lines'] = $lines[(int) $booking['ma_dat_san']] ?? [];
        unset($booking);
        return ['items' => $items] + $paging;
    }

    private function paging(int $total, array $filters): array
    {
        $limit = (int) $filters['per_page'];
        $pages = max(1, (int) ceil($total / $limit));
        $page = min($pages, max(1, (int) $filters['page']));
        return compact('total', 'pages', 'page', 'limit') + ['offset' => ($page - 1) * $limit];
    }

    private function rows(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
