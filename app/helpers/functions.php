<?php

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(): string
{
    $base = $_ENV['APP_BASE_PATH'] ?? str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $base = '/' . trim($base, '/.');
    if (!preg_match('~^/[a-zA-Z0-9/_-]*$~D', $base)) {
        throw new RuntimeException('Invalid APP_BASE_PATH.');
    }
    return rtrim($base, '/');
}

function url(string $path): string
{
    // Works on XAMPP without requiring mod_rewrite.
    return base_path() . '/index.php?route=' . rawurlencode($path);
}

function request_path(): string
{
    if (isset($_GET['route'])) {
        $path = $_GET['route'];
    } else {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $base = base_path();
        if ($base !== '' && is_string($path) && strpos($path, $base . '/') === 0) {
            $path = substr($path, strlen($base));
        }
        if ($path === '/index.php') {
            $path = '/';
        }
    }
    if (!is_string($path) || !preg_match('~^/[a-zA-Z0-9/_-]*$~D', $path)) {
        throw new Core\HttpException(404, 'Không tìm thấy trang.');
    }
    return rtrim($path, '/') ?: '/';
}

function post_string(string $key, bool $trim = true): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    return $trim ? trim($value) : $value;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Core\Csrf::token()) . '">';
}

function field_error_attributes(string $field, array $errors): string
{
    return isset($errors[$field])
        ? ' aria-invalid="true" aria-describedby="' . e($field . '-error') . '"'
        : '';
}

function field_error(string $field, array $errors): string
{
    if (!isset($errors[$field])) {
        return '';
    }
    return '<small class="field-error" id="' . e($field . '-error') . '" role="alert">'
        . '<span aria-hidden="true">!</span> ' . e($errors[$field]) . '</small>';
}
