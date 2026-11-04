<?php
// Explicit opt-in; never writes to the configured database.
if (PHP_SAPI !== 'cli' || !in_array($argv[1] ?? '', ['--run','--worker'], true)) exit("php tests/receipt-integration.php --run\n");
require dirname(__DIR__).'/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
$config = require dirname(__DIR__).'/config/database.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');
function connection(?string $db = null): PDO {
    global $config;
    if (!in_array($config['host'], ['localhost','127.0.0.1','::1'], true)
        || ($db !== null && (!preg_match('/\Astryke_receipt_test_[a-f0-9]{10}\z/', $db) || $db === $config['dbname']))) throw new RuntimeException('Unsafe test database');
    $p = new PDO("mysql:host={$config['host']};port={$config['port']};".($db ? "dbname=$db;" : '').'charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $p->exec("SET time_zone='+07:00'"); return $p;
}
function executeAction(PDO $pdo, array $call): array {
    $booking = new App\Services\BookingService($pdo);
    try {
        $result = match ($call[0]) {
            'receipt' => $booking->acceptReceipt(...$call[1]),
            'approve' => (new App\Services\BookingManagementService($pdo))->confirmBookingAndPayment(...$call[1]),
            'checkout' => $booking->confirm(...$call[1]),
            'expire' => (new App\Models\Booking($pdo))->expire($call[1][0]),
        };
        return ['ok'=>true,'result'=>$result];
    } catch (Core\HttpException|App\Services\ValidationException $e) { return ['ok'=>false,'error'=>$e->getMessage()]; }
}
if ($argv[1] === '--worker') { echo json_encode(executeAction(connection($argv[2]), json_decode(base64_decode($argv[3]), true, 512, JSON_THROW_ON_ERROR))); exit; }
$checks=0;
function checkReceipt(bool $ok, string $label): void { global $checks; if (!$ok) throw new RuntimeException('FAIL: '.$label); $checks++; echo "PASS: $label\n"; }
function insertFixture(string $table, array $row): int { global $pdo; $pdo->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row)); return (int)$pdo->lastInsertId(); }
function race(array $calls, ?int $bookingLock = null, int $delay = 350000): array {
    global $pdo,$company,$db;
    $pdo->beginTransaction();
    $pdo->query($bookingLock ? "SELECT ma_dat_san FROM dat_san WHERE ma_dat_san=$bookingLock FOR UPDATE" : "SELECT ma_cong_ty FROM cong_ty_nhuong_quyen WHERE ma_cong_ty=$company FOR UPDATE")->fetch();
    $jobs=[];
    try {
        foreach ($calls as $call) {
            $proc=proc_open([PHP_BINARY,__FILE__,'--worker',$db,base64_encode(json_encode($call))],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
            if (!is_resource($proc)) throw new RuntimeException('Worker unavailable');
            fclose($pipes[0]); $jobs[]=[$proc,$pipes];
        }
        usleep($delay); $pdo->commit(); $results=[];
        foreach ($jobs as [$proc,$pipes]) {
            $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
            if (proc_close($proc)!==0) throw new RuntimeException($err);
            $results[]=json_decode($out,true,512,JSON_THROW_ON_ERROR);
        }
        return $results;
    } finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
}
$root=dirname(__DIR__); $db='stryke_receipt_test_'.bin2hex(random_bytes(5)); $admin=connection(); $files=[];
$admin->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    $pdo=connection($db);
    $sql=preg_replace('/CREATE DATABASE.*?;|USE quanly_chuoi_san_the_thao;/s','',file_get_contents($root.'/database/quanly_chuoi_san_the_thao.sql'));
    $pdo->exec($sql);
    $pdo->exec(file_get_contents($root.'/database/migrations/20261101_tai_khoan_ngan_hang_co_so.sql'));
    $pdo->exec(file_get_contents($root.'/database/migrations/20261102_hoa_don_fields.sql'));
    $company=insertFixture('cong_ty_nhuong_quyen',['ten_cong_ty'=>'Receipt test','dia_chi'=>'Test']);
    $facility=insertFixture('co_so',['ma_cong_ty'=>$company,'ten_co_so'=>'Receipt facility','dia_chi'=>'Test','gio_mo_cua'=>'05:00','gio_dong_cua'=>'23:00']);
    $type=insertFixture('loai_san',['ma_bo_mon'=>1,'ten_loai_san'=>'Test']);
    $court=insertFixture('san',['ma_co_so'=>$facility,'ma_loai_san'=>$type,'ten_san'=>'Receipt court']);
    $customer=insertFixture('khach_hang',['ho_ten'=>'Test customer','so_dien_thoai'=>'0901234567','ma_tai_khoan'=>(new App\Services\AccountService($pdo))->create('receipttest','Testpass1!','KHACH_HANG')]);
    $customer2=insertFixture('khach_hang',['ho_ten'=>'Second test customer','so_dien_thoai'=>'0901234568','ma_tai_khoan'=>(new App\Services\AccountService($pdo))->create('receipttest2','Testpass1!','KHACH_HANG')]);
    $date=date('Y-m-d',strtotime('+2 days')); $day=(int)date('N',strtotime($date))>=6?'CUOI_TUAN':'NGAY_THUONG';
    insertFixture('bang_gia',['ma_co_so'=>$facility,'ma_loai_san'=>$type,'ten_bang_gia'=>'Test','loai_ngay'=>$day,'gio_bat_dau'=>'05:00','gio_ket_thuc'=>'23:00','don_gia'=>'100000','ngay_bat_dau'=>$date]);
    $svc=insertFixture('dich_vu',['ma_co_so'=>$facility,'ten_dich_vu'=>'Water','loai_dich_vu'=>'DO_UONG','don_vi_tinh'=>'bottle','don_gia'=>'10000','so_luong_ton'=>4]);
    $booking=new App\Services\BookingService($pdo); $model=new App\Models\Booking($pdo); $management=new App\Services\BookingManagementService($pdo); $payments=new App\Services\PaymentService($pdo);
    $user=['ten_vai_tro'=>'QUAN_TRI_TAP_DOAN','trang_thai'=>'HOAT_DONG'];
    $pick=fn($hour)=>['facility'=>$facility,'date'=>$date,'slots'=>[['court'=>$court,'start'=>sprintf('%02d:00',$hour),'end'=>sprintf('%02d:30',$hour)]]];
    $extras=['services'=>[$svc=>2],'note'=>'']; $none=['services'=>[],'note'=>''];
    $create=function($hour,$withService=true)use($pick,$extras,$none,$booking,$payments,$customer){$input=$pick($hour);$ex=$withService?$extras:$none;$q=$booking->quote($input,$ex);$id=$booking->confirm($customer,bin2hex(random_bytes(32)),$input,$ex,$q['fingerprint']);$payments->createPaymentRequest($id,$q['total']);return $id;};
    $image=function($id)use(&$files,$root){$path='uploads/receipts/receipt_'.$id.'_'.bin2hex(random_bytes(8)).'.png'; if(!is_dir($root.'/public/uploads/receipts'))mkdir($root.'/public/uploads/receipts',0755,true);file_put_contents($root.'/public/'.$path,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));$files[]=$path;return $path;};
    $id=$create(6); $path=$image($id); $original=$model->find($id);
    checkReceipt(!executeAction($pdo,['receipt',[$id,$customer+999,$path]])['ok'],'receipt ownership enforced');
    $results=race([['receipt',[$id,$customer,$path]],['receipt',[$id,$customer,$image($id)]]]);
    checkReceipt(count(array_filter($results,fn($r)=>$r['ok']))===1,'simultaneous duplicate receipts: exactly one committed');
    checkReceipt($model->find($id)['trang_thai_dat_san']==='CHO_XAC_NHAN' && $payments->getPaymentByBooking($id)['trang_thai']==='CHO_THANH_TOAN','receipt does not mark payment successful');
    checkReceipt($model->find($id)['het_han_giu_san_luc']===$original['het_han_giu_san_luc'],'receipt never extends deadline');
    $pdo->exec("UPDATE dat_san SET bat_dau_giu_san_luc=NOW()-INTERVAL 3 DAY,het_han_giu_san_luc=NOW()-INTERVAL 3 DAY+INTERVAL 6 MINUTE WHERE ma_dat_san=$id");
    $deadline=$model->find($id)['het_han_giu_san_luc'];
    checkReceipt($booking->expirePending()===0 && $model->reserved($svc,date('Y-m-d H:i:s'))===2,'three-day review survives expiry and retains inventory');
    checkReceipt((int)$pdo->query("SELECT so_luong_ton FROM dich_vu WHERE ma_dich_vu=$svc")->fetchColumn()===4,'receipt did not deduct physical stock');
    checkReceipt(!executeAction($pdo,['checkout',[$customer,bin2hex(random_bytes(32)),$pick(6),$none,'']])['ok'],'awaiting review blocks another checkout');
    $browser=new App\Models\CourtBrowser($pdo);$facilityRow=$browser->facilities(['ma_co_so'=>(string)$facility])[0];
    $grid=App\Services\CourtScheduleService::build($facilityRow,$browser->courts($facility),$browser->intervals($facility,$date),$date,App\Services\CourtScheduleService::now());
    $cell=array_values(array_filter($grid['rows'][0]['cells'],fn($c)=>$c['start']==='06:00:00'))[0];
    checkReceipt($cell['state']==='held','public schedule marks awaiting receipt review held');
    $management->markNeedsReconciliation($id,'60000','REVIEW-TEST','Check transfer',$user);
    checkReceipt($booking->expirePending()===0 && $model->reserved($svc,date('Y-m-d H:i:s'))===2,'reconciliation keeps review reservations');
    $args=[$id,'RECEIPT-APPROVED','70000',null,null,$user];
    checkReceipt(!executeAction($pdo,['approve',[$id,'WRONG-AMOUNT','60000',null,null,$user]])['ok'],'wrong amount rejected without changing review');
    $pdo->exec('RENAME TABLE hoa_don TO receipt_test_invoice_unavailable');
    try { $management->confirmBookingAndPayment(...$args); throw new RuntimeException('Missing invoice dependency ignored'); }
    catch(PDOException $e) { checkReceipt($model->find($id)['trang_thai_dat_san']==='CHO_XAC_NHAN' && $payments->getPaymentByBooking($id)['trang_thai']==='CAN_DOI_SOAT'
        && (int)$pdo->query("SELECT so_luong_ton FROM dich_vu WHERE ma_dich_vu=$svc")->fetchColumn()===4,'invoice failure rolls back booking, payment and stock'); }
    finally { $pdo->exec('RENAME TABLE receipt_test_invoice_unavailable TO hoa_don'); }
    $results=race([['approve',$args],['approve',$args]]);
    checkReceipt(count(array_filter($results,fn($r)=>$r['ok']))===1,'concurrent admin approval commits exactly once');
    checkReceipt($model->find($id)['trang_thai_dat_san']==='DA_XAC_NHAN' && $payments->getPaymentByBooking($id)['trang_thai']==='THANH_CONG','verified approval confirms both booking and payment');
    checkReceipt($model->find($id)['het_han_giu_san_luc']===$deadline,'approval does not rewrite old deadline');
    checkReceipt((int)$pdo->query("SELECT COUNT(*) FROM hoa_don WHERE ma_dat_san=$id")->fetchColumn()===1 && (int)$pdo->query("SELECT so_luong_ton FROM dich_vu WHERE ma_dich_vu=$svc")->fetchColumn()===2,'one invoice and one stock deduction');
    try{$management->cancelBookingByAdmin($id,'Reject approved payment',$user);throw new RuntimeException('Confirmed cancel accepted');}catch(App\Services\ValidationException $e){checkReceipt(true,'cannot erase confirmed payment through rejection');}
    $cancel=$create(7);$booking->acceptReceipt($cancel,$customer,$image($cancel));$management->cancelBookingByAdmin($cancel,'Invalid proof',$user);
    checkReceipt($model->find($cancel)['trang_thai_dat_san']==='DA_HUY' && $model->reserved($svc,date('Y-m-d H:i:s'))===0,'reject receipt releases reservation without restocking twice');
    $expired=$create(8);$lateImage=$image($expired);$at=$model->find($expired)['het_han_giu_san_luc'];
    $pdo->exec('SET timestamp='.strtotime($at));
    checkReceipt(!executeAction($pdo,['receipt',[$expired,$customer,$lateImage]])['ok'],'receipt exactly at deadline rejected');
    $pdo->exec('SET timestamp=0');
    $pdo->exec("UPDATE dat_san SET bat_dau_giu_san_luc=NOW()-INTERVAL 7 MINUTE,het_han_giu_san_luc=NOW()-INTERVAL 1 MINUTE WHERE ma_dat_san=$expired");
    $results=race([['receipt',[$expired,$customer,$lateImage]],['expire',[$expired]]],$expired);
    checkReceipt(!$results[0]['ok'] && $model->find($expired)['trang_thai_dat_san']==='HET_HAN','expiry versus late upload cannot revive booking');
    checkReceipt(!executeAction($pdo,['approve',[$expired,'LATE-PAYMENT','70000',null,null,$user]])['ok'],'late admin approval rejected');
    $newPick=$pick(8);$q=$booking->quote($newPick);
    $results=race([['receipt',[$expired,$customer,$lateImage]],['checkout',[$customer2,bin2hex(random_bytes(32)),$newPick,$none,$q['fingerprint']]]]);
    checkReceipt(!$results[0]['ok'] && $results[1]['ok'],'expired receipt versus new customer: only new checkout can occupy court');
    $edge=$create(9,false);$edgeImage=$image($edge);
    $pdo->exec("UPDATE dat_san SET bat_dau_giu_san_luc=NOW()-INTERVAL 358 SECOND,het_han_giu_san_luc=NOW()+INTERVAL 2 SECOND WHERE ma_dat_san=$edge");
    $results=race([['receipt',[$edge,$customer,$edgeImage]]],$edge,2500000);
    checkReceipt(!$results[0]['ok'],'upload starting before deadline rechecks after lock wait');
    checkReceipt(App\Services\ReceiptImage::getReceiptFromNote($payments->getPaymentByBooking($edge)['ghi_chu'])===null,'failed upload leaves no committed receipt tag');
    $booking->expirePending();
    $review=$create(10,false);$booking->acceptReceipt($review,$customer,$image($review));
    // Legacy conflicting records must be rejected instead of silently double-confirming.
    $other=insertFixture('dat_san',['ma_co_so'=>$facility,'ma_khach_hang'=>$customer,'trang_thai_dat_san'=>'DA_XAC_NHAN','bat_dau_giu_san_luc'=>date('Y-m-d H:i:s'),'het_han_giu_san_luc'=>date('Y-m-d H:i:s',time()+360)]);
    insertFixture('chi_tiet_dat_san',['ma_dat_san'=>$other,'ma_san'=>$court,'ngay_choi'=>$date,'gio_bat_dau'=>'10:00','gio_ket_thuc'=>'10:30','don_gia'=>100000,'thanh_tien'=>50000]);
    checkReceipt(!executeAction($pdo,['approve',[$review,'LEGACY-CONFLICT','50000',null,null,$user]])['ok'],'legacy overlapping order cannot be approved');
    echo "$checks receipt integration checks passed in disposable database.\n";
} finally {
    if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
    foreach($files as $file) App\Services\ReceiptImage::remove($file);
    $admin->exec("DROP DATABASE `$db`");
}
