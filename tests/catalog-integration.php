<?php

// Opt-in local HTTP/DB tests. Only this run's recorded fixture IDs are modified.
if (PHP_SAPI !== 'cli' || !in_array('--run', $argv, true)) {
    exit("Usage: php tests/catalog-integration.php --run\nSet LOCAL_ADMIN_USERNAME and LOCAL_ADMIN_PASSWORD in the process environment.\n");
}
require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
$config = require dirname(__DIR__) . '/config/database.php';
$base = rtrim(getenv('CATALOG_TEST_URL') ?: 'http://localhost/BaiTapNhomPHP/public', '/');
if (!in_array(strtolower($config['host']), ['localhost', '127.0.0.1', '::1'], true)
    || !in_array(strtolower(parse_url($base, PHP_URL_HOST) ?: ''), ['localhost', '127.0.0.1', '::1'], true)) {
    fwrite(STDERR, "Tests require local database and HTTP hosts.\n");
    exit(1);
}
$adminUsername = getenv('LOCAL_ADMIN_USERNAME');
$adminPassword = getenv('LOCAL_ADMIN_PASSWORD');
if (!$adminUsername || !$adminPassword) {
    fwrite(STDERR, "Missing local Admin credentials in process environment.\n");
    exit(1);
}
$pdo = (new Core\Database())->getConnection();
$checks = 0;
$tag = 'PR1_' . bin2hex(random_bytes(5));
$fixtures = ['bo_mon' => [], 'loai_san' => [], 'san' => [], 'bang_gia' => [],
    'cong_ty_nhuong_quyen' => [], 'co_so' => [], 'tai_khoan' => [], 'khach_hang' => [], 'nhan_vien' => []];
$keys = ['bo_mon' => 'ma_bo_mon', 'loai_san' => 'ma_loai_san', 'san' => 'ma_san', 'bang_gia' => 'ma_bang_gia',
    'cong_ty_nhuong_quyen' => 'ma_cong_ty', 'co_so' => 'ma_co_so', 'tai_khoan' => 'ma_tai_khoan',
    'khach_hang' => 'ma_khach_hang', 'nhan_vien' => 'ma_nhan_vien'];

function checkCatalog(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    $checks++;
}

function catalogSnapshot(): array
{
    global $pdo;
    $snapshot = [];
    foreach ($pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $table)) {
            throw new RuntimeException('Unexpected table name.');
        }
        $rows = array_map(static fn(array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $pdo->query("SELECT * FROM `$table`")->fetchAll());
        sort($rows);
        $snapshot[$table] = ['count' => count($rows), 'hash' => hash('sha256', implode("\n", $rows))];
    }
    ksort($snapshot);
    return $snapshot;
}

function catalogClient(): CurlHandle
{
    $client = curl_init();
    curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEFILE => '', CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15]);
    return $client;
}

function catalogRequest(CurlHandle $client, string $route, ?array $fields = null, array $query = []): array
{
    global $base;
    curl_setopt($client, CURLOPT_URL, $base . '/index.php?' . http_build_query(['route' => $route] + $query));
    curl_setopt($client, CURLOPT_POST, $fields !== null);
    if ($fields !== null) {
        curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $raw = curl_exec($client);
    if ($raw === false) {
        throw new RuntimeException('Local HTTP request failed.');
    }
    $length = curl_getinfo($client, CURLINFO_HEADER_SIZE);
    return ['status' => curl_getinfo($client, CURLINFO_RESPONSE_CODE),
        'body' => substr($raw, $length), 'headers' => substr($raw, 0, $length)];
}

function catalogToken(array $response): string
{
    if (!preg_match('/name="_token" value="([a-f0-9]{64})"/', $response['body'], $match)) {
        throw new RuntimeException('Missing CSRF token.');
    }
    return $match[1];
}

function catalogLogin(CurlHandle $client, string $username, string $password): string
{
    $token = catalogToken(catalogRequest($client, '/login'));
    checkCatalog(catalogRequest($client, '/login', ['_token' => $token, 'username' => $username, 'password' => $password])['status'] === 303, 'existing login flow');
    return catalogToken(catalogRequest($client, '/account'));
}

function fixtureInsert(string $table, array $data): int
{
    global $pdo, $fixtures;
    // Table/column names below are constants in this test, never HTTP input.
    $stmt = $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(', ', array_keys($data)) . ') VALUES (:' . implode(', :', array_keys($data)) . ')');
    $stmt->execute($data);
    $id = (int) $pdo->lastInsertId();
    $fixtures[$table][] = $id;
    return $id;
}

