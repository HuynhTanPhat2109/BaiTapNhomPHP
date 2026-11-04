<?php
// Pure URL/context checks. No application bootstrap, .env or database connection.
require dirname(__DIR__) . '/vendor/autoload.php';

$_SERVER['SCRIPT_NAME'] = '/stryke/index.php';
$_GET = $_POST = [];
$checks = 0;
function navigationCheck(bool $ok, string $label): void
{
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
function navigationQuery(string $url): array
{
    parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
    return $query;
}
$lists = array_map(static fn($slug) => '/admin/' . $slug, ['bo-mon', 'loai-san', 'co-so', 'san', 'bang-gia', 'dich-vu']);
foreach (['/company', '/facility', '/staff'] as $root) {
    foreach (['co-so', 'san', 'bang-gia'] as $slug) $lists[] = $root . '/' . $slug;
}
foreach ($lists as $list) {
    $slug = $list;
    $filters = ['page' => 2, 'per_page' => 20, 'q' => 'Tên & % + sân'];
    $_GET = ['route' => $list] + array_map('strval', $filters);
    $return = admin_context_url($list);
    navigationCheck(navigationQuery($return) === ['route' => $list, 'q' => $filters['q'], 'per_page' => '20', 'page' => '2'], "$slug preserves search/page/size");
    foreach (['show', 'edit', 'delete', 'status', 'update', 'store'] as $action) {
        $link = navigationQuery(admin_context_url($list . '/' . $action));
        navigationCheck($link['route'] === $list . '/' . $action && $link['return_to'] === $return, "$slug $action preserves return URL");
    }
    $_GET = ['route' => $list . '/edit', 'return_to' => $return];
    navigationCheck(admin_context_url($list) === $return, "$slug back link restores context");
    $_POST = ['return_to' => $return]; $_GET = ['route' => $list . '/update'];
    navigationCheck(admin_context_url($list) === $return, "$slug POST and validation preserve context");
    $_POST = [];
    foreach (['https://evil.test' . $return, '//evil.test' . $return, 'file:' . $return,
        '/outside/index.php?route=' . rawurlencode($list), url('/admin/companies') . '&page=2',
        url('/company/employees') . '&page=2', url($list === '/admin/san' ? '/staff/san' : '/admin/san') . '&page=2',
        $return . '#fragment', ['bad'], $return . '&page[]=2', $return . '&per_page=15',
        $return . '&q=%0D%0ALocation%3Aevil', $return . '&q=' . rawurlencode(str_repeat('á', 151))] as $unsafe) {
        $_GET['return_to'] = $unsafe;
        navigationCheck(admin_context_url($list) === url($list), "$slug rejects unsafe/foreign/invalid return context");
    }
    $_GET['return_to'] = $return . '&route=' . rawurlencode($list) . '&_token=secret&id=99&sort=not_supported';
    $safe = navigationQuery(admin_context_url($list));
    navigationCheck(!isset($safe['_token'], $safe['id'], $safe['sort']) && count($safe) === 4, "$slug strips unrelated parameters");
    $_GET = $_POST = [];
    navigationCheck(admin_context_url($list . '/create') === url($list . '/create'), "$slug standalone create keeps existing flow");
}
$filters = ['page' => 3, 'per_page' => 50, 'q' => 'nước', 'ma_co_so' => '1', 'loai_dich_vu' => 'DO_UONG', 'trang_thai' => 'NGUNG', 'don_vi_tinh' => 'chai'];
$service = navigationQuery(admin_context_url('/admin/dich-vu', $filters));
foreach ($filters as $key => $value) navigationCheck(($service[$key] ?? null) === (string) $value, 'combined service filter ' . $key);
$pricing = navigationQuery(admin_context_url('/admin/bang-gia', ['page' => 2, 'ma_co_so' => '1', 'ma_loai_san' => '2', 'loai_ngay' => 'CUOI_TUAN', 'trang_thai' => 'DANG_AP_DUNG']));
navigationCheck($pricing['loai_ngay'] === 'CUOI_TUAN' && $pricing['ma_loai_san'] === '2' && $pricing['page'] === '2', 'pricing combined filters retained');
foreach (['/facility/bank', '/company/dich-vu', '/login', '/booking/wait'] as $path) {
    navigationCheck(admin_context_url($path, $filters) === url($path), 'unrelated navigation unchanged: ' . $path);
}
echo "PASS: $checks Admin navigation checks; no database.\n";
