<?php

// Real models/controllers/views with in-memory PDO; no .env or database connection.
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\{BoMon, LoaiSan, CoSo, San, BangGia, TaiKhoan};
use App\Services\{AdminListService, BangGiaService, AuthService};
use Core\{Application, HttpException, Router, Session, View};

final class ListMemoryPDO extends PDO
{
    public array $rows = [];
    public array $calls = [];
    public array $modules = [
        'bo-mon' => ['bo_mon b', 'ma_bo_mon', 'ten_bo_mon', [], ['HOAT_DONG', 'NGUNG']],
        'loai-san' => ['loai_san l', 'ma_loai_san', 'ten_loai_san', ['ma_bo_mon'], ['HOAT_DONG', 'NGUNG']],
        'co-so' => ['co_so f', 'ma_co_so', 'ten_co_so', ['ma_cong_ty'], ['HOAT_DONG', 'TAM_NGUNG']],
        'san' => ['san s', 'ma_san', 'ten_san', ['ma_co_so', 'ma_loai_san'], ['HOAT_DONG', 'BAO_TRI', 'TAM_NGUNG']],
        'bang-gia' => ['bang_gia g', 'ma_bang_gia', 'ten_bang_gia', ['ma_co_so', 'ma_loai_san'], ['DANG_AP_DUNG', 'NGUNG']],
    ];

    public function __construct()
    {
        foreach ($this->modules as $slug => [$from, $key, $name, $links, $states]) {
            for ($i = 0; $i < 53; $i++) {
                $this->rows[$slug][] = [
                    'ma_bo_mon' => $i % 3 + 1, 'ma_loai_san' => intdiv($i, 3) % 2 + 1,
                    'ma_co_so' => intdiv($i, 2) % 3 + 1, 'ma_cong_ty' => $i % 2 + 1,
                    'ten_bo_mon' => 'Bộ môn', 'ten_loai_san' => 'Loại sân', 'ten_co_so' => 'Cơ sở',
                    'ten_cong_ty' => 'Công ty', 'trang_thai_cong_ty' => 'HOAT_DONG',
                    'mo_ta' => 'Mô tả', 'dia_chi' => 'Địa chỉ', 'gio_mo_cua' => '06:00:00', 'gio_dong_cua' => '23:00:00',
                    'so_loai_san' => 2, 'so_san' => 2, 'loai_ngay' => $i % 2 ? 'CUOI_TUAN' : 'NGAY_THUONG',
                    'ngay_bat_dau' => '2020-01-01', 'ngay_ket_thuc' => null, 'gio_bat_dau' => '08:00:00',
                    'gio_ket_thuc' => '10:00:00', 'don_gia' => '80000.00',
                ];
                $this->rows[$slug][$i][$key] = 1000 - $i * 7;
                $this->rows[$slug][$i][$name] = 'Nhóm ' . ($i % 3) . ' ' . $slug . ' ' . $i;
                $this->rows[$slug][$i]['trang_thai'] = $states[$i % count($states)];
            }
            $this->rows[$slug][8][$name] = 'Ký tự %_! và <script>unsafe</script>';
        }
    }

    public function prepare(string $query, array $options = []): PDOStatement|false { return new ListMemoryStatement($this, $query); }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $stmt = $this->prepare($query);
        $stmt->execute();
        return $stmt;
    }
}

final class ListMemoryStatement extends PDOStatement
{
    private array $params = [];
    private array $types = [];
    private array $rows = [];

