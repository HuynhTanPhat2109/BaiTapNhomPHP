<?php

use App\Controllers\HomeController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

$home = new HomeController($app);
$router->get('/', [$home, 'index']);
$router->get('/account', [$home, 'account'], [new AuthMiddleware($app)]);

// Auth-only landing pages, not implementations of business modules.
$areas = [
    '/admin' => ['Quản trị tập đoàn', ['QUAN_TRI_TAP_DOAN']],
    '/company' => ['Quản lý công ty', ['QUAN_LY_CONG_TY']],
    '/facility' => ['Quản lý cơ sở', ['QUAN_LY_CO_SO']],
    '/staff' => ['Nhân viên', ['NHAN_VIEN']],
    '/customer' => ['Khách hàng', ['KHACH_HANG']],
];
foreach ($areas as $path => [$title, $roles]) {
    $router->get($path, static function () use ($home, $title): void {
        $home->rolePage($title);
    }, [new RoleMiddleware($app, $roles)]);
}

require __DIR__ . '/auth.php';
