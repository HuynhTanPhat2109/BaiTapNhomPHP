<?php
// Explicit opt-in; every database write is confined to a random disposable test DB.
if (PHP_SAPI !== 'cli' || !in_array($argv[1] ?? '', ['--run','--expire-worker','--upload-worker'], true)) exit("php tests/receipt-hold-http.php --run [--browser]\n");
require dirname(__DIR__).'/vendor/autoload.php';$root=dirname(__DIR__);Dotenv\Dotenv::createImmutable($root)->load();$c=require $root.'/config/database.php';if(!in_array($c['host'],['localhost','127.0.0.1'],true))throw new RuntimeException('Local only');date_default_timezone_set('Asia/Ho_Chi_Minh');
if ($argv[1] === '--expire-worker') {
    $target=$argv[2]??'';
    if (!preg_match('/\\Astryke_receipt_http_[a-f0-9]{10}\\z/',$target) || $target===$c['dbname'] || !ctype_digit($argv[3]??'')) throw new RuntimeException('Unsafe worker DB');
    $p=new PDO("mysql:host={$c['host']};port={$c['port']};dbname=$target;charset=utf8mb4",$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $p->exec("SET time_zone='+07:00'");$m=new App\Models\Booking($p);$count=$m->expire((int)$argv[3]);
    echo json_encode(['count'=>$count,'state'=>$m->find((int)$argv[3])['trang_thai_dat_san']]);exit;
}
if ($argv[1] === '--upload-worker') {
    $args=json_decode(base64_decode($argv[2]),true,512,JSON_THROW_ON_ERROR);
    $ch=curl_init('http://127.0.0.1:18993/index.php?route=%2Fbooking%2Freceipt');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>['_token'=>$args['token'],'id'=>$args['id'],'bien_lai'=>new CURLFile($root.'/storage/temp/result-proof.png','image/png','proof.png')],CURLOPT_COOKIE=>$args['cookie'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20]);
    curl_exec($ch);echo json_encode(['status'=>curl_getinfo($ch,CURLINFO_RESPONSE_CODE)]);exit;
}
$db='stryke_receipt_http_'.bin2hex(random_bytes(5));$pdo=new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4",$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$server=null;$paths=[];$checks=0;
function ck($v,$label){global $checks;if(!$v)throw new RuntimeException('FAIL '.$label);$checks++;echo "PASS $label\n";}
function putRow($t,$r){global $pdo;$pdo->prepare('INSERT INTO '.$t.' ('.implode(',',array_keys($r)).') VALUES ('.implode(',',array_fill(0,count($r),'?')).')')->execute(array_values($r));return (int)$pdo->lastInsertId();}
try{$pdo->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$pdo->exec("USE `$db`");$pdo->exec("SET time_zone='+07:00'");$pdo->exec(preg_replace('/CREATE DATABASE.*?;|USE quanly_chuoi_san_the_thao;/s','',file_get_contents($root.'/database/quanly_chuoi_san_the_thao.sql')));
$pdo->exec(file_get_contents($root.'/database/migrations/20261101_tai_khoan_ngan_hang_co_so.sql'));$pdo->exec(file_get_contents($root.'/database/migrations/20261102_hoa_don_fields.sql'));
$account=(new App\Services\AccountService($pdo))->create('resulttest','Testpass1!','KHACH_HANG');$customer=putRow('khach_hang',['ma_tai_khoan'=>$account,'ho_ten'=>'Result test','so_dien_thoai'=>'0901234567']);$company=putRow('cong_ty_nhuong_quyen',['ten_cong_ty'=>'Test only','dia_chi'=>'Test']);$facility=putRow('co_so',['ma_cong_ty'=>$company,'ten_co_so'=>'Test only','dia_chi'=>'Test','gio_mo_cua'=>'05:00','gio_dong_cua'=>'23:00']);
putRow('tai_khoan_ngan_hang_co_so',['ma_co_so'=>$facility,'ten_ngan_hang'=>'TEST ONLY','ma_bin'=>'970422','so_tai_khoan'=>'0000000000','ten_chu_tai_khoan'=>'TEST ONLY']);$type=putRow('loai_san',['ma_bo_mon'=>1,'ten_loai_san'=>'Test']);$court=putRow('san',['ma_co_so'=>$facility,'ma_loai_san'=>$type,'ten_san'=>'Test']);
$svc=putRow('dich_vu',['ma_co_so'=>$facility,'ten_dich_vu'=>'Water','loai_dich_vu'=>'DO_UONG','don_vi_tinh'=>'bottle','don_gia'=>10000,'so_luong_ton'=>5]);
$make=function()use($facility,$customer,$court,$svc){$id=putRow('dat_san',['ma_co_so'=>$facility,'ma_khach_hang'=>$customer,'trang_thai_dat_san'=>'CHO_THANH_TOAN','bat_dau_giu_san_luc'=>date('Y-m-d H:i:s'),'het_han_giu_san_luc'=>date('Y-m-d H:i:s',time()+360),'tong_tien_san'=>40000,'tong_tien_dich_vu'=>10000,'tong_tien_tam_tinh'=>50000]);putRow('thanh_toan',['ma_dat_san'=>$id,'ma_tham_chieu'=>'TESTRESULT'.$id,'so_tien'=>50000]);putRow('chi_tiet_dat_san',['ma_dat_san'=>$id,'ma_san'=>$court,'ten_san_snapshot'=>'Test','ngay_choi'=>date('Y-m-d',time()+86400),'gio_bat_dau'=>sprintf('%02d:00',16+$id),'gio_ket_thuc'=>sprintf('%02d:00',17+$id),'don_gia'=>40000,'thanh_tien'=>40000]);putRow('chi_tiet_dich_vu_dat',['ma_dat_san'=>$id,'ma_dich_vu'=>$svc,'ten_dich_vu_snapshot'=>'Water','don_vi_tinh_snapshot'=>'bottle','so_luong'=>1,'don_gia'=>10000,'thanh_tien'=>10000]);return $id;};$id=$make();$browserId=$make();
$env=getenv();foreach($_ENV as $k=>$v)$env[$k]=$v;$env['DB_NAME']=$db;$env['APP_BASE_PATH']='/';$env['RESULT_TEST_ID']=(string)$browserId;
$server=proc_open([PHP_BINARY,'-d','variables_order=EGPCS','-d','upload_max_filesize=8M','-d','post_max_size=10M','-d','session.save_path='.$root.'/storage/temp','-S','127.0.0.1:18993','-t','public'],[0=>['pipe','r'],1=>['file',$root.'/storage/temp/result-server.log','a'],2=>['file',$root.'/storage/temp/result-server.log','a']],$pipes,$root,$env);usleep(600000);
$client=curl_init();curl_setopt_array($client,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>20]);$http=function($route,$post=null,$query=[])use($client){curl_setopt($client,CURLOPT_URL,'http://127.0.0.1:18993/index.php?'.http_build_query(['route'=>$route]+$query));curl_setopt($client,CURLOPT_POST,$post!==null);if($post!==null)curl_setopt($client,CURLOPT_POSTFIELDS,$post);$r=curl_exec($client);if($r===false)throw new RuntimeException(curl_error($client));$n=curl_getinfo($client,CURLINFO_HEADER_SIZE);return ['status'=>curl_getinfo($client,CURLINFO_RESPONSE_CODE),'head'=>substr($r,0,$n),'body'=>substr($r,$n)];};
$r=$http('/login');preg_match('/name="_token" value="([^"]+)"/',$r['body'],$m);$token=$m[1];ck($http('/login',['_token'=>$token,'username'=>'resulttest','password'=>'Testpass1!'])['status']===303,'actual login');$r=$http('/booking/wait',null,['id'=>$id]);preg_match('/name="_token" value="([^"]+)"/',$r['body'],$m);$token=$m[1];ck(str_contains($r['body'],'form-submit-receipt'),'pending upload form exists');
$upload=$root.'/storage/temp/result-proof.png';file_put_contents($upload,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));$bad=$root.'/storage/temp/result-bad.png';file_put_contents($bad,'not an image');$large=$root.'/storage/temp/result-large.png';file_put_contents($large,str_repeat('x',5*1024*1024+1));
$send=function($file)use($http,$id,$token){$post=['id'=>$id,'_token'=>$token];if($file)$post['bien_lai']=new CURLFile($file,'image/png','proof.png');return $http('/booking/receipt',$post);};
// Current navigation submits only id + CSRF, in both HTML and AJAX variants.
$deadline=$pdo->query("SELECT het_han_giu_san_luc FROM dat_san WHERE ma_dat_san=$browserId")->fetchColumn();
$r=$http('/booking/cancel',['_token'=>$token,'id'=>$browserId]);
ck($r['status']===303&&$pdo->query("SELECT trang_thai_dat_san FROM dat_san WHERE ma_dat_san=$browserId")->fetchColumn()==='CHO_THANH_TOAN','leaving payment does not cancel an unpaid live hold');
curl_setopt($client,CURLOPT_HTTPHEADER,['Accept: application/json']);$r=$http('/booking/cancel',['_token'=>$token,'id'=>$browserId]);curl_setopt($client,CURLOPT_HTTPHEADER,[]);
$navigation=json_decode($r['body'],true);ck($r['status']===200&&$navigation['state']==='CHO_THANH_TOAN'&&!empty($navigation['redirect']),'existing AJAX Back contract can navigate without cancelling');
$r=$http('/booking/wait',null,['id'=>$browserId]);ck($r['status']===200&&str_contains($r['body'],'data-state="CHO_THANH_TOAN"')&&$pdo->query("SELECT het_han_giu_san_luc FROM dat_san WHERE ma_dat_san=$browserId")->fetchColumn()===$deadline,'revisit resumes the same booking with its original deadline');
ck($http('/booking/receipt',['id'=>$id])['status']===403,'CSRF required');foreach([null,$bad,$large] as $file){$r=$send($file);ck($r['status']===303&&str_contains($r['head'],'booking%2Fwait'),'missing/invalid/oversize returns to wait');$page=$http('/booking/wait',null,['id'=>$id]);ck(!str_contains($page['body'],'data-payment-result')&&str_contains($page['body'],'stryke-toast-error'),'upload failure never shows result');}
$before=$pdo->query("SELECT * FROM dat_san WHERE ma_dat_san=$id")->fetch();$r=$send($upload);ck($r['status']===303&&str_contains($r['head'],'booking%2Fresult'),'successful upload redirects to result');$p=$pdo->query("SELECT * FROM thanh_toan WHERE ma_dat_san=$id")->fetch();$paths[]=App\Services\ReceiptImage::getReceiptFromNote($p['ghi_chu']);$after=$pdo->query("SELECT * FROM dat_san WHERE ma_dat_san=$id")->fetch();ck($p['trang_thai']==='CHO_THANH_TOAN'&&$after['trang_thai_dat_san']==='CHO_XAC_NHAN','B: upload atomically enters review without confirming money');ck($after['het_han_giu_san_luc']===$before['het_han_giu_san_luc'],'upload does not extend deadline');
// History must use the same private receipt service as the payment/admin flows.
foreach([$p['ghi_chu'],null,'[BIEN_LAI:uploads/receipts/receipt_'.$id.'_aaaaaaaaaaaaaaaa.png]'] as $note){
    $pdo->prepare('UPDATE thanh_toan SET ghi_chu=? WHERE ma_dat_san=?')->execute([$note,$id]);
    $h=$http('/booking/history');
    ck(str_contains($h['body'],'data-proof-src="/index.php?route=%2Fbooking%2Fhistory%2Freceipt&amp;id='.$id.'"'),'history exposes protected receipt_url, including column-only records');
    ck(!str_contains($h['body'],'receipts/receipt_'.$id.'_'),'history does not expose private storage path');
    $proof=$http('/booking/history/receipt',null,['id'=>$id]);
    ck($proof['status']===200&&str_contains($proof['head'],'image/png')&&$proof['body']===file_get_contents($upload),'owner reads exact uploaded bytes from private storage');
}
$pdo->prepare('UPDATE thanh_toan SET ghi_chu=? WHERE ma_dat_san=?')->execute([$p['ghi_chu'],$id]);
$pdo->prepare('UPDATE thanh_toan SET anh_minh_chung=? WHERE ma_dat_san=?')->execute([$p['anh_minh_chung'],$browserId]);
ck($http('/booking/history/receipt',null,['id'=>$browserId])['status']===404,'receipt filename must belong to requested booking, even for same owner');
$h=$http('/booking/history');ck(!str_contains($h['body'],'booking%2Fhistory%2Freceipt&amp;id='.$browserId.'"'),'no image button for missing or mismatched receipt');
$pdo->prepare('UPDATE thanh_toan SET anh_minh_chung=NULL WHERE ma_dat_san=?')->execute([$browserId]);
ck($http('/booking/history/receipt',null,['id'=>$browserId])['status']===404,'no receipt returns 404');
foreach(['/booking/result','/booking/result','/booking/wait'] as $route){$r=$http($route,null,['id'=>$id]);ck($r['status']===200&&str_contains($r['body'],'Gửi minh chứng thành công!')&&str_contains($r['body'],'đang chờ nhân viên xác minh'),'result, refresh and revisit use server receipt state');foreach(['data-countdown','data-receipt-waiting','data-payment-pending','stryke-countdown-banner','ĐÃ GỬI MINH CHỨNG'] as $old)ck(!str_contains($r['body'],$old),'result contains no old '.$old);file_put_contents($root.'/storage/temp/receipt-result-test.html',$r['body']);}
$model=new App\Models\Booking($pdo);
$scope=App\Services\BookingManagementService::resolveScope(['ten_vai_tro'=>'QUAN_TRI_TAP_DOAN','trang_thai'=>'HOAT_DONG']);
foreach([361,1800,3600,86400] as $age){
    $pdo->prepare('UPDATE dat_san SET bat_dau_giu_san_luc=DATE_SUB(CURRENT_TIMESTAMP,INTERVAL ? SECOND),het_han_giu_san_luc=DATE_SUB(CURRENT_TIMESTAMP,INTERVAL ? SECOND) WHERE ma_dat_san=?')->execute([$age,$age-360,$id]);
    ck($model->expire()===0 && $model->find($id)['trang_thai_dat_san']==='CHO_XAC_NHAN','C: review survives scheduler after '.$age.' seconds');
    ck($model->reserved($svc,date('Y-m-d H:i:s'))===2,'review continues reserving stock');
    $r=$http('/booking/wait',null,['id'=>$id,'status'=>'1']);ck(json_decode($r['body'],true)['state']==='CHO_XAC_NHAN','wait API agrees with review state');
    ck($model->history($customer,'CHO_XAC_NHAN')['total']===1,'history counts/filter preserve review');
    $adminModel=new App\Models\AdminBooking($pdo);ck($adminModel->find($id,$scope)['trang_thai_dat_san']==='CHO_XAC_NHAN','admin detail agrees after deadline');
    ck($adminModel->page(['booking_status'=>'CHO_XAC_NHAN'],$scope)['total']===1,'admin filter/count agrees');
    $r=$http('/booking/result',null,['id'=>$id]);ck(str_contains($r['body'],'data-state="CHO_XAC_NHAN"')&&!str_contains($r['body'],'data-countdown'),'review result has no clock after deadline');
}
$browser=new App\Models\CourtBrowser($pdo);$fac=$pdo->query("SELECT * FROM co_so WHERE ma_co_so=$facility")->fetch();
$grid=App\Services\CourtScheduleService::build($fac,$browser->courts($facility),$browser->intervals($facility,date('Y-m-d',time()+86400)),date('Y-m-d',time()+86400),App\Services\CourtScheduleService::now());
$slot=array_search('17:00:00',array_column($grid['slots'],'start'));ck($grid['rows'][0]['cells'][$slot]['state']==='held','review still blocks court after one day');
ck((int)$pdo->query("SELECT so_luong_ton FROM dich_vu WHERE ma_dich_vu=$svc")->fetchColumn()===5,'receipt/review never deducts physical stock');
$holdDeadline=$model->find($id)['het_han_giu_san_luc'];$send($upload);
ck($model->find($id)['het_han_giu_san_luc']===$holdDeadline,'duplicate after one day never renews deadline');

