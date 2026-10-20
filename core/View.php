<?php

namespace Core;

final class View
{
    public static function render(string $template, array $data = []): void
    {
        // Template names must be constants in controllers, never request input.
        $file = dirname(__DIR__) . '/app/views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('View missing.');
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
            $content = ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        $layout = in_array($template, ['auth/login', 'auth/register'], true) ? 'auth' : 'main';
        require dirname(__DIR__) . '/app/views/layouts/' . $layout . '.php';
    }
}
