<?php

namespace Core;

final class Session
{
    public const IDLE_TIMEOUT = 1800;
    public const ABSOLUTE_TIMEOUT = 28800;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_name('sports_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => base_path() . '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if (!session_start()) {
            throw new \RuntimeException('Session unavailable.');
        }
    }

    public static function authenticate(int $accountId): void
    {
        if (!session_regenerate_id(true)) {
            throw new \RuntimeException('Session rotation failed.');
        }
        $_SESSION = ['account_id' => $accountId, 'authenticated_at' => time(), 'last_active_at' => time()];
    }

    public static function accountId(): ?int
    {
        $id = $_SESSION['account_id'] ?? null;
        if (!is_int($id) || $id < 1) {
            return null;
        }
        $now = time();
        $created = $_SESSION['authenticated_at'] ?? 0;
        $active = $_SESSION['last_active_at'] ?? 0;
        if (!is_int($created) || !is_int($active) || $created > $now || $active > $now
            || $now - $active >= self::IDLE_TIMEOUT || $now - $created >= self::ABSOLUTE_TIMEOUT) {
            self::forgetAuthentication();
            $_SESSION['notice'] = 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.';
            return null;
        }
        $_SESSION['last_active_at'] = $now;
        return $id;
    }

    public static function forgetAuthentication(): void
    {
        $_SESSION = [];
        if (!session_regenerate_id(true)) {
            throw new \RuntimeException('Session rotation failed.');
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $params['path'],
            'secure' => $params['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_destroy();
    }
}
