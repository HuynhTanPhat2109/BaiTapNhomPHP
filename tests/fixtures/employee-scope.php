<?php
// Production entry point/authentication on an explicitly isolated disposable database.
$db=getenv('STRYKE_EMPLOYEE_TEST_DB');
if(PHP_SAPI!=='cli-server'||!preg_match('/\Astryke_employee_test_[a-f0-9]{10}\z/',$db?:'')){http_response_code(404);exit;}
if(str_starts_with(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/assets/'))return false;
$root=dirname(__DIR__,2);require $root.'/vendor/autoload.php';Dotenv\Dotenv::createImmutable($root)->load();
$config=require $root.'/config/database.php';
if($db===$config['dbname']||!in_array($config['host'],['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Unsafe test database');
$_ENV['DB_NAME']=$db;$_ENV['APP_BASE_PATH']='';$_SERVER['REMOTE_ADDR']='employee-scope-'.$db;
ini_set('session.save_path',getenv('STRYKE_EMPLOYEE_TEST_ROOT').'/sessions');
require $root.'/public/index.php';
