<?php
namespace App\Controllers;

use Core\Application;
use Core\Controller;
use Core\HttpException;
use App\Models\CourtBrowser;
use App\Services\AdministrativeAddress;
use App\Services\CourtScheduleService;
use App\Services\CoSoService;

final class CourtBrowserController extends Controller
{
    public function __construct(private Application $app) {}
    private function input(string $key, string $default = ''): string
    {
        $value = $_GET[$key] ?? $default;
        if (!is_string($value) || mb_strlen($value) > 200) throw new HttpException(422, 'Thông tin tìm kiếm không hợp lệ.');
        return trim($value);
    }
    private function model(): CourtBrowser { return new CourtBrowser($this->app->pdo()); }
    public function index(?array $openedFacility = null): void
    {
        $filters = ['q' => $this->input('q'), 'ma_tinh' => $this->input('ma_tinh'), 'ma_phuong_xa' => $this->input('ma_phuong_xa'), 'ma_bo_mon' => $this->input('ma_bo_mon')];
        if ($filters['ma_bo_mon'] !== '' && !CoSoService::validId($filters['ma_bo_mon'])) throw new HttpException(422, 'Bộ môn không hợp lệ.');
        if ($filters['ma_phuong_xa'] !== '' && $filters['ma_tinh'] === '') throw new HttpException(422, 'Chọn tỉnh trước khi chọn phường/xã.');
        if ($openedFacility === null && $this->input('facility') !== '') $openedFacility = $this->facility($this->input('facility'));
        $this->render('courts/index', ['title' => 'Tìm sân của bạn', 'filters' => $filters,
            'openedFacility' => $openedFacility, 'openedCourts' => $openedFacility ? $this->model()->courts((int) $openedFacility['ma_co_so']) : [],
            'provinces' => AdministrativeAddress::provinces(), 'sports' => $this->model()->sports(), 'items' => $this->model()->facilities($filters)]);
    }
    private function facility(?string $id = null): array
    {
        $id ??= $this->input('id');
        if (!CoSoService::validId($id)) throw new HttpException(404, 'Không tìm thấy cơ sở.');
        $items = $this->model()->facilities(['ma_co_so' => $id]);
        if (!$items) throw new HttpException(404, 'Cơ sở không khả dụng.');
        return $items[0];
    }
    public function show(): void
    {
        $facility = $this->facility();
        $this->index($facility);
    }

    public function panel(): void
    {
        $facility = $this->facility();
        $courts = $this->model()->courts((int) $facility['ma_co_so']);
        header('Content-Type: text/html; charset=UTF-8');
        require dirname(__DIR__) . '/views/courts/detail-panel.php';
    }
    public function schedule(): void
    {
        header('Cache-Control: no-store');
        $facility = $this->facility();
        $now = CourtScheduleService::now();
        $date = $this->input('date', $now->format('Y-m-d'));
        CourtScheduleService::validateDate($date, $now);
        $model = $this->model();
        $courts = $model->courts((int) $facility['ma_co_so']);
        $intervals = $model->intervals((int) $facility['ma_co_so'], $date);
        $now = CourtScheduleService::now();
        $schedule = CourtScheduleService::build($facility, $courts, $intervals, $date, $now);
        if ($this->input('status') === '1') {
            header('Content-Type: application/json; charset=UTF-8');
            // Only public slot states. Never include booking IDs, customers, prices or payment data.
            echo json_encode([
                'facility' => (int)$facility['ma_co_so'], 'date' => $date, 'now' => $now->format(DATE_ATOM),
                'slots' => array_map(static fn($slot) => [substr($slot['start'],0,5), substr($slot['end'],0,5)], $schedule['slots']),
                'courts' => array_map(static fn($row) => ['id' => (int)$row['court']['ma_san'], 'states' => array_column($row['cells'],'state')], $schedule['rows']),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            return;
        }
        $selection = null; $error = null;
        if (isset($_GET['court'], $_GET['start'], $_GET['end'])) {
            try { $selection = CourtScheduleService::selection($schedule, $this->input('court'), $this->input('start'), $this->input('end')); }
            catch (HttpException $e) { $error = $e->getMessage(); }
        }
        if ($this->input('preview') === '1') {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code($selection && !$error ? 200 : 422);
            echo json_encode(['selection' => $selection, 'date' => $date, 'error' => $error], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            return;
        }
        $bookingNotice = $_SESSION['booking_notice'] ?? null;
        unset($_SESSION['booking_notice']);
        $this->render('courts/schedule', compact('facility', 'date', 'now', 'schedule', 'selection', 'error', 'bookingNotice') + ['title' => 'Lịch sân'], $error ? 422 : 200);
    }
}
