<?php
// Opt-in. All writes go to a new randomly named database, never the configured database.
if (PHP_SAPI!=='cli' || !in_array($argv[1]??'', ['--run','--worker','--expire-worker'],true)) exit("php tests/booking-integration.php --run [--browser]\n");
require dirname(__DIR__).'/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
$config=require dirname(__DIR__).'/config/database.php';
if (!in_array($config['host'],['localhost','127.0.0.1','::1'],true)) throw new RuntimeException('Local isolated database required');
date_default_timezone_set('Asia/Ho_Chi_Minh');
function connectBookingTest(?string $db=null): PDO {
    global $config;
    if ($db!==null && (!preg_match('/\Astryke_booking_test_[a-f0-9]{10}\z/',$db) || $db===$config['dbname'])) throw new RuntimeException('Unsafe test database');
    $pdo=new PDO("mysql:host={$config['host']};port={$config['port']};".($db?"dbname=$db;":'')."charset=utf8mb4",$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec("SET time_zone='+07:00'"); return $pdo;
}
if ($argv[1]==='--expire-worker') {
    $pdo=connectBookingTest($argv[2]);
    if(!ctype_digit($argv[3]??''))throw new RuntimeException('Invalid fixture ID');
    $pdo->prepare("UPDATE dat_san SET bat_dau_giu_san_luc=DATE_SUB(NOW(),INTERVAL 7 MINUTE),het_han_giu_san_luc=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE ma_dat_san=? AND trang_thai_dat_san='CHO_THANH_TOAN'")->execute([$argv[3]]);
    exit;
}
if ($argv[1]==='--worker') {
    $pdo=connectBookingTest($argv[2]); $args=json_decode(base64_decode($argv[3]),true,512,JSON_THROW_ON_ERROR);
    try { $id=(new App\Services\BookingService($pdo))->confirm(...$args); echo json_encode(['id'=>$id]); }
    catch (Core\HttpException $e) { echo json_encode(['error'=>$e->status]); }
    exit;
}
$checks=0;
function checkBooking(bool $ok,string $label): void { global $checks; if(!$ok) throw new RuntimeException('FAIL: '.$label); $checks++; echo "PASS: $label\n"; }
function rejectBooking(callable $fn,int $status,string $label): void {
    try { $fn(); } catch(Core\HttpException $e) { checkBooking($e->status===$status,$label);return; }
    throw new RuntimeException('Expected rejection: '.$label);
}
function insertBookingFixture(string $table,array $data): int {
    global $pdo; $pdo->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($data)).') VALUES ('.implode(',',array_fill(0,count($data),'?')).')')->execute(array_values($data)); return (int)$pdo->lastInsertId();
}
function raceBookings(array $calls): array {
    global $pdo,$db,$company;
    $pdo->beginTransaction(); $pdo->query("SELECT * FROM cong_ty_nhuong_quyen WHERE ma_cong_ty=$company FOR UPDATE")->fetch();
    $jobs=[];
    try {
        foreach($calls as $args) {
            $process=proc_open([PHP_BINARY,__FILE__,'--worker',$db,base64_encode(json_encode($args))],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
            if(!is_resource($process)) throw new RuntimeException('Cannot start race worker');
            fclose($pipes[0]); $jobs[]=[$process,$pipes];
        }
        usleep(350000); $pdo->commit(); $results=[];
        foreach($jobs as [$process,$pipes]) {
            $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
            if(proc_close($process)!==0) throw new RuntimeException('Race worker: '.$err);
            $results[]=json_decode($out,true,512,JSON_THROW_ON_ERROR);
        }
        return $results;
    } finally { if($pdo->inTransaction())$pdo->rollBack(); }
}
$admin=connectBookingTest();$db='stryke_booking_test_'.bin2hex(random_bytes(5));$server=null;$receiptFiles=[];
$admin->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    $pdo=connectBookingTest($db);
    $sql=file_get_contents(dirname(__DIR__).'/database/quanly_chuoi_san_the_thao.sql');
    $sql=preg_replace('/CREATE DATABASE.*?;|USE quanly_chuoi_san_the_thao;/s','',$sql);
    $sql=preg_replace('/^    (?:khoa_yeu_cau CHAR|dau_van_tay_yeu_cau CHAR|UNIQUE KEY uq_datsan_khoa_yeu_cau).*\R/m','',$sql);
    $pdo->exec($sql);
    $company=insertBookingFixture('cong_ty_nhuong_quyen',['ten_cong_ty'=>'Booking test','dia_chi'=>'Test']);
    $facility=insertBookingFixture('co_so',['ma_cong_ty'=>$company,'ten_co_so'=>'Test facility','dia_chi'=>'Test','gio_mo_cua'=>'05:00','gio_dong_cua'=>'23:00']);
    $other=insertBookingFixture('co_so',['ma_cong_ty'=>$company,'ten_co_so'=>'Other facility','dia_chi'=>'Test','gio_mo_cua'=>'05:00','gio_dong_cua'=>'23:00']);
    $type=insertBookingFixture('loai_san',['ma_bo_mon'=>1,'ten_loai_san'=>'Test type']);
    $court=insertBookingFixture('san',['ma_co_so'=>$facility,'ma_loai_san'=>$type,'ten_san'=>'Court A']);
    $court2=insertBookingFixture('san',['ma_co_so'=>$facility,'ma_loai_san'=>$type,'ten_san'=>'Court B']);
    $customers=[];
    for($i=1;$i<=2;$i++) {
        $account=(new App\Services\AccountService($pdo))->create('booktest'.$i,'Testpass1!','KHACH_HANG');
        $customers[]=insertBookingFixture('khach_hang',['ma_tai_khoan'=>$account,'ho_ten'=>'Customer '.$i,'so_dien_thoai'=>'0901234567']);
    }
    $legacy=insertBookingFixture('dat_san',['ma_co_so'=>$facility,'ma_khach_hang'=>$customers[0]]);
    $pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/20261031_booking_request_key.sql'));
    checkBooking($pdo->query("SELECT khoa_yeu_cau FROM dat_san WHERE ma_dat_san=$legacy")->fetchColumn()===null,'migration preserves legacy booking');
    $date=(new DateTimeImmutable('tomorrow'))->format('Y-m-d');$day=(int)(new DateTimeImmutable($date))->format('N')>=6?'CUOI_TUAN':'NGAY_THUONG';
    foreach([['05:00','10:00','80000.00'],['10:00','23:00','100000.00']] as [$start,$end,$price]) insertBookingFixture('bang_gia',['ma_co_so'=>$facility,'ma_loai_san'=>$type,'ten_bang_gia'=>'Test rate','loai_ngay'=>$day,'gio_bat_dau'=>$start,'gio_ket_thuc'=>$end,'don_gia'=>$price,'ngay_bat_dau'=>$date]);
    $serviceId=insertBookingFixture('dich_vu',['ma_co_so'=>$facility,'ten_dich_vu'=>'Water','loai_dich_vu'=>'DO_UONG','don_vi_tinh'=>'bottle','don_gia'=>'10000.00','so_luong_ton'=>3]);
    $foreignService=insertBookingFixture('dich_vu',['ma_co_so'=>$other,'ten_dich_vu'=>'Other water','loai_dich_vu'=>'DO_UONG','don_vi_tinh'=>'bottle','don_gia'=>'10000','so_luong_ton'=>3]);
    $rental=insertBookingFixture('dich_vu',['ma_co_so'=>$facility,'ten_dich_vu'=>'Racket','loai_dich_vu'=>'CHO_THUE','don_vi_tinh'=>'piece','don_gia'=>'10000','so_luong_ton'=>3]);
    $booking=new App\Services\BookingService($pdo);
    $pick=static fn($start,$end,$id=null)=>['facility'=>$facility,'date'=>$date,'slots'=>[['court'=>$id??$court,'start'=>$start,'end'=>$end]]];
    $none=['services'=>[],'note'=>''];
    $input=$pick('09:30','10:00');$quote=$booking->quote($input);
    checkBooking($quote['total']==='40000.00','30 minutes uses half hourly price');
    $multi=$input;$multi['slots'][]=['court'=>$court,'start'=>'10:00','end'=>'10:30'];$multi['slots'][]=['court'=>$court2,'start'=>'11:00','end'=>'11:30'];
    checkBooking($booking->quote($multi)['total']==='140000.00','multi court and price boundary calculation');
    $duplicate=$input;$duplicate['slots'][]=$input['slots'][0];
    rejectBooking(fn()=>$booking->quote($duplicate),422,'duplicate slots rejected');
    $past=$input;$past['date']='2020-01-01';rejectBooking(fn()=>$booking->quote($past),422,'past date rejected');
    rejectBooking(fn()=>$booking->quote($input,['services'=>[$foreignService=>1],'note'=>'']),409,'foreign facility service rejected');
    rejectBooking(fn()=>$booking->quote($input,['services'=>[$rental=>1],'note'=>'']),409,'rental unsupported lifecycle rejected');
    rejectBooking(fn()=>App\Services\BookingService::extras([$serviceId=>-1],''),422,'negative quantity rejected');
    $extras=['services'=>[$serviceId=>2],'note'=>'<script>alert(1)</script>'];$quote=$booking->quote($input,$extras);$key=bin2hex(random_bytes(32));
    $id=$booking->confirm($customers[0],$key,$input,$extras,$quote['fingerprint']);
    checkBooking($booking->confirm($customers[0],$key,$input,$extras,$quote['fingerprint'])===$id,'retry returns one booking');
    rejectBooking(fn()=>$booking->confirm($customers[1],$key,$input,$extras,$quote['fingerprint']),409,'request key bound to customer');
    rejectBooking(fn()=>$booking->confirm($customers[0],$key,$input,$none,$quote['fingerprint']),409,'request key bound to content');
    $row=$booking->owned($id,$customers[0]);
    checkBooking(strtotime($row['het_han_giu_san_luc'])-strtotime($row['bat_dau_giu_san_luc'])===360,'exact six-minute hold');
    checkBooking($booking->owned($id,$customers[0])['het_han_giu_san_luc']===$row['het_han_giu_san_luc'],'refresh never extends hold');
    checkBooking((int)$pdo->query("SELECT so_luong_ton FROM dich_vu WHERE ma_dich_vu=$serviceId")->fetchColumn()===3,'hold does not deduct stock');
    rejectBooking(fn()=>$booking->quote($pick('12:00','12:30'),$extras),409,'held service quantities excluded from availability');
    rejectBooking(fn()=>$booking->owned($id,$customers[1]),404,'other customer cannot read booking');
    rejectBooking(fn()=>$booking->quote($input),409,'active hold blocks slot');
    $pdo->exec("UPDATE dat_san SET bat_dau_giu_san_luc=DATE_SUB(NOW(),INTERVAL 7 MINUTE),het_han_giu_san_luc=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE ma_dat_san=$id");
    checkBooking($booking->quote($input,$extras)['total']==='60000.00','overdue hold releases court and inventory before cleanup');
    $pdo->beginTransaction();rejectBooking(fn()=>$booking->lockPayable($id),409,'late payment guard rejects overdue hold');$pdo->rollBack();
    checkBooking($booking->owned($id,$customers[0])['trang_thai_dat_san']==='HET_HAN','lazy expiry updates status');
    checkBooking($booking->cancelHold($id,$customers[0])['trang_thai_dat_san']==='HET_HAN','cancel cannot revive expired booking');
    $cancelPick=$pick('11:30','12:00'); $cancelKey=bin2hex(random_bytes(32)); $cancelQuote=$booking->quote($cancelPick,$extras);
    $cancelId=$booking->confirm($customers[0],$cancelKey,$cancelPick,$extras,$cancelQuote['fingerprint']);
    rejectBooking(fn()=>$booking->cancelHold($cancelId,$customers[1]),404,'cancel checks ownership');
    checkBooking($booking->cancelHold($cancelId,$customers[0])['trang_thai_dat_san']==='DA_HUY','cancel pending hold');
    checkBooking($booking->cancelHold($cancelId,$customers[0])['trang_thai_dat_san']==='DA_HUY','cancel is idempotent');
    checkBooking($booking->quote($cancelPick,$extras)['total']==='70000.00' && (int)$pdo->query("SELECT so_luong_ton FROM dich_vu WHERE ma_dich_vu=$serviceId")->fetchColumn()===3,'cancel releases court and virtual inventory without restocking');
    checkBooking($booking->confirm($customers[0],$cancelKey,$cancelPick,$extras,$cancelQuote['fingerprint'])===$cancelId && $booking->owned($cancelId,$customers[0])['trang_thai_dat_san']==='DA_HUY','retry cancelled request never restores hold');
    $stockA=$pick('12:00','12:30');$stockB=$pick('12:00','12:30',$court2);
    $stockCalls=[[$customers[0],bin2hex(random_bytes(32)),$stockA,$extras,$booking->quote($stockA,$extras)['fingerprint']],[$customers[1],bin2hex(random_bytes(32)),$stockB,$extras,$booking->quote($stockB,$extras)['fingerprint']]];
    $stockResults=raceBookings($stockCalls);
    checkBooking(count(array_filter($stockResults,fn($r)=>isset($r['id'])))===1,'concurrent different courts cannot oversell held stock');
    $stockBooking=array_values(array_filter($stockResults,fn($r)=>isset($r['id'])))[0]['id'];
    $adminServices=new App\Services\DichVuService($pdo,['ten_vai_tro'=>'QUAN_TRI_TAP_DOAN','trang_thai'=>'HOAT_DONG']);
    $current=$adminServices->find($serviceId);
    $edit=$current; $edit['ma_co_so']=(string)$facility;$edit['theo_doi_ton']='1';$edit['so_luong_ton']='1';$edit['mo_ta']='';$edit['version']=App\Services\DichVuService::version($current);
    try { $adminServices->save($edit,$serviceId); throw new RuntimeException('Admin stock reduction allowed'); }
    catch(App\Services\ValidationException $e){checkBooking(isset($e->errors['so_luong_ton']),'admin cannot reduce stock below reservations');}
    $pdo->exec("UPDATE dat_san SET bat_dau_giu_san_luc=DATE_SUB(NOW(),INTERVAL 7 MINUTE),het_han_giu_san_luc=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE ma_dat_san=$stockBooking");
    checkBooking($booking->expirePending()===1,'scheduled expiration is conditional');
    checkBooking($booking->expirePending()===0,'repeated expiration does not release stock twice');
    $r1=$pick('13:00','13:30');$r2=$pick('13:30','14:00');
    $q1=$booking->quote($r1);$q2=$booking->quote($r2);
    $calls=[[$customers[0],bin2hex(random_bytes(32)),$r1,$none,$q1['fingerprint']],[$customers[1],bin2hex(random_bytes(32)),$r1,$none,$q1['fingerprint']]];
    $results=raceBookings($calls);checkBooking(count(array_filter($results,fn($r)=>isset($r['id'])))===1,'two concurrent overlapping requests: exactly one succeeds');
    $r1=$pick('14:00','14:30');$r2=$pick('14:30','15:00');
    $results=raceBookings([[$customers[0],bin2hex(random_bytes(32)),$r1,$none,$booking->quote($r1)['fingerprint']],[$customers[1],bin2hex(random_bytes(32)),$r2,$none,$booking->quote($r2)['fingerprint']]]);
    checkBooking(count(array_filter($results,fn($r)=>isset($r['id'])))===2,'two concurrent non-overlapping requests both succeed');
    $r1=$pick('15:00','15:30');$same=[$customers[0],bin2hex(random_bytes(32)),$r1,$none,$booking->quote($r1)['fingerprint']];
    $results=raceBookings([$same,$same]);checkBooking(isset($results[0]['id']) && $results[0]['id']===$results[1]['id'],'concurrent identical requests create one booking');
    $pdo->exec("UPDATE dat_san SET trang_thai_dat_san='DA_XAC_NHAN' WHERE ma_dat_san=".(int)$results[0]['id']);
    checkBooking($booking->cancelHold((int)$results[0]['id'],$customers[0])['trang_thai_dat_san']==='DA_XAC_NHAN','cancel protects confirmed booking');
    rejectBooking(fn()=>$booking->quote($r1),409,'confirmed booking blocks slot');
    $partial=$pick('14:30','15:00');$partial['slots'][]=['court'=>$court,'start'=>'15:00','end'=>'15:30'];
    rejectBooking(fn()=>$booking->quote($partial),409,'partial overlap across a larger range rejected');
    $many=$pick('18:00','18:30');$many['slots'][]=['court'=>$court2,'start'=>'18:00','end'=>'18:30'];
    $manyQuote=$booking->quote($many);$otherPick=$pick('18:00','18:30',$court2);
    $booking->confirm($customers[1],bin2hex(random_bytes(32)),$otherPick,$none,$booking->quote($otherPick)['fingerprint']);
    $count=(int)$pdo->query('SELECT COUNT(*) FROM dat_san')->fetchColumn();
    rejectBooking(fn()=>$booking->confirm($customers[0],bin2hex(random_bytes(32)),$many,$none,$manyQuote['fingerprint']),409,'multi court conflict rolls back whole request');
    checkBooking((int)$pdo->query('SELECT COUNT(*) FROM dat_san')->fetchColumn()===$count,'failed transaction creates no partial booking');
    $r1=$pick('16:00','16:30');$hash=$booking->quote($r1)['fingerprint'];
    $pdo->exec("UPDATE bang_gia SET don_gia='120000' WHERE gio_bat_dau='10:00:00'");
    rejectBooking(fn()=>$booking->confirm($customers[0],bin2hex(random_bytes(32)),$r1,$none,$hash),409,'changed price requires review');
    $pdo->exec("UPDATE bang_gia SET don_gia='100000' WHERE gio_bat_dau='10:00:00'");

    // Payment lifecycle tests: only this newly created, randomly named database.
    $payments = new App\Services\PaymentService($pdo);
    $paymentPick = $pick('20:00','20:30');
    $paymentQuote = $booking->quote($paymentPick);
    $paymentBooking = $booking->confirm($customers[0],bin2hex(random_bytes(32)),$paymentPick,$none,$paymentQuote['fingerprint']);
    $deadline = $booking->inspect($paymentBooking,$customers[0])['het_han_giu_san_luc'];
    try { $payments->createPaymentRequest($paymentBooking,$paymentQuote['total']); throw new RuntimeException('Missing bank table was ignored'); }
    catch (PDOException $e) { checkBooking((int)$e->errorInfo[1]===1146,'missing bank schema fails payment transaction'); }
    checkBooking((int)$pdo->query('SELECT COUNT(*) FROM thanh_toan')->fetchColumn()===0,'payment failure rolls back without losing booking');
    $pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/20261101_tai_khoan_ngan_hang_co_so.sql'));
    $payment = $payments->createPaymentRequest($paymentBooking,$paymentQuote['total']);
    checkBooking($payment['bank']===null,'missing bank configuration distinguished from technical failure');
    $retried = $payments->createPaymentRequest($paymentBooking,$paymentQuote['total']);
    checkBooking($payment['payment_id']===$retried['payment_id'] && $retried['expires_at']===$deadline,'retry reuses payment and never extends hold');
    try { $payments->createPaymentRequest($legacy,0); throw new RuntimeException('Draft accepted'); }
    catch (App\Services\ValidationException $e) { checkBooking(isset($e->errors['booking']),'draft without hold cannot request payment'); }
    $booking->cancelHold($paymentBooking,$customers[0]);
    checkBooking($payments->getPayment($payment['payment_id'])['trang_thai']==='HUY','cancellation synchronizes pending payment');
    $booking->cancelHold($paymentBooking,$customers[0]);
    checkBooking($payments->getPayment($payment['payment_id'])['trang_thai']==='HUY','repeated cancellation leaves payment cancelled');
    try { $payments->createPaymentRequest($paymentBooking,$paymentQuote['total']); throw new RuntimeException('Cancelled accepted'); }
    catch (App\Services\ValidationException $e) { checkBooking(isset($e->errors['booking']),'cancelled booking cannot retry payment'); }
    $paymentBooking = $booking->confirm($customers[0],bin2hex(random_bytes(32)),$paymentPick,$none,$paymentQuote['fingerprint']);
    $payment = $payments->createPaymentRequest($paymentBooking,$paymentQuote['total']);
    $pdo->exec("UPDATE dat_san SET bat_dau_giu_san_luc=DATE_SUB(NOW(),INTERVAL 6 MINUTE),het_han_giu_san_luc=NOW() WHERE ma_dat_san=$paymentBooking");
    checkBooking($booking->inspect($paymentBooking,$customers[0])['trang_thai_dat_san']==='HET_HAN'
        && $pdo->query("SELECT trang_thai_dat_san FROM dat_san WHERE ma_dat_san=$paymentBooking")->fetchColumn()==='CHO_THANH_TOAN','GET projection reports expiry without writing');
    try { $payments->createPaymentRequest($paymentBooking,$paymentQuote['total']); throw new RuntimeException('Expired accepted'); }
    catch (App\Services\ValidationException $e) { checkBooking(isset($e->errors['booking']),'expiry boundary rejects payment retry'); }
    $booking->expirePending();
    checkBooking($payments->getPayment($payment['payment_id'])['trang_thai']==='HET_HAN','expiry synchronizes pending payment');
    checkBooking($booking->expirePending()===0,'repeated payment expiry is harmless');
    foreach (['CAN_DOI_SOAT','THANH_CONG'] as $protected) {
        $pid=$booking->confirm($customers[0],bin2hex(random_bytes(32)),$paymentPick,$none,$paymentQuote['fingerprint']);
        $pmt=$payments->createPaymentRequest($pid,$paymentQuote['total']);
        // Simulate a recorded payment only in the disposable DB; no callback implementation.
        $pdo->prepare("UPDATE thanh_toan SET trang_thai=?,ma_giao_dich=?,ngay_thanh_toan=NOW(),so_tien_thuc_nhan=so_tien WHERE ma_thanh_toan=?")
            ->execute([$protected,'TEST-'.$pid,$pmt['payment_id']]);
        try { $payments->createPaymentRequest($pid,$paymentQuote['total']); throw new RuntimeException('Protected payment reset'); }
        catch (App\Services\ValidationException $e) { checkBooking(isset($e->errors['payment']),'retry preserves '.$protected); }
        $booking->cancelHold($pid,$customers[0]);
        checkBooking($payments->getPayment($pmt['payment_id'])['trang_thai']===$protected,'cancel preserves '.$protected);
        $pdo->exec("UPDATE dat_san SET bat_dau_giu_san_luc=DATE_SUB(NOW(),INTERVAL 7 MINUTE),het_han_giu_san_luc=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE ma_dat_san=$pid");
        $booking->expirePending();
        checkBooking($payments->getPayment($pmt['payment_id'])['trang_thai']===$protected,'expiry preserves '.$protected);
    }
    insertBookingFixture('tai_khoan_ngan_hang_co_so',['ma_co_so'=>$facility,'ten_ngan_hang'=>'TEST ONLY','ma_bin'=>'970422','so_tai_khoan'=>'0000000000','ten_chu_tai_khoan'=>'TEST ONLY']);

    // Real HTTP with its own session cookies, auth, CSRF and the isolated database.
    $env=getenv();foreach($_ENV as $k=>$v)$env[$k]=$v;
    $env['DB_NAME']=$db;$env['APP_BASE_PATH']='/';
    $port=18989;
    $server=proc_open([PHP_BINARY,'-d','variables_order=EGPCS','-d','session.save_path='.dirname(__DIR__).'/storage/temp','-S','127.0.0.1:'.$port,'-t','public'],[0=>['pipe','r'],1=>['file',dirname(__DIR__).'/storage/temp/booking-server.log','a'],2=>['file',dirname(__DIR__).'/storage/temp/booking-server.log','a']],$pipes,dirname(__DIR__),$env);
    usleep(600000);
    $client=curl_init();curl_setopt_array($client,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>15]);
    $http=static function(string $route,?array $post=null,array $query=[])use($client,$port):array {
        curl_setopt($client,CURLOPT_URL,'http://127.0.0.1:'.$port.'/index.php?'.http_build_query(['route'=>$route]+$query));
        curl_setopt($client,CURLOPT_POST,$post!==null);if($post!==null)curl_setopt($client,CURLOPT_POSTFIELDS,http_build_query($post));
        $raw=curl_exec($client);if($raw===false)throw new RuntimeException(curl_error($client));$length=curl_getinfo($client,CURLINFO_HEADER_SIZE);
        return ['status'=>curl_getinfo($client,CURLINFO_RESPONSE_CODE),'headers'=>substr($raw,0,$length),'body'=>substr($raw,$length)];
    };
    $token=static function(array $r):string { if(!preg_match('/name="_token" value="([a-f0-9]{64})"/',$r['body'],$m))throw new RuntimeException('Missing CSRF');return $m[1]; };
    $r=$http('/courts/schedule',null,['id'=>$facility,'date'=>$date]);checkBooking($r['status']===200,'public schedule HTTP');$csrf=$token($r);
    checkBooking(str_contains($r['headers'],'no-store') && str_contains($r['body'],'aria-label="Về danh sách cơ sở"'),'schedule back goes to directory and disables cache');
    $status=static function()use($http,$facility,$date):array{return json_decode($http('/courts/schedule',null,['id'=>$facility,'date'=>$date,'status'=>'1'])['body'],true);};
    $slotState=static function(array $snapshot,int $courtId,string $start):string{foreach($snapshot['courts'] as $row)if($row['id']===$courtId)foreach($snapshot['slots'] as $i=>$slot)if($slot[0]===$start)return $row['states'][$i];return '';};
    $snapshot=$status();checkBooking($snapshot['date']===$date && $slotState($snapshot,$court,'17:00')==='free','public state endpoint returns actual free slots');
    checkBooking(array_keys($snapshot)===['facility','date','now','slots','courts'] && array_keys($snapshot['courts'][0])===['id','states'],'public state response excludes booking identity and payment');
    checkBooking($http('/courts/schedule',null,['id'=>$facility,'date'=>'2020-01-01','status'=>'1'])['status']===422,'state endpoint rejects past day');
    checkBooking($http('/booking/start',['selection'=>json_encode($pick('17:00','17:30'))])['status']===403,'CSRF enforced');
    $priceResponse=$http('/booking/quote',['_token'=>$csrf,'selection'=>json_encode($pick('17:00','17:30'))]);
    checkBooking($priceResponse['status']===200 && json_decode($priceResponse['body'],true)['total']==='50000.00','HTTP price estimate uses backend price');
    $r=$http('/booking/start',['_token'=>$csrf,'selection'=>json_encode($pick('17:00','17:30'))]);
    checkBooking($r['status']===303,'schedule to booking details HTTP');preg_match('/draft=([a-f0-9]{64})/',$r['headers'],$m);$draft=$m[1];
    $r=$http('/booking/details',null,['draft'=>$draft]);checkBooking($r['status']===200 && str_contains($r['body'],'Đăng nhập'),'guest review permitted');
    checkBooking(str_contains($r['body'],'data-booking-back') && str_contains($r['body'],'&amp;date='.$date) && !str_contains($r['body'],'← Chọn lại lịch sân'),'details header back preserves date without duplicate link');
    $r=$http('/booking/review',['_token'=>$csrf,'draft'=>$draft,'services'=>[$serviceId=>1],'note'=>'<script>x</script>','action'=>'login']);
    checkBooking($r['status']===303,'login handoff preserves draft');
    $r=$http('/login',['_token'=>$csrf,'username'=>'booktest1','password'=>'Testpass1!']);
    checkBooking($r['status']===303 && str_contains($r['headers'],'draft='.$draft),'login returns to same draft');
    $r=$http('/booking/details',null,['draft'=>$draft]);$csrf=$token($r);
    checkBooking(str_contains($r['body'],'Customer 1') && str_contains($r['body'],'0901234567'),'trusted customer name and phone');
    checkBooking(str_contains($r['body'],'&lt;script&gt;x&lt;/script&gt;'),'booking note escaped');
    $updated=$http('/booking/review',['_token'=>$csrf,'draft'=>$draft,'services'=>[$serviceId=>2],'note'=>'<script>x</script>']);
    checkBooking($updated['status']===200 && str_contains($updated['body'],'70.000'),'increase service quantity recomputes total');
    $updated=$http('/booking/review',['_token'=>$csrf,'draft'=>$draft,'services'=>[$serviceId=>0],'note'=>'<script>x</script>']);
    checkBooking($updated['status']===200 && str_contains($updated['body'],'50.000'),'zero quantity removes service');
    $r=$http('/booking/review',['_token'=>$csrf,'draft'=>$draft,'services'=>[$serviceId=>1],'note'=>'<script>x</script>']);
    // Save actual rendered markup for offline browser layout checks; only test fixtures.
    file_put_contents(dirname(__DIR__).'/storage/temp/booking-details-test.html',$r['body']);
    preg_match('/name="quote"\s+value="([a-f0-9]{64})"/',$r['body'],$m);$hash=$m[1];
    $post=['_token'=>$csrf,'draft'=>$draft,'services'=>[$serviceId=>1],'note'=>'<script>x</script>','quote'=>$hash];
    $r=$http('/booking/confirm',$post);checkBooking($r['status']===303,'HTTP confirm redirects to waiting');preg_match('/id=([0-9]+)/',$r['headers'],$m);$httpId=$m[1];
    $r=$http('/booking/confirm',$post);checkBooking($r['status']===303 && str_contains($r['headers'],'id='.$httpId),'HTTP retry same booking');
    $r=$http('/booking/wait',null,['id'=>$httpId]);checkBooking($r['status']===200 && str_contains($r['body'],'data-payment-pending') && str_contains($r['body'],'đang được tích hợp'),'real waiting page without payment simulation');
    file_put_contents(dirname(__DIR__).'/storage/temp/booking-wait-test.html',$r['body']);
    // A deterministic image in the isolated payment's exact cache key, never a real bank request.
    $testPayment=$payments->getPaymentByBooking($httpId);
    $qrUrl=(new App\Services\VietQRService())->generateQuickLink('970422','0000000000',$testPayment['so_tien'],$testPayment['ma_tham_chieu'],'TEST ONLY');
    $qrCache=dirname(__DIR__).'/storage/temp/vietqr_'.md5($qrUrl).'.png';
    $chunk=static fn(string $type,string $data): string => pack('N',strlen($data)).$type.$data.pack('N',crc32($type.$data));
    $png="\x89PNG\r\n\x1a\n".$chunk('IHDR',pack('NNCCCCC',200,200,8,2,0,0,0))
        .$chunk('IDAT',gzcompress(str_repeat("\0".str_repeat("\xf0\xff\xf0",200),200))).$chunk('IEND','');
    try {
        file_put_contents($qrCache,$png);
        $download=$http('/payment/qr',null,['booking_id'=>$httpId,'download'=>'1']);
        checkBooking($download['status']===200 && $download['body']===$png && str_contains($download['headers'],'attachment; filename="VietQR_DonHang_'.$httpId.'.png"'),'QR download returns exact validated image and attachment filename');
        $svg='<svg xmlns="http://www.w3.org/2000/svg" width="540" height="640"><rect width="540" height="640" fill="white"/><path d="M10 10h20v20H10z"/></svg>';
        file_put_contents($qrCache,$svg);
        $display=$http('/payment/qr',null,['booking_id'=>$httpId]);
        $download=$http('/payment/qr',null,['booking_id'=>$httpId,'download'=>'1']);
        checkBooking($download['status']===200 && $download['body']===$display['body'] && $download['body']===$svg
            && str_contains($download['headers'],'Content-Type: image/svg+xml')
            && str_contains($download['headers'],'.svg"'),'SVG QuickLink download returns same image as display without 503');
        file_put_contents($qrCache,str_repeat('<html>upstream error</html>',20));
        $download=$http('/payment/qr',null,['booking_id'=>$httpId,'download'=>'1']);
        checkBooking($download['status']===503 && !str_contains($download['headers'],'attachment;'),'QR download rejects HTML instead of saving corrupt PNG');
        $hold=$pdo->query('SELECT bat_dau_giu_san_luc,het_han_giu_san_luc FROM dat_san WHERE ma_dat_san='.(int)$httpId)->fetch();
        $pdo->exec('UPDATE dat_san SET bat_dau_giu_san_luc=DATE_SUB(NOW(),INTERVAL 7 MINUTE),het_han_giu_san_luc=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE ma_dat_san='.(int)$httpId);
        try {
            checkBooking($http('/payment/qr',null,['booking_id'=>$httpId,'download'=>'1'])['status']===409,'QR download rejects expired booking');
        } finally {
            $pdo->prepare('UPDATE dat_san SET bat_dau_giu_san_luc=?,het_han_giu_san_luc=? WHERE ma_dat_san=?')->execute([$hold['bat_dau_giu_san_luc'],$hold['het_han_giu_san_luc'],$httpId]);
        }
    } finally { unlink($qrCache); }
    checkBooking(str_contains($r['body'],'Court A') && preg_match('/17:00\s*–\s*17:30/u',$r['body']) && !str_contains($r['body'],'Quay lại lịch sân'),'waiting shows actual visit and only header back');
    checkBooking($slotState($status(),$court,'17:00')==='held','other viewer sees new hold through endpoint');
    checkBooking((int)$pdo->query("SELECT COUNT(*) FROM thanh_toan WHERE ma_dat_san=$httpId")->fetchColumn()===1 && (int)$pdo->query('SELECT COUNT(*) FROM hoa_don')->fetchColumn()===0,'checkout creates one payment and no invoice');
    checkBooking($http('/booking/payment',['id'=>$httpId])['status']===403,'payment retry requires CSRF');
    checkBooking($http('/booking/payment',null,['id'=>$httpId])['status']===405,'GET cannot create payment');
    checkBooking($http('/booking/payment',['_token'=>$csrf,'id'=>$httpId])['status']===303,'owned POST payment retry succeeds');
    checkBooking((int)$pdo->query("SELECT COUNT(*) FROM thanh_toan WHERE ma_dat_san=$httpId")->fetchColumn()===1,'HTTP retry does not duplicate payment');
    $redirect=$http('/payment',null,['booking_id'=>$httpId]);
    checkBooking($redirect['status']===303 && str_contains($redirect['headers'],'booking%2Fwait'),'legacy payment route uses CSP-compatible waiting page');
    $waitOne=json_decode($http('/booking/wait',null,['id'=>$httpId,'status'=>'1'])['body'],true);
    $waitTwo=json_decode($http('/booking/wait',null,['id'=>$httpId,'status'=>'1'])['body'],true);
    checkBooking($waitOne['expires']===$waitTwo['expires'],'HTTP refresh does not reset deadline');
    $pdo->exec("UPDATE thanh_toan SET trang_thai='THANH_CONG',ma_giao_dich='TEST-HTTP',ngay_thanh_toan=NOW(),so_tien_thuc_nhan=so_tien WHERE ma_dat_san=$httpId");
    $pstatus=json_decode($http('/payment/status',null,['booking_id'=>$httpId])['body'],true);
    checkBooking($pstatus['is_confirmed']===false && $pstatus['booking_status']==='CHO_THANH_TOAN','payment success alone does not claim booking confirmation');
    $recorded=$http('/booking/wait',null,['id'=>$httpId]);
    checkBooking(!str_contains($recorded['body'],'data-open-qr') && str_contains($recorded['body'],'Không chuyển khoản thêm'),'recorded payment does not offer another QR while awaiting booking confirmation');
    $pdo->exec("UPDATE thanh_toan SET trang_thai='CHO_THANH_TOAN',ma_giao_dich=NULL,ngay_thanh_toan=NULL,so_tien_thuc_nhan=NULL WHERE ma_dat_san=$httpId");
    checkBooking($http('/booking/cancel',['id'=>$httpId])['status']===403,'cancellation requires CSRF');
    checkBooking($http('/booking/cancel',null,['id'=>$httpId])['status']===405,'GET cannot cancel');
    $r=$http('/booking/cancel',['_token'=>$csrf,'id'=>$httpId]);
    checkBooking($r['status']===303 && str_contains($r['headers'],'date='.$date) && str_contains($r['headers'],'id='.$facility),'header POST cancels and redirects to correct schedule');
    checkBooking($slotState($status(),$court,'17:00')==='free','other viewer sees cancellation without reloading HTML');
    checkBooking($http('/booking/cancel',['_token'=>$csrf,'id'=>$httpId])['status']===303,'repeated HTTP cancellation succeeds');
    checkBooking(json_decode($http('/booking/wait',null,['id'=>$httpId,'status'=>'1'])['body'],true)['state']==='DA_HUY','revisit cancelled URL does not restore hold');
    checkBooking($pdo->query("SELECT trang_thai FROM thanh_toan WHERE ma_dat_san=$httpId")->fetchColumn()==='HUY','HTTP cancel synchronizes payment');
    // Simulate a temporarily unavailable dependency in the test DB only.
    $r=$http('/booking/start',['_token'=>$csrf,'selection'=>json_encode($pick('22:00','22:30'))]);
    preg_match('/draft=([a-f0-9]{64})/',$r['headers'],$m);$retryDraft=$m[1];
    $r=$http('/booking/details',null,['draft'=>$retryDraft]);
    preg_match('/name="quote"\s+value="([a-f0-9]{64})"/',$r['body'],$m);$retryHash=$m[1];
    $pdo->exec('RENAME TABLE tai_khoan_ngan_hang_co_so TO test_bank_unavailable');
    $r=$http('/booking/confirm',['_token'=>$csrf,'draft'=>$retryDraft,'quote'=>$retryHash,'note'=>'']);
    checkBooking($r['status']===303,'payment failure still redirects to recoverable booking');
    preg_match('/id=([0-9]+)/',$r['headers'],$m);$retryId=(int)$m[1];
    $before=$pdo->query("SELECT het_han_giu_san_luc FROM dat_san WHERE ma_dat_san=$retryId")->fetchColumn();
    $r=$http('/booking/wait',null,['id'=>$retryId]);
    checkBooking($r['status']===200 && str_contains($r['body'],'booking-payment-retry') && !str_contains($r['body'],'SQLSTATE'),'technical failure has safe retry without SQL disclosure');
    $pdo->exec('RENAME TABLE test_bank_unavailable TO tai_khoan_ngan_hang_co_so');
    $http('/booking/wait',null,['id'=>$retryId]);
    $http('/payment/status',null,['booking_id'=>$retryId]);
    checkBooking((int)$pdo->query("SELECT COUNT(*) FROM thanh_toan WHERE ma_dat_san=$retryId")->fetchColumn()===0,'GET wait/status never creates missing payment');
    checkBooking($http('/booking/payment',['_token'=>$csrf,'id'=>$retryId])['status']===303,'POST retry recovers after dependency is restored');
    checkBooking((int)$pdo->query("SELECT COUNT(*) FROM thanh_toan WHERE ma_dat_san=$retryId")->fetchColumn()===1
        && $pdo->query("SELECT het_han_giu_san_luc FROM dat_san WHERE ma_dat_san=$retryId")->fetchColumn()===$before,'recovered payment preserves booking and original expiry');
    // Real multipart upload through auth + CSRF + controller + image validation.
    checkBooking($http('/booking/receipt',['id'=>$retryId])['status']===403,'receipt submission requires CSRF');
    checkBooking($http('/booking/receipt',null,['id'=>$retryId])['status']===405,'receipt submission cannot use GET');
    $uploadPath=tempnam(dirname(__DIR__).'/storage/temp','receipt-http-');
    $multipart=static function(array $data)use($client,$port):int {
        curl_setopt($client,CURLOPT_URL,'http://127.0.0.1:'.$port.'/index.php?route=%2Fbooking%2Freceipt');
        curl_setopt($client,CURLOPT_POST,true);curl_setopt($client,CURLOPT_POSTFIELDS,$data);
        if(curl_exec($client)===false)throw new RuntimeException(curl_error($client));
        return curl_getinfo($client,CURLINFO_RESPONSE_CODE);
    };
    try {
        file_put_contents($uploadPath,'not an image');
        $multipart(['_token'=>$csrf,'id'=>$retryId,'bien_lai'=>new CURLFile($uploadPath,'image/png','proof.png')]);
        checkBooking($booking->inspect($retryId,$customers[0])['trang_thai_dat_san']==='CHO_THANH_TOAN','invalid image does not change booking');
        file_put_contents($uploadPath,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        checkBooking($multipart(['_token'=>$csrf,'id'=>$retryId,'bien_lai'=>new CURLFile($uploadPath,'image/png','proof.png')])===303,'valid receipt redirects immediately to result');
        $receiptFiles[]=App\Services\ReceiptImage::getReceiptFromNote($payments->getPaymentByBooking($retryId)['ghi_chu']);
        $result=$http('/booking/wait',null,['id'=>$retryId]);
        checkBooking($result['status']===200 && preg_match('/<section[^>]*data-payment-result(?![^>]*\bhidden\b)[^>]*>/',$result['body']) && !str_contains($result['body'],'data-receipt-waiting'),'receipt result replaces intermediate screen');
        file_put_contents(dirname(__DIR__).'/storage/temp/receipt-result-test.html',$result['body']);
        $firstReceipt=$payments->getPaymentByBooking($retryId)['ghi_chu'];
        $multipart(['_token'=>$csrf,'id'=>$retryId,'bien_lai'=>new CURLFile($uploadPath,'image/png','proof.png')]);
        checkBooking($payments->getPaymentByBooking($retryId)['ghi_chu']===$firstReceipt,'duplicate HTTP upload cannot overwrite accepted receipt');
        $model=new App\Models\Booking($pdo);
        $pdo->exec("UPDATE dat_san SET bat_dau_giu_san_luc=NOW()-INTERVAL 3 DAY,het_han_giu_san_luc=NOW()-INTERVAL 3 DAY+INTERVAL 6 MINUTE WHERE ma_dat_san=$retryId");
        checkBooking($http('/booking/expire',['_token'=>$csrf,'id'=>$retryId])['status']===303 && $model->find($retryId)['trang_thai_dat_san']==='CHO_XAC_NHAN','expiry endpoint preserves receipt review after days');
        $reviewStatus=json_decode($http('/payment/status',null,['booking_id'=>$retryId])['body'],true);
        checkBooking(!$reviewStatus['is_expired'] && !$reviewStatus['is_confirmed'] && $reviewStatus['booking_status']==='CHO_XAC_NHAN','payment status endpoint neither expires nor confirms receipt review');
        $history=$http('/booking/history');
        checkBooking($history['status']===200 && str_contains($history['body'],'Chờ xác nhận'),'existing history renders receipt-review state');
        checkBooking(str_contains($history['body'],'booking%2Fhistory%2Freceipt'),'history provides protected receipt URL');
        $proof=$http('/booking/history/receipt',null,['id'=>$retryId]);
        checkBooking($proof['status']===200 && str_contains($proof['headers'],'image/png') && str_starts_with($proof['body'],"\x89PNG"),'owner can read uploaded receipt through history endpoint');
        // Regression: old inconsistent expired receipt records must never revive on GET.
        $pdo->exec("UPDATE dat_san SET trang_thai_dat_san='HET_HAN' WHERE ma_dat_san=$retryId");
        $http('/booking/wait',null,['id'=>$retryId]);
        $statusResult=json_decode($http('/booking/wait',null,['id'=>$retryId,'status'=>'1'])['body'],true);
        checkBooking($model->find($retryId)['trang_thai_dat_san']==='HET_HAN' && $statusResult['state']==='HET_HAN','GET and polling do not revive expired receipt booking');
    } finally { unlink($uploadPath); }
    $http('/logout',['_token'=>$csrf]);
    $r=$http('/courts/schedule',null,['id'=>$facility,'date'=>$date]);$csrf=$token($r);
    $r=$http('/booking/start',['_token'=>$csrf,'selection'=>json_encode($pick('19:00','19:30'))]);preg_match('/draft=([a-f0-9]{64})/',$r['headers'],$m);$signupDraft=$m[1];
    $http('/booking/review',['_token'=>$csrf,'draft'=>$signupDraft,'action'=>'register','note'=>'signup']);
    $username='bk'.bin2hex(random_bytes(5));
    $r=$http('/register',['_token'=>$csrf,'username'=>$username,'name'=>'Signup test','phone'=>'0901234567','email'=>'','password'=>'Testpass1!','password_confirmation'=>'Testpass1!']);
    checkBooking($r['status']===303,'registration uses existing auth');
    $r=$http('/login',['_token'=>$csrf,'username'=>$username,'password'=>'Testpass1!']);
    checkBooking($r['status']===303 && str_contains($r['headers'],'draft='.$signupDraft),'register then login returns to original draft');
    checkBooking($http('/booking/wait',null,['id'=>$httpId])['status']===404,'HTTP ownership prevents another customer viewing booking');
    checkBooking($http('/payment/qr',null,['booking_id'=>$httpId,'download'=>'1'])['status']===404,'QR download rejects other customer');
    checkBooking($http('/booking/history/receipt',null,['id'=>$retryId])['status']===404,'other customer cannot read history receipt');
    $r=$http('/booking/details',null,['draft'=>$signupDraft]);
    checkBooking($http('/booking/cancel',['_token'=>$token($r),'id'=>$httpId])['status']===404,'HTTP ownership prevents cancellation by another customer');
    checkBooking($http('/booking/payment',['_token'=>$token($r),'id'=>$httpId])['status']===404,'HTTP ownership prevents payment retry by another customer');
    if (in_array('--browser',$argv,true)) {
        $browser=proc_open(['node',__DIR__.'/booking-browser.cjs',json_encode(['port'=>$port,'facility'=>$facility,'court'=>$court,'date'=>$date,'db'=>$db])], [0=>['pipe','r'],1=>STDOUT,2=>STDERR], $browserPipes,dirname(__DIR__));
        fclose($browserPipes[0]);
        if(proc_close($browser)!==0) throw new RuntimeException('Browser checks failed');
    }
    echo "PASS: $checks isolated booking integration checks.\n";
} finally {
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    foreach($receiptFiles as $file)if($file)App\Services\ReceiptImage::remove($file);
    $admin->exec("DROP DATABASE `$db`");
}
