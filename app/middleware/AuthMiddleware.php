<?php

namespace App\Middleware;

use Core\Application;

final class AuthMiddleware
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function __invoke(): void
    {
        if (!isset($_SESSION['account_id']) || !$this->app->auth()->user()) {
            header('Location: ' . url('/login'), true, 303);
            exit;
        }
    }
}