function fixtureUpdate(string $table, int $id, array $data): void
{
    global $pdo, $fixtures, $keys;
    if (!in_array($id, $fixtures[$table], true)) {
        throw new RuntimeException('Refusing to change a non-fixture record.');
    }
    $assignments = array_map(static fn(string $field): string => "$field = :$field", array_keys($data));
    $stmt = $pdo->prepare('UPDATE ' . $table . ' SET ' . implode(', ', $assignments) . ' WHERE ' . $keys[$table] . ' = :id');
    $stmt->execute($data + ['id' => $id]);
}

function fixtureDelete(string $table, int $id): void
{
    global $pdo, $fixtures, $keys;
    if (!in_array($id, $fixtures[$table], true)) {
        throw new RuntimeException('Refusing to delete a non-fixture record.');
    }
    $stmt = $pdo->prepare('DELETE FROM ' . $table . ' WHERE ' . $keys[$table] . ' = :id');
    $stmt->execute(['id' => $id]);
}

function catalogCreate(CurlHandle $client, string $token, string $table, array $fields): int
{
    global $pdo, $fixtures, $keys;
    $sport = $table === 'bo_mon';
    $nameField = $sport ? 'ten_bo_mon' : 'ten_loai_san';
    $response = catalogRequest($client, '/admin/' . ($sport ? 'bo-mon' : 'loai-san') . '/store', ['_token' => $token] + $fields);
    $stmt = $pdo->prepare('SELECT ' . $keys[$table] . ' FROM ' . $table . ' WHERE ' . $nameField . ' = :name' . ($sport ? '' : ' AND ma_bo_mon = :sport'));
    $stmt->execute(['name' => $fields[$nameField]] + ($sport ? [] : ['sport' => $fields['ma_bo_mon']]));
    $id = (int) $stmt->fetchColumn();
    if ($id) {
        $fixtures[$table][] = $id;
    }
    checkCatalog($response['status'] === 303 && $id > 0, 'HTTP create ' . $table);
    return $id;
}

