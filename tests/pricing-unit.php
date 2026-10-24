<?php

// In-memory PDO only. Never loads .env, connects to MySQL, or writes database fixtures.
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\BangGia;
use App\Models\TaiKhoan;
use App\Services\AuthService;
use App\Services\BangGiaService;
use App\Services\ValidationException;
use Core\Application;
use Core\Csrf;
use Core\HttpException;
use Core\Router;
use Core\Session;
use Core\View;

final class PricingMemoryPDO extends PDO
{
    public array $prices = [];
    public array $calls = [];
    public array $facilities;
    public array $companies;
    public array $types;
    public array $sports;
    public ?int $failWrite = null;
    private bool $transaction = false;
    private array $snapshot = [];

    public function __construct()
    {
        $this->companies = [1 => ['ma_cong_ty' => 1, 'ten_cong_ty' => 'Công ty', 'trang_thai' => 'HOAT_DONG']];
        $this->facilities = [1 => ['ma_co_so' => 1, 'ma_cong_ty' => 1, 'ten_co_so' => 'Cơ sở', 'trang_thai' => 'HOAT_DONG',
            'gio_mo_cua' => '06:00:00', 'gio_dong_cua' => '23:00:00']];
        $this->facilities[2] = array_replace($this->facilities[1], ['ma_co_so' => 2, 'ten_co_so' => 'Cơ sở 2']);
        $this->sports = [1 => ['ma_bo_mon' => 1, 'ten_bo_mon' => 'Cầu lông', 'trang_thai' => 'HOAT_DONG']];
        $this->types = [1 => ['ma_loai_san' => 1, 'ma_bo_mon' => 1, 'ten_loai_san' => 'Tiêu chuẩn', 'trang_thai' => 'HOAT_DONG']];
        $this->types[2] = array_replace($this->types[1], ['ma_loai_san' => 2, 'ten_loai_san' => 'Loại 2']);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new PricingMemoryStatement($this, $query); }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $statement = $this->prepare($query);
        $statement->execute();
        return $statement;
    }
    public function beginTransaction(): bool
    {
        if ($this->transaction) throw new RuntimeException('Nested transaction');
        $this->snapshot = $this->prices;
        return $this->transaction = true;
    }
    public function commit(): bool { $this->transaction = false; return true; }
    public function rollBack(): bool { $this->prices = $this->snapshot; $this->transaction = false; return true; }
    public function inTransaction(): bool { return $this->transaction; }
    public function lastInsertId(?string $name = null): string|false { return (string) max(array_keys($this->prices)); }
    public function joined(array $price): array
    {
        $facility = $this->facilities[$price['ma_co_so']];
        $type = $this->types[$price['ma_loai_san']];
        return $price + ['ten_co_so' => $facility['ten_co_so'], 'ten_cong_ty' => $this->companies[$facility['ma_cong_ty']]['ten_cong_ty'],
            'ten_loai_san' => $type['ten_loai_san'], 'ma_bo_mon' => $type['ma_bo_mon'], 'ten_bo_mon' => $this->sports[$type['ma_bo_mon']]['ten_bo_mon'],
            'trang_thai_co_so' => $facility['trang_thai'], 'trang_thai_cong_ty' => $this->companies[$facility['ma_cong_ty']]['trang_thai'],
            'trang_thai_loai_san' => $type['trang_thai'], 'trang_thai_bo_mon' => $this->sports[$type['ma_bo_mon']]['trang_thai']];
    }
}