$first=$p['ghi_chu'];$send($upload);ck($pdo->query("SELECT ghi_chu FROM thanh_toan WHERE ma_dat_san=$id")->fetchColumn()===$first,'duplicate upload preserves accepted receipt');
$pdo->exec("UPDATE thanh_toan SET trang_thai='CAN_DOI_SOAT' WHERE ma_dat_san=$id");$r=$http('/booking/result',null,['id'=>$id]);ck(!str_contains($r['body'],'data-payment-result')&&str_contains($r['body'],'GIAO DỊCH CẦN ĐỐI SOÁT'),'reconciliation never displays success');$pdo->exec("UPDATE thanh_toan SET trang_thai='CHO_THANH_TOAN' WHERE ma_dat_san=$id");
// A/F: unpaid deadline, shared effective reads, actual expiry and late upload.
$expired=$make();
$pdo->exec("UPDATE dat_san SET bat_dau_giu_san_luc=NOW()-INTERVAL 7 MINUTE,het_han_giu_san_luc=NOW()-INTERVAL 1 MINUTE WHERE ma_dat_san=$expired");
ck($model->findEffective($expired)['trang_thai_dat_san']==='HET_HAN'
    && (new App\Models\AdminBooking($pdo))->find($expired,$scope)['trang_thai_dat_san']==='HET_HAN','A/D: customer and admin agree before job persists expiry');
