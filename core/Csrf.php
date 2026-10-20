<?php

namespace Core;

final class Csrf
{
    public static function token(): string
    {
        return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function verify($token): void
    {
        if (!is_string($token) || !isset($_SESSION['_csrf'])
            || !hash_equals($_SESSION['_csrf'], $token)) {
            throw new HttpException(403, 'Phiên biểu mẫu không hợp lệ. Vui lòng tải lại trang và thử lại.');
        }
    }
}
