<?php
if (PHP_SAPI !== 'cli' || !in_array('--run', $argv, true)) exit("Usage: php tests/facility-migration-integration.php --run\nCreates and removes an isolated test database.\n");
require dirname(__DIR__) . '/vendor/autoload.php';
$root = dirname(__DIR__);
Dotenv\Dotenv::createImmutable($root)->load();
$c = require $root . '/config/database.php';
if (!in_array($c['host'], ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Only an isolated local test database is allowed.');
}
$pdo = new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4", $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db = 'stryke_pr2_test_' . bin2hex(random_bytes(5));
$pdo->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$server = null;
try {
    $pdo->exec("USE `$db`");
    $schema = file_get_contents($root . '/database/quanly_chuoi_san_the_thao.sql');
    $schema = preg_replace('/CREATE DATABASE.*?;|USE quanly_chuoi_san_the_thao;/s', '', $schema);
    // Reproduce the old schema, then apply the migration to an existing row.
    $old = preg_replace('/^    (?:ma_tinh VARCHAR|ten_tinh VARCHAR|ma_phuong_xa VARCHAR|ten_phuong_xa VARCHAR|anh_dai_dien VARCHAR|logo_clb VARCHAR|INDEX idx_coso_tinh).*\R/m', '', $schema);
    $pdo->exec($old);
    $pdo->exec("INSERT INTO cong_ty_nhuong_quyen (ten_cong_ty,dia_chi) VALUES ('Migration test','Old address')");
    $pdo->exec("INSERT INTO co_so (ma_cong_ty,ten_co_so,dia_chi,gio_mo_cua,gio_dong_cua) VALUES (1,'Old facility','Old address','06:00','23:00')");
    $pdo->exec(file_get_contents($root . '/database/migrations/20261023_co_so_address_image.sql'));
    $pdo->exec(file_get_contents($root . '/database/migrations/20261023_co_so_logo.sql'));
    $row = $pdo->query('SELECT * FROM co_so WHERE ma_co_so=1')->fetch(PDO::FETCH_ASSOC);
    if ($row['dia_chi'] !== 'Old address' || $row['ma_tinh'] !== null || $row['anh_dai_dien'] !== null) throw new RuntimeException('Migration lost data');
    echo "PASS: migration preserves legacy row.\n";
    $env = getenv();
    foreach ($_ENV as $key => $value) $env[$key] = $value;
    $env['DB_NAME'] = $db;
    $env['APP_BASE_PATH'] = '/';
    $env['STRYKE_TEST_DB'] = $db;
    $env['STRYKE_TEST_URL'] = 'http://127.0.0.1:18987';
    // Separate rate-limit bucket from manual localhost logins and previous tests.
    $env['STRYKE_TEST_CLIENT_IP'] = '127.0.0.' . random_int(2, 254);
    $server = proc_open([PHP_BINARY, '-d', 'variables_order=EGPCS', '-d', 'session.save_path=' . $root . '/storage/temp', '-S', '127.0.0.1:18987', '-t', 'public'],
        [0 => ['pipe','r'],1 => ['file', $root.'/storage/temp/facility-server.log','a'],2 => ['file', $root.'/storage/temp/facility-server.log','a']], $pipes, $root, $env);
    usleep(700000);
    $env['DB_NAME'] = $c['dbname'];
    $test = proc_open([PHP_BINARY, '-d', 'variables_order=EGPCS', 'tests/facility-integration.php', '--run'], [0=>['pipe','r'],1=>STDOUT,2=>STDERR], $pipes, $root, $env);
    $status = proc_close($test);
    if ($status !== 0) throw new RuntimeException('Integration suite failed');
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    $pdo->exec("DROP DATABASE `$db`"); // Only the random database created above.
}
