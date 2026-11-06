<?php

namespace App\Controllers\admin;

use App\Services\CustomerManagementService;
use Core\Application;
use Core\Controller;

final class CustomerController extends Controller
{
    public function __construct(private Application $app) {}

    public function index(): void
    {
        $user = $this->app->auth()->user() ?? [];
        $result = (new CustomerManagementService($this->app->pdo()))->listing($user, $_GET);
        $this->render('admin/customers/index', $result + ['title' => 'Quản lý khách hàng', 'user' => $user]);
    }

    public function show(): void
    {
        $user = $this->app->auth()->user() ?? [];
        $result = (new CustomerManagementService($this->app->pdo()))->detail($user, $_GET['id'] ?? null, $_GET);
        $this->render('admin/customers/show', $result + ['title' => 'Chi tiết khách hàng', 'user' => $user]);
    }
}
