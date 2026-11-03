<?php
// All fixture writes are confined to a random disposable MySQL database.
if (PHP_SAPI !== 'cli' || !in_array($argv[1] ?? '', ['--run','--worker'], true)) exit("php tests/booking-expiry-integration.php --run\n");
require dirname(__DIR__).'/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
$config = require dirname(__DIR__).'/config/database.php';
if (!in_array($config['host'], ['localhost','127.0.0.1','::1'], true)) throw new RuntimeException('Local test server required');
date_default_timezone_set('Asia/Ho_Chi_Minh');
function expiryConnection(?string $db = null): PDO {
    global $config;
    if ($db !== null && (!preg_match('/\Astryke_expiry_test_[a-f0-9]{10}\z/', $db) || $db === $config['dbname'])) throw new RuntimeException('Unsafe test database');
    $p = new PDO("mysql:host={$config['host']};port={$config['port']};" . ($db ? "dbname=$db;" : '') . 'charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $p->exec("SET time_zone='+07:00'"); return $p;
}
if ($argv[1] === '--worker') {
    $p = expiryConnection($argv[2]); $m = new App\Models\Booking($p);
    if ($argv[3] === 'job') {echo json_encode(['count'=>$m->expire(),'report'=>$m->expiryReport()]); exit;}
    $id = filter_var($argv[4] ?? '', FILTER_VALIDATE_INT); if (!$id) throw new RuntimeException('Invalid test ID');
    if ($argv[3] === 'single') {echo json_encode(['count'=>$m->expire($id)]);exit;}
    // Contract simulator only, NOT a provider callback implementation.
    $p->beginTransaction();
    try {
        (new App\Services\BookingService($p))->lockPayable($id);
        $p->prepare('SELECT ma_thanh_toan FROM thanh_toan WHERE ma_dat_san=? FOR UPDATE')->execute([$id]);
        $s=$p->prepare("UPDATE dat_san SET trang_thai_dat_san='DA_XAC_NHAN' WHERE ma_dat_san=? AND trang_thai_dat_san='CHO_THANH_TOAN' AND het_han_giu_san_luc>NOW()");$s->execute([$id]);
        if($s->rowCount()!==1)throw new Core\HttpException(409,'Late callback');
        $p->prepare("UPDATE thanh_toan SET trang_thai='THANH_CONG',ngay_thanh_toan=NOW(),ma_giao_dich=CONCAT('TEST-',ma_dat_san),so_tien_thuc_nhan=so_tien WHERE ma_dat_san=?")->execute([$id]);
        $p->commit();echo json_encode(['confirmed'=>true]);
    } catch (Core\HttpException $e) {$p->rollBack();echo json_encode(['rejected'=>$e->status]);}
    exit;
}
function expiryCheck(bool $ok, string $label): void {global $checks;if(!$ok)throw new RuntimeException('FAIL: '.$label);$checks++;echo "PASS: $label\n";}
function expiryInsert(string $table, array $row): int {global $p;$p->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row));return (int)$p->lastInsertId();}
function expiryWorker(string $mode, int $id = 0): array {global $db;$proc=proc_open([PHP_BINARY,__FILE__,'--worker',$db,$mode,(string)$id],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));if(!is_resource($proc))throw new RuntimeException('Worker failed');fclose($pipes[0]);return [$proc,$pipes];}
function expiryJoin(array $job): array {[$proc,$pipes]=$job;$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($proc)!==0)throw new RuntimeException($err);return json_decode($out,true,512,JSON_THROW_ON_ERROR);}
$checks=0; $admin=expiryConnection(); $db='stryke_expiry_test_'.bin2hex(random_bytes(5));
$admin->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    $p=expiryConnection($db);
    $sql=preg_replace('/CREATE DATABASE.*?;|USE quanly_chuoi_san_the_thao;/s','',file_get_contents(dirname(__DIR__).'/database/quanly_chuoi_san_the_thao.sql'));
    $p->exec($sql);
    $company=expiryInsert('cong_ty_nhuong_quyen',['ten_cong_ty'=>'Expiry fixture','dia_chi'=>'Test']);
    $facility=expiryInsert('co_so',['ma_cong_ty'=>$company,'ten_co_so'=>'Expiry fixture','dia_chi'=>'Test','gio_mo_cua'=>'05:00','gio_dong_cua'=>'23:00']);
    $account=(new App\Services\AccountService($p))->create('expiretest','Testpass1!','KHACH_HANG');
    $customer=expiryInsert('khach_hang',['ma_tai_khoan'=>$account,'ho_ten'=>'Expiry test','so_dien_thoai'=>'0901234567']);
    $service=expiryInsert('dich_vu',['ma_co_so'=>$facility,'ten_dich_vu'=>'Test water','loai_dich_vu'=>'DO_UONG','don_vi_tinh'=>'bottle','don_gia'=>'10000','so_luong_ton'=>10]);
    $make=static function(int $offset, string $state='CHO_THANH_TOAN', ?string $payment=null) use ($facility,$customer,$service): int {
        global $p;
        $deadline=(new DateTimeImmutable())->modify(($offset>=0?'+':'').$offset.' seconds');
        $id=expiryInsert('dat_san',['ma_co_so'=>$facility,'ma_khach_hang'=>$customer,'ngay_tao'=>$deadline->modify('-6 minutes')->format('Y-m-d H:i:s'),
            'bat_dau_giu_san_luc'=>$deadline->modify('-6 minutes')->format('Y-m-d H:i:s'),'het_han_giu_san_luc'=>$deadline->format('Y-m-d H:i:s'),
            'trang_thai_dat_san'=>$state,'tong_tien_dich_vu'=>'10000','tong_tien_tam_tinh'=>'10000']);
        expiryInsert('chi_tiet_dich_vu_dat',['ma_dat_san'=>$id,'ma_dich_vu'=>$service,'ten_dich_vu_snapshot'=>'Test water','don_vi_tinh_snapshot'=>'bottle','so_luong'=>1,'don_gia'=>'10000','thanh_tien'=>'10000']);
        if($payment!==null)expiryInsert('thanh_toan',['ma_dat_san'=>$id,'ma_tham_chieu'=>'EXPIRY-'.$id,'so_tien'=>'10000','trang_thai'=>$payment]
            + ($payment==='THANH_CONG' ? ['ngay_thanh_toan'=>date('Y-m-d H:i:s'),'ma_giao_dich'=>'TEST-'.$id,'so_tien_thuc_nhan'=>'10000'] : []));
        return $id;
    };
    $m=new App\Models\Booking($p);$s=new App\Services\BookingService($p);
    $fresh=$make(360);$boundary=$make(0,'CHO_THANH_TOAN','CHO_THANH_TOAN');$old=$make(-172800);
    $paid=$make(-360,'CHO_THANH_TOAN','THANH_CONG');$review=$make(-360,'CHO_THANH_TOAN','CAN_DOI_SOAT');
    $fixed=[];foreach(['DA_XAC_NHAN','HOAN_THANH','DA_HUY','HET_HAN'] as $state)$fixed[$state]=$make(-360,$state);
    $before=$m->history($customer);
    expiryCheck($before['counts']['CHO_THANH_TOAN']===3 && $before['counts']['HET_HAN']===3,'effective history counts include overdue, protect payment anomalies');
    $expiredPage=$m->history($customer,'HET_HAN');
    expiryCheck($expiredPage['total']===3 && count($expiredPage['items'])===3,'effective filter and pagination match counts');
    expiryCheck($m->find($boundary)['trang_thai_dat_san']==='CHO_THANH_TOAN','GET projection did not persist expiry');
    expiryCheck($s->inspect($paid,$customer)['trang_thai_dat_san']==='CHO_THANH_TOAN','inspect shares protected payment rule');
    expiryCheck($s->expirePending()===2,'deadline and old unattended bookings expired');
    expiryCheck($m->find($fresh)['trang_thai_dat_san']==='CHO_THANH_TOAN','unexpired booking preserved');
    expiryCheck($m->find($old)['trang_thai_dat_san']==='HET_HAN','old backlog cleared');
    expiryCheck($p->query("SELECT trang_thai FROM thanh_toan WHERE ma_dat_san=$boundary")->fetchColumn()==='HET_HAN','pending payment synchronized');
    foreach([$paid=>'THANH_CONG',$review=>'CAN_DOI_SOAT'] as $id=>$state) {
        expiryCheck($m->find($id)['trang_thai_dat_san']==='CHO_THANH_TOAN' && $p->query("SELECT trang_thai FROM thanh_toan WHERE ma_dat_san=$id")->fetchColumn()===$state,'protect booking and payment '.$state);
        expiryCheck($m->expire($id)===0,'single expiry protects '.$state);
    }
    foreach($fixed as $state=>$id)expiryCheck($m->find($id)['trang_thai_dat_san']===$state,'terminal state unchanged '.$state);
    expiryCheck($s->expirePending()===0,'repeated job idempotent');
    expiryCheck(count($s->expiryReport()['protected_sample'])===2,'reconciliation reported');
    $exact=$make(60,'CHO_THANH_TOAN','CHO_THANH_TOAN');
    $exactTime=$m->find($exact)['het_han_giu_san_luc'];
    // Freeze only this disposable DB connection's clock to prove the exact boundary.
    $p->exec('SET timestamp=' . (strtotime($exactTime)-1));
    expiryCheck($m->expire($exact)===0,'one second before deadline is still valid');
    $p->exec('SET timestamp=' . strtotime($exactTime));
    expiryCheck($m->expire($exact)===1,'exact deadline expires (not only strictly after)');
    $p->exec('SET timestamp=0');
    expiryCheck((int)$p->query("SELECT so_luong_ton FROM dich_vu WHERE ma_dich_vu=$service")->fetchColumn()===10 && $m->reserved($service,date('Y-m-d H:i:s'))===1,'stock unchanged; expired/protected-overdue holds not reserved');
    // Hold the DB-scoped job lock: another real process must skip immediately.
    $lock='stryke_expiry_'.hash('sha1',$db);$p->prepare('SELECT GET_LOCK(?,0)')->execute([$lock]);
    $result=expiryJoin(expiryWorker('job'));
    expiryCheck($result['count']===0 && $result['report']['overlap_skipped'],'overlapping scheduler instance skips');
    $p->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
    $parallel=$make(-60,'CHO_THANH_TOAN','CHO_THANH_TOAN');
    $a=expiryWorker('single',$parallel);$b=expiryWorker('single',$parallel);
    expiryCheck(expiryJoin($a)['count']+expiryJoin($b)['count']===1,'two concurrent row expirations update once');
    // A timely payment transaction wins the booking lock; competing expiration waits.
    $winner=$make(5,'CHO_THANH_TOAN','CHO_THANH_TOAN');$p->beginTransaction();$s->lockPayable($winner);
    $worker=expiryWorker('single',$winner);usleep(200000);
    $p->exec("UPDATE dat_san SET trang_thai_dat_san='DA_XAC_NHAN' WHERE ma_dat_san=$winner");
    $p->exec("UPDATE thanh_toan SET trang_thai='THANH_CONG',ngay_thanh_toan=NOW(),ma_giao_dich='WINNER',so_tien_thuc_nhan=so_tien WHERE ma_dat_san=$winner");$p->commit();
    expiryCheck(expiryJoin($worker)['count']===0 && $m->find($winner)['trang_thai_dat_san']==='DA_XAC_NHAN','expiry rechecks state after waiting for payment transaction');
    // Real guard simulator accepts before deadline, rejects duplicate callback and late callback.
    $near=$make(2,'CHO_THANH_TOAN','CHO_THANH_TOAN');
    expiryCheck(expiryJoin(expiryWorker('callback',$near))['confirmed']??false,'callback contract accepts before deadline');
    expiryCheck((expiryJoin(expiryWorker('callback',$near))['rejected']??0)===409,'duplicate callback guard rejects second transition');
    $late=$make(-1,'CHO_THANH_TOAN','CHO_THANH_TOAN');
    $p->beginTransaction();$m->find($late,true);$worker=expiryWorker('callback',$late);usleep(200000);$m->expire($late);$p->commit();
    expiryCheck((expiryJoin($worker)['rejected']??0)===409 && $m->find($late)['trang_thai_dat_san']==='HET_HAN','expiry wins lock; concurrent late callback rejected');
    expiryCheck((int)$p->query("SELECT so_luong_ton FROM dich_vu WHERE ma_dich_vu=$service")->fetchColumn()===10,'concurrent expiry never updates stock');
    // High volume with older protected rows: capped batches must continue next run.
    $p->beginTransaction();for($i=0;$i<2105;$i++)$make(-3600);$p->commit();
    $count=$m->expire();$report=$m->expiryReport();
    expiryCheck($count===2000 && $report['batches']===10 && $report['more_pending'],'batch cap leaves backlog explicitly');
    expiryCheck($m->expire()===105 && !$m->expiryReport()['more_pending'],'next run clears remaining backlog without anomaly starvation');
    $plan=$p->query("EXPLAIN SELECT b.ma_dat_san FROM dat_san b WHERE b.trang_thai_dat_san='CHO_THANH_TOAN' AND b.het_han_giu_san_luc<=NOW() AND NOT EXISTS (SELECT 1 FROM thanh_toan p WHERE p.ma_dat_san=b.ma_dat_san AND p.trang_thai IN ('THANH_CONG','CAN_DOI_SOAT')) ORDER BY b.het_han_giu_san_luc,b.ma_dat_san LIMIT 200")->fetchAll();
    expiryCheck($plan[0]['key']==='idx_datsan_trangthai_hethan' && in_array($plan[0]['type'],['range','ref'],true),'EXPLAIN uses deadline/status range index');
    // Run production CLI against this disposable DB only, with environment override.
    $cli=$make(-60,'CHO_THANH_TOAN','CHO_THANH_TOAN');$env=getenv();foreach($_ENV as $k=>$v)$env[$k]=$v;$env['DB_NAME']=$db;
    $proc=proc_open([PHP_BINARY,'-d','variables_order=EGPCS',dirname(__DIR__).'/bin/expire-bookings.php','--run'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),$env);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($proc);
    expiryCheck($code===0 && str_contains($out,'1 expired booking(s)') && $m->find($cli)['trang_thai_dat_san']==='HET_HAN','production CLI executes only test DB');
    $logs=file_get_contents(dirname(__DIR__).'/storage/temp/booking-expiry/'.date('Y-m-d').'.log');
    expiryCheck(str_contains($logs,'"event":"completed"') && str_contains($logs,'"protected_sample"'),'CLI writes report and anomaly log');
    echo "PASS: $checks expiry integration checks; disposable DB removed on exit.\n";
} finally {
    if(isset($p) && $p->inTransaction())$p->rollBack();
    $admin->exec("DROP DATABASE `$db`");
}
