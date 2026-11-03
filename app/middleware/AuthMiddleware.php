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
            // Only remember this public route key; never trust a supplied return URL.
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
                && ($_GET['route'] ?? '') === '/booking/history') {
                $_SESSION['auth_return'] = '/booking/history';
            }
            header('Location: ' . url('/login'), true, 303);
            exit;
        }
    }
}
