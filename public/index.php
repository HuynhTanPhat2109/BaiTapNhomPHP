<?php

use Core\Application;
use Core\HttpException;
use Core\Session;
use Core\View;

ini_set('display_errors', '0');
require_once __DIR__ . '/../vendor/autoload.php';

try {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
    date_default_timezone_set('Asia/Ho_Chi_Minh');
    Session::start();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'");
    $app = new Application();
    $router = new Core\Router();
    require __DIR__ . '/../routes/web.php';
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', request_path());
} catch (HttpException $e) {
    http_response_code($e->status);
    View::render('errors/error', ['title' => (string) $e->status, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    // Do not expose SQL, credentials, request fields or stack traces.
    error_log('Application failure: ' . get_class($e) . ' at ' . basename($e->getFile()) . ':' . $e->getLine());
    http_response_code(500);
    try {
        View::render('errors/error', [
            'title' => '500',
            'message' => 'Hệ thống tạm thời không khả dụng. Vui lòng thử lại sau.',
        ]);
    } catch (Throwable $renderError) {
        // Keep an independent fallback if the view/config itself is unavailable.
        echo '<!doctype html><html lang="vi"><meta charset="utf-8"><title>500</title>'
            . '<h1>500</h1><p>Hệ thống tạm thời không khả dụng. Vui lòng thử lại sau.</p></html>';
    }
}
