<?php
// Optional scheduler: php bin/expire-bookings.php --run (once a minute).
if (PHP_SAPI !== 'cli' || !in_array('--run',$argv,true)) exit("Usage: php bin/expire-bookings.php --run\n");
require dirname(__DIR__).'/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
date_default_timezone_set('Asia/Ho_Chi_Minh');
$service=new App\Services\BookingService((new Core\Database())->getConnection());
echo $service->expirePending()." expired booking(s).\n";
