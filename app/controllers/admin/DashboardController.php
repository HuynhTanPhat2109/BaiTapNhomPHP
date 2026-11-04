<?php

namespace App\Controllers\admin;

use App\Services\BookingManagementService;
use App\Services\DashboardService;
use Core\Application;
use Core\Controller;

final class DashboardController extends Controller
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function index(): void
    {
        $user = $this->app->auth()->user() ?? [];
        $scope = BookingManagementService::resolveScope($user);
        $template = match ($scope['role']) {
            'QUAN_TRI_TAP_DOAN' => 'admin/dashboard', 'QUAN_LY_CONG_TY' => 'company/dashboard',
            'QUAN_LY_CO_SO' => 'facility/dashboard', 'NHAN_VIEN' => 'staff/dashboard',
        };
        $revenuePage = str_ends_with(request_path(), '/revenue');
        if ($revenuePage) $template = 'admin/revenue';
        try {
            $dashboard = (new DashboardService($this->app->pdo()))->data($user, $revenuePage ? $_GET : [], null, $revenuePage);
        } catch (\PDOException $e) {
            error_log('Dashboard read failed at ' . basename($e->getFile()) . ':' . $e->getLine());
            $this->render($template, ['title' => 'Tổng quan', 'user' => $user,
                'dashboardError' => 'Chưa thể tải số liệu. Vui lòng thử lại sau; số liệu không được thay thế bằng 0.'], 503);
            return;
        }
        $this->render($template, ['title' => $revenuePage ? 'Thống kê doanh thu' : 'Tổng quan', 'user' => $user, 'dashboard' => $dashboard,
            'stats' => $dashboard['catalog']]);
    }
}
