<?php

namespace Core;

abstract class Controller
{
    protected function render(string $view, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        View::render($view, $data);
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . url($path), true, 303);
        exit;
    }
}
