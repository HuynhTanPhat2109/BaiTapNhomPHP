<?php
namespace App\Services;

use App\Models\Dashboard;
use Core\HttpException;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class DashboardService
{
    private Dashboard $model;
    public function __construct(private PDO $pdo) { $this->model = new Dashboard($pdo); }

    public static function filters(array $query, ?DateTimeImmutable $now = null): array
    {
        $today = ($now ?? new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh')))
            ->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->setTime(0, 0);
        $period = $query['period'] ?? 'month';
        if (!is_string($period) || !in_array($period, ['today', 'week', 'month', 'last_month', 'custom'], true)) throw new HttpException(422, 'Khoảng thời gian không hợp lệ.');
        $from = match ($period) {
            'today' => $today, 'week' => $today->modify('-6 days'),
            'last_month' => $today->modify('first day of last month'), default => $today->modify('first day of this month'),
        };
        $to = $period === 'last_month' ? $today->modify('last day of last month') : $today;
        if ($period === 'custom') {
            $dates = [];
            foreach (['from', 'to'] as $field) {
                $value = $query[$field] ?? null;
                $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value, $today->getTimezone()) : false;
                if (!$date || $date->format('Y-m-d') !== $value || $date->format('Y') < '1000' || $date->format('Y') > '9998') throw new HttpException(422, 'Ngày bắt đầu/kết thúc phải đúng định dạng YYYY-MM-DD và tồn tại.');
                $dates[$field] = $date;
            }
            [$from, $to] = [$dates['from'], $dates['to']];
        }
        if ($to < $from || $from->diff($to)->days > 3660) throw new HttpException(422, 'Khoảng ngày phải có thứ tự hợp lệ và không vượt quá 10 năm.');
        $group = $query['group_by'] ?? 'auto';
        if (!is_string($group) || !in_array($group, ['auto', 'day', 'month'], true)) throw new HttpException(422, 'Cách nhóm biểu đồ không hợp lệ.');
        $groupMode = $group;
        if ($group === 'auto') $group = $from->diff($to)->days > 62 ? 'month' : 'day';
        if ($group === 'day' && $from->diff($to)->days > 366) throw new HttpException(422, 'Biểu đồ theo ngày hỗ trợ tối đa 367 ngày. Chọn nhóm theo tháng cho khoảng dài hơn.');
        return ['period' => $period, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'),
            'start' => $from->format('Y-m-d H:i:s'), 'end' => $to->modify('+1 day')->format('Y-m-d H:i:s'), 'group_by' => $group, 'group_mode' => $groupMode];
    }

    private static function id(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if ((!is_int($value) && !is_string($value)) || !preg_match('/\A[1-9][0-9]*\z/', (string) $value)) throw new HttpException(422, 'Mã công ty/cơ sở không hợp lệ.');
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) throw new HttpException(422, 'Mã công ty/cơ sở không hợp lệ.');
        return $id;
    }

    public function data(array $user, array $query, ?DateTimeImmutable $now = null, bool $report = false): array
    {
        $scope = BookingManagementService::resolveScope($user);
        $company = self::id($query['company_id'] ?? null);
        $facility = self::id($query['facility_id'] ?? null);
        if (($company !== null && $scope['company'] !== null && $company !== $scope['company'])
            || ($facility !== null && $scope['facility'] !== null && $facility !== $scope['facility'])) throw new HttpException(403, 'Bộ lọc nằm ngoài phạm vi được phân quyền.');
        $scope['company'] ??= $company;
        $scope['facility'] ??= $facility;
        $filters = self::filters($query, $now);
        // One consistent read snapshot for KPIs, charts and tables while payments may be confirmed.
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $this->pdo->beginTransaction();
        }
        try {
            $companyOptions = $this->model->companies($scope['role'] === 'QUAN_TRI_TAP_DOAN' ? null : $scope['company']);
            $companies = array_values(array_filter($companyOptions, static fn($c) => $scope['company'] === null || (int) $c['ma_cong_ty'] === $scope['company']));
            if ($scope['company'] !== null && !$companies) throw new HttpException(403, 'Công ty không thuộc phạm vi hợp lệ.');
            $facilityOptions = $this->model->facilities(['company' => $scope['role'] === 'QUAN_TRI_TAP_DOAN' ? null : $scope['company'],
                'facility' => in_array($scope['role'], ['QUAN_LY_CO_SO', 'NHAN_VIEN'], true) ? $scope['facility'] : null]);
            $facilities = array_values(array_filter($facilityOptions, static fn($f) => $scope['company'] === null || (int) $f['ma_cong_ty'] === $scope['company']));
            if ($scope['facility'] !== null && !in_array($scope['facility'], array_map('intval', array_column($facilities, 'ma_co_so')), true)) throw new HttpException(403, 'Cơ sở không thuộc phạm vi được phân quyền.');
            $selected = array_values(array_filter($facilities, static fn($f) => $scope['facility'] === null || (int) $f['ma_co_so'] === $scope['facility']));
            // A facility user receives neither a list nor names of sibling facilities/companies.
            if (in_array($scope['role'], ['QUAN_LY_CO_SO', 'NHAN_VIEN'], true)) $facilities = $selected;
            $totals = $this->model->revenue($scope, $filters)[0];
            $lifetime = $this->model->revenue($scope, null)[0];
            $states = array_fill_keys(['CHO_XAC_NHAN', 'CHO_THANH_TOAN', 'DA_XAC_NHAN', 'HOAN_THANH', 'DA_HUY', 'HET_HAN'], 0);
            foreach ($this->model->bookingStates($scope, $filters) as $row) $states[$row['status']] = (int) $row['total'];
            $groupType = $scope['role'] === 'QUAN_TRI_TAP_DOAN' ? 'company' : 'facility';
            $revenueRows = array_column($this->model->revenue($scope, $filters, $groupType), null, 'bucket');
            $groups = [];
            foreach ($groupType === 'company' ? $companies : $selected as $row) {
                $id = (int) $row[$groupType === 'company' ? 'ma_cong_ty' : 'ma_co_so'];
                if ($groupType === 'company' && $scope['facility'] !== null && !in_array($id, array_map('intval', array_column($selected, 'ma_cong_ty')), true)) continue;
                $groups[] = ['id' => $id, 'name' => $row[$groupType === 'company' ? 'ten_cong_ty' : 'ten_co_so'],
                    'facilities' => count(array_filter($selected, static fn($f) => (int) $f['ma_cong_ty'] === $id))]
                    + ($revenueRows[$id] ?? ['bookings' => 0, 'courts' => '0', 'services' => '0', 'total' => '0']);
            }
            usort($groups, static fn($a, $b) => (float) $b['total'] <=> (float) $a['total'] ?: $a['id'] <=> $b['id']);
            $series = array_column($this->model->revenue($scope, $filters, $filters['group_by']), null, 'bucket');
            $timeline = [];
            $cursor = new DateTimeImmutable($filters['from'], new DateTimeZone('Asia/Ho_Chi_Minh'));
            if ($filters['group_by'] === 'month') $cursor = $cursor->modify('first day of this month');
            while ($cursor->format('Y-m-d') <= $filters['to']) {
                $bucket = $cursor->format($filters['group_by'] === 'month' ? 'Y-m' : 'Y-m-d');
                $timeline[] = ['label' => $cursor->format($filters['group_by'] === 'month' ? 'm/Y' : 'd/m'), 'date' => $bucket,
                    'value' => (string) ($series[$bucket]['total'] ?? '0')];
                $cursor = $cursor->modify($filters['group_by'] === 'month' ? '+1 month' : '+1 day');
            }
            $result = compact('scope', 'filters', 'companies', 'facilities', 'companyOptions', 'facilityOptions', 'totals', 'lifetime', 'states', 'groups', 'groupType', 'timeline');
            $result['assets'] = $this->model->assets($scope) + ['companies' => $scope['facility'] === null ? count($companies) : count(array_unique(array_column($selected, 'ma_cong_ty'))),
                'facilities' => count($selected), 'active_facilities' => count(array_filter($selected, static fn($f) => $f['trang_thai'] === 'HOAT_DONG'))];
            $result['recent'] = $this->model->recent($scope, $filters);
            $result['popular_courts'] = $this->model->popular($scope, $filters, false);
            $result['popular_services'] = $this->model->popular($scope, $filters, true);
            $result['catalog'] = $scope['role'] === 'QUAN_TRI_TAP_DOAN' ? $this->model->catalog() : [];
            if ($report) {
                $facilityRevenue = array_column($this->model->revenue($scope, $filters, 'facility'), null, 'bucket');
                $result['facilityGroups'] = array_map(static fn($row) => ['name' => $row['ten_co_so'], 'company' => $row['ten_cong_ty']]
                    + ($facilityRevenue[(int) $row['ma_co_so']] ?? ['bookings' => 0, 'courts' => '0', 'services' => '0', 'total' => '0']), $selected);
                $result['reviewFunds'] = $this->model->reviewFunds($scope, $filters);
                $result['transactions'] = $this->model->transactions($scope, $filters);
            }
            if ($ownsTransaction) $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public static function money(string|int $amount): string
    {
        // Format DECIMAL aggregates as strings: chart geometry may use floats, monetary totals never do.
        if (!preg_match('/\A([0-9]+)(?:\.([0-9]{1,2}))?\z/', (string) $amount, $parts)) throw new \UnexpectedValueException('Invalid dashboard amount');
        $fraction = str_pad($parts[2] ?? '', 2, '0');
        return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $parts[1]) . ($fraction === '00' ? '' : ',' . $fraction) . ' đ';
    }
}
