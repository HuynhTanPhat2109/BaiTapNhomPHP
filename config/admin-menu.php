<?php

// Navigation only; each route remains protected by its own middleware.
return [
    'QUAN_TRI_TAP_DOAN' => [
        'TỔNG QUAN' => [
            ['label' => 'Tổng quan', 'icon' => 'overview', 'route' => '/admin', 'template' => 'admin/dashboard'],
        ],
        'QUẢN LÝ HỆ THỐNG' => [
            ['label' => 'Công ty nhượng quyền', 'icon' => 'company', 'route' => '/admin/companies', 'template' => 'admin/companies'],
            ['label' => 'Hợp đồng nhượng quyền', 'icon' => 'contract', 'route' => '/admin/contracts', 'template' => 'admin/contracts'],
            ['label' => 'Nhân viên', 'icon' => 'people', 'route' => '/admin/employees', 'template' => 'admin/employees'],
            ['label' => 'Bộ môn', 'icon' => 'sport', 'route' => '/admin/bo-mon', 'template' => 'admin/bo-mon'],
            ['label' => 'Loại sân', 'icon' => 'layers', 'route' => '/admin/loai-san', 'template' => 'admin/loai-san'],
            ['label' => 'Cơ sở thể thao', 'icon' => 'location', 'route' => '/admin/co-so', 'template' => 'admin/co-so'],
            ['label' => 'Sân thể thao', 'icon' => 'court', 'route' => '/admin/san', 'template' => 'admin/san'],
            ['label' => 'Bảng giá', 'icon' => 'price', 'route' => '/admin/bang-gia', 'template' => 'admin/bang-gia'],
            ['label' => 'Dịch vụ', 'icon' => 'service', 'route' => '/admin/dich-vu', 'template' => 'admin/dich-vu'],
        ],
        'VẬN HÀNH VÀ THANH TOÁN' => [
            ['label' => 'Quản lý booking', 'icon' => 'calendar', 'route' => '/admin/bookings', 'template' => 'admin/bookings'],
            ['label' => 'Tài khoản ngân hàng', 'icon' => 'payment', 'route' => '/facility/bank', 'template' => 'facility/bank'],
            ['label' => 'Thống kê doanh thu', 'icon' => 'overview', 'route' => '/admin/revenue', 'template' => 'admin/revenue'],
            ['label' => 'Hóa đơn', 'icon' => 'invoice', 'route' => '/admin/invoices', 'template' => 'admin/invoices'],
        ],
    ],
    'QUAN_LY_CONG_TY' => [
        'CÔNG TY' => [
            ['label' => 'Tổng quan công ty', 'icon' => 'overview', 'route' => '/company', 'template' => 'company/dashboard'],
            ['label' => 'Thông tin công ty', 'icon' => 'company', 'route' => '/company/profile', 'template' => 'company/index'],
            ['label' => 'Cơ sở thể thao', 'icon' => 'location', 'route' => '/company/co-so', 'template' => 'admin/co-so'],
            ['label' => 'Sân thể thao', 'icon' => 'court', 'route' => '/company/san', 'template' => 'admin/san'],
            ['label' => 'Bảng giá', 'icon' => 'price', 'route' => '/company/bang-gia', 'template' => 'admin/bang-gia'],
            ['label' => 'Quản lý nhân viên', 'icon' => 'people', 'route' => '/company/employees', 'template' => 'company/employees'],
            ['label' => 'Dịch vụ', 'icon' => 'service', 'route' => '/company/dich-vu', 'template' => 'services/page'],
        ],
        'VẬN HÀNH' => [
            ['label' => 'Quản lý booking', 'icon' => 'calendar', 'route' => '/admin/bookings', 'template' => 'admin/bookings'],
            ['label' => 'Tài khoản ngân hàng', 'icon' => 'payment', 'route' => '/facility/bank', 'template' => 'facility/bank'],
            ['label' => 'Thống kê doanh thu', 'icon' => 'overview', 'route' => '/company/revenue', 'template' => 'admin/revenue'],
            ['label' => 'Hóa đơn', 'icon' => 'invoice', 'route' => '/admin/invoices', 'template' => 'admin/invoices'],
        ],
    ],
    'QUAN_LY_CO_SO' => [
        'CƠ SỞ' => [
            ['label' => 'Tổng quan cơ sở', 'icon' => 'overview', 'route' => '/facility', 'template' => 'facility/dashboard'],
            ['label' => 'Quản lý nhân viên', 'icon' => 'people', 'route' => '/facility/employees', 'template' => 'company/employees'],
            ['label' => 'Thông tin cơ sở', 'icon' => 'location', 'route' => '/facility/co-so', 'template' => 'admin/co-so'],
            ['label' => 'Sân thể thao', 'icon' => 'court', 'route' => '/facility/san', 'template' => 'admin/san'],
            ['label' => 'Bảng giá', 'icon' => 'price', 'route' => '/facility/bang-gia', 'template' => 'admin/bang-gia'],
            ['label' => 'Dịch vụ', 'icon' => 'service', 'route' => '/facility/dich-vu', 'template' => 'services/page'],
        ],
        'VẬN HÀNH' => [
            ['label' => 'Quản lý booking', 'icon' => 'calendar', 'route' => '/admin/bookings', 'template' => 'admin/bookings'],
            ['label' => 'Hóa đơn', 'icon' => 'invoice', 'route' => '/admin/invoices', 'template' => 'admin/invoices'],
            ['label' => 'Tài khoản ngân hàng', 'icon' => 'payment', 'route' => '/facility/bank', 'template' => 'facility/bank'],
            ['label' => 'Thống kê doanh thu', 'icon' => 'overview', 'route' => '/facility/revenue', 'template' => 'admin/revenue'],
        ],
    ],
    'NHAN_VIEN' => [
        'VẬN HÀNH' => [
            ['label' => 'Tổng quan ca trực', 'icon' => 'overview', 'route' => '/staff', 'template' => 'staff/dashboard'],
            ['label' => 'Thông tin cơ sở', 'icon' => 'location', 'route' => '/staff/co-so', 'template' => 'admin/co-so'],
            ['label' => 'Sân thể thao', 'icon' => 'court', 'route' => '/staff/san', 'template' => 'admin/san'],
            ['label' => 'Bảng giá', 'icon' => 'price', 'route' => '/staff/bang-gia', 'template' => 'admin/bang-gia'],
            ['label' => 'Quản lý booking', 'icon' => 'calendar', 'route' => '/admin/bookings', 'template' => 'admin/bookings'],
            ['label' => 'Hóa đơn', 'icon' => 'invoice', 'route' => '/admin/invoices', 'template' => 'admin/invoices'],
            ['label' => 'Dịch vụ', 'icon' => 'service', 'route' => '/staff/dich-vu', 'template' => 'services/page'],
            ['label' => 'Tài khoản ngân hàng', 'icon' => 'payment', 'route' => '/facility/bank', 'template' => 'facility/bank'],
            ['label' => 'Thống kê doanh thu', 'icon' => 'overview', 'route' => '/staff/revenue', 'template' => 'admin/revenue'],
        ],
    ],
];
