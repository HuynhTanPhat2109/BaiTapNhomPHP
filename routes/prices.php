<?php

use App\Controllers\admin\BangGiaController;
use App\Middleware\RoleMiddleware;

$prices = new BangGiaController($app);
foreach (['/admin' => 'QUAN_TRI_TAP_DOAN', '/company' => 'QUAN_LY_CONG_TY', '/facility' => 'QUAN_LY_CO_SO', '/staff' => 'NHAN_VIEN'] as $root => $role) {
    $allowed = [new RoleMiddleware($app, [$role])];
    $prefix = $root . '/bang-gia';
    $router->get($prefix, [$prices, 'index'], $allowed);
    $router->get($prefix . '/show', [$prices, 'show'], $allowed);
    if ($role === 'NHAN_VIEN') continue;
    $router->get($prefix . '/create', [$prices, 'create'], $allowed);
    $router->get($prefix . '/edit', [$prices, 'edit'], $allowed);
    $router->post($prefix . '/store', [$prices, 'store'], $allowed);
    $router->post($prefix . '/update', [$prices, 'update'], $allowed);
    $router->post($prefix . '/status', [$prices, 'changeStatus'], $allowed);
}