    public function __construct(private ListMemoryPDO $pdo, private string $sql) {}
    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $key = ltrim((string) $param, ':');
        $this->params[$key] = $value;
        $this->types[$key] = $type;
        return true;
    }
    public function execute(?array $params = null): bool
    {
        $params ??= $this->params;
        $sql = preg_replace('/\s+/', ' ', $this->sql);
        if (!str_starts_with($sql, 'SELECT ')) throw new RuntimeException('Writes forbidden in list tests');
        $this->pdo->calls[] = compact('sql', 'params') + ['types' => $this->types];
        if (str_contains($sql, 'FROM cong_ty_nhuong_quyen ORDER')) {
            $this->rows = [['ma_cong_ty' => 1, 'ten_cong_ty' => 'Công ty 1'], ['ma_cong_ty' => 2, 'ten_cong_ty' => 'Công ty 2']];
            return true;
        }
        foreach ($this->pdo->modules as $slug => [$from, $key, $name]) {
            if (!str_contains($sql, ' FROM ' . $from)) continue;
            $rows = $this->pdo->rows[$slug];
            foreach ($params as $field => $value) {
                if ($field === 'keyword') {
                    $literal = strtr(substr($value, 1, -1), ['!!' => '!', '!%' => '%', '!_' => '_']);
                    $rows = array_filter($rows, fn($r) => mb_stripos($r[$name], $literal) !== false);
                } elseif (!in_array($field, ['limit', 'offset'], true)) {
                    $rows = array_filter($rows, fn($r) => (string) $r[$field] === (string) $value);
                }
            }
            usort($rows, fn($a, $b) => $b[$key] <=> $a[$key]);
            $this->rows = str_starts_with($sql, 'SELECT COUNT(*)') ? [['total' => count($rows)]]
                : array_slice($rows, $params['offset'] ?? 0, $params['limit'] ?? count($rows));
            return true;
        }
        throw new RuntimeException('Unexpected SELECT: ' . $sql);
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column = 0): mixed { return isset($this->rows[0]) ? array_values($this->rows[0])[$column] : false; }
}

