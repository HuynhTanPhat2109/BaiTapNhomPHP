<?php

// Enable an item only after its real GET route allows QUAN_TRI_TAP_DOAN.
return [
    'TỔNG QUAN' => [
        ['label' => 'Tổng quan', 'icon' => 'overview', 'route' => '/admin', 'template' => 'admin/dashboard'],
    ],
    'QUẢN LÝ HỆ THỐNG' => [
        ['label' => 'Công ty nhượng quyền', 'icon' => 'company', 'route' => '/admin/companies', 'template' => 'admin/companies'],
        ['label' => 'Hợp đồng nhượng quyền', 'icon' => 'contract', 'route' => null, 'template' => null],
        ['label' => 'Nhân viên', 'icon' => 'people', 'route' => null, 'template' => null],
        ['label' => 'Bộ môn', 'icon' => 'sport', 'route' => '/admin/bo-mon', 'template' => 'admin/bo-mon'],
        ['label' => 'Loại sân', 'icon' => 'layers', 'route' => '/admin/loai-san', 'template' => 'admin/loai-san'],
        ['label' => 'Cơ sở thể thao', 'icon' => 'location', 'route' => '/admin/co-so', 'template' => 'admin/co-so'],
        ['label' => 'Sân thể thao', 'icon' => 'court', 'route' => '/admin/san', 'template' => 'admin/san'],
        ['label' => 'Bảng giá', 'icon' => 'price', 'route' => null, 'template' => null],
        ['label' => 'Dịch vụ', 'icon' => 'service', 'route' => null, 'template' => null],
    ],
    'VẬN HÀNH VÀ THANH TOÁN' => [
        ['label' => 'Quản lý booking', 'icon' => 'calendar', 'route' => null, 'template' => null],
        ['label' => 'Thanh toán', 'icon' => 'payment', 'route' => null, 'template' => null],
        ['label' => 'Hóa đơn', 'icon' => 'invoice', 'route' => null, 'template' => null],
    ],
];
