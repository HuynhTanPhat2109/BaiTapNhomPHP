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
            ['label' => 'Quản lý booking', 'icon' => 'calendar', 'route' => null, 'template' => null],
            ['label' => 'Thanh toán', 'icon' => 'payment', 'route' => null, 'template' => null],
            ['label' => 'Hóa đơn', 'icon' => 'invoice', 'route' => null, 'template' => null],
        ],
    ],
    'QUAN_LY_CONG_TY' => [
        'CÔNG TY' => [
            ['label' => 'Tổng quan công ty', 'icon' => 'overview', 'route' => '/company', 'template' => 'company/index'],
            ['label' => 'Nhân viên', 'icon' => 'people', 'route' => '/company/employees', 'template' => 'company/employees'],
            ['label' => 'Dịch vụ', 'icon' => 'service', 'route' => '/company/dich-vu', 'template' => 'services/page'],
        ],
    ],
];