$checks = 0;
function checkList(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
function rejectList(callable $callback, int $status): void
{
    try { $callback(); } catch (HttpException $e) { checkList($e->status === $status, 'HTTP ' . $status); return; }
    throw new RuntimeException('Expected HTTP ' . $status);
}

$pdo = new ListMemoryPDO();
$models = ['bo-mon' => new BoMon($pdo), 'loai-san' => new LoaiSan($pdo), 'co-so' => new CoSo($pdo), 'san' => new San($pdo), 'bang-gia' => new BangGia($pdo)];
$adminListPreviews = [];
$formatPrice = require dirname(__DIR__) . '/app/views/admin/bang-gia/format-price.php';
foreach ([['80000.00', '80.000 ₫/giờ'], ['80000.50', '80.000,50 ₫/giờ'], ['0.01', '0,01 ₫/giờ'],
    ['9999999999.99', '9.999.999.999,99 ₫/giờ'], ['0000000001', '1 ₫/giờ'], ['12.5', '12,50 ₫/giờ'],
    ['', '—'], ['<script>bad</script>', '—'], [[], '—'], ['1e5', '—']] as [$input, $expected]) {
    checkList($formatPrice($input) === $expected, 'exact Vietnamese price display');
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
ini_set('session.save_path', dirname(__DIR__) . '/storage/temp');
Session::start();
set_error_handler(static function (int $severity, string $message, string $file, int $line): never { throw new ErrorException($message, 0, $severity, $file, $line); });
try {
    foreach ($models as $slug => $model) {
        [$from, $key, $name, $links, $states] = $pdo->modules[$slug];
        $options = $slug === 'bang-gia' ? ['loai_ngay' => ['NGAY_THUONG', 'CUOI_TUAN'], 'trang_thai' => $states] : ['trang_thai' => $states];
        $parse = fn($query) => $slug === 'bang-gia' ? BangGiaService::filters($query) : AdminListService::filters($query, $links, $options);
        foreach ([['page' => '0'], ['page' => '-1'], ['page' => 'bad'], ['page' => []], ['page' => '2147483648'],
            ['per_page' => '15'], ['q' => []], ['q' => "\xff"], ['q' => "bad\0"], ['q' => str_repeat('á', 151)], ['trang_thai' => 'UNKNOWN']] as $bad) {
            rejectList(fn() => $parse($bad), 422);
        }
        foreach ($links as $field) foreach (['01', '0', '1 OR 1=1', ['1']] as $bad) rejectList(fn() => $parse([$field => $bad]), 422);
        foreach ([10, 20, 50] as $size) foreach ([1, 2, 999] as $requested) {
            $filters = $parse(['per_page' => (string) $size, 'page' => (string) $requested]);
            $result = $model->page($filters);
            $expectedPage = min($requested, (int) ceil(53 / $size));
            checkList($result['total'] === 53 && $result['page'] === $expectedPage && $result['pages'] === (int) ceil(53 / $size), "$slug page metadata");
            checkList(count($result['items']) === min($size, 53 - ($expectedPage - 1) * $size), "$slug page length");
            checkList($result['items'][0][$key] === 1000 - ($expectedPage - 1) * $size * 7, "$slug newest first, actual ID");
            [$countCall, $pageCall] = array_slice($pdo->calls, -2);
            checkList(str_starts_with($countCall['sql'], 'SELECT COUNT(*)'), "$slug backend total");
            checkList(str_contains($pageCall['sql'], "ORDER BY " . substr($from, -1) . ".$key DESC LIMIT :limit OFFSET :offset"), "$slug stable SQL order and database pagination");
            checkList($pageCall['types']['limit'] === PDO::PARAM_INT && $pageCall['types']['offset'] === PDO::PARAM_INT, "$slug integer bindings");
            checkList($pageCall['params']['limit'] === $size && $pageCall['params']['offset'] === ($expectedPage - 1) * $size, "$slug real limit/offset");
        }
        $sample = ['q' => 'Nhóm 1', 'trang_thai' => $states[0]];
        foreach ($links as $field) $sample[$field] = '1';
        if ($slug === 'bang-gia') $sample['loai_ngay'] = 'NGAY_THUONG';
        $fields = array_keys($sample);
        for ($mask = 1; $mask < (1 << count($fields)); $mask++) {
            $query = [];
            foreach ($fields as $bit => $field) if ($mask & (1 << $bit)) $query[$field] = $sample[$field];
            $result = $model->page($parse($query));
            $expected = array_filter($pdo->rows[$slug], static function ($row) use ($query, $name) {
                foreach ($query as $field => $value) {
                    if ($field === 'q' ? !str_contains($row[$name], $value) : (string) $row[$field] !== $value) return false;
                }
                return true;
            });
            checkList($result['total'] === count($expected), "$slug individual/combined filters");
            [$countCall, $pageCall] = array_slice($pdo->calls, -2);
            checkList($countCall['params'] === array_diff_key($pageCall['params'], ['limit' => 1, 'offset' => 1]), "$slug count/select same filter bindings");
        }
        $literal = $model->page($parse(['q' => '%_!']));
        checkList($literal['total'] === 1, "$slug wildcard characters are literal");
        checkList($model->page($parse(['q' => "' OR 1=1 --"]))['total'] === 0, "$slug SQL injection remains data");

        foreach (['page1' => [], 'page2' => ['page' => '2'], 'page20' => ['page' => '2', 'per_page' => '20'],
            'page50' => ['page' => '2', 'per_page' => '50'], 'last' => ['page' => '999'],
            'empty' => ['q' => 'NO_MATCH'], 'filtered' => $sample + ['per_page' => '20']] as $case => $query) {
            $filters = $parse($query);
            $data = $model->page($filters) + ['title' => 'Kiểm tra danh sách', 'filters' => $filters, 'notice' => null,
                'sports' => $pdo->rows['bo-mon'], 'companies' => [['ma_cong_ty' => 1, 'ten_cong_ty' => 'Công ty 1']],
                'facilities' => $pdo->rows['co-so'], 'types' => $pdo->rows['loai-san']];
            ob_start(); View::render('admin/' . $slug . '/index', $data); $html = ob_get_clean();
            checkList(!str_contains($html, '<caption>') && str_contains($html, '>STT</th>'), "$slug no upper caption, STT header");
            checkList(!str_contains($html, '<script>unsafe</script>'), "$slug escaped names");
            checkList(str_contains($html, '<span>Tìm kiếm</span>') && str_contains($html, '<span>Đặt lại</span>'), "$slug toolbar labels");
            if ($slug === 'bang-gia' && $data['items']) {
                checkList(str_contains($html, '80.000 ₫/giờ') && str_contains($html, 'value="80000.00"'), 'format only display, preserve DECIMAL value');
                checkList(str_contains($html, 'list-action-menu') && str_contains($html, 'confirm=1#status-confirmation'), 'price overflow action keeps confirmation URL');
            }
            checkList(str_contains($html, 'list-pagination__summary') && str_contains($html, 'tổng số ' . $data['total'] . ' kết quả'), "$slug footer total");
            checkList(!preg_match('/name="page"/', $html), "$slug submitting filters/page size resets page");
            preg_match_all('/<td class="list-stt">([0-9]+)<\/td>/', $html, $numbers);
            $expectedNumbers = $data['total'] ? range(($data['page'] - 1) * $filters['per_page'] + 1, ($data['page'] - 1) * $filters['per_page'] + count($data['items'])) : [];
            checkList(array_map('intval', $numbers[1]) === $expectedNumbers, "$slug consecutive STT across pages");
            if ($case === 'page2') {
                preg_match('/rel="prev" href="([^"]+)"/', $html, $previous);
                parse_str(parse_url(html_entity_decode($previous[1]), PHP_URL_QUERY), $previousQuery);
                checkList($previousQuery['page'] === '1' && $previousQuery['per_page'] === '10', "$slug previous page URL");
                $id = $data['items'][0][$key];
                foreach (['show', 'edit', $slug === 'bang-gia' ? 'show' : 'delete'] as $action) {
                    checkList(str_contains($html, e(url('/admin/' . $slug . '/' . $action) . '&id=' . $id)), "$slug action keeps database ID");
                }
                checkList(str_contains($html, 'list-action--view') && str_contains($html, 'list-action--edit') && str_contains($html, '<svg viewBox="0 0 24 24"'), "$slug SVG actions");
            }
            $adminListPreviews[$slug][$case] = $html;
        }
        // Force several pages under all filters to inspect every preserved URL parameter.
        $filters = $parse($sample + ['page' => '2', 'per_page' => '20']);
        $listPath = '/admin/' . $slug; $listLabel = $slug; $page = 2; $pages = 4; $total = 65;
        ob_start(); require dirname(__DIR__) . '/app/views/admin/partials/pagination.php'; $linksHtml = ob_get_clean();
        preg_match('/rel="next" href="([^"]+)"/', $linksHtml, $next);
        parse_str(parse_url(html_entity_decode($next[1]), PHP_URL_QUERY), $nextQuery);
        foreach ($sample + ['per_page' => '20', 'page' => '3'] as $field => $value) checkList(($nextQuery[$field] ?? null) === $value, "$slug pagination retains $field");
    }

    $accounts = new class extends TaiKhoan {
        public array $user = [];
        public function __construct() {}
        public function identity(int $id): ?array { return $this->user; }
    };
    $app = new Application($pdo);
    (new ReflectionProperty(Application::class, 'auth'))->setValue($app, new AuthService($accounts));
    $router = new Router();
    require dirname(__DIR__) . '/routes/catalog.php';
    require dirname(__DIR__) . '/routes/facilities.php';
    require dirname(__DIR__) . '/routes/prices.php';
    $identity = ['ma_tai_khoan' => 1, 'ten_dang_nhap' => 'admin.preview', 'trang_thai' => 'HOAT_DONG',
        'ma_khach_hang' => 1, 'trang_thai_khach' => 'HOAT_DONG', 'ma_nhan_vien' => 1, 'trang_thai_nhan_vien' => 'DANG_LAM',
        'ma_cong_ty' => 1, 'ma_co_so' => 1, 'cong_ty_cua_co_so' => 1, 'trang_thai_cong_ty' => 'HOAT_DONG', 'trang_thai_co_so' => 'HOAT_DONG'];
    foreach (['QUAN_TRI_TAP_DOAN', 'KHACH_HANG', 'QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO', 'NHAN_VIEN'] as $role) {
        $accounts->user = $identity + ['ten_vai_tro' => $role]; Session::authenticate(1);
        foreach ($models as $slug => $model) {
            $_GET = ['q' => 'Nhóm', 'per_page' => '20', 'page' => '2'];
            $queryCount = count($pdo->calls);
            if ($role === 'QUAN_TRI_TAP_DOAN') {
                ob_start(); $router->dispatch('GET', '/admin/' . $slug); $html = ob_get_clean();
                checkList(str_contains($html, 'aria-current="page"') && str_contains($html, 'class="list-toolbar"'), "$slug Admin controller renders paged list");
                $_GET = ['page' => '0']; rejectList(fn() => $router->dispatch('GET', '/admin/' . $slug), 422);
                $_POST = []; rejectList(fn() => $router->dispatch('POST', '/admin/' . $slug . '/store'), 403);
            } else {
                rejectList(fn() => $router->dispatch('GET', '/admin/' . $slug), 403);
                checkList(count($pdo->calls) === $queryCount, "$slug forbidden role does not query list");
            }
        }
    }
} finally {
    restore_error_handler(); Session::forgetAuthentication(); session_write_close();
}
echo "PASS: $checks Admin list checks across five modules; no database connection or writes.\n";
