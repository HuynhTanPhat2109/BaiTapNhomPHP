<?php

use App\Controllers\admin\AdminBookingController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

$adminBooking = new AdminBookingController($app);

// Các vai trò nội bộ được phân quyền truy cập và thao tác trên đơn đặt sân
$bookingRoles = [
    new AuthMiddleware($app),
    new RoleMiddleware($app, ['QUAN_TRI_TAP_DOAN', 'QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO', 'NHAN_VIEN'])
];

$router->get('/admin/bookings', [$adminBooking, 'index'], $bookingRoles);
$router->get('/admin/bookings/show', [$adminBooking, 'show'], $bookingRoles);
$router->post('/admin/bookings/confirm', [$adminBooking, 'confirm'], $bookingRoles);
$router->post('/admin/bookings/reconcile', [$adminBooking, 'reconcile'], $bookingRoles);
$router->post('/admin/bookings/cancel', [$adminBooking, 'cancel'], $bookingRoles);
