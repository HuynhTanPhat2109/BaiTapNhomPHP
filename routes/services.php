<?php

use App\Controllers\admin\DichVuController;
use App\Middleware\RoleMiddleware;

$services = new DichVuController($app);
foreach (['/admin' => 'QUAN_TRI_TAP_DOAN', '/company' => 'QUAN_LY_CONG_TY', '/facility' => 'QUAN_LY_CO_SO', '/staff' => 'NHAN_VIEN'] as $area => $role) {
    $prefix = $area . '/dich-vu';
    $allowed = [new RoleMiddleware($app, [$role])];
    $router->get($prefix, [$services, 'index'], $allowed);
    $router->get($prefix . '/show', [$services, 'show'], $allowed);
    if ($role === 'NHAN_VIEN') continue;
    $router->get($prefix . '/create', [$services, 'create'], $allowed);
    $router->get($prefix . '/edit', [$services, 'edit'], $allowed);
    $router->post($prefix . '/store', [$services, 'store'], $allowed);
    $router->post($prefix . '/update', [$services, 'update'], $allowed);
    $router->post($prefix . '/status', [$services, 'changeStatus'], $allowed);
}
