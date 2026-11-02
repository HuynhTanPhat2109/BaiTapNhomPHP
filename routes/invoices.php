<?php

use App\Controllers\admin\AdminInvoiceController;
use App\Controllers\InvoiceController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

$adminInvoice = new AdminInvoiceController($app);
$clientInvoice = new InvoiceController($app);

// 1. Tuyến đường quản lý hóa đơn nội bộ (Admin)
$adminRoles = [
    new AuthMiddleware($app),
    new RoleMiddleware($app, ['QUAN_TRI_TAP_DOAN', 'QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO', 'NHAN_VIEN'])
];

$router->get('/admin/invoices', [$adminInvoice, 'index'], $adminRoles);
$router->get('/admin/invoices/show', [$adminInvoice, 'show'], $adminRoles);
$router->get('/admin/invoices/print', [$adminInvoice, 'print'], $adminRoles);

// 2. Tuyến đường xem hóa đơn phía khách hàng
$customerRoles = [
    new AuthMiddleware($app),
    new RoleMiddleware($app, ['KHACH_HANG'])
];

$router->get('/invoices', [$clientInvoice, 'index'], $customerRoles);
$router->get('/invoices/show', [$clientInvoice, 'show'], $customerRoles);
$router->get('/invoices/print', [$clientInvoice, 'print'], $customerRoles);
