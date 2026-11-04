<?php

use App\Controllers\admin\CoSoController;
use App\Controllers\admin\SanController;
use App\Middleware\RoleMiddleware;

$facilityControllers = ['co-so' => new CoSoController($app), 'san' => new SanController($app)];
foreach (['/admin' => 'QUAN_TRI_TAP_DOAN', '/company' => 'QUAN_LY_CONG_TY', '/facility' => 'QUAN_LY_CO_SO', '/staff' => 'NHAN_VIEN'] as $root => $role) {
    $allowed = [new RoleMiddleware($app, [$role])];
    foreach ($facilityControllers as $module => $controller) {
        $prefix = $root . '/' . $module;
        $router->get($prefix, [$controller, 'index'], $allowed);
        $router->get($prefix . '/show', [$controller, 'show'], $allowed);
        if ($role === 'NHAN_VIEN') continue;
        $router->get($prefix . '/edit', [$controller, 'edit'], $allowed);
        $router->post($prefix . '/update', [$controller, 'update'], $allowed);
        if ($role !== 'QUAN_LY_CO_SO' || $module !== 'co-so') {
            $router->get($prefix . '/create', [$controller, 'create'], $allowed);
            $router->post($prefix . '/store', [$controller, 'store'], $allowed);
        }
        if ($role === 'QUAN_TRI_TAP_DOAN') {
            $router->get($prefix . '/delete', [$controller, 'confirmDelete'], $allowed);
            $router->post($prefix . '/delete', [$controller, 'destroy'], $allowed);
        }
    }
}
