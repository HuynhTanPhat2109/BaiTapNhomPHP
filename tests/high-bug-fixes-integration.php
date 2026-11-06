<?php
// Opt-in regression for BUG-001..004. Every write uses an owned disposable database.
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--run') exit("php tests/high-bug-fixes-integration.php --run [--only=BUG-001]\n");
require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
$root = dirname(__DIR__);
$config = require $root . '/config/database.php';
if (!in_array($config['host'], ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Local test server required');
date_default_timezone_set('Asia/Ho_Chi_Minh');
$only = null;
foreach (array_slice($argv, 2) as $argument) {
    if (!preg_match('/\A--only=(BUG-00[1-4])\z/', $argument, $match)) throw new RuntimeException('Invalid test filter');
    $only = $match[1];
}
$results = [];
$check = static function (bool $ok, string $bug, string $label) use (&$results): void {
    $results[] = ['bug' => $bug, 'test' => $label, 'pass' => $ok];
    echo ($ok ? 'PASS' : 'FAIL') . " [$bug] $label\n";
};
$enabled = static fn(string $bug): bool => $only === null || $only === $bug;
$connection = static function (?string $database = null) use ($config): PDO {
    if ($database !== null && (!preg_match('/\Astryke_highfix_test_[a-f0-9]{10}\z/', $database) || $database === $config['dbname'])) throw new RuntimeException('Unsafe database');
    $pdo = new PDO("mysql:host={$config['host']};port={$config['port']};" . ($database ? "dbname=$database;" : '') . 'charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $pdo->exec("SET time_zone='+07:00'");
    return $pdo;
};
$database = 'stryke_highfix_test_' . bin2hex(random_bytes(5));
$admin = $connection();
$admin->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$server = null;
$client = null;
$ownedReceipts = [];
$directory = $root . '/storage/temp/high-bug-fixes-' . bin2hex(random_bytes(5));
try {
    $pdo = $connection($database);
    if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) throw new RuntimeException('Wrong write connection');
    $sql = preg_replace('/CREATE DATABASE.*?;|USE quanly_chuoi_san_the_thao;/s', '', file_get_contents($root . '/database/quanly_chuoi_san_the_thao.sql'));
    if (preg_match('/\b(?:CREATE DATABASE|USE)\b/i', preg_replace('/--[^\r\n]*/', '', $sql))) throw new RuntimeException('Unsafe schema import');
    $pdo->exec($sql);
    $columns = $pdo->query('SHOW COLUMNS FROM hoa_don')->fetchAll(PDO::FETCH_UNIQUE);
    $hasBank = (bool) $pdo->query("SHOW TABLES LIKE 'tai_khoan_ngan_hang_co_so'")->fetchColumn();
    if ($enabled('BUG-002')) {
        $check(isset($columns['so_hoa_don'], $columns['ma_thanh_toan']), 'BUG-002', 'fresh schema has both invoice columns');
        $check($hasBank, 'BUG-002', 'fresh schema includes facility bank table');
    }
    // Pre-fix runs still exercise the other bugs. These upgrades run ONLY in this test DB;
    // the fresh-schema assertions above remain FAIL if an upgrade was necessary.
    if (!$hasBank) $pdo->exec(file_get_contents($root . '/database/migrations/20261101_tai_khoan_ngan_hang_co_so.sql'));
    if (!isset($columns['ma_thanh_toan'])) $pdo->exec(file_get_contents($root . '/database/migrations/20261102_hoa_don_fields.sql'));
    $insert = static function (string $table, array $data) use ($pdo): int {
        $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(',', array_keys($data)) . ') VALUES (' . implode(',', array_fill(0, count($data), '?')) . ')')->execute(array_values($data));
        return (int) $pdo->lastInsertId();
    };
    $company = $insert('cong_ty_nhuong_quyen', ['ten_cong_ty' => 'High bug test', 'dia_chi' => 'Test only']);
    $facility = $insert('co_so', ['ma_cong_ty' => $company, 'ten_co_so' => 'High bug test', 'dia_chi' => 'Test only', 'gio_mo_cua' => '05:00', 'gio_dong_cua' => '23:00']);
    $type = $insert('loai_san', ['ma_bo_mon' => 1, 'ten_loai_san' => 'Test type']);
    $court = $insert('san', ['ma_co_so' => $facility, 'ma_loai_san' => $type, 'ten_san' => 'Test court']);
    $account = (new App\Services\AccountService($pdo))->create('highfixuser', 'Testpass1!', 'KHACH_HANG');
    $customer = $insert('khach_hang', ['ma_tai_khoan' => $account, 'ho_ten' => 'Test customer', 'so_dien_thoai' => '0900000000']);
    $date = date('Y-m-d', strtotime('+2 days'));
    $makeBooking = static fn(string $status = 'DA_XAC_NHAN', int $seconds = 360): int => $insert('dat_san', ['ma_co_so' => $facility, 'ma_khach_hang' => $customer, 'trang_thai_dat_san' => $status, 'bat_dau_giu_san_luc' => date('Y-m-d H:i:s', time() + $seconds - 360), 'het_han_giu_san_luc' => date('Y-m-d H:i:s', time() + $seconds), 'tong_tien_san' => 100000, 'tong_tien_dich_vu' => 0, 'tong_tien_tam_tinh' => 100000]);
    $mustReject = static function (callable $operation, string $field): bool {
        try { $operation(); } catch (App\Services\ValidationException $e) { return isset($e->errors[$field]); }
        return false;
    };
    if ($enabled('BUG-002')) {
        $columns = $pdo->query('SHOW COLUMNS FROM hoa_don')->fetchAll(PDO::FETCH_UNIQUE);
        $check($columns['so_hoa_don']['Type'] === 'varchar(50)' && $columns['so_hoa_don']['Null'] === 'YES' && $columns['ma_thanh_toan']['Null'] === 'YES', 'BUG-002', 'invoice column types and nullability match upgrade contract');
        $indexes = $pdo->query('SHOW INDEX FROM hoa_don')->fetchAll();
        $unique = array_column(array_filter($indexes, static fn($i) => (int) $i['Non_unique'] === 0), 'Column_name');
        $check(!array_diff(['ma_dat_san', 'so_hoa_don', 'ma_thanh_toan'], $unique), 'BUG-002', 'invoice booking/payment/number remain unique');
        $fk = $pdo->prepare("SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND CONSTRAINT_NAME='fk_hoadon_thanhtoan'");
        $fk->execute([$database]);
        $check($fk->fetch() === ['COLUMN_NAME' => 'ma_thanh_toan', 'REFERENCED_TABLE_NAME' => 'thanh_toan', 'REFERENCED_COLUMN_NAME' => 'ma_thanh_toan'], 'BUG-002', 'invoice FK references payment after table creation');
        $bankIndexes = array_column($pdo->query('SHOW INDEX FROM tai_khoan_ngan_hang_co_so')->fetchAll(), 'Key_name');
        $check(in_array('idx_tknh_coso', $bankIndexes, true) && in_array('uq_coso_bin_stk', $bankIndexes, true), 'BUG-002', 'bank indexes preserved');
        $check((new App\Models\TaiKhoanNganHang($pdo))->getDefaultByFacility($facility) === null && !(new App\Models\HoaDon($pdo))->existsByPaymentId(999), 'BUG-002', 'bank and invoice models query compatible test schema');
        $bank = $insert('tai_khoan_ngan_hang_co_so', ['ma_co_so' => $facility, 'ten_ngan_hang' => 'Test bank', 'ma_bin' => '970422', 'so_tai_khoan' => '0000000000', 'ten_chu_tai_khoan' => 'TEST ONLY']);
        $check((int) (new App\Models\TaiKhoanNganHang($pdo))->getDefaultByFacility($facility)['ma_tk_ngan_hang'] === $bank, 'BUG-002', 'existing bank model returns correct facility account');
        $fk->execute([$database]);
        $bankFk = $pdo->prepare("SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=? AND CONSTRAINT_NAME='fk_tknh_coso'");
        $bankFk->execute([$database]);
        $check($bankFk->fetchColumn() === 'RESTRICT', 'BUG-002', 'bank facility FK keeps delete restriction');
    }
    if ($enabled('BUG-001')) {
        $id = $makeBooking('CHO_XAC_NHAN', -86400);
        $insert('chi_tiet_dat_san', ['ma_dat_san' => $id, 'ma_san' => $court, 'ten_san_snapshot' => 'Test court', 'ngay_choi' => $date, 'gio_bat_dau' => '08:00', 'gio_ket_thuc' => '09:00', 'don_gia' => 100000, 'thanh_tien' => 100000]);
        $facilities = new App\Models\CoSo($pdo);
        $courts = new App\Models\San($pdo);
        foreach ([['CHO_XAC_NHAN', -86400, true], ['CHO_THANH_TOAN', 360, true], ['CHO_THANH_TOAN', -60, false], ['DA_XAC_NHAN', -86400, true], ['HOAN_THANH', -86400, false], ['DA_HUY', -60, false], ['HET_HAN', -60, false]] as [$state, $seconds, $expected]) {
            $pdo->prepare('UPDATE dat_san SET trang_thai_dat_san=?,bat_dau_giu_san_luc=?,het_han_giu_san_luc=? WHERE ma_dat_san=?')->execute([$state, date('Y-m-d H:i:s', time() + $seconds - 360), date('Y-m-d H:i:s', time() + $seconds), $id]);
            $before = $pdo->query("SELECT * FROM dat_san WHERE ma_dat_san=$id")->fetch();
            $check(($facilities->activeBookings($facility) > 0) === $expected && ($courts->related($court)['booking_hieu_luc'] > 0) === $expected, 'BUG-001', "$state/$seconds future resource guards");
            $check($pdo->query("SELECT * FROM dat_san WHERE ma_dat_san=$id")->fetch() === $before, 'BUG-001', "$state read guards never mutate booking");
        }
        $pdo->exec("UPDATE dat_san SET trang_thai_dat_san='CHO_XAC_NHAN' WHERE ma_dat_san=$id");
        $facilityForm = ['ma_cong_ty' => (string) $company, 'ten_co_so' => 'High bug test', 'dia_chi' => 'Test only', 'gio_mo_cua' => '10:00', 'gio_dong_cua' => '23:00', 'trang_thai' => 'HOAT_DONG'];
        $courtForm = ['ma_co_so' => (string) $facility, 'ma_loai_san' => (string) $type, 'ten_san' => 'Test court', 'trang_thai' => 'TAM_NGUNG'];
        $check($mustReject(fn() => (new App\Services\CoSoService($pdo))->save($facilityForm, $facility), 'gio_dong_cua'), 'BUG-001', 'reject changed facility hours conflicting with aged review');
        $pdo->exec("UPDATE co_so SET gio_mo_cua='05:00' WHERE ma_co_so=$facility");
        $check($mustReject(fn() => (new App\Services\SanService($pdo))->save($courtForm, $court), 'trang_thai'), 'BUG-001', 'reject stopping court with aged review');
        $pdo->exec("UPDATE san SET trang_thai='HOAT_DONG' WHERE ma_san=$court");
        (new App\Services\CoSoService($pdo))->save(array_replace($facilityForm, ['gio_mo_cua' => '06:00']), $facility);
        $check($facilities->find($facility)['gio_mo_cua'] === '06:00:00', 'BUG-001', 'nonconflicting facility hours still editable');
        $pdo->exec("UPDATE co_so SET gio_mo_cua='05:00' WHERE ma_co_so=$facility");
        foreach (['HET_HAN', 'DA_HUY', 'CHO_THANH_TOAN'] as $state) {
            $pdo->prepare('UPDATE dat_san SET trang_thai_dat_san=? WHERE ma_dat_san=?')->execute([$state, $id]);
            (new App\Services\SanService($pdo))->save($courtForm, $court);
            $check($courts->find($court)['trang_thai'] === 'TAM_NGUNG', 'BUG-001', "$state expired/cancelled allows court stop");
            $pdo->exec("UPDATE san SET trang_thai='HOAT_DONG' WHERE ma_san=$court");
        }
        $pdo->exec("UPDATE dat_san SET trang_thai_dat_san='DA_HUY' WHERE ma_dat_san=$id");
    }
    if ($enabled('BUG-003')) {
        $a = $makeBooking(); $b = $makeBooking();
        $payment = $insert('thanh_toan', ['ma_dat_san' => $b, 'ma_tham_chieu' => 'HIGHFIX-PAID', 'so_tien' => 100000, 'so_tien_thuc_nhan' => 100000, 'trang_thai' => 'THANH_CONG', 'ngay_thanh_toan' => date('Y-m-d H:i:s'), 'ma_giao_dich' => 'HIGHFIX-TEST-ONLY']);
        $invoice = new App\Services\InvoiceService($pdo);
        $pdo->beginTransaction();
        $rejected = $mustReject(fn() => $invoice->createInvoice($a, $payment), 'payment');
        $check($rejected && (int) $pdo->query('SELECT COUNT(*) FROM hoa_don')->fetchColumn() === 0, 'BUG-003', 'cross-booking payment with equal amount rejected without invoice');
        $pdo->rollBack();
        $pdo->beginTransaction();
        $check($mustReject(fn() => $invoice->createInvoice($a, 2147483647), 'payment'), 'BUG-003', 'missing payment rejected safely');
        $pdo->rollBack();
        $pdo->beginTransaction();
        $created = $invoice->createInvoice($b, $payment);
        $pdo->commit();
        $check($created['booking_id'] === $b && $created['payment_id'] === $payment && (int) $pdo->query('SELECT COUNT(*) FROM hoa_don')->fetchColumn() === 1, 'BUG-003', 'valid pair creates one invoice');
        $pdo->beginTransaction();
        $check($mustReject(fn() => $invoice->createInvoice($b, $payment), 'invoice'), 'BUG-003', 'duplicate request rejected without second invoice');
        $pdo->rollBack();
        $rollbackBooking = $makeBooking();
        $rollbackPayment = $insert('thanh_toan', ['ma_dat_san' => $rollbackBooking, 'ma_tham_chieu' => 'HIGHFIX-ROLLBACK', 'so_tien' => 100000, 'so_tien_thuc_nhan' => 100000, 'trang_thai' => 'THANH_CONG', 'ngay_thanh_toan' => date('Y-m-d H:i:s'), 'ma_giao_dich' => 'HIGHFIX-ROLLBACK-ONLY']);
        $pdo->beginTransaction();
        try { $invoice->createInvoice($rollbackBooking, $rollbackPayment); throw new RuntimeException('Simulated caller failure'); }
        catch (RuntimeException $e) { $pdo->rollBack(); }
        $check(!(new App\Models\HoaDon($pdo))->existsByBookingId($rollbackBooking), 'BUG-003', 'caller failure rolls back created invoice');
    }
    if ($enabled('BUG-001') || $enabled('BUG-004')) {
        $day = (int) date('N', strtotime($date)) >= 6 ? 'CUOI_TUAN' : 'NGAY_THUONG';
        $insert('bang_gia', ['ma_co_so' => $facility, 'ma_loai_san' => $type, 'ten_bang_gia' => 'Test rate', 'loai_ngay' => $day, 'gio_bat_dau' => '05:00', 'gio_ket_thuc' => '23:00', 'don_gia' => 100000, 'ngay_bat_dau' => $date]);
        $bookingService = new App\Services\BookingService($pdo);
        $selection = ['facility' => $facility, 'date' => $date, 'slots' => [['court' => $court, 'start' => '17:00', 'end' => '17:30']]];
        $extras = ['services' => [], 'note' => ''];
        $service = $insert('dich_vu', ['ma_co_so' => $facility, 'ten_dich_vu' => 'Test water', 'loai_dich_vu' => 'DO_UONG', 'don_vi_tinh' => 'chai', 'don_gia' => 10000, 'so_luong_ton' => 3]);
        if ($enabled('BUG-004')) $extras['services'] = [$service => 2];
        $quote = $bookingService->quote($selection, $extras);
        $hold = $bookingService->confirm($customer, bin2hex(random_bytes(32)), $selection, $extras, $quote['fingerprint']);
        if ($enabled('BUG-001')) $check($quote['court_total'] === '50000.00' && $bookingService->inspect($hold, $customer)['trang_thai_dat_san'] === 'CHO_THANH_TOAN', 'BUG-001', 'new booking and server pricing still work');
    }
    if ($enabled('BUG-004')) {
        (new App\Services\PaymentService($pdo))->createPaymentRequest($hold, $quote['total']);
        mkdir($directory . '/sessions', 0700, true);
        $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $address = stream_socket_get_name($listener, false); fclose($listener);
        $env = getenv(); foreach ($_ENV as $key => $value) $env[$key] = $value;
        $env['DB_NAME'] = $database; $env['APP_BASE_PATH'] = '/';
        $server = proc_open([PHP_BINARY, '-d', 'variables_order=EGPCS', '-d', 'session.save_path=' . $directory . '/sessions', '-d', 'upload_max_filesize=8M', '-d', 'post_max_size=10M', '-S', $address, '-t', 'public'], [0 => ['pipe', 'r'], 1 => ['file', $directory . '/server.log', 'w'], 2 => ['file', $directory . '/server.log', 'a']], $pipes, $root, $env, ['bypass_shell' => true]);
        if (!is_resource($server)) throw new RuntimeException('Test HTTP server unavailable');
        fclose($pipes[0]);
        for ($i = 0; $i < 60; $i++) { if ($socket = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1)) { fclose($socket); break; } usleep(100000); }
        $client = curl_init();
        curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEFILE => '', CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15]);
        $http = static function (string $route, ?array $data = null) use ($client, $address): array {
            curl_setopt_array($client, [CURLOPT_URL => 'http://' . $address . '/index.php?route=' . rawurlencode($route), CURLOPT_POST => $data !== null]);
            if ($data !== null) curl_setopt($client, CURLOPT_POSTFIELDS, $data);
            $raw = curl_exec($client); if ($raw === false) throw new RuntimeException(curl_error($client));
            $length = curl_getinfo($client, CURLINFO_HEADER_SIZE);
            return ['status' => curl_getinfo($client, CURLINFO_RESPONSE_CODE), 'head' => substr($raw, 0, $length), 'body' => substr($raw, $length)];
        };
        $token = static function (array $response): string { if (!preg_match('/name="_token" value="([a-f0-9]{64})"/', $response['body'], $m)) throw new RuntimeException('Missing test CSRF'); return $m[1]; };
        $csrf = $token($http('/login'));
        $check($http('/login', ['_token' => $csrf, 'username' => 'highfixuser', 'password' => 'Testpass1!'])['status'] === 303, 'BUG-004', 'real customer login on isolated database');
        $csrf = $token($http('/account'));
        $png = $directory . '/proof.png';
        file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        $deadline = $bookingService->inspect($hold, $customer)['het_han_giu_san_luc'];
        $check($http('/booking/receipt', ['id' => $hold, 'bien_lai' => new CURLFile($png)])['status'] === 403, 'BUG-004', 'upload still enforces CSRF');
        $oldFiles = glob($root . '/storage/receipts/receipt_' . $hold . '_*') ?: [];
        $response = $http('/booking/receipt', ['id' => $hold, '_token' => $csrf, 'bien_lai' => new CURLFile($png, 'image/png', 'proof.png')]);
        $paymentModel = new App\Models\ThanhToan($pdo);
        $receiptService = new App\Services\BookingReceiptService($pdo);
        $payment = $paymentModel->findByBookingId($hold);
        $receipt = $receiptService->info($payment);
        if ($receipt && !in_array($receipt['absolute'], $oldFiles, true)) $ownedReceipts[] = $receipt['absolute'];
        $check($response['status'] === 303 && str_contains($response['head'], 'booking%2Fresult'), 'BUG-004', 'valid upload redirects to existing result route');
        $check($receipt !== null && !empty($payment['anh_minh_chung']) && App\Services\ReceiptImage::getReceiptFromNote($payment['ghi_chu']) !== null, 'BUG-004', 'modern and legacy receipt links resolve same real uploaded file');
        $check($bookingService->inspect($hold, $customer)['trang_thai_dat_san'] === 'CHO_XAC_NHAN' && $payment['trang_thai'] === 'CHO_THANH_TOAN', 'BUG-004', 'upload atomically enters review without confirming payment');
        $check($bookingService->inspect($hold, $customer)['het_han_giu_san_luc'] === $deadline, 'BUG-004', 'upload never resets six-minute deadline');
        $http('/booking/receipt', ['id' => $hold, '_token' => $csrf, 'bien_lai' => new CURLFile($png, 'image/png', 'proof.png')]);
        $check($paymentModel->findByBookingId($hold)['anh_minh_chung'] === $payment['anh_minh_chung'], 'BUG-004', 'duplicate upload preserves accepted receipt');
        $pdo->exec("UPDATE dat_san SET bat_dau_giu_san_luc=NOW()-INTERVAL 1 DAY,het_han_giu_san_luc=NOW()-INTERVAL 1 DAY+INTERVAL 6 MINUTE WHERE ma_dat_san=$hold");
        (new App\Models\Booking($pdo))->expire($hold);
        $check($bookingService->inspect($hold, $customer)['trang_thai_dat_san'] === 'CHO_XAC_NHAN' && (new App\Models\Booking($pdo))->reserved($service, date('Y-m-d H:i:s')) === 2, 'BUG-004', 'one-day review survives expiry and keeps inventory reservation');
        try { $bookingService->quote($selection); $blocked = false; } catch (Core\HttpException $e) { $blocked = $e->status === 409; }
        $check($blocked, 'BUG-004', 'one-day review still blocks another booking');
        $actor = (new App\Services\AccountService($pdo))->create('highfixroot', 'Testpass1!', 'QUAN_TRI_TAP_DOAN');
        $user = ['ma_tai_khoan' => $actor, 'ten_vai_tro' => 'QUAN_TRI_TAP_DOAN', 'trang_thai' => 'HOAT_DONG'];
        (new App\Services\BookingManagementService($pdo))->confirmReceiptPayment($hold, $user);
        $check($bookingService->inspect($hold, $customer)['trang_thai_dat_san'] === 'DA_XAC_NHAN' && $paymentModel->findByBookingId($hold)['trang_thai'] === 'THANH_CONG', 'BUG-003', 'existing manual approval still confirms correct booking/payment');
        $check((new App\Models\HoaDon($pdo))->existsByBookingId($hold) && (int) $pdo->query("SELECT so_luong_ton FROM dich_vu WHERE ma_dich_vu=$service")->fetchColumn() === 1, 'BUG-003', 'approval creates invoice and deducts held stock once');
        try { (new App\Services\BookingManagementService($pdo))->confirmReceiptPayment($hold, $user); $again = false; }
        catch (Core\HttpException $e) { $again = $e->status === 409; }
        $check($again && (int) $pdo->query("SELECT so_luong_ton FROM dich_vu WHERE ma_dich_vu=$service")->fetchColumn() === 1, 'BUG-003', 'duplicate approval cannot create invoice or deduct stock again');
        $late = $makeBooking('CHO_THANH_TOAN');
        (new App\Services\PaymentService($pdo))->createPaymentRequest($late, '100000.00');
        $pdo->exec("UPDATE dat_san SET bat_dau_giu_san_luc=NOW()-INTERVAL 7 MINUTE,het_han_giu_san_luc=NOW()-INTERVAL 1 MINUTE WHERE ma_dat_san=$late");
        (new App\Models\Booking($pdo))->expire($late);
        $http('/booking/receipt', ['id' => $late, '_token' => $csrf, 'bien_lai' => new CURLFile($png, 'image/png', 'proof.png')]);
        $check($bookingService->inspect($late, $customer)['trang_thai_dat_san'] === 'HET_HAN', 'BUG-004', 'late upload cannot restore an expired booking');
    }
} finally {
    if ($client) curl_close($client);
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    foreach ($ownedReceipts as $file) if (is_file($file)) unlink($file);
    if (isset($png) && is_file($png)) unlink($png);
    $pdo = null;
    $admin->exec("DROP DATABASE `$database`");
    echo "Removed owned test database $database\n";
}
$failed = count(array_filter($results, static fn($r) => !$r['pass']));
echo json_encode(['pass' => count($results) - $failed, 'fail' => $failed, 'artifacts' => $directory], JSON_UNESCAPED_SLASHES) . "\n";
exit($failed ? 1 : 0);
