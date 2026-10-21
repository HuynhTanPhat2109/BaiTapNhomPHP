<?php

use App\Controllers\admin\BoMonController;
use App\Controllers\admin\DashboardController;
use App\Controllers\admin\LoaiSanController;
use App\Middleware\RoleMiddleware;

$catalogAdminOnly = [new RoleMiddleware($app, ['QUAN_TRI_TAP_DOAN'])];
$router->get('/admin', [new DashboardController($app), 'index'], $catalogAdminOnly);
foreach ([
    '/admin/bo-mon' => new BoMonController($app),
    '/admin/loai-san' => new LoaiSanController($app),
] as $prefix => $controller) {
    $router->get($prefix, [$controller, 'index'], $catalogAdminOnly);
    $router->get($prefix . '/create', [$controller, 'create'], $catalogAdminOnly);
    $router->post($prefix . '/store', [$controller, 'store'], $catalogAdminOnly);
    $router->get($prefix . '/show', [$controller, 'show'], $catalogAdminOnly);
    $router->get($prefix . '/edit', [$controller, 'edit'], $catalogAdminOnly);
    $router->post($prefix . '/update', [$controller, 'update'], $catalogAdminOnly);
    $router->get($prefix . '/delete', [$controller, 'confirmDelete'], $catalogAdminOnly);
    $router->post($prefix . '/delete', [$controller, 'destroy'], $catalogAdminOnly);
}

