<?php

namespace App\Controllers;

use Core\Application;
use Core\Controller;

final class HomeController extends Controller
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function index(): void
    {
        $this->render('home', ['title' => 'Hệ thống quản lý sân thể thao']);
    }

    public function account(): void
    {
        $this->render('auth/account', ['title' => 'Tài khoản', 'user' => $this->app->auth()->user()]);
    }

    public function rolePage(string $title): void
    {
        $this->render('auth/role', ['title' => $title, 'user' => $this->app->auth()->user()]);
    }
}
