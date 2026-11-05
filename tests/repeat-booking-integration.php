<?php
// Opt-in real MariaDB/HTTP/Chrome regression. Only copy application DDL, never rows.
if (PHP_SAPI !== 'cli' || !in_array($argv[1] ?? '', ['--run','--expire','--add-price'], true)) exit("php tests/repeat-booking-integration.php --run [--baseline]\n");
require dirname(__DIR__).'/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
date_default_timezone_set('Asia/Ho_Chi_Minh');
$config=require dirname(__DIR__).'/config/database.php';
if(!in_array($config['host'],['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Local isolated test required');
$connect=static function(?string $database=null)use($config):PDO {
    $pdo=new PDO("mysql:host={$config['host']};port={$config['port']};".($database===null?'':"dbname=$database;").'charset=utf8mb4',$config['username'],$config['password'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec("SET time_zone='+07:00'");return $pdo;
};
if(in_array($argv[1],['--expire','--add-price'],true)){
    $database=getenv('STRYKE_REPEAT_TEST_DB');$id=$argv[2]??'';
    if(!preg_match('/\Astryke_repeat_test_[a-f0-9]{10}\z/',$database?:'') || $database===$config['dbname'] || !ctype_digit($id))throw new RuntimeException('Unsafe expiry worker');
    $pdo=$connect($database);
    if($argv[1]==='--expire')$pdo->prepare("UPDATE dat_san SET bat_dau_giu_san_luc=DATE_SUB(NOW(),INTERVAL 7 MINUTE),het_han_giu_san_luc=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE ma_dat_san=? AND trang_thai_dat_san='CHO_THANH_TOAN'")->execute([$id]);
    else{
        $stmt=$pdo->prepare('SELECT ma_co_so,ma_loai_san FROM san WHERE ma_san=?');$stmt->execute([$id]);$court=$stmt->fetch();
        if(!$court)throw new RuntimeException('Missing fixture court');
        foreach(['NGAY_THUONG','CUOI_TUAN'] as $day)$pdo->prepare("INSERT INTO bang_gia (ma_co_so,ma_loai_san,ten_bang_gia,loai_ngay,gio_bat_dau,gio_ket_thuc,don_gia,ngay_bat_dau) VALUES (?,?,'TEST ONLY afternoon price',?,'12:00:00','15:00:00','60000','2000-01-01')")->execute([$court['ma_co_so'],$court['ma_loai_san'],$day]);
    }
    exit;
}
$root=dirname(__DIR__);$out=$root.'/storage/temp/repeat-booking-review';if(!is_dir($out))mkdir($out,0700,true);
$live=$connect($config['dbname']);$live->exec('SET SESSION TRANSACTION READ ONLY');$live->beginTransaction();
try{
    $definitions=[];
    foreach($live->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as [$table]){
        if(!preg_match('/\A[a-z0-9_]+\z/i',$table))throw new RuntimeException('Unexpected table name');
        $definitions[$table]=preg_replace('/ AUTO_INCREMENT=\d+/','',$live->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1]);
    }
    file_put_contents($out.'/schema-audit.json',json_encode(['server'=>$live->query('SELECT VERSION()')->fetchColumn(),
        'payment_columns'=>array_column($live->query('SHOW COLUMNS FROM thanh_toan')->fetchAll(),'Field')],JSON_PRETTY_PRINT));
}finally{$live->rollBack();$live=null;}
$db='stryke_repeat_test_'.bin2hex(random_bytes(5));$admin=$connect();$owned=false;$server=null;$checks=0;
$check=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException('FAIL: '.$label);$checks++;echo "PASS: $label\n";};
try{
    if($db===$config['dbname'] || !preg_match('/\Astryke_repeat_test_[a-f0-9]{10}\z/',$db))throw new RuntimeException('Unsafe test database');
    $admin->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$owned=true;$pdo=$connect($db);
    $check($pdo->query('SELECT DATABASE()')->fetchColumn()===$db,'write connection uses newly owned test database');
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');foreach($definitions as $sql)$pdo->exec($sql);$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $insert=static function(string $table,array $row)use($pdo):int{
        $pdo->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row));return (int)$pdo->lastInsertId();
    };
    foreach(['QUAN_TRI_TAP_DOAN','QUAN_LY_CONG_TY','QUAN_LY_CO_SO','NHAN_VIEN','KHACH_HANG'] as $role)$insert('vai_tro',['ten_vai_tro'=>$role]);
    $password='T'.bin2hex(random_bytes(4)).'a1!';$accounts=new App\Services\AccountService($pdo);
    $adminId=$accounts->create('repeattestadmin',$password,'QUAN_TRI_TAP_DOAN');
    $customerId=$accounts->create('repeattestuser',$password,'KHACH_HANG');
    $otherId=$accounts->create('repeattestother',$password,'KHACH_HANG');
    $company=$insert('cong_ty_nhuong_quyen',['ten_cong_ty'=>'TEST ONLY company','dia_chi'=>'TEST']);
    $facility=$insert('co_so',['ma_cong_ty'=>$company,'ten_co_so'=>'TEST ONLY Hoang Quan','dia_chi'=>'TEST','gio_mo_cua'=>'06:00:00','gio_dong_cua'=>'15:00:00']);
    $customer=$insert('khach_hang',['ma_tai_khoan'=>$customerId,'ho_ten'=>'TEST ONLY customer','so_dien_thoai'=>'0900000000']);
    $insert('khach_hang',['ma_tai_khoan'=>$otherId,'ho_ten'=>'TEST ONLY other customer','so_dien_thoai'=>'0900000001']);
    $sport=$insert('bo_mon',['ten_bo_mon'=>'TEST ONLY badminton']);$type=$insert('loai_san',['ma_bo_mon'=>$sport,'ten_loai_san'=>'TEST ONLY type']);
    $court=$insert('san',['ma_co_so'=>$facility,'ma_loai_san'=>$type,'ten_san'=>'TEST ONLY court']);
    $service=$insert('dich_vu',['ma_co_so'=>$facility,'ten_dich_vu'=>'TEST ONLY water','loai_dich_vu'=>'DO_UONG','don_vi_tinh'=>'bottle','don_gia'=>'10000','so_luong_ton'=>200]);
    foreach(['NGAY_THUONG','CUOI_TUAN'] as $day)$insert('bang_gia',['ma_co_so'=>$facility,'ma_loai_san'=>$type,'ten_bang_gia'=>'TEST ONLY morning price','loai_ngay'=>$day,'gio_bat_dau'=>'06:00:00','gio_ket_thuc'=>'12:00:00','don_gia'=>'60000','ngay_bat_dau'=>'2000-01-01']);
    $insert('tai_khoan_ngan_hang_co_so',['ma_co_so'=>$facility,'ma_bin'=>'970422','ten_ngan_hang'=>'TEST ONLY bank','so_tai_khoan'=>'12345678','ten_chu_tai_khoan'=>'TEST FIXTURE','la_mac_dinh'=>1]);
    $date=date('Y-m-d',time()+86400);$env=getenv();foreach($_ENV as $key=>$value)$env[$key]=$value;
    $env['STRYKE_REPEAT_TEST_DB']=$db;$env['STRYKE_REPEAT_TEST_PASSWORD']=$password;$env['STRYKE_REPEAT_TEST_ROOT']=$out.'/'.$db;
    foreach(['sessions','receipts'] as $directory)mkdir($env['STRYKE_REPEAT_TEST_ROOT'].'/'.$directory,0700,true);
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);if(!$socket)throw new RuntimeException('No test port');
    $address=stream_socket_get_name($socket,false);fclose($socket);$env['STRYKE_REPEAT_TEST_URL']='http://'.$address;
    file_put_contents($out.'/fixture-ids.json',json_encode(compact('facility','court','service','date')));
    $server=proc_open([PHP_BINARY,'-d','variables_order=EGPCS','-S',$address,'-t','public','tests/fixtures/repeat-booking.php'],
        [0=>['pipe','r'],1=>['file',$out.'/server.log','a'],2=>['file',$out.'/server.log','a']],$pipes,$root,$env,['bypass_shell'=>true]);
    if(!is_resource($server))throw new RuntimeException('Cannot start test server');fclose($pipes[0]);
    $ready=false;for($i=0;$i<100;$i++){$socket=@stream_socket_client('tcp://'.$address,$errno,$error,0.05);if($socket){fclose($socket);$ready=true;break;}usleep(50000);}
    $check($ready,'real HTTP server uses isolated schema and sessions');
    $mode=in_array('--baseline',$argv,true)?'baseline':'regression';
    $browser=proc_open(['node','tests/repeat-booking-browser.cjs',$mode],[0=>['pipe','r'],1=>['file',$out.'/browser-'.$mode.'.log','w'],2=>['file',$out.'/browser-'.$mode.'.log','a']],$pipes,$root,$env,['bypass_shell'=>true]);
    if(!is_resource($browser))throw new RuntimeException('Cannot start browser tests');fclose($pipes[0]);$exit=proc_close($browser);
    echo file_get_contents($out.'/browser-'.$mode.'.log');$check($exit===0,'Chrome '.$mode.' checks complete');
    if($mode==='regression'){
        $rows=$pdo->query('SELECT b.ma_dat_san,b.trang_thai_dat_san,p.trang_thai AS payment_state FROM dat_san b LEFT JOIN thanh_toan p ON p.ma_dat_san=b.ma_dat_san ORDER BY 1')->fetchAll();
        $check(count($rows)>=5,'one real customer created several different booking rows, including 13:00-14:00 after test-only price configuration');
        $check((int)$pdo->query('SELECT so_luong_ton FROM dich_vu WHERE ma_dich_vu='.$service)->fetchColumn()===198,'manual confirmation deducted service stock once; retries did not repeat');
        $check((int)$pdo->query('SELECT COUNT(*) FROM hoa_don')->fetchColumn()===1,'one invoice for the confirmed booking');
        $manager=new App\Services\BookingManagementService($pdo,receipts:new App\Services\BookingReceiptService($pdo,$env['STRYKE_REPEAT_TEST_ROOT']));
        $operator=(new App\Models\TaiKhoan($pdo))->identity($adminId);
        foreach([2=>'cancelled',3=>'expired'] as $id=>$state){
            try{$manager->confirmReceiptPayment($id,$operator);throw new RuntimeException('Terminal booking confirmed');}
            catch(App\Services\ValidationException $e){$check(!$pdo->inTransaction()
                && (int)$pdo->query('SELECT so_luong_ton FROM dich_vu WHERE ma_dich_vu='.$service)->fetchColumn()===198,'real backend rejects '.$state.' confirmation without another stock deduction');}
        }
        file_put_contents($out.'/database-results.json',json_encode($rows,JSON_PRETTY_PRINT));
    }
    echo "PASS: $checks MariaDB orchestration checks; configured database was read-only.\n";
}finally{
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    if(isset($pdo) && $pdo->inTransaction())$pdo->rollBack();$pdo=null;
    if($owned && $db!==$config['dbname'] && preg_match('/\Astryke_repeat_test_[a-f0-9]{10}\z/',$db))$admin->exec("DROP DATABASE `$db`");
}