final class PricingMemoryStatement extends PDOStatement
{
    private array $bound = [];
    private array $boundTypes = [];
    private array $rows = [];
    public function __construct(private PricingMemoryPDO $db, private string $sql) {}
    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $key = ltrim((string) $param, ':');
        $this->bound[$key] = $value;
        $this->boundTypes[$key] = $type;
        return true;
    }
    public function execute(?array $params = null): bool
    {
        $params ??= $this->bound;
        $sql = preg_replace('/\s+/', ' ', trim($this->sql));
        $this->db->calls[] = ['sql' => $sql, 'params' => $params, 'types' => $this->boundTypes];
        $this->rows = [];
        if (str_starts_with($sql, 'INSERT INTO bang_gia') || str_starts_with($sql, 'UPDATE bang_gia')) {
            if ($this->db->failWrite !== null) {
                $e = new PDOException('Simulated transaction conflict');
                $e->errorInfo = ['40001', $this->db->failWrite, ''];
                throw $e;
            }
            $id = $params['id'] ?? ($this->db->prices ? max(array_keys($this->db->prices)) + 1 : 1);
            if (isset($params['status'])) {
                $this->db->prices[$id]['trang_thai'] = $params['status'];
            } else {
                unset($params['id']);
                $this->db->prices[$id] = $params + ['ma_bang_gia' => $id];
            }
        } elseif (str_starts_with($sql, 'SELECT * FROM bang_gia WHERE ma_bang_gia')) {
            $this->rows = isset($this->db->prices[$params['id']]) ? [$this->db->prices[$params['id']]] : [];
        } elseif (str_starts_with($sql, 'SELECT * FROM bang_gia WHERE ma_co_so')) {
            $this->rows = array_values(array_filter($this->db->prices, static fn(array $r): bool =>
                $r['ma_co_so'] == $params['facility'] && $r['ma_loai_san'] == $params['type'] && $r['loai_ngay'] === $params['day']
                && $r['trang_thai'] === 'DANG_AP_DUNG' && $r['ma_bang_gia'] != $params['id']
                && $r['gio_bat_dau'] < $params['ending'] && $r['gio_ket_thuc'] > $params['opening']
                && ($r['ngay_ket_thuc'] === null || $r['ngay_ket_thuc'] >= $params['first_date'])
                && (!isset($params['last_date']) || $r['ngay_bat_dau'] <= $params['last_date'])));
        } elseif (str_contains($sql, ' FROM bang_gia g')) {
            $rows = array_filter($this->db->prices, function (array $r) use ($params): bool {
                if (isset($params['id'])) return $r['ma_bang_gia'] == $params['id'];
                foreach (['ma_co_so', 'ma_loai_san', 'loai_ngay', 'trang_thai'] as $field) {
                    if (isset($params[$field]) && (string) $r[$field] !== (string) $params[$field]) return false;
                }
                if (isset($params['keyword'])) {
                    $literal = strtr(substr($params['keyword'], 1, -1), ['!!' => '!', '!%' => '%', '!_' => '_']);
                    return mb_stripos($r['ten_bang_gia'], $literal) !== false;
                }
                return true;
            });
            if (str_starts_with($sql, 'SELECT COUNT(*)')) {
                $this->rows = [['total' => count($rows)]];
            } else {
                krsort($rows);
                $this->rows = array_map(fn(array $r): array => $this->db->joined($r), array_slice(array_values($rows), $params['offset'] ?? 0, $params['limit'] ?? count($rows)));
            }
        } elseif (str_contains($sql, 'FROM cong_ty_nhuong_quyen WHERE')) {
            $this->rows = isset($this->db->companies[$params['id']]) ? [$this->db->companies[$params['id']]] : [];
        } elseif (str_contains($sql, 'FROM bo_mon WHERE')) {
            $this->rows = isset($this->db->sports[$params['id']]) ? [$this->db->sports[$params['id']]] : [];
        } elseif (str_contains($sql, 'FROM co_so')) {
            foreach ($this->db->facilities as $id => $row) {
                if (isset($params['id']) && $params['id'] != $id) continue;
                $company = $this->db->companies[$row['ma_cong_ty']];
                if (isset($params['current']) && $id != $params['current'] && ($row['trang_thai'] !== 'HOAT_DONG' || $company['trang_thai'] !== 'HOAT_DONG')) continue;
                $this->rows[] = $row + ['ten_cong_ty' => $company['ten_cong_ty'], 'trang_thai_cong_ty' => $company['trang_thai'], 'so_san' => 0];
            }
        } elseif (str_contains($sql, 'FROM loai_san')) {
            foreach ($this->db->types as $id => $row) {
                if (isset($params['id']) && $params['id'] != $id) continue;
                $sport = $this->db->sports[$row['ma_bo_mon']];
                if (isset($params['current']) && $id != $params['current'] && ($row['trang_thai'] !== 'HOAT_DONG' || $sport['trang_thai'] !== 'HOAT_DONG')) continue;
                $this->rows[] = $row + ['ten_bo_mon' => $sport['ten_bo_mon'], 'trang_thai_bo_mon' => $sport['trang_thai']];
            }
        } else {
            throw new RuntimeException('Unexpected SQL in memory test: ' . $sql);
        }
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return array_shift($this->rows) ?: false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column = 0): mixed { return isset($this->rows[0]) ? array_values($this->rows[0])[$column] : false; }
}

