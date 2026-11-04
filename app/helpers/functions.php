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
    $fragment = '';
    if (strpos($path, '#') !== false) {
        [$path, $fragment] = explode('#', $path, 2);
        $fragment = '#' . $fragment;
    }
    $parts = explode('?', $path, 2);
    $route = base_path() . '/index.php?route=' . rawurlencode($parts[0]);
    if (isset($parts[1]) && $parts[1] !== '') {
        $route .= '&' . $parts[1];
    }
    return $route . $fragment;
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

// Preserve list context only for the existing paginated Admin catalogs.
// Rebuild the destination from a fixed module and validated filters, never a client URL.
function admin_context_url(string $path, ?array $filters = null): string
{
    if (!preg_match('~\A(/admin/(?:bo-mon|loai-san|co-so|san|bang-gia|dich-vu|companies|contracts|employees)|/company/employees|/(?:company|facility|staff)/(?:co-so|san|bang-gia))(?:/(?:create|store|show|edit|update|delete|status))?\z~', $path, $match)) {
        return url($path);
    }
    $listPath = $match[1];
    $module = basename($listPath);
    if ($filters === null) {
        $return = $_POST['return_to'] ?? $_GET['return_to'] ?? null;
        if ($return !== null) {
            $parts = is_string($return) && strlen($return) <= 4096 ? parse_url($return) : false;
            if (!$parts || isset($parts['scheme']) || isset($parts['host'])
                || isset($parts['fragment']) || ($parts['path'] ?? '') !== base_path() . '/index.php') return url($path);
            parse_str($parts['query'] ?? '', $filters);
            if (($filters['route'] ?? null) !== $listPath) return url($path);
        } else {
            $filters = ($_GET['route'] ?? null) === $listPath ? $_GET : [];
        }
    }
    [$links, $options] = match ($module) {
        'bo-mon' => [[], ['trang_thai' => ['HOAT_DONG', 'NGUNG']]],
        'loai-san' => [['ma_bo_mon'], ['trang_thai' => ['HOAT_DONG', 'NGUNG']]],
        'co-so' => [['ma_cong_ty'], ['trang_thai' => ['HOAT_DONG', 'TAM_NGUNG']]],
        'san' => [['ma_co_so', 'ma_loai_san'], ['trang_thai' => ['HOAT_DONG', 'BAO_TRI', 'TAM_NGUNG']]],
        'bang-gia' => [['ma_co_so', 'ma_loai_san'], ['loai_ngay' => ['NGAY_THUONG', 'CUOI_TUAN'], 'trang_thai' => ['DANG_AP_DUNG', 'NGUNG']]],
        'dich-vu' => [['ma_co_so'], ['loai_dich_vu' => array_keys(App\Services\DichVuService::TYPES), 'trang_thai' => array_keys(App\Services\DichVuService::STATES)]],
        default => [[], []],
    };
    try {
        $filters = array_map(static fn($value) => is_int($value) ? (string) $value : $value, $filters);
        $query = in_array($module, ['companies', 'contracts', 'employees'], true)
            ? App\Services\AdminListService::managementFilters($listPath, $filters)
            : App\Services\AdminListService::filters($filters, $links, $options);
        if ($module === 'dich-vu') {
            $unit = $filters['don_vi_tinh'] ?? '';
            if (!is_string($unit) || !mb_check_encoding($unit, 'UTF-8') || mb_strlen($unit) > 30 || preg_match('/\p{C}/u', $unit)) return url($path);
            $query['don_vi_tinh'] = trim($unit);
        }
    } catch (Core\HttpException $e) {
        return url($path);
    }
    $query = array_filter($query, static fn($value, $key) => $value !== '' && !($key === 'page' && $value === 1) && !($key === 'per_page' && $value === 10) && !($key === 'sort' && $value === 'newest'), ARRAY_FILTER_USE_BOTH);
    if (!$query) return url($path);
    $return = url($listPath) . '&' . http_build_query($query);
    return $path === $listPath ? $return : url($path) . '&return_to=' . rawurlencode($return);
}

function admin_list_redirect(string $path, ?array $filters = null): void
{
    header('Location: ' . admin_context_url($path, $filters), true, 303);
    exit;
}
