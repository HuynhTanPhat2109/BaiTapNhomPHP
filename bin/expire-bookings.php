<?php
// Explicit opt-in. Schedule once a minute; never invoke from an HTTP request.
if (PHP_SAPI !== 'cli' || !in_array('--run',$argv,true)) exit("Usage: php bin/expire-bookings.php --run\n");
date_default_timezone_set('Asia/Ho_Chi_Minh');
ini_set('display_errors', '0');
$root = dirname(__DIR__);
$directory = $root . '/storage/temp/booking-expiry';
if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
    fwrite(STDERR, "Cannot create expiry log directory.\n"); exit(1);
}
$log = $directory . '/' . date('Y-m-d') . '.log';
$logHandle = fopen($log, 'ab');
if ($logHandle === false) { fwrite(STDERR, "Cannot open expiry log.\n"); exit(1); }
fclose($logHandle);
ini_set('log_errors', '1');
ini_set('error_log', $log);
$started = microtime(true);
$writeLog = static function(array $entry) use ($log): void {
    $line = json_encode(['at'=>date(DATE_ATOM), 'pid'=>getmypid()] + $entry, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    if (file_put_contents($log, $line, FILE_APPEND | LOCK_EX) === false) throw new RuntimeException('Cannot write expiry log');
};
try {
    $writeLog(['event'=>'started']);
    require $root . '/vendor/autoload.php';
    Dotenv\Dotenv::createImmutable($root)->load();
    $pdo = (new Core\Database())->getConnection();
    // Bound waits as well as batch duration; failed rows remain pending for the next run.
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=5');
    $service = new App\Services\BookingService($pdo);
    $count = $service->expirePending();
    $report = $service->expiryReport();
    $writeLog(['event'=>'completed', 'seconds'=>round(microtime(true)-$started, 3)] + $report);
    echo $report['overlap_skipped'] ? "Skipped: expiry job already running.\n" : "$count expired booking(s).\n";
    if ($report['protected_sample']) echo "Reconciliation required; see expiry log (up to 100 booking IDs per run).\n";
    if ($report['more_pending']) echo "More expired bookings remain; next scheduled run will continue.\n";
    exit(0);
} catch (Throwable $error) {
    // No connection credentials, SQL text, payment details or stack traces in job output.
    $writeLog(['event'=>'failed', 'error_type'=>get_class($error), 'error_code'=>(string)$error->getCode(),
        'seconds'=>round(microtime(true)-$started, 3)]);
    fwrite(STDERR, "Booking expiry failed; see expiry log. Retry on next scheduled run.\n");
    exit(1);
}
