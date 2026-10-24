<?php

use App\Controllers\admin\BangGiaController;
use App\Middleware\RoleMiddleware;

$prices = new BangGiaController($app);
$pricesAdminOnly = [new RoleMiddleware($app, ['QUAN_TRI_TAP_DOAN'])];
$router->get('/admin/bang-gia', [$prices, 'index'], $pricesAdminOnly);
$router->get('/admin/bang-gia/create', [$prices, 'create'], $pricesAdminOnly);
$router->get('/admin/bang-gia/show', [$prices, 'show'], $pricesAdminOnly);
$router->get('/admin/bang-gia/edit', [$prices, 'edit'], $pricesAdminOnly);
$router->post('/admin/bang-gia/store', [$prices, 'store'], $pricesAdminOnly);
$router->post('/admin/bang-gia/update', [$prices, 'update'], $pricesAdminOnly);
$router->post('/admin/bang-gia/status', [$prices, 'changeStatus'], $pricesAdminOnly);
