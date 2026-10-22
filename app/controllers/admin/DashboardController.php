<?php

namespace App\Controllers\admin;

use App\Models\BoMon;
use App\Models\LoaiSan;
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
        $this->render('admin/dashboard', [
            'title' => 'Quản trị tập đoàn', 'user' => $this->app->auth()->user(),
            'stats' => [
                'bo_mon' => (new BoMon($this->app->pdo()))->countAll(),
                'loai_san' => (new LoaiSan($this->app->pdo()))->countAll(),
            ],
        ]);
    }
}
