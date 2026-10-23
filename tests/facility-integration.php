<?php

// Opt-in HTTP + SQL tests. Provision a NEW test database from the existing schema,
// seed vai_tro, then start PHP with DB_NAME=<test DB>, APP_BASE_PATH=/ and
// -d variables_order=EGPCS -S 127.0.0.1:<test port> -t public.
// Run with STRYKE_TEST_DB and STRYKE_TEST_URL in the process environment.
// Fixtures are retained ONLY in that test database for manual/browser review.
if (PHP_SAPI !== 'cli' || !in_array('--run', $argv, true)) {
    exit("Usage: php tests/facility-integration.php --run\nRequires a separate stryke_pr2_test_<random> database and loopback test server.\n");
}
require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
$config = require dirname(__DIR__) . '/config/database.php';
$database = getenv('STRYKE_TEST_DB') ?: '';
$base = rtrim(getenv('STRYKE_TEST_URL') ?: '', '/');
if (!preg_match('/\Astryke_pr2_test_[a-f0-9]{10}\z/', $database) || $database === $config['dbname']
    || !in_array($config['host'], ['127.0.0.1', 'localhost', '::1'], true)
    || parse_url($base, PHP_URL_HOST) !== '127.0.0.1' || parse_url($base, PHP_URL_SCHEME) !== 'http'
    || (int) parse_url($base, PHP_URL_PORT) < 1024 || parse_url($base, PHP_URL_PATH)) {
    throw new RuntimeException('Refusing to run without an isolated local test DB/server.');
}
$pdo = new PDO("mysql:host={$config['host']};port={$config['port']};dbname=$database;charset=utf8mb4",
    $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) {
    throw new RuntimeException('Wrong test database.');
}
$pdo->exec("SET time_zone = '+07:00'");
date_default_timezone_set('Asia/Ho_Chi_Minh');
$checks = 0;
$tag = 'P2' . bin2hex(random_bytes(4));
function checkFacility(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    $checks++;
}
function insertFacilityFixture(string $table, array $data): int
{
    global $pdo;
    // Identifiers and data are defined in this CLI test, never HTTP input.
    $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(',', array_keys($data)) . ') VALUES (:'
        . implode(',:', array_keys($data)) . ')')->execute($data);
    return (int) $pdo->lastInsertId();
}
function facilityClient(): CurlHandle
{
    $client = curl_init();
    curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEFILE => '', CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15]);
    return $client;
}
function facilityRequest(CurlHandle $client, string $route, ?array $fields = null, array $query = []): array
{
    global $base;
    curl_setopt($client, CURLOPT_URL, $base . '/index.php?' . http_build_query(['route' => $route] + $query));
    curl_setopt($client, CURLOPT_POST, $fields !== null);
    if ($fields !== null) {
        curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $raw = curl_exec($client);
    if ($raw === false) {
        throw new RuntimeException('Test HTTP server unavailable.');
    }
    $length = curl_getinfo($client, CURLINFO_HEADER_SIZE);
    return ['status' => curl_getinfo($client, CURLINFO_RESPONSE_CODE),
        'body' => substr($raw, $length), 'headers' => substr($raw, 0, $length)];
}
function facilityToken(array $response): string
{
    if (!preg_match('/name="_token" value="([a-f0-9]{64})"/', $response['body'], $match)) {
        throw new RuntimeException('Missing test CSRF token.');
    }
    return $match[1];
}
function facilityLogin(CurlHandle $client, string $username, string $password, string $home): string
{
    $token = facilityToken(facilityRequest($client, '/login'));
    $response = facilityRequest($client, '/login', ['_token' => $token, 'username' => $username, 'password' => $password]);
    checkFacility($response['status'] === 303 && str_contains($response['headers'], 'route=' . rawurlencode($home)), 'login destination ' . $home);
    return facilityToken(facilityRequest($client, '/account'));
}
function expectFacilityFailure(callable $operation, string $field = ''): void
{
    try {
        $operation();
    } catch (App\Services\ValidationException $e) {
        checkFacility(isset($e->errors[$field]), 'validation blocks ' . $field);
        return;
    } catch (Core\HttpException $e) {
        checkFacility($e->status === 409, 'referenced deletion blocked');
        return;
    }
    throw new RuntimeException('Expected operation to be blocked: ' . $field);
}

$companies = [];
foreach (['HOAT_DONG', 'HOAT_DONG', 'NGUNG_HOAT_DONG'] as $state) {
    $companies[] = insertFacilityFixture('cong_ty_nhuong_quyen', ['ten_cong_ty' => $tag . ' Company ' . count($companies),
        'dia_chi' => 'Địa chỉ test', 'trang_thai' => $state]);
}
$facilities = new App\Services\CoSoService($pdo);
$courts = new App\Services\SanService($pdo);
$facilityModel = new App\Models\CoSo($pdo);
$courtModel = new App\Models\San($pdo);
$facility = ['ma_cong_ty' => (string) $companies[0], 'ten_co_so' => $tag . ' Cơ sở', 'dia_chi' => 'Địa chỉ',
    'gio_mo_cua' => '06:00', 'gio_dong_cua' => '23:00', 'mo_ta' => '0', 'trang_thai' => 'HOAT_DONG'];
$f = $facilities->save($facility);
$f2 = $facilities->save($facility);
checkFacility($f !== $f2, 'facility duplicate names are allowed by schema');
checkFacility($facilityModel->find($f)['mo_ta'] === '0', 'description zero preserved');
expectFacilityFailure(fn() => $facilities->save(array_replace($facility, ['ma_cong_ty' => (string) $companies[2]])), 'ma_cong_ty');
expectFacilityFailure(fn() => $facilities->save(array_replace($facility, ['ma_cong_ty' => '2147483647'])), 'ma_cong_ty');
$sport = insertFacilityFixture('bo_mon', ['ten_bo_mon' => $tag . ' Sport']);
$type = insertFacilityFixture('loai_san', ['ma_bo_mon' => $sport, 'ten_loai_san' => $tag . ' Type']);
$inactiveSport = insertFacilityFixture('bo_mon', ['ten_bo_mon' => $tag . ' Inactive', 'trang_thai' => 'NGUNG']);
$inactiveType = insertFacilityFixture('loai_san', ['ma_bo_mon' => $sport, 'ten_loai_san' => $tag . ' Inactive', 'trang_thai' => 'NGUNG']);
$orphanType = insertFacilityFixture('loai_san', ['ma_bo_mon' => $inactiveSport, 'ten_loai_san' => $tag . ' Parent inactive']);
$court = ['ma_co_so' => (string) $f, 'ma_loai_san' => (string) $type, 'ten_san' => $tag . ' Sân',
    'vi_tri' => '0', 'mo_ta' => '<script>alert(1)</script>', 'trang_thai' => 'HOAT_DONG'];
$s = $courts->save($court);
checkFacility($courtModel->find($s)['ma_bo_mon'] === $sport, 'court type joins correct sport');
expectFacilityFailure(fn() => $courts->save($court), 'ten_san');
expectFacilityFailure(fn() => $courts->save(array_replace($court, ['ten_san' => mb_strtolower($court['ten_san'])])), 'ten_san');
$sameNameOtherFacility = $courts->save(array_replace($court, ['ma_co_so' => (string) $f2]));
foreach ([$inactiveType, $orphanType, 2147483647] as $badType) {
    expectFacilityFailure(fn() => $courts->save(array_replace($court, ['ma_loai_san' => (string) $badType])), 'ma_loai_san');
}
expectFacilityFailure(fn() => $facilities->save(array_replace($facility, ['trang_thai' => 'TAM_NGUNG']), $f), 'trang_thai');
expectFacilityFailure(fn() => $facilities->save(array_replace($facility, ['ma_cong_ty' => (string) $companies[1]]), $f), 'ma_cong_ty');
expectFacilityFailure(fn() => $facilities->delete($f));
$courts->save(array_replace($court, ['trang_thai' => 'TAM_NGUNG']), $s);
$facilities->save(array_replace($facility, ['trang_thai' => 'TAM_NGUNG']), $f);
expectFacilityFailure(fn() => $courts->save($court, $s), 'ma_co_so');
expectFacilityFailure(fn() => $courts->save(array_replace($court, ['ten_san' => $tag . ' New stopped', 'trang_thai' => 'TAM_NGUNG'])), 'ma_co_so');
$courts->save(array_replace($court, ['trang_thai' => 'TAM_NGUNG', 'vi_tri' => 'Cập nhật khi cha ngừng']), $s);
checkFacility($courtModel->find($s)['trang_thai'] === 'TAM_NGUNG', 'inactive current parent can remain stopped');
$facilities->save($facility, $f);
$courts->save($court, $s);

// Existing inactive parents remain visible while editing, but cannot be used to activate a child.
foreach (['loai_san' => [$type, 'ma_loai_san', 'NGUNG'], 'bo_mon' => [$sport, 'ma_bo_mon', 'NGUNG'],
    'cong_ty_nhuong_quyen' => [$companies[0], 'ma_cong_ty', 'NGUNG_HOAT_DONG']] as $table => [$parentId, $key, $inactive]) {
    $courts->save(array_replace($court, ['trang_thai' => 'TAM_NGUNG']), $s);
    $pdo->prepare("UPDATE $table SET trang_thai = ? WHERE $key = ?")->execute([$inactive, $parentId]);
    expectFacilityFailure(fn() => $courts->save($court, $s), $table === 'cong_ty_nhuong_quyen' ? 'ma_co_so' : 'ma_loai_san');
    $courts->save(array_replace($court, ['trang_thai' => 'TAM_NGUNG', 'mo_ta' => 'Sửa mô tả']), $s);
    $options = $table === 'cong_ty_nhuong_quyen' ? $facilityModel->options($f) : $courtModel->types($type);
    $optionKey = $table === 'cong_ty_nhuong_quyen' ? 'ma_co_so' : 'ma_loai_san';
    checkFacility(in_array($table === 'cong_ty_nhuong_quyen' ? $f : $type, array_column($options, $optionKey), true), 'retain current inactive parent option');
    $pdo->prepare("UPDATE $table SET trang_thai = 'HOAT_DONG' WHERE $key = ?")->execute([$parentId]);
    $courts->save($court, $s);
}
$unused = $facilities->save(array_replace($facility, ['ten_co_so' => $tag . ' Move']));
$facilities->save(array_replace($facility, ['ma_cong_ty' => (string) $companies[1]]), $unused);
checkFacility($facilityModel->find($unused)['ma_cong_ty'] === $companies[1], 'unreferenced facility may move to active company');
$facilities->delete($unused);
checkFacility($facilityModel->find($unused) === null, 'unreferenced facility deletion succeeds');
$alternateType = insertFacilityFixture('loai_san', ['ma_bo_mon' => $sport, 'ten_loai_san' => $tag . ' Alternate']);
$courts->save(array_replace($court, ['ma_co_so' => (string) $f2, 'ma_loai_san' => (string) $alternateType]), $sameNameOtherFacility);
checkFacility($courtModel->find($sameNameOtherFacility)['ma_loai_san'] === $alternateType, 'unreferenced court may change to active type');
$courts->save(array_replace($court, ['trang_thai' => 'BAO_TRI']), $s);
expectFacilityFailure(fn() => $facilities->save(array_replace($facility, ['trang_thai' => 'TAM_NGUNG']), $f), 'trang_thai');
$courts->save($court, $s);

$password = 'Aa1!' . bin2hex(random_bytes(5));
$accounts = new App\Services\AccountService($pdo);
$logins = [];
$customerId = 0;
foreach (['QUAN_TRI_TAP_DOAN' => '/admin', 'KHACH_HANG' => '/', 'QUAN_LY_CONG_TY' => '/company',
    'QUAN_LY_CO_SO' => '/facility', 'NHAN_VIEN' => '/staff'] as $role => $home) {
    $username = strtolower($tag) . count($logins);
    $id = $accounts->create($username, $password, $role);
    if ($role === 'KHACH_HANG') {
        $customerId = insertFacilityFixture('khach_hang', ['ma_tai_khoan' => $id, 'ho_ten' => 'Khách test', 'so_dien_thoai' => '0901234567']);
    } elseif ($role !== 'QUAN_TRI_TAP_DOAN') {
        insertFacilityFixture('nhan_vien', ['ma_tai_khoan' => $id, 'ma_cong_ty' => $companies[0],
            'ma_co_so' => $f2, 'ho_ten' => 'Nhân viên test']);
    }
    $logins[$role] = ['username' => $username, 'home' => $home];
}
$admin = facilityClient();
$token = facilityLogin($admin, $logins['QUAN_TRI_TAP_DOAN']['username'], $password, '/admin');
// A randomly generated Admin existing only in the test DB verifies the HTTP target before any POST CRUD.
checkFacility(facilityRequest($admin, '/admin')['status'] === 200, 'isolated server uses test database');
$guest = facilityClient();
$guestToken = facilityToken(facilityRequest($guest, '/login'));
foreach (['co-so', 'san'] as $module) {
    foreach (['', '/create', '/show', '/edit', '/delete'] as $suffix) {
        checkFacility(facilityRequest($guest, '/admin/' . $module . $suffix, null, ['id' => $s])['status'] === 303, 'guest route protected');
    }
    foreach (['store', 'update', 'delete'] as $action) {
        checkFacility(facilityRequest($guest, '/admin/' . $module . '/' . $action, ['_token' => $guestToken, 'id' => $s])['status'] === 303, 'guest write protected');
        checkFacility(facilityRequest($admin, '/admin/' . $module . '/' . $action, ['id' => $s])['status'] === 403, 'CSRF required');
    }
}
foreach ($logins as $role => $login) {
    $client = facilityClient();
    $roleToken = facilityLogin($client, $login['username'], $password, $login['home']);
    foreach (['/account', '/account/settings', $login['home']] as $path) {
        $response = facilityRequest($client, $path);
        checkFacility($response['status'] === 200, $role . ' account/area accessible');
        checkFacility(str_contains($response['body'], 'class="admin-sidebar"') === ($role === 'QUAN_TRI_TAP_DOAN'), $role . ' correct layout');
    }
    $invalidPassword = facilityRequest($client, '/account/password', ['_token' => $roleToken]);
    checkFacility($invalidPassword['status'] === 422 && str_contains($invalidPassword['body'], 'class="admin-sidebar"') === ($role === 'QUAN_TRI_TAP_DOAN'), 'password validation keeps role layout');
    if ($role === 'QUAN_TRI_TAP_DOAN') {
        checkFacility(facilityRequest($client, '/account', ['_token' => $roleToken, 'name' => 'Not a customer'])['status'] === 403, 'Admin cannot edit customer profile');
        continue;
    }
    foreach (['co-so' => $f, 'san' => $s] as $module => $id) {
        foreach (['', '/create', '/show', '/edit', '/delete'] as $suffix) {
            checkFacility(facilityRequest($client, '/admin/' . $module . $suffix, null, ['id' => $id])['status'] === 403, $role . ' CRUD GET forbidden');
        }
        foreach (['store', 'update', 'delete'] as $action) {
            checkFacility(facilityRequest($client, '/admin/' . $module . '/' . $action, ['_token' => $roleToken, 'id' => $id])['status'] === 403, $role . ' CRUD POST forbidden');
        }
    }
}
foreach (['co-so' => $f, 'san' => $s] as $module => $id) {
    foreach (['', '/create', '/show', '/edit', '/delete'] as $suffix) {
        $response = facilityRequest($admin, '/admin/' . $module . $suffix, null, ['id' => $id]);
        checkFacility($response['status'] === 200 && str_contains($response['body'], 'class="admin-sidebar"'), 'Admin CRUD view ' . $module . $suffix);
        checkFacility(!str_contains($response['body'], '<script>alert(1)</script>'), 'stored HTML escaped');
    }
    foreach (['0', '01', '2147483647', ['1']] as $badId) {
        checkFacility(facilityRequest($admin, '/admin/' . $module . '/show', null, ['id' => $badId])['status'] === 404, 'invalid/missing ID is 404');
    }
}
$badForm = facilityRequest($admin, '/admin/san/store', ['_token' => $token] + array_replace($court, ['ten_san' => '', 'mo_ta' => str_repeat('á', 1000)]));
checkFacility($badForm['status'] === 422 && str_contains($badForm['body'], str_repeat('á', 1000)), '422 preserves TEXT and Sidebar');
$createForm = facilityRequest($admin, '/admin/san/create');
preg_match('/<select[^>]*id="ma_loai_san"[^>]*>(.*?)<\/select>/s', $createForm['body'], $typeOptions);
checkFacility(isset($typeOptions[1]) && !str_contains($typeOptions[1], 'value="' . $inactiveType . '"')
    && !str_contains($typeOptions[1], 'value="' . $orphanType . '"'), 'only active type/sport options');
preg_match('/<select[^>]*id="ma_cong_ty"[^>]*>(.*?)<\/select>/s', facilityRequest($admin, '/admin/co-so/create')['body'], $companyOptions);
checkFacility(isset($companyOptions[1]) && !str_contains($companyOptions[1], 'value="' . $companies[2] . '"'), 'only active company options');
// Full HTTP create/update/delete flow, independently verified through SQL reads.
foreach (['co-so' => [$facility, 'co_so', 'ma_co_so', 'ten_co_so'], 'san' => [$court, 'san', 'ma_san', 'ten_san']] as $module => [$data, $table, $key, $name]) {
    $data[$name] = $tag . ' HTTP ' . $module;
    checkFacility(facilityRequest($admin, '/admin/' . $module . '/store', ['_token' => $token] + $data)['status'] === 303, 'HTTP create ' . $module);
    $query = $pdo->prepare("SELECT $key FROM $table WHERE $name = ?");
    $query->execute([$data[$name]]);
    $id = (int) $query->fetchColumn();
    checkFacility($id > 0, 'HTTP create persists ' . $module);
    $data[$name] .= ' updated';
    checkFacility(facilityRequest($admin, '/admin/' . $module . '/update', ['_token' => $token, 'id' => $id] + $data)['status'] === 303, 'HTTP update ' . $module);
    checkFacility(facilityRequest($admin, '/admin/' . $module . '/delete', ['_token' => $token, 'id' => $id])['status'] === 303, 'HTTP delete ' . $module);
    $query->execute([$data[$name]]);
    checkFacility($query->fetchColumn() === false, 'HTTP deletion persists ' . $module);
}
checkFacility(facilityRequest($admin, '/admin/co-so/delete', ['_token' => $token, 'id' => $f])['status'] === 409, 'HTTP referenced facility deletion blocked');
checkFacility(facilityRequest($admin, '/admin/san/store', ['_token' => $token] + $court)['status'] === 422, 'HTTP duplicate court rejected');

// Every facility FK blocks deletion even when its record is inactive/historical.
foreach (['nhan_vien', 'bang_gia', 'dich_vu', 'dat_san'] as $reference) {
    $isolated = $facilities->save(array_replace($facility, ['ten_co_so' => $tag . ' Ref ' . $reference]));
    $data = match ($reference) {
        'nhan_vien' => ['ma_cong_ty' => $companies[0], 'ho_ten' => 'NV test', 'trang_thai' => 'DANG_LAM'],
        'bang_gia' => ['ma_loai_san' => $type, 'ten_bang_gia' => 'Giá test', 'loai_ngay' => 'NGAY_THUONG',
            'gio_bat_dau' => '08:00', 'gio_ket_thuc' => '20:00', 'don_gia' => 100000, 'ngay_bat_dau' => '2026-01-01'],
        'dich_vu' => ['ten_dich_vu' => 'DV test', 'loai_dich_vu' => 'KHAC', 'don_vi_tinh' => 'lần', 'don_gia' => 10000],
        'dat_san' => ['ma_khach_hang' => $customerId, 'trang_thai_dat_san' => 'DA_HUY'],
    };
    $refId = insertFacilityFixture($reference, ['ma_co_so' => $isolated] + $data);
    expectFacilityFailure(fn() => $facilities->delete($isolated));
    if ($reference !== 'dat_san') {
        expectFacilityFailure(fn() => $facilities->save(array_replace($facility, ['trang_thai' => 'TAM_NGUNG']), $isolated), 'trang_thai');
        if ($reference === 'bang_gia') {
            expectFacilityFailure(fn() => $facilities->save(array_replace($facility, ['gio_mo_cua' => '09:00']), $isolated), 'gio_dong_cua');
            $pricedCourt = $courts->save(array_replace($court, ['ma_co_so' => (string) $isolated]));
            $courts->delete($pricedCourt);
            checkFacility($courtModel->find($pricedCourt) === null, 'price is not a direct court FK');
        }
        $inactive = ['nhan_vien' => 'DA_NGHI', 'bang_gia' => 'NGUNG', 'dich_vu' => 'NGUNG'][$reference];
        $key = ['nhan_vien' => 'ma_nhan_vien', 'bang_gia' => 'ma_bang_gia', 'dich_vu' => 'ma_dich_vu'][$reference];
        $pdo->prepare("UPDATE $reference SET trang_thai = ? WHERE $key = ?")->execute([$inactive, $refId]);
    }
    $facilities->save(array_replace($facility, ['trang_thai' => 'TAM_NGUNG']), $isolated);
    expectFacilityFailure(fn() => $facilities->delete($isolated));
}
// Reservation semantics are documented in the existing SQL header. No guessed hold duration.
$booking = insertFacilityFixture('dat_san', ['ma_co_so' => $f, 'ma_khach_hang' => $customerId]);
$detail = insertFacilityFixture('chi_tiet_dat_san', ['ma_dat_san' => $booking, 'ma_san' => $s,
    'ten_san_snapshot' => $court['ten_san'], 'ngay_choi' => date('Y-m-d', strtotime('+1 day')),
    'gio_bat_dau' => '08:00', 'gio_ket_thuc' => '10:00', 'don_gia' => 100000, 'thanh_tien' => 200000]);
foreach ([['CHO_XAC_NHAN', null, false], ['CHO_THANH_TOAN', date('Y-m-d H:i:s', time() + 3600), true],
    ['CHO_THANH_TOAN', date('Y-m-d H:i:s'), false],
    ['DA_XAC_NHAN', null, true], ['HOAN_THANH', null, false], ['HET_HAN', null, false], ['DA_HUY', null, false]] as [$state, $expiry, $blocks]) {
    $needsHold = !in_array($state, ['CHO_XAC_NHAN', 'DA_HUY'], true);
    $pdo->prepare('UPDATE dat_san SET trang_thai_dat_san = ?, bat_dau_giu_san_luc = ?, het_han_giu_san_luc = ? WHERE ma_dat_san = ?')
        ->execute([$state, $needsHold ? date('Y-m-d H:i:s', strtotime('-2 days')) : null,
            $needsHold ? ($expiry ?? date('Y-m-d H:i:s', strtotime('-1 day'))) : null, $booking]);
    checkFacility(($courtModel->related($s)['booking_hieu_luc'] > 0) === $blocks, 'court reservation ' . $state);
    checkFacility(($facilityModel->activeBookings($f) > 0) === $blocks, 'facility reservation ' . $state);
    foreach (['BAO_TRI', 'TAM_NGUNG'] as $stopped) {
        if ($blocks) {
            expectFacilityFailure(fn() => $courts->save(array_replace($court, ['trang_thai' => $stopped]), $s), 'trang_thai');
        } else {
            $courts->save(array_replace($court, ['trang_thai' => $stopped]), $s);
            checkFacility($courtModel->find($s)['trang_thai'] === $stopped, 'non-reserving history permits court stop');
        }
    }
    $courts->save($court, $s);
    expectFacilityFailure(fn() => $courts->delete($s));
    expectFacilityFailure(fn() => $courts->save(array_replace($court, ['ma_co_so' => (string) $f2]), $s), 'ma_co_so');
    checkFacility($pdo->query('SELECT trang_thai_dat_san FROM dat_san WHERE ma_dat_san = ' . $booking)->fetchColumn() === $state, 'no cascade state changes');
}
$pdo->prepare("UPDATE dat_san SET trang_thai_dat_san = 'DA_XAC_NHAN', bat_dau_giu_san_luc = NOW() - INTERVAL 1 DAY,
    het_han_giu_san_luc = NOW() WHERE ma_dat_san = ?")->execute([$booking]);
expectFacilityFailure(fn() => $facilities->save(array_replace($facility, ['gio_mo_cua' => '09:00']), $f), 'gio_dong_cua');
$pdo->prepare('UPDATE chi_tiet_dat_san SET ngay_choi = ? WHERE ma_chi_tiet = ?')->execute([date('Y-m-d', strtotime('-1 day')), $detail]);
checkFacility($courtModel->related($s)['booking_hieu_luc'] === 0, 'past confirmed slot no longer occupies court');
$courts->save(array_replace($court, ['trang_thai' => 'TAM_NGUNG']), $s);
$facilities->save(array_replace($facility, ['trang_thai' => 'TAM_NGUNG']), $f);
checkFacility($facilityModel->find($f)['trang_thai'] === 'TAM_NGUNG', 'historical booking permits facility stop');
$facilities->save($facility, $f);
$courts->save($court, $s);
// PR 1 and Chí's Company pages continue to render through the same Admin layout.
foreach (['/admin', '/admin/companies', '/admin/companies/create', '/admin/companies/edit',
    '/admin/bo-mon', '/admin/loai-san', '/admin/bo-mon/show', '/admin/loai-san/show'] as $path) {
    $id = str_contains($path, 'companies') ? $companies[0] : (str_contains($path, 'loai-san') ? $type : $sport);
    $response = facilityRequest($admin, $path, null, ['id' => $id]);
    checkFacility($response['status'] === 200 && str_contains($response['body'], 'class="admin-sidebar"'), 'existing Admin regression ' . $path);
}
$nextPassword = 'Bb2!' . bin2hex(random_bytes(5));
$changed = facilityRequest($admin, '/account/password', ['_token' => $token, 'current_password' => $password,
    'new_password' => $nextPassword, 'new_password_confirmation' => $nextPassword]);
checkFacility($changed['status'] === 303 && str_contains($changed['headers'], rawurlencode('/account/settings')), 'test Admin password change redirects to settings');
checkFacility(facilityRequest($admin, '/admin')['status'] === 200, 'Admin returns to Dashboard without logging out');
$fresh = facilityClient();
facilityLogin($fresh, $logins['QUAN_TRI_TAP_DOAN']['username'], $nextPassword, '/admin');
echo "OK: $checks facility integration checks on $database; application DB untouched.\n";
if (getenv('STRYKE_BROWSER_FIXTURE') === '1') {
    // Pipe directly to the browser harness; never save or commit these generated credentials.
    echo json_encode(['base' => $base, 'logins' => $logins, 'password' => $password, 'adminPassword' => $nextPassword,
        'facility' => $f, 'court' => $s, 'company' => $companies[0], 'sport' => $sport, 'type' => $type], JSON_THROW_ON_ERROR) . "\n";
}
