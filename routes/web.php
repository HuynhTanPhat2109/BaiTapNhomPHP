<?php

use App\Controllers\HomeController;
use App\Controllers\AccountController;
use App\Controllers\CompanyController;
use App\Controllers\ContractController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

$home = new HomeController($app);
$account = new AccountController($app);
$company = new CompanyController($app);
$contract = new ContractController($app);
$router->get('/', [$home, 'index']);
$router->get('/account', [$account, 'show'], [new AuthMiddleware($app)]);
$router->get('/account/settings', [$account, 'settings'], [new AuthMiddleware($app)]);
$router->post('/account', [$account, 'updateProfile'], [new RoleMiddleware($app, ['KHACH_HANG'])]);
$router->post('/account/password', [$account, 'changePassword'], [new AuthMiddleware($app)]);

$router->get('/admin/companies', [$company, 'index'], [new RoleMiddleware($app, ['QUAN_TRI_TAP_DOAN'])]);
$router->get('/admin/companies/create', [$company, 'create'], [new RoleMiddleware($app, ['QUAN_TRI_TAP_DOAN'])]);
$router->post('/admin/companies', [$company, 'store'], [new RoleMiddleware($app, ['QUAN_TRI_TAP_DOAN'])]);
$router->get('/admin/companies/edit', [$company, 'edit'], [new RoleMiddleware($app, ['QUAN_TRI_TAP_DOAN'])]);
$router->post('/admin/companies/update', [$company, 'update'], [new RoleMiddleware($app, ['QUAN_TRI_TAP_DOAN'])]);
$router->post('/admin/companies/status', [$company, 'changeStatus'], [new RoleMiddleware($app, ['QUAN_TRI_TAP_DOAN'])]);

$router->get('/admin/contracts', [$contract, 'index'], [new RoleMiddleware($app, ['QUAN_TRI_TAP_DOAN'])]);
$router->get('/admin/contracts/create', [$contract, 'create'], [new RoleMiddleware($app, ['QUAN_TRI_TAP_DOAN'])]);
$router->post('/admin/contracts', [$contract, 'store'], [new RoleMiddleware($app, ['QUAN_TRI_TAP_DOAN'])]);
$router->get('/admin/contracts/edit', [$contract, 'edit'], [new RoleMiddleware($app, ['QUAN_TRI_TAP_DOAN'])]);
$router->post('/admin/contracts/update', [$contract, 'update'], [new RoleMiddleware($app, ['QUAN_TRI_TAP_DOAN'])]);
$router->post('/admin/contracts/status', [$contract, 'changeStatus'], [new RoleMiddleware($app, ['QUAN_TRI_TAP_DOAN'])]);

// Auth-only landing pages, not implementations of business modules.
$areas = [
    '/company' => ['Quản lý công ty', ['QUAN_LY_CONG_TY']],
    '/facility' => ['Quản lý cơ sở', ['QUAN_LY_CO_SO']],
    '/staff' => ['Nhân viên', ['NHAN_VIEN']],
];
foreach ($areas as $path => [$title, $roles]) {
    $router->get($path, static function () use ($home, $title): void {
        $home->rolePage($title);
    }, [new RoleMiddleware($app, $roles)]);
}

$router->get('/customer', static function (): void {
    header('Location: ' . url('/'), true, 303);
    exit;
}, [new RoleMiddleware($app, ['KHACH_HANG'])]);

require __DIR__ . '/auth.php';
require __DIR__ . '/catalog.php';
require __DIR__ . '/facilities.php';
require __DIR__ . '/courts.php';
