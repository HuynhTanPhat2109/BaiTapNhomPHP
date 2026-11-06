<?php

use App\Controllers\admin\CustomerController;
use App\Middleware\RoleMiddleware;
use App\Services\CustomerManagementService;

$customers = new CustomerController($app);
$customerRoles = [new RoleMiddleware($app, CustomerManagementService::ROLES)];
$router->get('/admin/customers', [$customers, 'index'], $customerRoles);
$router->get('/admin/customers/show', [$customers, 'show'], $customerRoles);