$baseline = catalogSnapshot();
$guest = catalogClient();
$admin = catalogClient();
$other = catalogClient();
$racer = catalogClient();
$sports = new App\Models\BoMon($pdo);
$types = new App\Models\LoaiSan($pdo);
try {
    $guestToken = catalogToken(catalogRequest($guest, '/login'));
    foreach (['bo-mon', 'loai-san'] as $module) {
        foreach (['', '/create', '/show', '/edit', '/delete'] as $suffix) {
            checkCatalog(catalogRequest($guest, '/admin/' . $module . $suffix, null, ['id' => '1'])['status'] === 303, 'guest GET protected');
        }
        foreach (['/store', '/update', '/delete'] as $suffix) {
            checkCatalog(catalogRequest($guest, '/admin/' . $module . $suffix, ['_token' => $guestToken, 'id' => '1'])['status'] === 303, 'guest POST protected');
        }
    }
    $token = catalogLogin($admin, $adminUsername, $adminPassword);
    $landing = catalogRequest($admin, '/admin');
    checkCatalog($landing['status'] === 200 && strpos($landing['body'], 'Quản lý bộ môn') !== false, 'Admin management links');
    foreach (['bo-mon', 'loai-san'] as $module) {
        checkCatalog(catalogRequest($admin, '/admin/' . $module)['status'] === 200, 'list renders');
        foreach (['store', 'update', 'delete'] as $action) {
            checkCatalog(catalogRequest($admin, '/admin/' . $module . '/' . $action, ['id' => '1'])['status'] === 403, 'missing CSRF rejected');
        }
        checkCatalog(catalogRequest($admin, '/admin/' . $module . '/store', ['_token' => ['invalid']])['status'] === 403, 'array CSRF rejected');
    }
    $sportA = ['ten_bo_mon' => $tag . ' A', 'mo_ta' => '0', 'trang_thai' => 'HOAT_DONG'];
    $sportB = ['ten_bo_mon' => $tag . ' B', 'trang_thai' => 'HOAT_DONG'];
    $sportC = ['ten_bo_mon' => $tag . ' C', 'trang_thai' => 'NGUNG'];
    $a = catalogCreate($admin, $token, 'bo_mon', $sportA);
    $b = catalogCreate($admin, $token, 'bo_mon', $sportB);
    $c = catalogCreate($admin, $token, 'bo_mon', $sportC);
    // Separate sessions avoid PHP's session lock serializing the competing requests.
    $raceToken = catalogLogin($racer, $adminUsername, $adminPassword);
    $raceSport = catalogCreate($admin, $token, 'bo_mon', ['ten_bo_mon' => $tag . ' Race parent', 'trang_thai' => 'HOAT_DONG']);
    $races = [
        [
            ['/admin/bo-mon/store', ['ten_bo_mon' => $tag . ' Race duplicate', 'trang_thai' => 'HOAT_DONG']],
            ['/admin/bo-mon/store', ['ten_bo_mon' => $tag . ' Race duplicate', 'trang_thai' => 'HOAT_DONG']],
        ],
        [
            ['/admin/bo-mon/update', ['id' => (string) $raceSport, 'ten_bo_mon' => $tag . ' Race parent', 'trang_thai' => 'NGUNG']],
            ['/admin/loai-san/store', ['ma_bo_mon' => (string) $raceSport, 'ten_loai_san' => $tag . ' Race child', 'trang_thai' => 'HOAT_DONG']],
        ],
    ];
    foreach ($races as $requests) {
        $multi = curl_multi_init();
        foreach ($requests as $index => [$route, $fields]) {
            $client = [$admin, $racer][$index];
            curl_setopt_array($client, [CURLOPT_URL => $base . '/index.php?' . http_build_query(['route' => $route]),
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['_token' => [$token, $raceToken][$index]] + $fields)]);
            curl_multi_add_handle($multi, $client);
        }
        do {
            $multiStatus = curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running && $multiStatus === CURLM_OK);
        $statuses = [];
        foreach ([$admin, $racer] as $client) {
            $statuses[] = curl_getinfo($client, CURLINFO_RESPONSE_CODE);
            curl_multi_remove_handle($multi, $client);
        }
        curl_multi_close($multi);
        // Register all potential race-created fixtures before asserting the outcome.
        foreach (['bo_mon' => 'ten_bo_mon', 'loai_san' => 'ten_loai_san'] as $table => $nameField) {
            $stmt = $pdo->prepare('SELECT ' . $keys[$table] . ' FROM ' . $table . ' WHERE ' . $nameField . ' IN (:duplicate, :child)');
            $stmt->execute(['duplicate' => $tag . ' Race duplicate', 'child' => $tag . ' Race child']);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $raceId) {
                $fixtures[$table][] = (int) $raceId;
            }
        }
        sort($statuses);
        checkCatalog($statuses === [303, 422], 'concurrent duplicate or stop/create has exactly one successful writer');
    }
    $raceParent = $sports->find($raceSport);
    $raceRelated = $sports->related($raceSport);
    checkCatalog($raceParent['trang_thai'] === 'HOAT_DONG' || $raceRelated['loai_san_dang_dung'] === 0, 'concurrent writes preserve parent/child state');
    checkCatalog($sports->find($a)['mo_ta'] === '0', 'zero description stored');
    checkCatalog(catalogRequest($admin, '/admin/bo-mon/store', ['_token' => $token] + array_replace($sportA, ['ten_bo_mon' => strtolower($sportA['ten_bo_mon'])]))['status'] === 422, 'duplicate uses database collation');
    checkCatalog(catalogRequest($admin, '/admin/bo-mon/store', ['_token' => $token, 'ten_bo_mon' => ['bad'], 'trang_thai' => 'HOAT_DONG'])['status'] === 422, 'array name rejected');
    foreach (['0', '-1', '01', '1bad', '2147483648', ['1']] as $badId) {
        foreach (['bo-mon', 'loai-san'] as $module) {
            checkCatalog(catalogRequest($admin, '/admin/' . $module . '/show', null, ['id' => $badId])['status'] === 404, 'invalid route ID');
        }
    }
    checkCatalog(catalogRequest($admin, '/admin/bo-mon/show', null, ['id' => '2147483647'])['status'] === 404, 'nonexistent record');
    $createForm = catalogRequest($admin, '/admin/loai-san/create');
    checkCatalog($createForm['status'] === 200 && strpos($createForm['body'], 'value="' . $a . '"') !== false
        && strpos($createForm['body'], 'value="' . $c . '"') === false, 'create options contain only active parents');
    $typeA = ['ma_bo_mon' => (string) $a, 'ten_loai_san' => $tag . ' Indoor', 'mo_ta' => '<img src=x onerror=alert(1)>', 'trang_thai' => 'HOAT_DONG'];
    foreach ([(string) $c, '2147483647', '0', ['1']] as $badParent) {
        checkCatalog(catalogRequest($admin, '/admin/loai-san/store', ['_token' => $token] + array_replace($typeA, ['ma_bo_mon' => $badParent]))['status'] === 422, 'invalid/inactive parent rejected');
    }
    $type1 = catalogCreate($admin, $token, 'loai_san', $typeA);
    $typeB = array_replace($typeA, ['ma_bo_mon' => (string) $b]);
    $type2 = catalogCreate($admin, $token, 'loai_san', $typeB);
    checkCatalog(catalogRequest($admin, '/admin/loai-san/store', ['_token' => $token] + $typeA)['status'] === 422, 'duplicate type within same parent');
    $details = catalogRequest($admin, '/admin/loai-san/show', null, ['id' => (string) $type1]);
    checkCatalog($details['status'] === 200 && strpos($details['body'], '&lt;img') !== false && strpos($details['body'], '<img src=x') === false, 'detail escapes HTML');
    checkCatalog(strpos(catalogRequest($admin, '/admin/bo-mon/show', null, ['id' => (string) $a])['body'], $typeA['ten_loai_san']) !== false, 'sport detail links its types');
    checkCatalog(catalogRequest($admin, '/admin/bo-mon/update', ['_token' => $token, 'id' => (string) $a] + $sportA)['status'] === 303, 'unchanged update succeeds');
    checkCatalog(catalogRequest($admin, '/admin/bo-mon/update', ['_token' => $token, 'id' => (string) $a] + array_replace($sportA, ['trang_thai' => 'NGUNG']))['status'] === 422, 'active type prevents stopping sport');
    checkCatalog($sports->find($a)['trang_thai'] === 'HOAT_DONG' && $types->find($type1)['trang_thai'] === 'HOAT_DONG', 'no child status cascade');
    checkCatalog(catalogRequest($admin, '/admin/loai-san/update', ['_token' => $token, 'id' => (string) $type1] + array_replace($typeA, ['ma_bo_mon' => (string) $c]))['status'] === 422, 'cannot move to inactive sport');
    $moved = array_replace($typeA, ['ma_bo_mon' => (string) $b, 'ten_loai_san' => $tag . ' Moved']);
    checkCatalog(catalogRequest($admin, '/admin/loai-san/update', ['_token' => $token, 'id' => (string) $type1] + $moved)['status'] === 303, 'unused type can change active parent');
    checkCatalog(catalogRequest($admin, '/admin/loai-san/update', ['_token' => $token, 'id' => (string) $type1] + $typeA)['status'] === 303, 'move unused type back');

    $company = fixtureInsert('cong_ty_nhuong_quyen', ['ten_cong_ty' => $tag, 'dia_chi' => 'Fixture local']);
    $facility = fixtureInsert('co_so', ['ma_cong_ty' => $company, 'ten_co_so' => $tag, 'dia_chi' => 'Fixture local', 'gio_mo_cua' => '06:00:00', 'gio_dong_cua' => '22:00:00']);
    $court = fixtureInsert('san', ['ma_co_so' => $facility, 'ma_loai_san' => $type1, 'ten_san' => $tag, 'trang_thai' => 'HOAT_DONG']);
    $price = fixtureInsert('bang_gia', ['ma_co_so' => $facility, 'ma_loai_san' => $type1, 'ten_bang_gia' => $tag, 'loai_ngay' => 'NGAY_THUONG', 'gio_bat_dau' => '06:00:00', 'gio_ket_thuc' => '22:00:00', 'don_gia' => '100000.00', 'ngay_bat_dau' => '2026-01-01', 'trang_thai' => 'NGUNG']);
    $stopType = ['_token' => $token, 'id' => (string) $type1] + array_replace($typeA, ['trang_thai' => 'NGUNG']);
    foreach (['HOAT_DONG', 'BAO_TRI'] as $courtState) {
        fixtureUpdate('san', $court, ['trang_thai' => $courtState]);
        checkCatalog(catalogRequest($admin, '/admin/loai-san/update', $stopType)['status'] === 422, 'active/maintenance court blocks stop');
    }
    fixtureUpdate('san', $court, ['trang_thai' => 'TAM_NGUNG']);
    fixtureUpdate('bang_gia', $price, ['trang_thai' => 'DANG_AP_DUNG']);
    checkCatalog(catalogRequest($admin, '/admin/loai-san/update', $stopType)['status'] === 422, 'active price alone blocks stop');
    checkCatalog(catalogRequest($admin, '/admin/loai-san/update', ['_token' => $token, 'id' => (string) $type1] + $moved)['status'] === 422, 'references block changing parent');
    fixtureUpdate('bang_gia', $price, ['trang_thai' => 'NGUNG']);
    checkCatalog(catalogRequest($admin, '/admin/loai-san/update', $stopType)['status'] === 303, 'stop type after children are inactive');
    $stopSport = ['_token' => $token, 'id' => (string) $a] + array_replace($sportA, ['trang_thai' => 'NGUNG']);
    checkCatalog(catalogRequest($admin, '/admin/bo-mon/update', $stopSport)['status'] === 303, 'stop sport after dependencies are inactive');
    $editForm = catalogRequest($admin, '/admin/loai-san/edit', null, ['id' => (string) $type1]);
    checkCatalog(strpos($editForm['body'], $sportA['ten_bo_mon'] . ' (Đã ngừng)') !== false, 'edit retains inactive current parent');
    checkCatalog(catalogRequest($admin, '/admin/loai-san/update', ['_token' => $token, 'id' => (string) $type1] + $typeA)['status'] === 422, 'inactive parent blocks activating existing type');
    checkCatalog(catalogRequest($admin, '/admin/loai-san/update', $stopType)['status'] === 303, 'inactive type can retain its inactive parent');
    checkCatalog(catalogRequest($admin, '/admin/loai-san/store', ['_token' => $token] + array_replace($typeA, ['ten_loai_san' => $tag . ' Invalid', 'trang_thai' => 'NGUNG']))['status'] === 422, 'even inactive new type requires active parent');
    // Simulate old inconsistent fixture data; ensure indirect dependencies are checked too.
    fixtureUpdate('san', $court, ['trang_thai' => 'HOAT_DONG']);
    checkCatalog(catalogRequest($admin, '/admin/bo-mon/update', $stopSport)['status'] === 422, 'indirect active court blocks stopping sport');
    fixtureUpdate('san', $court, ['trang_thai' => 'TAM_NGUNG']);
    fixtureUpdate('bang_gia', $price, ['trang_thai' => 'DANG_AP_DUNG']);
    checkCatalog(catalogRequest($admin, '/admin/bo-mon/update', $stopSport)['status'] === 422, 'indirect active price blocks stopping sport');
    fixtureUpdate('bang_gia', $price, ['trang_thai' => 'NGUNG']);
    foreach (['bo-mon' => $a, 'loai-san' => $type1] as $module => $recordId) {
        checkCatalog(catalogRequest($admin, '/admin/' . $module . '/delete', null, ['id' => (string) $recordId])['status'] === 200, 'GET only confirms deletion');
        checkCatalog(catalogRequest($admin, '/admin/' . $module . '/delete', ['_token' => $token, 'id' => (string) $recordId])['status'] === 409, 'inactive references still prevent delete');
    }
    fixtureDelete('san', $court);
    checkCatalog(catalogRequest($admin, '/admin/loai-san/delete', ['_token' => $token, 'id' => (string) $type1])['status'] === 409, 'price reference alone prevents delete');
    checkCatalog(catalogRequest($admin, '/admin/loai-san/update', ['_token' => $token, 'id' => (string) $type1] + $moved)['status'] === 422, 'inactive price reference alone prevents changing parent');

    // A separate fixture account exercises all denied roles, never the real Admin.
    $fixtureUser = 't1_' . bin2hex(random_bytes(4));
    $fixturePassword = 'Aa1!' . bin2hex(random_bytes(6));
    $account = (new App\Services\AccountService($pdo))->create($fixtureUser, $fixturePassword, 'KHACH_HANG');
    $fixtures['tai_khoan'][] = $account;
    fixtureInsert('khach_hang', ['ma_tai_khoan' => $account, 'ho_ten' => $tag, 'so_dien_thoai' => '0901234567']);
    fixtureInsert('nhan_vien', ['ma_tai_khoan' => $account, 'ma_cong_ty' => $company, 'ma_co_so' => $facility, 'ho_ten' => $tag]);
    $otherToken = catalogLogin($other, $fixtureUser, $fixturePassword);
    foreach (['KHACH_HANG', 'NHAN_VIEN', 'QUAN_LY_CO_SO', 'QUAN_LY_CONG_TY'] as $roleCode) {
        $role = (new App\Models\VaiTro($pdo))->idByCode($roleCode);
        fixtureUpdate('tai_khoan', $account, ['ma_vai_tro' => $role]);
        foreach (['bo-mon', 'loai-san'] as $module) {
            foreach (['', '/create', '/show', '/edit', '/delete'] as $suffix) {
                checkCatalog(catalogRequest($other, '/admin/' . $module . $suffix, null, ['id' => (string) ($module === 'bo-mon' ? $a : $type1)])['status'] === 403, 'deny GET for ' . $roleCode);
            }
            foreach (['/store', '/update', '/delete'] as $suffix) {
                checkCatalog(catalogRequest($other, '/admin/' . $module . $suffix, ['_token' => $otherToken, 'id' => (string) ($module === 'bo-mon' ? $a : $type1)])['status'] === 403, 'deny POST for ' . $roleCode);
            }
        }
    }
    checkCatalog($sports->find($a) !== null && $types->find($type1) !== null, 'denied requests preserve records');
    fixtureDelete('bang_gia', $price);
    foreach ([$type1, $type2] as $id) {
        checkCatalog(catalogRequest($admin, '/admin/loai-san/delete', ['_token' => $token, 'id' => (string) $id])['status'] === 303, 'delete unreferenced type');
        checkCatalog($types->find($id) === null, 'type actually deleted');
    }
    foreach ([$a, $b, $c] as $id) {
        checkCatalog(catalogRequest($admin, '/admin/bo-mon/delete', ['_token' => $token, 'id' => (string) $id])['status'] === 303, 'delete unreferenced sport');
        checkCatalog($sports->find($id) === null, 'sport actually deleted');
    }
    checkCatalog(catalogRequest($admin, '/admin/loai-san/show', null, ['id' => (string) $type1])['status'] === 404, 'deleted record returns 404');
    echo "PASS: $checks HTTP/catalog checks.\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Delete only IDs created by this run, in foreign-key order; never reset tables.
    $pdo->beginTransaction();
    try {
        foreach (['bang_gia', 'san', 'loai_san', 'bo_mon', 'nhan_vien', 'khach_hang', 'tai_khoan', 'co_so', 'cong_ty_nhuong_quyen'] as $table) {
            foreach (array_unique($fixtures[$table]) as $fixtureId) {
                fixtureDelete($table, $fixtureId);
            }
        }
        $pdo->commit();
    } catch (Throwable $cleanupError) {
        $pdo->rollBack();
        throw new RuntimeException('Fixture cleanup failed. Inspect only this run tag: ' . $tag, 0, $cleanupError);
    }
    foreach ([$guest, $admin, $other, $racer] as $client) {
        curl_close($client);
    }
    checkCatalog(catalogSnapshot() === $baseline, 'all original database rows/counts unchanged');
    echo "PASS: Fixtures removed; every original table's row count and SHA-256 digest match the baseline.\n";
}
