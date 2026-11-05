<?php
$courtBrowser = new App\Controllers\CourtBrowserController($app);
$router->get('/courts', [$courtBrowser, 'index']);
$router->get('/courts/show', [$courtBrowser, 'show']);
$router->get('/courts/panel', [$courtBrowser, 'panel']);
$router->get('/courts/schedule', [$courtBrowser, 'schedule']);
$router->get('/courts/prices', [$courtBrowser, 'prices']);

$booking = new App\Controllers\BookingController($app);
$bookingHistory = new App\Controllers\BookingHistoryController($app);
$router->get('/booking/history', [$bookingHistory, 'index'], [new App\Middleware\RoleMiddleware($app, ['KHACH_HANG'])]);
$router->get('/booking/history/receipt', [$bookingHistory, 'receipt'], [new App\Middleware\RoleMiddleware($app, ['KHACH_HANG'])]);
$router->post('/booking/quote', [$booking, 'quote']);
$router->post('/booking/start', [$booking, 'start']);
$router->get('/booking/details', [$booking, 'details']);
$router->post('/booking/review', [$booking, 'review']);
$router->post('/booking/confirm', [$booking, 'confirm']); // Controller checks draft owner + customer role.
$router->get('/booking/wait', [$booking, 'waiting'], [new App\Middleware\RoleMiddleware($app, ['KHACH_HANG'])]);
$router->get('/booking/result', [$booking, 'waiting'], [new App\Middleware\RoleMiddleware($app, ['KHACH_HANG'])]);
$router->post('/booking/receipt', [$booking, 'uploadReceipt'], [new App\Middleware\RoleMiddleware($app, ['KHACH_HANG'])]);
$router->get('/booking/receipt', [$booking, 'receipt'], [new App\Middleware\AuthMiddleware($app), new App\Middleware\RoleMiddleware($app,
    ['KHACH_HANG','QUAN_TRI_TAP_DOAN','QUAN_LY_CONG_TY','QUAN_LY_CO_SO','NHAN_VIEN'])]);
$router->post('/booking/expire', [$booking, 'expire'], [new App\Middleware\RoleMiddleware($app, ['KHACH_HANG'])]);
$router->post('/booking/cancel', [$booking, 'cancel'], [new App\Middleware\RoleMiddleware($app, ['KHACH_HANG'])]);
$router->post('/booking/payment', [$booking, 'retryPayment'], [new App\Middleware\RoleMiddleware($app, ['KHACH_HANG'])]);
