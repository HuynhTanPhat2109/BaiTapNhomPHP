<?php

use App\Controllers\BankController;
use App\Controllers\PaymentController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

$bankController = new BankController($app);
$paymentController = new PaymentController($app);

// 1. Quản lý tài khoản ngân hàng của cơ sở (Dành cho Quản trị viên, Quản lý công ty, Quản lý cơ sở)
$managerRoles = [new AuthMiddleware($app), new RoleMiddleware($app, ['QUAN_TRI_TAP_DOAN', 'QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO'])];

$router->get('/facility/bank', [$bankController, 'index'], $managerRoles);
$router->get('/facility/bank/create', [$bankController, 'create'], $managerRoles);
$router->post('/facility/bank/store', [$bankController, 'store'], $managerRoles);
$router->get('/facility/bank/edit', [$bankController, 'edit'], $managerRoles);
$router->post('/facility/bank/update', [$bankController, 'update'], $managerRoles);
$router->post('/facility/bank/set-default', [$bankController, 'setDefault'], $managerRoles);
$router->post('/facility/bank/toggle-status', [$bankController, 'toggleStatus'], $managerRoles);

// 2. Trang thanh toán VietQR dành cho khách hàng
$router->get('/payment', [$paymentController, 'show'], [new RoleMiddleware($app, ['KHACH_HANG'])]);
$router->get('/payment/status', [$paymentController, 'status']);
$router->get('/payment/qr', [$paymentController, 'qrImage']);
