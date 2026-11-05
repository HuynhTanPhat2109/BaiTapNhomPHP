<?php
// CLI-server only. Real production auth/routes/controllers/PDO, confined to disposable test DB.
$database=getenv('STRYKE_REPEAT_TEST_DB');
if(PHP_SAPI!=='cli-server' || !preg_match('/\Astryke_repeat_test_[a-f0-9]{10}\z/',$database?:'')){http_response_code(404);exit;}
if(str_starts_with(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/assets/'))return false;
$root=dirname(__DIR__,2);require $root.'/vendor/autoload.php';Dotenv\Dotenv::createImmutable($root)->load();
$config=require $root.'/config/database.php';
if($database===$config['dbname'] || !in_array($config['host'],['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Unsafe test connection');
$_ENV['DB_NAME']=$database;$_ENV['APP_BASE_PATH']='';date_default_timezone_set('Asia/Ho_Chi_Minh');
$receiptRoot=getenv('STRYKE_REPEAT_TEST_ROOT');ini_set('session.save_path',$receiptRoot.'/sessions');Core\Session::start();
$_SERVER['REMOTE_ADDR']='repeat-booking-test'; // Isolate the real login throttle from real local users.
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: https://img.vietqr.io; font-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'");
$app=new Core\Application();$pdo=$app->pdo();
if($pdo->query('SELECT DATABASE()')->fetchColumn()!==$database)throw new RuntimeException('Wrong test database');
$router=new Core\Router();require $root.'/routes/web.php';
$receipts=new App\Services\BookingReceiptService($pdo,$receiptRoot);
(new ReflectionProperty($booking,'receipts'))->setValue($booking,$receipts);
(new ReflectionProperty($adminBooking,'receipts'))->setValue($adminBooking,$receipts);
(new ReflectionProperty($adminBooking,'service'))->setValue($adminBooking,new App\Services\BookingManagementService($pdo,receipts:$receipts));
try{$router->dispatch($_SERVER['REQUEST_METHOD'],request_path());}
catch(Core\HttpException $e){http_response_code($e->status);Core\View::render('errors/error',['title'=>(string)$e->status,'message'=>$e->getMessage()]);}
catch(Throwable $e){http_response_code(500);error_log((string)$e);echo 'Isolated repeat-booking test failed';}
