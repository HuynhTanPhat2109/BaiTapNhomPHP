<?php

namespace Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler, array $middleware = []): void
    {
        $this->routes['GET'][$path] = [$handler, $middleware];
    }

    public function post(string $path, callable $handler, array $middleware = []): void
    {
        $this->routes['POST'][$path] = [$handler, $middleware];
    }

    public function dispatch(string $method, string $path): void
    {
        if (!isset($this->routes[$method][$path])) {
            $allowed = [];
            foreach ($this->routes as $verb => $routes) {
                if (isset($routes[$path])) {
                    $allowed[] = $verb;
                }
            }
            if ($allowed) {
                header('Allow: ' . implode(', ', $allowed));
                throw new HttpException(405, 'Phương thức không được hỗ trợ.');
            }
            throw new HttpException(404, 'Không tìm thấy trang.');
        }
        if ($method === 'POST') {
            Csrf::verify($_POST['_token'] ?? null);
        }
        [$handler, $middleware] = $this->routes[$method][$path];
        foreach ($middleware as $check) {
            $check();
        }
        $handler();
    }
}