ck($model->expire($expired)===1,'A: unpaid hold expires');
$r=$http('/booking/receipt',['id'=>$expired,'_token'=>$token,'bien_lai'=>new CURLFile($upload,'image/png','proof.png')]);
ck($r['status']===303&&str_contains($r['head'],'booking%2Fwait')&&$model->find($expired)['trang_thai_dat_san']==='HET_HAN','F: late upload cannot restore expired booking');
ck(!App\Services\ReceiptImage::getReceiptFromNote($pdo->query("SELECT ghi_chu FROM thanh_toan WHERE ma_dat_san=$expired")->fetchColumn()?:null),'late upload never commits a receipt');

// G: block payment while HTTP upload locks booking, then queue expiry behind it.
$cookieParts=[];foreach(curl_getinfo($client,CURLINFO_COOKIELIST) as $cookieRow){$parts=explode("\t",$cookieRow);$cookieParts[]=$parts[5].'='.$parts[6];}$cookie=implode('; ',$cookieParts);
$spawn=static function(array $args)use($root){$proc=proc_open([PHP_BINARY,__FILE__,...$args],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);if(!is_resource($proc))throw new RuntimeException('No worker');fclose($pipes[0]);return [$proc,$pipes];};
$join=static function(array $job){[$proc,$pipes]=$job;$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($proc)!==0)throw new RuntimeException($err);return json_decode($out,true,512,JSON_THROW_ON_ERROR);};
foreach([false,true] as $crossDeadline){
    $race=$make();
    if($crossDeadline)$pdo->exec("UPDATE dat_san SET bat_dau_giu_san_luc=NOW()-INTERVAL 359 SECOND,het_han_giu_san_luc=NOW()+INTERVAL 1 SECOND WHERE ma_dat_san=$race");
    $pdo->beginTransaction();$pdo->query("SELECT * FROM thanh_toan WHERE ma_dat_san=$race FOR UPDATE")->fetch();
    try{
        $u=$spawn(['--upload-worker',base64_encode(json_encode(['id'=>$race,'token'=>$token,'cookie'=>$cookie]))]);usleep(350000);
        $e=$spawn(['--expire-worker',$db,(string)$race]);usleep($crossDeadline?1800000:200000);$pdo->commit();
        $uploadResult=$join($u);$expireResult=$join($e);
    }finally{if($pdo->inTransaction())$pdo->rollBack();}
    if($crossDeadline){
        ck($expireResult['count']===1&&$expireResult['state']==='HET_HAN','G: deadline passes under lock; expiry wins safely');
        ck(!App\Services\ReceiptImage::getReceiptFromNote($pdo->query("SELECT ghi_chu FROM thanh_toan WHERE ma_dat_san=$race")->fetchColumn()?:null),'failed concurrent upload leaves no receipt');
    }else{
        ck($expireResult['count']===0&&$expireResult['state']==='CHO_XAC_NHAN','G: accepted upload wins lock; expiry cannot release review');
    }
    ck($uploadResult['status']===303,'concurrent upload finishes through normal POST redirect');
}

