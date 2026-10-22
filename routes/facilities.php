<?php

use App\Controllers\admin\CoSoController;
use App\Controllers\admin\SanController;
use App\Middleware\RoleMiddleware;

$facilitiesAdminOnly = [new RoleMiddleware($app, ['QUAN_TRI_TAP_DOAN'])];
foreach ([
    '/admin/co-so' => new CoSoController($app),
    '/admin/san' => new SanController($app),
] as $prefix => $controller) {
    $router->get($prefix, [$controller, 'index'], $facilitiesAdminOnly);
    $router->get($prefix . '/create', [$controller, 'create'], $facilitiesAdminOnly);
    $router->post($prefix . '/store', [$controller, 'store'], $facilitiesAdminOnly);
    $router->get($prefix . '/show', [$controller, 'show'], $facilitiesAdminOnly);
    $router->get($prefix . '/edit', [$controller, 'edit'], $facilitiesAdminOnly);
    $router->post($prefix . '/update', [$controller, 'update'], $facilitiesAdminOnly);
    $router->get($prefix . '/delete', [$controller, 'confirmDelete'], $facilitiesAdminOnly);
    $router->post($prefix . '/delete', [$controller, 'destroy'], $facilitiesAdminOnly);
}
