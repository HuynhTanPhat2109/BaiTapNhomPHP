<?php

// No .env, real connection, migrations or database fixtures. All writes stay in these arrays.
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Models\{DichVu, TaiKhoan};
use App\Services\{AuthService, DichVuService as Service, ValidationException};
use Core\{Application, Csrf, HttpException, Router, Session};

final class ServiceMemoryPDO extends PDO
{
    public array $services = [], $calls = [], $refs = [];
    public array $companies = [1 => ['ma_cong_ty' => 1, 'ten_cong_ty' => 'Công ty 1', 'trang_thai' => 'HOAT_DONG'], 2 => ['ma_cong_ty' => 2, 'ten_cong_ty' => 'Công ty 2', 'trang_thai' => 'HOAT_DONG']];
    public array $facilities = [
        1 => ['ma_co_so' => 1, 'ma_cong_ty' => 1, 'ten_co_so' => 'Cơ sở Nguyễn Văn Linh', 'trang_thai' => 'HOAT_DONG'],
        2 => ['ma_co_so' => 2, 'ma_cong_ty' => 1, 'ten_co_so' => 'Cơ sở 2', 'trang_thai' => 'HOAT_DONG'],
        3 => ['ma_co_so' => 3, 'ma_cong_ty' => 2, 'ten_co_so' => 'Cơ sở công ty khác', 'trang_thai' => 'HOAT_DONG'],
    ];
    public ?int $failWrite = null;
    private bool $transaction = false;
    private array $snapshot = [];
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new ServiceMemoryStatement($this, $query); }
    public function beginTransaction(): bool { if ($this->transaction) throw new RuntimeException('Nested transaction'); $this->snapshot = $this->services; return $this->transaction = true; }
    public function commit(): bool { $this->transaction = false; return true; }
    public function rollBack(): bool { $this->services = $this->snapshot; $this->transaction = false; return true; }
    public function inTransaction(): bool { return $this->transaction; }
    public function lastInsertId(?string $name = null): string|false { return (string) max(array_keys($this->services)); }
    public function permits(array $row, array $params): bool
    {
        $f = isset($row['ma_dich_vu']) ? $this->facilities[$row['ma_co_so']] : $row;
        return (!isset($params['scope_company']) || $params['scope_company'] == $f['ma_cong_ty'])
            && (!isset($params['scope_facility']) || $params['scope_facility'] == $f['ma_co_so']);
    }
    public function joined(array $row): array
    {
        $f = $this->facilities[$row['ma_co_so']]; $c = $this->companies[$f['ma_cong_ty']];
        return $row + ['ten_co_so' => $f['ten_co_so'], 'ten_cong_ty' => $c['ten_cong_ty'], 'trang_thai_co_so' => $f['trang_thai'], 'trang_thai_cong_ty' => $c['trang_thai']];
    }
}
final class ServiceMemoryStatement extends PDOStatement
{
    private array $bound = [], $types = [], $rows = [];
    public function __construct(private ServiceMemoryPDO $db, private string $sql) {}
    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool { $key = ltrim((string) $param, ':'); $this->bound[$key] = $value; $this->types[$key] = $type; return true; }
    public function execute(?array $params = null): bool
    {
        $p = $params ?? $this->bound; $sql = preg_replace('/\s+/', ' ', trim($this->sql)); $this->rows = [];
        $this->db->calls[] = ['sql' => $sql, 'params' => $p, 'types' => $this->types];
        if (preg_match('/\A(?:INSERT INTO|UPDATE) dich_vu\b/', $sql)) {
            if (!$this->db->inTransaction()) throw new RuntimeException('Write without transaction');
            if ($this->db->failWrite !== null) { $e = new PDOException('Simulated write error'); $e->errorInfo = ['40001', $this->db->failWrite, '']; throw $e; }
            $id = $p['id'] ?? ($this->db->services ? max(array_keys($this->db->services)) + 1 : 1);
            if (isset($p['status'])) $this->db->services[$id]['trang_thai'] = $p['status'];
            else { unset($p['id']); $this->db->services[$id] = $p + ['ma_dich_vu' => $id]; }
        } elseif (str_starts_with($sql, 'SELECT 1 FROM dich_vu')) {
            foreach ($this->db->services as $r) if ($r['ma_co_so'] == $p['facility'] && mb_strtolower($r['ten_dich_vu']) === mb_strtolower($p['name']) && $r['ma_dich_vu'] != $p['id']) $this->rows[] = [1];
        } elseif (str_contains($sql, ' FROM dich_vu d')) {
            $rows = array_filter($this->db->services, function ($r) use ($p) {
                if (!$this->db->permits($r, $p)) return false;
                if (isset($p['id']) && $r['ma_dich_vu'] != $p['id']) return false;
                foreach (['ma_co_so', 'loai_dich_vu', 'trang_thai', 'don_vi_tinh'] as $field) if (isset($p[$field]) && (string) $r[$field] !== (string) $p[$field]) return false;
                if (isset($p['keyword'])) { $needle = strtr(substr($p['keyword'], 1, -1), ['!!' => '!', '!%' => '%', '!_' => '_']); if (mb_stripos($r['ten_dich_vu'], $needle) === false) return false; }
                return true;
            });
            if (str_starts_with($sql, 'SELECT COUNT(*)')) $this->rows = [[count($rows)]];
            elseif (str_starts_with($sql, 'SELECT DISTINCT')) { $units = array_unique(array_column($rows, 'don_vi_tinh')); sort($units); $this->rows = array_map(fn($u) => [$u], $units); }
            else { usort($rows, fn($a, $b) => $b['ma_dich_vu'] <=> $a['ma_dich_vu']); $rows = array_slice($rows, $p['offset'] ?? 0, $p['limit'] ?? count($rows)); $this->rows = array_map(fn($r) => str_starts_with($sql, 'SELECT d.* FROM') ? $r : $this->db->joined($r), $rows); }
        } elseif (str_contains($sql, ' FROM chi_tiet_dich_vu_dat ct')) {
            foreach ($this->db->refs[$p['id']] ?? [] as $i => $state) $this->rows[] = ['ma_chi_tiet_dv' => $i + 1, 'trang_thai_dat_san' => $state];
        } elseif (str_contains($sql, 'FROM cong_ty_nhuong_quyen WHERE')) {
            if (isset($this->db->companies[$p['id']])) $this->rows[] = $this->db->companies[$p['id']];
        } elseif (str_contains($sql, 'FROM co_so')) {
            foreach ($this->db->facilities as $r) {
                if (!$this->db->permits($r, $p) || (isset($p['id']) && $p['id'] != $r['ma_co_so'])) continue;
                $c = $this->db->companies[$r['ma_cong_ty']];
                if (isset($p['current']) && $r['ma_co_so'] != $p['current'] && ($r['trang_thai'] !== 'HOAT_DONG' || $c['trang_thai'] !== 'HOAT_DONG')) continue;
                $this->rows[] = $r + ['ten_cong_ty' => $c['ten_cong_ty'], 'trang_thai_cong_ty' => $c['trang_thai']];
            }
        } else throw new RuntimeException('Unexpected SQL: ' . $sql);
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return array_shift($this->rows) ?: false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $mode === PDO::FETCH_COLUMN ? array_column($this->rows, 0) : $this->rows; }
    public function fetchColumn(int $column = 0): mixed { return isset($this->rows[0]) ? array_values($this->rows[0])[$column] : false; }
}

