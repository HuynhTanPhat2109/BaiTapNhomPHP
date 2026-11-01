<?php
$courtBrowser = new App\Controllers\CourtBrowserController($app);
$router->get('/courts', [$courtBrowser, 'index']);
$router->get('/courts/show', [$courtBrowser, 'show']);
$router->get('/courts/panel', [$courtBrowser, 'panel']);
$router->get('/courts/schedule', [$courtBrowser, 'schedule']);

$booking = new App\Controllers\BookingController($app);
$router->post('/booking/quote', [$booking, 'quote']);
$router->post('/booking/start', [$booking, 'start']);
$router->get('/booking/details', [$booking, 'details']);
$router->post('/booking/review', [$booking, 'review']);
$router->post('/booking/confirm', [$booking, 'confirm']); // Controller checks draft owner + customer role.
$router->get('/booking/wait', [$booking, 'waiting'], [new App\Middleware\RoleMiddleware($app, ['KHACH_HANG'])]);
$router->post('/booking/cancel', [$booking, 'cancel'], [new App\Middleware\RoleMiddleware($app, ['KHACH_HANG'])]);
