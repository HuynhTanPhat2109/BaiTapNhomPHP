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
        $isAccountPage = in_array($template, ['auth/account', 'auth/settings'], true);
        $isAdminAccount = $isAccountPage
            && ($data['user']['ten_vai_tro'] ?? null) === 'QUAN_TRI_TAP_DOAN';
        $layout = (str_starts_with($template, 'admin/') || $isAdminAccount) ? 'admin'
            : (str_starts_with($template, 'courts/') ? 'courts'
            : (in_array($template, ['auth/login', 'auth/register'], true) ? 'auth' : 'main'));
        require dirname(__DIR__) . '/app/views/layouts/' . $layout . '.php';
    }
}
