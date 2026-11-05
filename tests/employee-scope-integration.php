<?php
// Opt-in. Copy DDL only; all test records live in a newly owned disposable database.
if(PHP_SAPI!=='cli' || ($argv[1]??'')!=='--run')exit("php tests/employee-scope-integration.php --run\n");
require dirname(__DIR__).'/vendor/autoload.php';Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
$config=require dirname(__DIR__).'/config/database.php';
if(!in_array($config['host'],['127.0.0.1','localhost','::1'],true))throw new RuntimeException('Local database required');
$connect=static function(?string $db=null)use($config):PDO{
 $pdo=new PDO("mysql:host={$config['host']};port={$config['port']};".($db===null?'':"dbname=$db;").'charset=utf8mb4',$config['username'],$config['password'],
 [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);$pdo->exec("SET time_zone='+07:00'");return $pdo;
};
$root=dirname(__DIR__);$out=$root.'/storage/temp/dashboard-employee-review';if(!is_dir($out))mkdir($out,0700,true);
$live=$connect($config['dbname']);$live->exec('SET SESSION TRANSACTION READ ONLY');$live->beginTransaction();
try{$ddl=[];foreach($live->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as [$name]){
 if(!preg_match('/\A[a-z0-9_]+\z/i',$name))throw new RuntimeException('Unexpected table');
 $ddl[]=preg_replace('/ AUTO_INCREMENT=\d+/','',$live->query("SHOW CREATE TABLE `$name`")->fetch(PDO::FETCH_NUM)[1]);
}}finally{$live->rollBack();$live=null;}
$db='stryke_employee_test_'.bin2hex(random_bytes(5));$admin=$connect();$owned=false;$server=null;$checks=0;
$check=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException('FAIL: '.$label);$checks++;echo "PASS: $label\n";};
try{
 if($db===$config['dbname'] || !preg_match('/\Astryke_employee_test_[a-f0-9]{10}\z/',$db))throw new RuntimeException('Unsafe test database');
 $admin->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$owned=true;$pdo=$connect($db);
 $pdo->exec('SET FOREIGN_KEY_CHECKS=0');foreach($ddl as $sql)$pdo->exec($sql);$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
 $insert=static function(string $table,array $row)use($pdo):int{$pdo->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row));return (int)$pdo->lastInsertId();};
 foreach(['QUAN_TRI_TAP_DOAN','QUAN_LY_CONG_TY','QUAN_LY_CO_SO','NHAN_VIEN','KHACH_HANG'] as $role)$insert('vai_tro',['ten_vai_tro'=>$role]);
 $companyA=$insert('cong_ty_nhuong_quyen',['ten_cong_ty'=>'TEST Company A','dia_chi'=>'TEST']);
 $companyB=$insert('cong_ty_nhuong_quyen',['ten_cong_ty'=>'TEST Company B','dia_chi'=>'TEST']);
 $facility=static fn($company,$name)=>$insert('co_so',['ma_cong_ty'=>$company,'ten_co_so'=>$name,'dia_chi'=>'TEST','gio_mo_cua'=>'06:00','gio_dong_cua'=>'23:00']);
 $x=$facility($companyA,'TEST Facility X');$y=$facility($companyA,'TEST Facility Y');$z=$facility($companyB,'TEST Facility Z');
 $password='T'.bin2hex(random_bytes(4)).'a1!';$accounts=new App\Services\AccountService($pdo);
 $adminId=$accounts->create('scope.admin',$password,'QUAN_TRI_TAP_DOAN');
 $person=static function(string $username,string $role,int $company,?int $facility,string $name)use($accounts,$password,$insert):int{
  $id=$accounts->create($username,$password,$role);return $insert('nhan_vien',['ma_tai_khoan'=>$id,'ma_cong_ty'=>$company,'ma_co_so'=>$facility,'ho_ten'=>$name,'trang_thai'=>'DANG_LAM']);
 };
 $manager=$person('scope.company','QUAN_LY_CONG_TY',$companyA,null,'TEST Company Manager');
 $facilityManager=$person('scope.facility','QUAN_LY_CO_SO',$companyA,$x,'TEST Facility Manager X');
 $person('scope.facilityy','QUAN_LY_CO_SO',$companyA,$y,'TEST Facility Manager Y');
 $staffX=$person('scope.staffx','NHAN_VIEN',$companyA,$x,'TEST Staff X');
 $staffY=$person('scope.staffy','NHAN_VIEN',$companyA,$y,'TEST Staff Y');
 $staffB=$person('scope.staffb','NHAN_VIEN',$companyB,$z,'TEST Foreign Staff B');
 $customerAccount=$accounts->create('scope.customer',$password,'KHACH_HANG');$insert('khach_hang',['ma_tai_khoan'=>$customerAccount,'ho_ten'=>'TEST Customer','so_dien_thoai'=>'0900000000']);
 for($i=1;$i<=25;$i++)$insert('nhan_vien',['ma_cong_ty'=>$companyA,'ma_co_so'=>$x,'ho_ten'=>sprintf('PAGE STAFF %02d',$i),'trang_thai'=>'DANG_LAM']);
 $ids=compact('companyA','companyB','x','y','z','manager','facilityManager','staffX','staffY','staffB');file_put_contents($out.'/employee-fixture-ids.json',json_encode($ids));
 $env=getenv();foreach($_ENV as $key=>$value)$env[$key]=$value;
 $env['STRYKE_EMPLOYEE_TEST_DB']=$db;$env['STRYKE_EMPLOYEE_TEST_PASSWORD']=$password;$env['STRYKE_EMPLOYEE_TEST_ROOT']=$out.'/'.$db;
 mkdir($env['STRYKE_EMPLOYEE_TEST_ROOT'].'/sessions',0700,true);
 $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);if(!$socket)throw new RuntimeException('No port');$address=stream_socket_get_name($socket,false);fclose($socket);
 $env['STRYKE_EMPLOYEE_TEST_URL']='http://'.$address;
 $server=proc_open([PHP_BINARY,'-d','variables_order=EGPCS','-S',$address,'-t','public','tests/fixtures/employee-scope.php'],[0=>['pipe','r'],1=>['file',$out.'/employee-server.log','w'],2=>['file',$out.'/employee-server.log','a']],$pipes,$root,$env,['bypass_shell'=>true]);
 if(!is_resource($server))throw new RuntimeException('No HTTP server');fclose($pipes[0]);
 $ready=false;for($i=0;$i<100;$i++){$socket=@stream_socket_client('tcp://'.$address,$errno,$error,0.05);if($socket){fclose($socket);$ready=true;break;}usleep(50000);}$check($ready,'real HTTP server runs on an isolated MySQL/MariaDB database');
 $browser=proc_open(['node','tests/employee-scope-browser.cjs'],[0=>['pipe','r'],1=>['file',$out.'/employee-browser.log','w'],2=>['file',$out.'/employee-browser.log','a']],$pipes,$root,$env,['bypass_shell'=>true]);
 if(!is_resource($browser))throw new RuntimeException('No browser runner');fclose($pipes[0]);$exit=proc_close($browser);echo file_get_contents($out.'/employee-browser.log');$check($exit===0,'Chrome role/scope/form/navigation checks complete');
 $row=$pdo->query("SELECT nv.*,vt.ten_vai_tro FROM nhan_vien nv LEFT JOIN tai_khoan tk ON tk.ma_tai_khoan=nv.ma_tai_khoan LEFT JOIN vai_tro vt ON vt.ma_vai_tro=tk.ma_vai_tro WHERE nv.ho_ten='CREATED BY FACILITY'")->fetch();
 $check($row && (int)$row['ma_cong_ty']===$companyA && (int)$row['ma_co_so']===$x,'new facility employee is bound to actor company and facility');
 $check($row['ten_vai_tro']==='NHAN_VIEN','facility can only issue the staff role');
 $check((int)$pdo->query("SELECT COUNT(*) FROM tai_khoan tk JOIN vai_tro vt ON vt.ma_vai_tro=tk.ma_vai_tro WHERE vt.ten_vai_tro='QUAN_TRI_TAP_DOAN'")->fetchColumn()===1,'forged requests did not create an Admin account');
 $check($pdo->query('SELECT ho_ten FROM nhan_vien WHERE ma_nhan_vien='.$manager)->fetchColumn()==='TEST Company Manager','company manager profile protected against self/peer mutation');
 $check($pdo->query('SELECT ho_ten FROM nhan_vien WHERE ma_nhan_vien='.$facilityManager)->fetchColumn()==='TEST Facility Manager X','facility manager profile protected against self/peer mutation');
 $check($pdo->query('SELECT ho_ten FROM nhan_vien WHERE ma_nhan_vien='.$staffB)->fetchColumn()==='TEST Foreign Staff B','foreign company employee remains unchanged');
 $staffRow=$pdo->query('SELECT nv.ma_cong_ty,nv.ma_co_so,vt.ten_vai_tro FROM nhan_vien nv JOIN tai_khoan tk ON tk.ma_tai_khoan=nv.ma_tai_khoan JOIN vai_tro vt ON vt.ma_vai_tro=tk.ma_vai_tro WHERE nv.ma_nhan_vien='.$staffY)->fetch();
 $check((int)$staffRow['ma_cong_ty']===$companyA&&(int)$staffRow['ma_co_so']===$y&&$staffRow['ten_vai_tro']==='NHAN_VIEN','forged edit fields cannot change company or elevate an account role');
 $check(!preg_match('/PHP (Warning|Fatal error|Parse error)/',file_get_contents($out.'/employee-server.log')),'no PHP warnings/fatal errors during real CRUD');
 echo "PASS: $checks real database integration checks; no application rows copied or changed.\n";
}finally{
 if(is_resource($server)){proc_terminate($server);proc_close($server);}if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();$pdo=null;
 if($owned && $db!==$config['dbname'] && preg_match('/\Astryke_employee_test_[a-f0-9]{10}\z/',$db))$admin->exec("DROP DATABASE `$db`");
}