$checks = 0;
function serviceCheck(bool $ok, string $label): void { global $checks; if (!$ok) throw new RuntimeException('FAIL: ' . $label); $checks++; }
function serviceReject(callable $callback, int|string $expected): void
{
    try { $callback(); }
    catch (ValidationException $e) { serviceCheck(is_string($expected) && isset($e->errors[$expected]), 'validation: ' . $expected); return; }
    catch (HttpException $e) { serviceCheck($expected === $e->status, 'HTTP: ' . $expected); return; }
    throw new RuntimeException('Expected rejection: ' . $expected);
}
$identity = ['ma_tai_khoan' => 1, 'ten_dang_nhap' => 'service.preview', 'trang_thai' => 'HOAT_DONG',
    'ma_nhan_vien' => 1, 'trang_thai_nhan_vien' => 'DANG_LAM', 'ma_cong_ty' => 1, 'ma_co_so' => 1,
    'cong_ty_cua_co_so' => 1, 'trang_thai_cong_ty' => 'HOAT_DONG', 'trang_thai_co_so' => 'HOAT_DONG',
    'ma_khach_hang' => 1, 'trang_thai_khach' => 'HOAT_DONG'];
$users = []; foreach (['QUAN_TRI_TAP_DOAN', 'QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO', 'NHAN_VIEN', 'KHACH_HANG'] as $role) $users[$role] = $identity + ['ten_vai_tro' => $role];
$input = ['ma_co_so' => '1', 'ten_dich_vu' => ' Nước uống ', 'loai_dich_vu' => 'DO_UONG', 'don_vi_tinh' => 'chai', 'don_gia' => '0', 'theo_doi_ton' => '1', 'so_luong_ton' => '0', 'mo_ta' => '', 'trang_thai' => 'DANG_CUNG_CAP'];
set_error_handler(static function (int $severity, string $message, string $file, int $line): never { throw new ErrorException($message, 0, $severity, $file, $line); });
try {
    serviceCheck(Service::validate($input) === [], 'zero price and stock valid');
    foreach (['ten_dich_vu' => 150, 'don_vi_tinh' => 30, 'mo_ta' => 255] as $field => $limit) {
        serviceCheck(!isset(Service::validate(array_replace($input, [$field => str_repeat('á', $limit)]))[$field]), 'Unicode VARCHAR boundary');
        serviceCheck(isset(Service::validate(array_replace($input, [$field => str_repeat('á', $limit + 1)]))[$field]), 'VARCHAR overflow');
    }
    foreach (['ten_dich_vu', 'don_vi_tinh'] as $field) foreach (['', '  ', [], "\xff", "bad\0"] as $bad) serviceCheck(isset(Service::validate(array_replace($input, [$field => $bad]))[$field]), 'bad text');
    foreach (['loai_dich_vu', 'trang_thai'] as $field) foreach ([[], '', 'UNKNOWN', null] as $bad) serviceCheck(isset(Service::validate(array_replace($input, [$field => $bad]))[$field]), 'enum types');
    foreach (['-1', '1e2', '1.5', '2147483648', [], '', ' 1 ', '01'] as $bad) serviceCheck(isset(Service::validate(array_replace($input, ['so_luong_ton' => $bad]))['so_luong_ton']), 'bad stock');
    foreach (['-1', '1e2', '1,00', '10000000000', '1.999', [], ''] as $bad) serviceCheck(isset(Service::validate(array_replace($input, ['don_gia' => $bad]))['don_gia']), 'bad decimal');
    foreach (['0', '0.01', '9999999999.99', '0000000001.5'] as $good) serviceCheck(!isset(Service::validate(array_replace($input, ['don_gia' => $good]))['don_gia']), 'exact price boundary');
    serviceCheck(Service::normalize(array_replace($input, ['don_gia' => '0000000001.5', 'mo_ta' => '0']))['don_gia'] === '1.50', 'no float normalization');
    serviceCheck(Service::normalize(array_replace($input, ['mo_ta' => '0']))['mo_ta'] === '0', 'preserve zero description');
    serviceCheck(Service::normalize(array_replace($input, ['theo_doi_ton' => '0']))['so_luong_ton'] === null, 'NULL differs from zero');
    serviceCheck(Service::validate(array_replace($input, ['so_luong_ton' => '2147483647'])) === [], 'INT maximum');
    foreach ([[], ['ten_vai_tro' => []], $users['KHACH_HANG'], array_replace($users['NHAN_VIEN'], ['ma_co_so' => null]), array_replace($users['QUAN_LY_CO_SO'], ['cong_ty_cua_co_so' => 2]), array_replace($users['QUAN_LY_CONG_TY'], ['ma_cong_ty' => []])] as $bad) serviceReject(fn() => Service::scope($bad), 403);
    $pdo = new ServiceMemoryPDO(); $admin = new Service($pdo, $users['QUAN_TRI_TAP_DOAN']); $model = new DichVu($pdo);
    $id = $admin->save($input); serviceCheck($pdo->services[$id]['don_gia'] === '0.00' && $pdo->services[$id]['so_luong_ton'] === 0, 'create free service');
    serviceReject(fn() => $admin->save($input), 'ten_dich_vu');
    $other = $admin->save(array_replace($input, ['ma_co_so' => '2'])); serviceCheck($other !== $id, 'same name in another facility');
    $pdo->facilities[2]['trang_thai'] = 'TAM_NGUNG';
    serviceReject(fn() => $admin->save(array_replace($input, ['ma_co_so' => '2', 'ten_dich_vu' => 'Dịch vụ mới'])), 'ma_co_so');
    $pdo->facilities[2]['trang_thai'] = 'HOAT_DONG';
    $pdo->companies[2]['trang_thai'] = 'NGUNG_HOAT_DONG';
    serviceReject(fn() => $admin->save(array_replace($input, ['ma_co_so' => '3'])), 'ma_co_so');
    $pdo->companies[2]['trang_thai'] = 'HOAT_DONG';
    $companyWriter = new Service($pdo, $users['QUAN_LY_CONG_TY']); $facilityWriter = new Service($pdo, $users['QUAN_LY_CO_SO']);
    serviceCheck($companyWriter->save(array_replace($input, ['ma_co_so' => '2', 'ten_dich_vu' => 'Trong công ty'])) > 0, 'company manager can create in company');
    serviceCheck($facilityWriter->save(array_replace($input, ['ten_dich_vu' => 'Trong cơ sở'])) > 0, 'facility manager can create in facility');
    $edit = fn(array $changes = []) => array_replace($input, ['ten_dich_vu' => $pdo->services[$id]['ten_dich_vu'], 'version' => Service::version($pdo->services[$id])], $changes);
    $admin->save($edit(['so_luong_ton' => '100']), $id); $original = $edit();
    $admin->save(array_replace($original, ['so_luong_ton' => '80']), $id);
    serviceReject(fn() => $admin->save(array_replace($original, ['so_luong_ton' => '90']), $id), 409);
    serviceCheck($pdo->services[$id]['so_luong_ton'] === 80, 'stale Admin cannot overwrite 80 with 90');
    serviceReject(fn() => $admin->save($edit(['don_vi_tinh' => 'thùng', 'so_luong_ton' => '0']), $id), 'don_vi_tinh');
    $admin->save($edit(['so_luong_ton' => '0']), $id);
    foreach (['CHO_XAC_NHAN', 'CHO_THANH_TOAN', 'DA_XAC_NHAN', 'UNKNOWN', null] as $state) {
        $pdo->refs[$id] = [$state];
        serviceReject(fn() => $admin->save($edit(['don_vi_tinh' => 'lon']), $id), 'don_vi_tinh');
        serviceReject(fn() => $admin->save($edit(['loai_dich_vu' => 'CHO_THUE']), $id), 'loai_dich_vu');
        $admin->save($edit(['don_gia' => '12500.50']), $id);
    }
    foreach (['HOAN_THANH', 'DA_HUY', 'HET_HAN'] as $state) {
        $pdo->refs[$id] = [$state]; $admin->save($edit(['don_vi_tinh' => $pdo->services[$id]['don_vi_tinh'] === 'chai' ? 'lon' : 'chai']), $id);
        serviceReject(fn() => $admin->save($edit(['ma_co_so' => '2']), $id), 'ma_co_so');
    }
    $refsBefore = $pdo->refs;
    $rowBefore = $pdo->services[$id];
    serviceReject(fn() => $admin->changeStatus($id, 'NGUNG', Service::version($rowBefore), false), 'confirmed');
    $admin->changeStatus($id, 'NGUNG', Service::version($rowBefore), true);
    serviceCheck(array_replace($rowBefore, ['trang_thai' => 'NGUNG']) === $pdo->services[$id] && $pdo->refs === $refsBefore, 'stop only changes master state');
    $pdo->facilities[1]['trang_thai'] = 'TAM_NGUNG';
    serviceReject(fn() => $admin->changeStatus($id, 'DANG_CUNG_CAP', Service::version($pdo->services[$id]), true), 'ma_co_so');
    $pdo->facilities[1]['trang_thai'] = 'HOAT_DONG'; $pdo->companies[1]['trang_thai'] = 'NGUNG_HOAT_DONG';
    serviceReject(fn() => $admin->changeStatus($id, 'DANG_CUNG_CAP', Service::version($pdo->services[$id]), true), 'ma_co_so');
    $pdo->companies[1]['trang_thai'] = 'HOAT_DONG';
    $admin->changeStatus($id, 'DANG_CUNG_CAP', Service::version($pdo->services[$id]), true);
    serviceCheck($pdo->services[$id]['trang_thai'] === 'DANG_CUNG_CAP', 'reactivate valid service');
    serviceCheck(Service::version($pdo->services[$id]) !== Service::version(array_replace($pdo->services[$id], ['so_luong_ton' => null])), 'fingerprint distinguishes NULL and zero');
    foreach ([1062 => 'ten_dich_vu', 1452 => 'ma_co_so', 1205 => 409, 1213 => 409] as $failure => $expected) {
        $before = $pdo->services; $pdo->failWrite = $failure;
        serviceReject(fn() => $admin->save($edit(['don_gia' => '20000']), $id), $expected);
        serviceCheck($pdo->services === $before && !$pdo->inTransaction(), 'failed transaction rolled back');
    }
    $pdo->failWrite = null;
    $locks = array_values(array_filter($pdo->calls, fn($c) => str_contains($c['sql'], 'FOR UPDATE')));
    serviceCheck(str_contains($locks[0]['sql'], 'cong_ty_nhuong_quyen') && str_contains($locks[1]['sql'], 'co_so'), 'parent locks first');
    $pdo->services = []; $pdo->refs = [];
    for ($i = 1; $i <= 53; $i++) $pdo->services[$i] = Service::normalize(array_replace($input, [
        'ma_co_so' => (string) (($i - 1) % 3 + 1), 'ten_dich_vu' => 'Dịch vụ ' . $i,
        'loai_dich_vu' => array_keys(Service::TYPES)[$i % 4], 'don_vi_tinh' => $i % 2 ? 'chai' : 'cây',
        'don_gia' => $i % 2 ? '10000.50' : '0', 'theo_doi_ton' => $i % 3 ? '1' : '0', 'so_luong_ton' => (string) ($i % 2 ? 100 : 0),
    ])) + ['ma_dich_vu' => $i];
    $pdo->services[11]['ten_dich_vu'] = 'Literal %_! <script>unsafe</script>';
    $pdo->services[3]['don_vi_tinh'] = 'ngoài phạm vi';
    foreach (array_slice($users, 0, 4, true) as $role => $user) {
        $service = new Service($pdo, $user); $scope = $service->scope;
        $expected = count(array_filter($pdo->services, fn($r) => $role === 'QUAN_TRI_TAP_DOAN' || ($role === 'QUAN_LY_CONG_TY' ? $r['ma_co_so'] !== 3 : $r['ma_co_so'] === 1)));
        foreach ([10, 20, 50] as $size) {
            $r = $model->page($service->filters(['per_page' => (string) $size, 'page' => '999']), $scope);
            serviceCheck($r['total'] === $expected && $r['page'] === $r['pages'] && count($r['items']) <= $size, "$role scoped pagination");
            [$countCall, $pageCall] = array_slice($pdo->calls, -2);
            serviceCheck(str_contains($pageCall['sql'], 'ORDER BY d.ma_dich_vu DESC LIMIT :limit OFFSET :offset') && $pageCall['types']['limit'] === PDO::PARAM_INT, 'prepared backend pagination');
            if ($role !== 'QUAN_TRI_TAP_DOAN') foreach ([$countCall, $pageCall] as $call) serviceCheck(str_contains($call['sql'], 'f.ma_cong_ty = :scope_company') && $call['params']['scope_company'] === 1, 'scope in count and item SQL');
        }
        if ($role !== 'QUAN_TRI_TAP_DOAN') {
            serviceReject(fn() => $service->find(3), 404);
            serviceReject(fn() => $service->filters(['ma_co_so' => '3']), 403);
            serviceCheck(!in_array('ngoài phạm vi', $model->units($scope), true), 'unit choices scoped');
            serviceCheck(!in_array(3, array_column($model->facilities($scope), 'ma_co_so'), true), 'facility choices scoped');
            if ($role !== 'QUAN_LY_CONG_TY') {
                serviceReject(fn() => $service->find(2), 404);
                serviceReject(fn() => $service->filters(['ma_co_so' => '2']), 403);
                serviceCheck(array_column($model->facilities($scope), 'ma_co_so') === [1], 'one assigned facility only');
            }
        }
        if ($role === 'NHAN_VIEN') {
            $before = $pdo->services; serviceReject(fn() => $service->save($input), 403);
            serviceReject(fn() => $service->changeStatus(1, 'NGUNG', Service::version($before[1]), true), 403);
            serviceCheck($pdo->services === $before, 'read-only staff cannot mutate');
        } else if ($role !== 'QUAN_TRI_TAP_DOAN') {
            serviceReject(fn() => $service->save(array_replace($input, ['ma_co_so' => '3'])), 403);
        }
        $query = ['q' => 'Dịch vụ', 'ma_co_so' => '1', 'loai_dich_vu' => 'DO_UONG', 'trang_thai' => 'DANG_CUNG_CAP', 'don_vi_tinh' => 'chai'];
        foreach ($query as $field => $value) { $r = $model->page($service->filters([$field => $value]), $scope); serviceCheck($r['total'] > 0, 'individual filter: ' . $field); }
        $r = $model->page($service->filters($query), $scope); serviceCheck($r['total'] > 0, 'combined filters');
        foreach ([['q' => []], ['page' => '0'], ['per_page' => '15'], ['don_vi_tinh' => []], ['loai_dich_vu' => 'BAD']] as $bad) serviceReject(fn() => $service->filters($bad), 422);
    }
    $company = new Service($pdo, $users['QUAN_LY_CONG_TY']); $facility = new Service($pdo, $users['QUAN_LY_CO_SO']);
    serviceCheck($model->page($admin->filters(['q' => '%_!']), $admin->scope)['total'] === 1, 'LIKE literal escaping');
    serviceCheck($model->page($admin->filters(['q' => "' OR 1=1 --"]), $admin->scope)['total'] === 0, 'SQL injection stays data');
    $filters = $facility->filters(['scope_company' => '2', 'scope_facility' => '3']);
    $r = $model->page($filters + ['scope_company' => 2, 'scope_facility' => 3], $facility->scope);
    serviceCheck($r['total'] === 18, 'client cannot replace trusted scope');

    $_SERVER['SCRIPT_NAME'] = '/index.php'; ini_set('session.save_path', dirname(__DIR__) . '/storage/temp'); Session::start();
    $accounts = new class extends TaiKhoan { public array $user = []; public function __construct() {} public function identity(int $id): ?array { return $this->user; } };
    $app = new Application($pdo); (new ReflectionProperty(Application::class, 'auth'))->setValue($app, new AuthService($accounts));
    $router = new Router(); require dirname(__DIR__) . '/routes/services.php';
    $servicePreviews = [];
    foreach ($users as $role => $user) {
        $accounts->user = $user; Session::authenticate(1); $base = Service::basePath($role);
        if ($role === 'KHACH_HANG') { foreach (['/admin', '/company', '/facility', '/staff'] as $area) serviceReject(fn() => $router->dispatch('GET', $area . '/dich-vu'), 403); continue; }
        foreach (['index' => '', 'show' => '/show', 'create' => '/create', 'edit' => '/edit'] as $page => $suffix) {
            $_GET = ['id' => '1', 'page' => $page === 'index' ? '2' : '1'];
            if ($role === 'NHAN_VIEN' && in_array($page, ['create', 'edit'], true)) { serviceReject(fn() => $router->dispatch('GET', $base . $suffix), 404); continue; }
            $before = $pdo->services; ob_start(); $router->dispatch('GET', $base . $suffix); $html = ob_get_clean();
            serviceCheck($pdo->services === $before, 'GET never writes');
            serviceCheck(str_contains($html, 'class="admin-sidebar"') === ($role === 'QUAN_TRI_TAP_DOAN'), "$role correct Sidebar isolation");
            serviceCheck(!str_contains($html, '<script>unsafe</script>'), 'escaped text');
            if ($role !== 'QUAN_TRI_TAP_DOAN') serviceCheck(!str_contains($html, '>Đặt sân</a>') && str_contains($html, 'page-services'), 'role area has service UI, no customer booking nav');
            if ($page === 'index') {
                serviceCheck(str_contains($html, 'Tìm kiếm') && str_contains($html, 'Đặt lại') && !str_contains($html, '<caption>'), 'shared list UI');
                serviceCheck(str_contains($html, '<td class="list-stt">11</td>') && str_contains($html, 'per_page=10'), 'STT and pagination');
                serviceCheck(str_contains($html, '+ Thêm dịch vụ') === ($role !== 'NHAN_VIEN'), 'staff no create control');
                if ($role === 'NHAN_VIEN') serviceCheck(!str_contains($html, 'list-action--edit') && !str_contains($html, 'list-action--stop'), 'staff no write actions');
            }
            $servicePreviews[$role][$page] = $html;
        }
        foreach ([10, 20, 50] as $size) {
            $_GET = ['page' => '2', 'per_page' => (string) $size];
            ob_start(); $router->dispatch('GET', $base); $html = ob_get_clean();
            preg_match_all('/<td class="list-stt">([0-9]+)<\/td>/', $html, $numbers);
            $count = $role === 'QUAN_TRI_TAP_DOAN' ? 53 : ($role === 'QUAN_LY_CONG_TY' ? 36 : 18);
            $pageNumber = min(2, (int) ceil($count / $size)); $start = ($pageNumber - 1) * $size + 1;
            serviceCheck(array_map('intval', $numbers[1]) === range($start, min($pageNumber * $size, $count)), "$role consecutive STT at size $size");
        }
        $_GET = ['id' => '1', 'confirm' => '1']; ob_start(); $router->dispatch('GET', $base . '/show'); $html = ob_get_clean();
        serviceCheck(str_contains($html, 'name="confirmed"') === ($role !== 'NHAN_VIEN'), 'only writers see confirmation'); $servicePreviews[$role]['confirm'] = $html;
        foreach (['store', 'update', 'status'] as $action) { $_POST = []; serviceReject(fn() => $router->dispatch('POST', $base . '/' . $action), $role === 'NHAN_VIEN' ? 404 : 403); }
        if ($role !== 'QUAN_TRI_TAP_DOAN') {
            $_GET = ['id' => '3']; serviceReject(fn() => $router->dispatch('GET', $base . '/show'), 404);
            if ($role !== 'NHAN_VIEN') {
                $_POST = ['_token' => Csrf::token(), 'id' => '3']; serviceReject(fn() => $router->dispatch('POST', $base . '/update'), 404);
                serviceReject(fn() => $router->dispatch('POST', $base . '/status'), 404);
            }
            $_GET = ['ma_co_so' => '3']; serviceReject(fn() => $router->dispatch('GET', $base), 403);
            $_POST = ['_token' => Csrf::token()]; serviceReject(fn() => $router->dispatch('POST', '/admin/dich-vu/store'), 403);
        }
        if ($role !== 'NHAN_VIEN') {
            $_GET = []; $_POST = ['_token' => Csrf::token(), 'ten_dich_vu' => '<keep-input>'];
            ob_start(); $router->dispatch('POST', $base . '/store'); $html = ob_get_clean();
            serviceCheck(http_response_code() === 422 && str_contains($html, 'value="&lt;keep-input&gt;"') && str_contains($html, 'aria-invalid="true"'), 'validation preserves escaped input');
            $_POST = array_replace($input, ['id' => '1', '_token' => Csrf::token(), 'version' => str_repeat('a', 64), 'ten_dich_vu' => 'Giữ dữ liệu khi xung đột', 'so_luong_ton' => '90']);
            ob_start(); $router->dispatch('POST', $base . '/update'); $html = ob_get_clean();
            serviceCheck(http_response_code() === 409 && str_contains($html, 'value="90"') && str_contains($html, 'Tải lại dữ liệu mới nhất'), '409 preserves stock and reload link'); $servicePreviews[$role]['conflict'] = $html;
        }
        serviceReject(fn() => $router->dispatch('GET', $base . '/delete'), 404);
        $_POST = []; serviceReject(fn() => $router->dispatch('POST', $base . '/delete'), 404);
    }
    foreach ($pdo->calls as $call) serviceCheck(!preg_match('/\A(?:DELETE|ALTER|CREATE|DROP)\b/', $call['sql']) && !preg_match('/\A(?:INSERT INTO|UPDATE) (?!dich_vu\b)/', $call['sql']), 'no schema, booking or invoice writes');
} finally {
    restore_error_handler(); if (session_status() === PHP_SESSION_ACTIVE) { Session::forgetAuthentication(); session_write_close(); }
}
echo "PASS: $checks service checks in memory; no MySQL connection or database writes.\n";