// Conservative protection of old inconsistent records; no automatic promotion/revival.
$legacy=$make();$legacyPath='receipts/receipt_'.$legacy.'_'.bin2hex(random_bytes(8)).'.png';copy($upload,$root.'/storage/'.$legacyPath);$paths[]=$legacyPath;
$pdo->exec("UPDATE dat_san SET bat_dau_giu_san_luc=NOW()-INTERVAL 7 MINUTE,het_han_giu_san_luc=NOW()-INTERVAL 1 MINUTE WHERE ma_dat_san=$legacy");
$pdo->prepare('UPDATE thanh_toan SET ghi_chu=? WHERE ma_dat_san=?')->execute([App\Services\ReceiptImage::appendReceiptToNote(null,$legacyPath,date('Y-m-d H:i:s',time()-400)),$legacy]);
ck($model->expire($legacy)===0&&$model->find($legacy)['trang_thai_dat_san']==='CHO_THANH_TOAN','legacy receipt anomaly is protected without changing stored state');
ck((new App\Models\AdminBooking($pdo))->find($legacy,$scope)['trang_thai_dat_san']===$model->findEffective($legacy)['trang_thai_dat_san'],'legacy admin/customer effective reads agree');
if(in_array('--browser',$argv,true)){
    putenv('RESULT_TEST_ID='.$browserId);putenv('RESULT_TEST_AGED_ID='.$id);
    passthru('node tests/receipt-hold-browser.cjs',$browserStatus);
    if($browserStatus!==0)throw new RuntimeException('Browser checks failed');
}
$adminId=(new App\Services\AccountService($pdo))->create('resultadmin','Testpass1!','QUAN_TRI_TAP_DOAN');
$adminCurl=curl_init();curl_setopt_array($adminCurl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>20]);
$customerCurl=$client;
$adminRequest=function($route,$post=null,$query=[])use($adminCurl){curl_setopt($adminCurl,CURLOPT_URL,'http://127.0.0.1:18993/index.php?'.http_build_query(['route'=>$route]+$query));curl_setopt($adminCurl,CURLOPT_POST,$post!==null);if($post!==null)curl_setopt($adminCurl,CURLOPT_POSTFIELDS,$post);$r=curl_exec($adminCurl);$n=curl_getinfo($adminCurl,CURLINFO_HEADER_SIZE);return ['status'=>curl_getinfo($adminCurl,CURLINFO_RESPONSE_CODE),'head'=>substr($r,0,$n),'body'=>substr($r,$n)];};
$r=$adminRequest('/login');preg_match('/name="_token" value="([^"]+)"/',$r['body'],$m);$adminRequest('/login',['_token'=>$m[1],'username'=>'resultadmin','password'=>'Testpass1!']);
$r=$adminRequest('/admin/bookings/show',null,['id'=>$id]);ck($r['status']===200&&str_contains($r['body'],'Chờ xác nhận'),'D: real admin page shows review after one day');preg_match('/name="_token" value="([^"]+)"/',$r['body'],$m);$adminToken=$m[1];
$r=$http('/booking/history');ck($r['status']===200&&str_contains($r['body'],'Chờ xác nhận'),'D: real customer history agrees');
$receipt=(new App\Services\BookingReceiptService($pdo))->info($pdo->query("SELECT * FROM thanh_toan WHERE ma_dat_san=$id")->fetch());
$r=$adminRequest('/admin/bookings/confirm',['_token'=>$adminToken,'id'=>$id,'receipt_version'=>$receipt['version']]);
ck($r['status']===303&&$model->find($id)['trang_thai_dat_san']==='DA_XAC_NHAN','E: real admin POST confirms aged review');
ck($pdo->query("SELECT trang_thai FROM thanh_toan WHERE ma_dat_san=$id")->fetchColumn()==='THANH_CONG','admin confirms money only at approval');
ck((int)$pdo->query("SELECT so_luong_ton FROM dich_vu WHERE ma_dich_vu=$svc")->fetchColumn()===4,'approval deducts held stock once');
ck((int)$pdo->query("SELECT COUNT(*) FROM hoa_don WHERE ma_dat_san=$id")->fetchColumn()===1,'existing approval still creates one invoice');
$adminRequest('/admin/bookings/confirm',['_token'=>$adminToken,'id'=>$id,'receipt_version'=>$receipt['version']]);
ck((int)$pdo->query("SELECT so_luong_ton FROM dich_vu WHERE ma_dich_vu=$svc")->fetchColumn()===4,'duplicate approval never deducts twice');
$r=$http('/booking/result',null,['id'=>$id]);ck(str_contains($r['body'],'data-payment-status="THANH_CONG"')&&str_contains($r['body'],'data-state="DA_XAC_NHAN"'),'verified result follows approved server state');
$client=$customerCurl;
$explicit=$make();$r=$http('/booking/cancel',['_token'=>$token,'id'=>$explicit,'action'=>'cancel']);
ck($r['status']===303&&$model->find($explicit)['trang_thai_dat_san']==='DA_HUY','explicit customer cancellation still uses the existing cancel service');
$http('/logout',['_token'=>$token]);ck($http('/booking/result',null,['id'=>$id])['status']===303,'guest result requires existing login');$a=(new App\Services\AccountService($pdo))->create('resultother','Testpass1!','KHACH_HANG');putRow('khach_hang',['ma_tai_khoan'=>$a,'ho_ten'=>'Other test','so_dien_thoai'=>'0901234568']);$r=$http('/login');preg_match('/name="_token" value="([^"]+)"/',$r['body'],$m);$http('/login',['_token'=>$m[1],'username'=>'resultother','password'=>'Testpass1!']);ck($http('/booking/result',null,['id'=>$id])['status']===404,'other customer cannot view result');
ck($http('/booking/history/receipt',null,['id'=>$id])['status']===404,'other customer cannot read receipt bytes');
echo "PASS $checks HTTP checks\n";
}finally{if(is_resource($server))proc_terminate($server);if(isset($facility))foreach($pdo->query('SELECT ghi_chu FROM thanh_toan')->fetchAll() as $row){$f=App\Services\ReceiptImage::getReceiptFromNote($row['ghi_chu']);if($f&&$i=App\Services\ReceiptImage::file($f,(int)explode('_',basename($f))[1]))@unlink($i['absolute']);}foreach($paths as $f)if($f&&$i=App\Services\ReceiptImage::file($f,(int)explode('_',basename($f))[1]))@unlink($i['absolute']);foreach([$upload??null,$bad??null,$large??null] as $f)if($f&&is_file($f))unlink($f);$pdo->exec("DROP DATABASE `$db`");}
