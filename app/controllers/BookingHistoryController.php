<?php
namespace App\Controllers;

use App\Models\Booking;
use Core\Application;
use Core\Controller;
use Core\HttpException;

final class BookingHistoryController extends Controller
{
    public const STATUSES = [
        'CHO_THANH_TOAN' => ['Chờ thanh toán', 'pending'],
        'DA_XAC_NHAN' => ['Đã xác nhận', 'confirmed'],
        'HOAN_THANH' => ['Hoàn thành', 'completed'],
        'DA_HUY' => ['Đã hủy', 'cancelled'],
        'HET_HAN' => ['Hết hạn', 'expired'],
        'CHO_XAC_NHAN' => ['Chờ xác nhận', 'pending'],
    ];

    public function __construct(private Application $app) {}

    public function index(): void
    {
        $user = $this->app->auth()->user();
        if (!$user || $user['ten_vai_tro'] !== 'KHACH_HANG' || empty($user['ma_khach_hang'])) {
            throw new HttpException(403, 'Chức năng này dành cho tài khoản khách hàng.');
        }
        $status = $_GET['status'] ?? '';
        $page = $_GET['page'] ?? '1';
        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';
        $sort = $_GET['sort'] ?? 'newest';
        foreach ([$from, $to] as $date) {
            $validFormat = is_string($date) && preg_match('/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}\z/', $date);
            $parsed = $validFormat ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
            if (!is_string($date) || ($date !== '' && (!$validFormat
                || !$parsed || $parsed->format('Y-m-d') !== $date))) {
                throw new HttpException(422, 'Ngày đặt không hợp lệ. Vui lòng chọn ngày theo định dạng năm-tháng-ngày.');
            }
        }
        if ($from !== '' && $to !== '' && $from > $to) throw new HttpException(422, 'Từ ngày phải nhỏ hơn hoặc bằng Đến ngày.');
        if (!is_string($sort) || !in_array($sort, ['newest', 'oldest'], true)) throw new HttpException(422, 'Thứ tự sắp xếp không hợp lệ.');
        if (!is_string($status) || ($status !== '' && !isset(self::STATUSES[$status]))
            || !is_string($page) || !preg_match('/\A[1-9][0-9]{0,8}\z/', $page)) {
            throw new HttpException(422, 'Bộ lọc hoặc số trang không hợp lệ.');
        }
        $history = (new Booking($this->app->pdo()))->history((int) $user['ma_khach_hang'], $status, (int) $page, $from, $to, $sort);
        header('Cache-Control: private, no-store');
        $this->render('courts/history', [
            'title' => 'Lịch sử đặt sân', 'history' => $history,
            'status' => $status, 'statuses' => self::STATUSES,
            'from' => $from, 'to' => $to, 'sort' => $sort,
        ]);
    }
}
