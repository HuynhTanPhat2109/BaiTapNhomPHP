<?php

namespace App\Middleware;

use Core\Application;
use Core\HttpException;

final class RoleMiddleware
{
    private Application $app;
    private array $roles;

    public function __construct(Application $app, array $roles)
    {
        $this->app = $app;
        $this->roles = $roles;
    }

    public function __invoke(): void
    {
        (new AuthMiddleware($this->app))();
        $user = $this->app->auth()->user();
        if (!$user || !in_array($user['ten_vai_tro'], $this->roles, true)) {
            throw new HttpException(403, 'Bạn không có quyền truy cập trang này.');
        }
    }
}