$checks = 0;
function checkPricing(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
function rejectPricing(callable $action, string|int $expected): void
{
    try { $action(); }
    catch (ValidationException $e) { checkPricing(is_string($expected) && isset($e->errors[$expected]), 'validation: ' . $expected); return; }
    catch (HttpException $e) { checkPricing($e->status === $expected, 'HTTP: ' . $expected); return; }
    throw new RuntimeException('Expected rejection: ' . $expected);
}

$price = ['ma_co_so' => '1', 'ma_loai_san' => '1', 'ten_bang_gia' => ' Giá sáng ', 'loai_ngay' => 'NGAY_THUONG',
    'gio_bat_dau' => '06:00', 'gio_ket_thuc' => '10:00', 'don_gia' => '100000.50', 'ngay_bat_dau' => '2020-01-01',
    'ngay_ket_thuc' => '', 'trang_thai' => 'DANG_AP_DUNG'];
checkPricing(BangGiaService::validate($price) === [], 'past date and unbounded end accepted');
checkPricing(BangGiaService::validate(array_replace($price, ['ngay_bat_dau' => '2024-02-29', 'ngay_ket_thuc' => '2024-02-29'])) === [], 'leap day and same-day range');
foreach (['ma_co_so', 'ma_loai_san'] as $field) {
    foreach (['', '0', '-1', '01', '1e2', '2147483648', [], null] as $bad) checkPricing(isset(BangGiaService::validate(array_replace($price, [$field => $bad]))[$field]), 'strict ID');
}
foreach (['', ' ', [], "\xff", "bad\0name", "bad\nname", str_repeat('á', 151)] as $bad) checkPricing(isset(BangGiaService::validate(array_replace($price, ['ten_bang_gia' => $bad]))['ten_bang_gia']), 'name validation');
checkPricing(BangGiaService::validate(array_replace($price, ['ten_bang_gia' => str_repeat('á', 150)])) === [], 'Unicode name boundary');
foreach (['', '0', '0.00', '-1', '1e5', '100,000', '100.000', '10000000000', '9999999999.999', [], null] as $bad) checkPricing(isset(BangGiaService::validate(array_replace($price, ['don_gia' => $bad]))['don_gia']), 'decimal validation');
foreach (['0.01', '9999999999.99', '0000000001', '100000.5'] as $good) checkPricing(BangGiaService::validate(array_replace($price, ['don_gia' => $good])) === [], 'decimal boundary');
$clean = BangGiaService::normalize($price + ['ignored' => 'no']);
checkPricing($clean['don_gia'] === '100000.50' && $clean['ten_bang_gia'] === 'Giá sáng' && $clean['ngay_ket_thuc'] === null && !isset($clean['ignored']), 'normalize exact values and whitelist');
foreach (['24:00', '6:00', '06:15', '06:00:01', '', [], null] as $bad) checkPricing(isset(BangGiaService::validate(array_replace($price, ['gio_bat_dau' => $bad]))['gio_bat_dau']), 'time validation');
foreach (['06:00', '05:30'] as $end) checkPricing(isset(BangGiaService::validate(array_replace($price, ['gio_ket_thuc' => $end]))['gio_ket_thuc']), 'zero/reversed/overnight interval');
foreach (['2023-02-29', '2024-02-30', '2024-13-01', '0000-01-01', '0999-01-01', '10000-01-01', [], null] as $bad) checkPricing(isset(BangGiaService::validate(array_replace($price, ['ngay_bat_dau' => $bad]))['ngay_bat_dau']), 'calendar validation');
checkPricing(isset(BangGiaService::validate(array_replace($price, ['ngay_ket_thuc' => '2019-12-31']))['ngay_ket_thuc']), 'end before start');
foreach (['loai_ngay', 'trang_thai'] as $field) foreach (['INVALID', [], ''] as $bad) checkPricing(isset(BangGiaService::validate(array_replace($price, [$field => $bad]))[$field]), 'enum allowlist');
foreach ([['06:00', '10:00', true], ['09:00', '12:00', true], ['07:00', '08:00', true], ['05:30', '10:30', true], ['10:00', '12:00', false], ['05:00', '06:00', false]] as [$start, $end, $expected]) {
    checkPricing(BangGiaService::overlaps($clean, array_replace($clean, ['gio_bat_dau' => $start, 'gio_ket_thuc' => $end])) === $expected, 'time overlap boundaries');
}
foreach (['ma_co_so' => 2, 'ma_loai_san' => 2, 'loai_ngay' => 'CUOI_TUAN', 'trang_thai' => 'NGUNG'] as $field => $value) checkPricing(!BangGiaService::overlaps($clean, array_replace($clean, [$field => $value])), 'different scope/status');
$a = array_replace($clean, ['ngay_bat_dau' => '2026-10-23', 'ngay_ket_thuc' => '2026-10-25']);
$b = array_replace($clean, ['ngay_bat_dau' => '2026-10-24', 'ngay_ket_thuc' => '2026-10-26']);
checkPricing(!BangGiaService::overlaps($a, $b), 'date intersection only weekend, not weekdays');
checkPricing(BangGiaService::overlaps(array_replace($a, ['loai_ngay' => 'CUOI_TUAN']), array_replace($b, ['loai_ngay' => 'CUOI_TUAN'])), 'actual shared weekend');
checkPricing(BangGiaService::overlaps($a, array_replace($b, ['ngay_bat_dau' => '2026-10-23'])), 'inclusive common Friday');
checkPricing(!BangGiaService::overlaps($a, array_replace($b, ['ngay_bat_dau' => '2026-10-26'])), 'disjoint dates');
checkPricing(!BangGiaService::overlaps(array_replace($clean, ['ngay_bat_dau' => '9999-12-31', 'loai_ngay' => 'CUOI_TUAN']), array_replace($clean, ['ngay_bat_dau' => '9999-12-31', 'loai_ngay' => 'CUOI_TUAN'])), 'date upper boundary does not overflow');
$defaults = BangGiaService::filters([]);
checkPricing($defaults['per_page'] === 10 && $defaults['page'] === 1, 'pagination defaults');
foreach ([['page' => '0'], ['page' => '2147483648'], ['page' => []], ['per_page' => '15'], ['q' => []], ['q' => "\xff"], ['loai_ngay' => 'ALL'], ['trang_thai' => 'ACTIVE'], ['ma_co_so' => '1 OR 1=1']] as $bad) rejectPricing(fn() => BangGiaService::filters($bad), 422);

$pdo = new PricingMemoryPDO();
$service = new BangGiaService($pdo);
$model = new BangGia($pdo);
$id = $service->save($price);
checkPricing($id === 1 && !$pdo->inTransaction(), 'create without any court; committed');
$locks = array_column(array_filter($pdo->calls, static fn(array $c): bool => str_contains($c['sql'], 'FOR UPDATE')), 'sql');
checkPricing(str_contains($locks[0], 'cong_ty_nhuong_quyen') && str_contains($locks[1], 'co_so') && str_contains($locks[2], 'bo_mon') && str_contains($locks[3], 'loai_san') && str_contains(end($locks), 'bang_gia'), 'shared parent anchors before current-read conflict check');
rejectPricing(fn() => $service->save($price), 'gio_bat_dau');
checkPricing(count($pdo->prices) === 1 && !$pdo->inTransaction(), 'overlap rolls back');
$service->save(array_replace($price, ['ten_bang_gia' => 'Đổi tên']), $id);
checkPricing($pdo->prices[$id]['ten_bang_gia'] === 'Đổi tên', 'update excludes itself');
rejectPricing(fn() => $service->save(array_replace($price, ['gio_ket_thuc' => '23:30'])), 'gio_ket_thuc');
rejectPricing(fn() => $service->save(array_replace($price, ['ma_co_so' => '999'])), 'ma_co_so');
rejectPricing(fn() => $service->save(array_replace($price, ['ma_loai_san' => '999'])), 'ma_loai_san');
$adjacent = $service->save(array_replace($price, ['gio_bat_dau' => '10:00', 'gio_ket_thuc' => '12:00']));
rejectPricing(fn() => $service->save(array_replace($price, ['gio_bat_dau' => '09:00', 'gio_ket_thuc' => '11:00']), $adjacent), 'gio_bat_dau');
$service->save(array_replace($price, ['ma_co_so' => '2']));
$service->save(array_replace($price, ['ma_loai_san' => '2']));
checkPricing(count($pdo->prices) === 4, 'other facilities/types allowed');
rejectPricing(fn() => $service->save(array_replace($price, ['trang_thai' => 'NGUNG']), $id), 'trang_thai');
$service->changeStatus($id, 'NGUNG');
$replacement = $service->save($price);
rejectPricing(fn() => $service->changeStatus($id, 'DANG_AP_DUNG'), 'gio_bat_dau');
checkPricing($pdo->prices[$id]['trang_thai'] === 'NGUNG', 'failed activation leaves stopped');
$service->changeStatus($replacement, 'NGUNG');
$service->changeStatus($id, 'DANG_AP_DUNG');
checkPricing($pdo->prices[$id]['trang_thai'] === 'DANG_AP_DUNG', 'reactivation when valid');
foreach (['facilities' => 'ma_co_so', 'companies' => 'ma_co_so', 'types' => 'ma_loai_san', 'sports' => 'ma_loai_san'] as $table => $field) {
    $oldStatus = $pdo->{$table}[1]['trang_thai'];
    $pdo->{$table}[1]['trang_thai'] = $table === 'facilities' ? 'TAM_NGUNG' : ($table === 'companies' ? 'NGUNG_HOAT_DONG' : 'NGUNG');
    rejectPricing(fn() => $service->save($price, $id), $field);
    $service->changeStatus($id, 'NGUNG');
    rejectPricing(fn() => $service->changeStatus($id, 'DANG_AP_DUNG'), $field);
    $pdo->{$table}[1]['trang_thai'] = $oldStatus;
    $service->changeStatus($id, 'DANG_AP_DUNG');
}
$pdo->prices[$replacement]['don_gia'] = '0.00';
rejectPricing(fn() => $service->changeStatus($replacement, 'DANG_AP_DUNG'), 'don_gia');
$service->changeStatus($replacement, 'NGUNG');
checkPricing($pdo->prices[$replacement]['don_gia'] === '0.00', 'stop legacy record without rewriting price');
$before = $pdo->prices;
$pdo->failWrite = 1213;
rejectPricing(fn() => $service->changeStatus($id, 'NGUNG'), 409);
checkPricing($before === $pdo->prices && !$pdo->inTransaction(), 'deadlock rolls back cleanly');
$pdo->failWrite = null;
rejectPricing(fn() => $service->changeStatus($id, []), 'trang_thai');
rejectPricing(fn() => $service->save($price, 9999), 404);

$pdo->prices = [];
for ($number = 1; $number <= 53; $number++) $pdo->prices[$number] = array_replace($clean, ['ma_bang_gia' => $number, 'ten_bang_gia' => $number === 53 ? 'Giá 100%_! đặc biệt' : 'Giá mẫu ' . $number,
    'ma_co_so' => $number % 2 + 1, 'ma_loai_san' => $number % 3 === 0 ? 2 : 1, 'loai_ngay' => $number % 4 === 0 ? 'CUOI_TUAN' : 'NGAY_THUONG', 'trang_thai' => 'NGUNG']);
foreach ([10 => 6, 20 => 3, 50 => 2] as $size => $pages) {
    $result = $model->page(BangGiaService::filters(['per_page' => (string) $size]));
    checkPricing(count($result['items']) === $size && $result['total'] === 53 && $result['pages'] === $pages, 'backend page size ' . $size);
}
$result = $model->page(BangGiaService::filters(['page' => '999']));
checkPricing($result['page'] === 6 && count($result['items']) === 3, 'large page clamped');
$result = $model->page(BangGiaService::filters(['q' => '%_!']));
checkPricing($result['total'] === 1 && $result['items'][0]['ma_bang_gia'] === 53, 'LIKE wildcards treated literally');
$last = end($pdo->calls);
checkPricing($last['types']['limit'] === PDO::PARAM_INT && $last['types']['offset'] === PDO::PARAM_INT && $last['params']['keyword'] === '%!%!_!!%', 'prepared integer pagination and escaped search');
$filters = BangGiaService::filters(['q' => 'Giá mẫu', 'ma_co_so' => '2', 'ma_loai_san' => '1', 'loai_ngay' => 'NGAY_THUONG', 'trang_thai' => 'NGUNG', 'per_page' => '20']);
$result = $model->page($filters);
checkPricing($result['total'] === 17, 'combined filters and search');
checkPricing($model->page(BangGiaService::filters(['q' => "' OR 1=1 --"]))['total'] === 0, 'search stays SQL data');

$_SERVER['SCRIPT_NAME'] = '/index.php';
ini_set('session.save_path', dirname(__DIR__) . '/storage/temp');
Session::start();
$accounts = new class extends TaiKhoan {
    public ?array $user = null;
    public function __construct() {}
    public function identity(int $id): ?array { return $id === 1 ? $this->user : null; }
};
$identity = ['ma_tai_khoan' => 1, 'ten_dang_nhap' => 'admin.preview', 'trang_thai' => 'HOAT_DONG',
    'ma_khach_hang' => 1, 'trang_thai_khach' => 'HOAT_DONG', 'ma_nhan_vien' => 1, 'trang_thai_nhan_vien' => 'DANG_LAM',
    'ma_cong_ty' => 1, 'ma_co_so' => 1, 'cong_ty_cua_co_so' => 1, 'trang_thai_cong_ty' => 'HOAT_DONG', 'trang_thai_co_so' => 'HOAT_DONG'];
$app = new Application($pdo);
(new ReflectionProperty(Application::class, 'auth'))->setValue($app, new AuthService($accounts));
$router = new Router();
require dirname(__DIR__) . '/routes/web.php';
$routes = (new ReflectionProperty(Router::class, 'routes'))->getValue($router);
$pricingPreviews = [];
set_error_handler(static function (int $severity, string $message, string $file, int $line): never { throw new ErrorException($message, 0, $severity, $file, $line); });
try {
    foreach (['QUAN_TRI_TAP_DOAN', 'KHACH_HANG', 'QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO', 'NHAN_VIEN'] as $role) {
        $accounts->user = $identity + ['ten_vai_tro' => $role];
        Session::authenticate(1);
        foreach ($routes as $method => $entries) foreach ($entries as $path => [$handler, $middleware]) {
            if (!str_starts_with($path, '/admin/bang-gia')) continue;
            checkPricing(count($middleware) === 1 && (new ReflectionProperty($middleware[0], 'roles'))->getValue($middleware[0]) === ['QUAN_TRI_TAP_DOAN'], 'every pricing route Admin-only');
            $_POST = ['_token' => Csrf::token(), 'id' => '1'];
            $_GET = ['id' => '1'];
            if ($role !== 'QUAN_TRI_TAP_DOAN') {
                $queryCount = count($pdo->calls);
                rejectPricing(fn() => $router->dispatch($method, $path), 403);
                checkPricing(count($pdo->calls) === $queryCount, 'forbidden request does not query price data');
            } elseif ($method === 'GET') {
                ob_start();
                $router->dispatch($method, $path);
                $html = ob_get_clean();
                checkPricing(http_response_code() === 200 && str_contains($html, 'class="admin-sidebar"'), 'Admin GET render');
                checkPricing(str_contains($html, 'href="' . e(url('/admin/bang-gia')) . '" aria-current="page"'), 'Sidebar active on all pricing pages');
                $pricingPreviews[$path] = $html;
            } else {
                $_POST = ['id' => '1'];
                rejectPricing(fn() => $router->dispatch($method, $path), 403);
            }
        }
    }
    $accounts->user = $identity + ['ten_vai_tro' => 'QUAN_TRI_TAP_DOAN'];
    Session::authenticate(1);
    foreach (['/admin/bang-gia/delete', '/admin/bang-gia/destroy'] as $path) rejectPricing(fn() => $router->dispatch('POST', $path), 404);
    rejectPricing(fn() => $router->dispatch('GET', '/admin/bang-gia/status'), 405);
    $_GET = ['id' => '01'];
    rejectPricing(fn() => $router->dispatch('GET', '/admin/bang-gia/show'), 404);
    $_POST = ['_token' => Csrf::token()];
    ob_start(); $router->dispatch('POST', '/admin/bang-gia/store'); $html = ob_get_clean();
    checkPricing(http_response_code() === 422 && str_contains($html, 'aria-invalid="true"') && str_contains($html, 'admin-sidebar'), 'validation renders 422 with Admin layout');
    $pricingPreviews['/admin/bang-gia/error'] = $html;
    $pdo->prices[1]['trang_thai'] = 'DANG_AP_DUNG';
    $_POST = ['_token' => Csrf::token(), 'id' => '1', 'trang_thai' => 'NGUNG'];
    ob_start(); $router->dispatch('POST', '/admin/bang-gia/status'); $html = ob_get_clean();
    checkPricing(http_response_code() === 422 && $pdo->prices[1]['trang_thai'] === 'DANG_AP_DUNG', 'stop requires explicit confirmation');
    checkPricing(str_contains($html, 'name="confirmed"') && str_contains($html, 'type="checkbox"'), 'stop confirmation checkbox');
    $pricingPreviews['/admin/bang-gia/confirm'] = $html;
    $_GET = ['id' => '1', 'confirm' => '1'];
    $beforeConfirmation = $pdo->prices;
    ob_start(); $router->dispatch('GET', '/admin/bang-gia/show'); ob_end_clean();
    checkPricing($beforeConfirmation === $pdo->prices, 'GET confirmation never changes records');
    $pdo->prices[1]['ten_bang_gia'] = '<script>unsafe</script>';
    $data = ['title' => 'Bảng giá', 'item' => $pdo->joined($pdo->prices[1]), 'errors' => [], 'confirm' => false];
    ob_start(); View::render('admin/bang-gia/show', $data); $html = ob_get_clean();
    checkPricing(str_contains($html, '&lt;script&gt;unsafe&lt;/script&gt;') && !str_contains($html, '<script>unsafe</script>'), 'stored HTML escaped');
    $data = $model->page(BangGiaService::filters(['q' => 'Giá mẫu', 'page' => '2'])) + ['title' => 'Bảng giá',
        'filters' => BangGiaService::filters(['q' => 'Giá mẫu', 'page' => '2']), 'notice' => null,
        'facilities' => (new App\Models\CoSo($pdo))->all(), 'types' => $model->types(true)];
    ob_start(); View::render('admin/bang-gia/index', $data); $html = ob_get_clean();
    checkPricing(str_contains($html, 'q=Gi%C3%A1+m%E1%BA%ABu') && str_contains($html, 'per_page=10') && str_contains($html, 'aria-label="Phân trang bảng giá"'), 'pagination preserves keyword and page size');
    $pricingPreviews['/admin/bang-gia'] = $html;
    $allFilters = BangGiaService::filters(['q' => 'Giá mẫu', 'ma_co_so' => '2', 'ma_loai_san' => '1',
        'loai_ngay' => 'NGAY_THUONG', 'trang_thai' => 'NGUNG', 'page' => '2', 'per_page' => '10']);
    $data = array_replace($data, $model->page($allFilters), ['filters' => $allFilters]);
    ob_start(); View::render('admin/bang-gia/index', $data); $filteredHtml = ob_get_clean();
    preg_match('/rel="prev"[^>]*href="([^"]+)"/', $filteredHtml, $previousLink);
    parse_str(parse_url(html_entity_decode($previousLink[1], ENT_QUOTES, 'UTF-8'), PHP_URL_QUERY), $previousQuery);
    foreach (['q', 'ma_co_so', 'ma_loai_san', 'loai_ngay', 'trang_thai', 'per_page'] as $field) {
        checkPricing($previousQuery[$field] === (string) $allFilters[$field], 'pagination retains ' . $field);
    }
    $pricingPreviews['/admin/bang-gia/show'] = $pricingPreviews['/admin/bang-gia/confirm'];
    foreach ($pdo->calls as $call) checkPricing(!preg_match('/\A(?:DELETE|CREATE|ALTER|DROP)\b/', $call['sql']), 'no delete or schema operations');
} finally {
    restore_error_handler();
    Session::forgetAuthentication();
    session_write_close();
}
echo "PASS: $checks pricing checks in memory; no MySQL connection or database writes.\n";
