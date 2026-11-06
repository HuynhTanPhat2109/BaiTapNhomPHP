<?php
// Explicit opt-in. DDL is read from the local database; all writes use an owned disposable database.
if (PHP_SAPI !== 'cli' || !in_array($argv[1] ?? '', ['--run', '--worker'], true)) exit("php tests/employee-account-integration.php --run [--browser]\n");
require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
$config = require dirname(__DIR__) . '/config/database.php';
if (!in_array($config['host'], ['localhost','127.0.0.1','::1'], true)) throw new RuntimeException('Local database required');
$connect = static function(?string $name = null) use ($config): PDO {
    $pdo = new PDO("mysql:host={$config['host']};port={$config['port']};" . ($name === null ? '' : "dbname=$name;") . 'charset=utf8mb4',
        $config['username'], $config['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES=>false, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->exec("SET time_zone='+07:00'");
    return $pdo;
};
if ($argv[1] === '--worker') {
    $db = getenv('STRYKE_EMPLOYEE_TEST_DB');
    if (!preg_match('/\Astryke_employee_test_[a-f0-9]{10}\z/', $db ?: '') || $db === $config['dbname']) throw new RuntimeException('Unsafe worker database');
    $pdo = $connect($db);
    $actor = (new App\Models\TaiKhoan($pdo))->identity((int) getenv('EMPLOYEE_ACCOUNT_ACTOR'));
    $directory = getenv('STRYKE_EMPLOYEE_TEST_ROOT');
    file_put_contents($directory . '/ready-' . $argv[3], 'ready');
    $deadline = microtime(true)+10;
    while (!is_file($directory . '/go')) { if (microtime(true)>$deadline) throw new RuntimeException('Race barrier timeout'); usleep(10000); }
    try {
        (new App\Services\EmployeeService($pdo,$actor))->grantAccount($actor['ten_vai_tro'],$actor['ma_cong_ty'],(int)$argv[2],
            ['username'=>$argv[3],'password'=>getenv('STRYKE_EMPLOYEE_TEST_PASSWORD'),
                'password_confirmation'=>getenv('STRYKE_EMPLOYEE_TEST_PASSWORD'),'role'=>'NHAN_VIEN']);
        echo 'created';
    } catch (App\Services\ValidationException $error) { echo 'rejected'; }
    exit;
}
$root = dirname(__DIR__);
$directory = $root . '/storage/temp/employee-account-' . bin2hex(random_bytes(5));
mkdir($directory . '/sessions', 0700, true);
$db = 'stryke_employee_test_' . bin2hex(random_bytes(5));
$admin = $connect(); $owned = false; $server = null; $workers = []; $checks = 0;
$check = static function(bool $ok,string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
};
try {
    $source = $connect($config['dbname']);
    $source->exec('SET TRANSACTION READ ONLY'); $source->beginTransaction();
    try {
        $ddl = [];
        foreach ($source->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as [$table]) {
            if (!preg_match('/\A[a-z0-9_]+\z/i',$table)) throw new RuntimeException('Unexpected table name');
            $ddl[] = preg_replace('/ AUTO_INCREMENT=\d+/','',$source->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1]);
        }
    } finally { $source->rollBack(); $source=null; }
    if ($db === $config['dbname'] || !preg_match('/\Astryke_employee_test_[a-f0-9]{10}\z/',$db)) throw new RuntimeException('Unsafe database name');
    $admin->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); $owned=true;
    $pdo=$connect($db);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0'); foreach ($ddl as $sql) $pdo->exec($sql); $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $insert=static function(string $table,array $data) use ($pdo): int {
        $pdo->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($data)).') VALUES ('.implode(',',array_fill(0,count($data),'?')).')')->execute(array_values($data));
        return (int)$pdo->lastInsertId();
    };
    foreach (['QUAN_TRI_TAP_DOAN','QUAN_LY_CONG_TY','QUAN_LY_CO_SO','NHAN_VIEN','KHACH_HANG'] as $role) $insert('vai_tro',['ten_vai_tro'=>$role]);
    $companyA=$insert('cong_ty_nhuong_quyen',['ten_cong_ty'=>'ACCOUNT TEST A','dia_chi'=>'Test only']);
    $companyB=$insert('cong_ty_nhuong_quyen',['ten_cong_ty'=>'ACCOUNT TEST B','dia_chi'=>'Test only']);
    $facility=static fn(int $company,string $name): int=>$insert('co_so',['ma_cong_ty'=>$company,'ten_co_so'=>$name,'dia_chi'=>'Test only','gio_mo_cua'=>'06:00','gio_dong_cua'=>'23:00']);
    $x=$facility($companyA,'ACCOUNT TEST X'); $y=$facility($companyA,'ACCOUNT TEST Y'); $z=$facility($companyB,'ACCOUNT TEST Z');
    $password='T'.bin2hex(random_bytes(4)).'a1!';
    $accounts=new App\Services\AccountService($pdo);
    $rootAccount=$accounts->create('grant.admin',$password,'QUAN_TRI_TAP_DOAN');
    $companyAccount=$accounts->create('grant.company',$password,'QUAN_LY_CONG_TY');
    $facilityAccount=$accounts->create('grant.facility',$password,'QUAN_LY_CO_SO');
    $insert('nhan_vien',['ma_tai_khoan'=>$companyAccount,'ma_cong_ty'=>$companyA,'ho_ten'=>'TEST COMPANY MANAGER']);
    $insert('nhan_vien',['ma_tai_khoan'=>$facilityAccount,'ma_cong_ty'=>$companyA,'ma_co_so'=>$x,'ho_ten'=>'TEST FACILITY MANAGER']);
    $identity=new App\Models\TaiKhoan($pdo);
    $companyUser=$identity->identity($companyAccount); $facilityUser=$identity->identity($facilityAccount); $rootUser=$identity->identity($rootAccount);
    $companyService=new App\Services\EmployeeService($pdo,$companyUser);
    $facilityService=new App\Services\EmployeeService($pdo,$facilityUser);
    $person=static fn(string $name,int $company,?int $facility,string $status='DANG_LAM'): int=>$insert('nhan_vien',
        ['ma_cong_ty'=>$company,'ma_co_so'=>$facility,'ho_ten'=>$name,'chuc_vu'=>'Test staff','trang_thai'=>$status]);
    $target=$person('ACCOUNT TARGET',$companyA,$x);
    $outside=$person('FOREIGN TARGET',$companyB,$z);
    $sibling=$person('SIBLING TARGET',$companyA,$y);
    $retired=$person('RETIRED TARGET',$companyA,$x,'DA_NGHI');
    $invalid=$person('INVALID TARGET',$companyA,$x);
    $race=$person('RACE TARGET',$companyA,$x);
    $httpTarget=$person('HTTP TARGET',$companyA,$x);
    $browserTarget=$person('BROWSER TARGET',$companyA,$x);
    $facilityTarget=$person('FACILITY GRANT TARGET',$companyA,$x);
    $rootTarget=$person('ROOT TARGET',$companyB,null);
    $payload=static fn(string $name): array=>['username'=>$name,'password'=>$password,'password_confirmation'=>$password,'role'=>'NHAN_VIEN'];
    $before=$pdo->query('SELECT * FROM nhan_vien WHERE ma_nhan_vien='.$target)->fetch();
    $count=(int)$pdo->query('SELECT COUNT(*) FROM nhan_vien')->fetchColumn();
    $accountId=$companyService->grantAccount('QUAN_LY_CONG_TY',$companyA,$target,$payload('grant.first'));
    $after=$pdo->query('SELECT * FROM nhan_vien WHERE ma_nhan_vien='.$target)->fetch();
    $check($after['ma_tai_khoan']===$accountId && array_diff_assoc($after,$before)===['ma_tai_khoan'=>$accountId],'grant changes only employee account link');
    $check((int)$pdo->query('SELECT COUNT(*) FROM nhan_vien')->fetchColumn()===$count,'grant does not create a second employee');
    $account=$identity->findByUsername('grant.first');
    $check(password_verify($password,$account['mat_khau']) && $account['mat_khau']!==$password,'AccountService hashes compatible passwords');
    $check($identity->identity($accountId)['ten_vai_tro']==='NHAN_VIEN','granted role matches existing company policy');
    $reject=static function(callable $call,int $status,string $field='') use ($check): void {
        try { $call(); throw new RuntimeException('Expected rejection'); }
        catch (Core\HttpException $error) { $check($error->status===$status,'scope/status rejection '.$status); }
        catch (App\Services\ValidationException $error) { $check($status===422 && ($field==='' || isset($error->errors[$field])),'validation rejection '.$field); }
    };
    $reject(fn()=>$companyService->grantAccount('QUAN_LY_CONG_TY',$companyA,$target,$payload('grant.repeat')),422,'account');
    $check($identity->findByUsername('grant.repeat')===null,'repeated request creates no orphan account');
    foreach ([['username'=>'grant.first'],['password'=>'weak'],['password_confirmation'=>'wrong'],['role'=>'QUAN_TRI_TAP_DOAN']] as $change) {
        $start=(int)$pdo->query('SELECT COUNT(*) FROM tai_khoan')->fetchColumn();
        $reject(fn()=>$companyService->grantAccount('QUAN_LY_CONG_TY',$companyA,$invalid,array_replace($payload('grant.invalid'),$change)),422);
        $check((int)$pdo->query('SELECT COUNT(*) FROM tai_khoan')->fetchColumn()===$start,'failed validation rolls back account creation');
    }
    $reject(fn()=>$companyService->grantAccount('QUAN_LY_CONG_TY',$companyA,$retired,$payload('grant.retired')),422,'account');
    $reject(fn()=>$companyService->grantAccount('QUAN_LY_CONG_TY',$companyA,$outside,$payload('grant.foreign')),404);
    $reject(fn()=>$facilityService->grantAccount('QUAN_LY_CO_SO',$companyA,$sibling,$payload('grant.sibling')),404);
    $reject(fn()=>$facilityService->grantAccount('QUAN_LY_CO_SO',$companyA,$invalid,array_replace($payload('grant.elevate'),['role'=>'QUAN_LY_CO_SO'])),422,'role');
    $pdo->exec("UPDATE tai_khoan SET trang_thai='KHOA' WHERE ma_tai_khoan=$accountId");
    $reject(fn()=>$companyService->grantAccount('QUAN_LY_CONG_TY',$companyA,$target,$payload('grant.unlock')),422,'account');
    $check($identity->findByUsername('grant.first')['trang_thai']==='KHOA','grant cannot unlock an existing account');
    $pdo->exec("UPDATE co_so SET trang_thai='TAM_NGUNG' WHERE ma_co_so=$y");
    $reject(fn()=>$companyService->grantAccount('QUAN_LY_CONG_TY',$companyA,$sibling,$payload('grant.stopped')),422,'ma_co_so');
    $pdo->exec("UPDATE co_so SET trang_thai='HOAT_DONG' WHERE ma_co_so=$y");
    $facilityGranted=$facilityService->grantAccount('QUAN_LY_CO_SO',$companyA,$facilityTarget,$payload('grant.byfac'));
    $grantedIdentity=$identity->identity($facilityGranted);
    $check($grantedIdentity['ten_vai_tro']==='NHAN_VIEN' && (int)$grantedIdentity['ma_co_so']===$x,'facility manager grants staff only within assigned facility');
    $customerAccount=$accounts->create('grant.customer',$password,'KHACH_HANG');
    $insert('khach_hang',['ma_tai_khoan'=>$customerAccount,'ho_ten'=>'TEST CUSTOMER','so_dien_thoai'=>'0900000000']);
    $customerBefore=$identity->findByUsername('grant.customer');
    // Existing create/edit/status contracts remain unchanged, including optional account creation.
    $profile=['ho_ten'=>'LEGACY CREATE','ma_co_so'=>(string)$x,'trang_thai'=>'DANG_LAM'];
    $legacy=$companyService->createEmployee('QUAN_LY_CONG_TY',$companyA,$profile);
    $check($pdo->query('SELECT ma_tai_khoan FROM nhan_vien WHERE ma_nhan_vien='.$legacy)->fetchColumn()===null,'existing create without account still works');
    $companyService->updateEmployee('QUAN_LY_CONG_TY',$companyA,$legacy,array_replace($profile,['ho_ten'=>'LEGACY EDIT']));
    $companyService->changeStatus('QUAN_LY_CONG_TY',$companyA,$legacy,'DA_NGHI');
    $legacyRow=$pdo->query('SELECT ho_ten,trang_thai FROM nhan_vien WHERE ma_nhan_vien='.$legacy)->fetch();
    $check($legacyRow['ho_ten']==='LEGACY EDIT' && $legacyRow['trang_thai']==='DA_NGHI','existing edit and retirement still work');
    $createdWithAccount=$companyService->createEmployee('QUAN_LY_CONG_TY',$companyA,$profile,$payload('grant.create'));
    $check($pdo->query('SELECT ma_tai_khoan FROM nhan_vien WHERE ma_nhan_vien='.$createdWithAccount)->fetchColumn()!==null,'existing create with account still works');
    $check($identity->findByUsername('grant.customer')===$customerBefore,'employee operations do not change customer credentials');
    $rootService=new App\Services\EmployeeService($pdo,$rootUser);
    $rootService->grantAccount('QUAN_TRI_TAP_DOAN',null,$rootTarget,array_replace($payload('grant.manager'),['role'=>'QUAN_LY_CONG_TY']));
    $check($identity->findByUsername('grant.manager')!==null,'root grants only existing company-manager policy');
    // Two connections/processes cross a barrier, then contend for the same employee row.
    $env=getenv(); foreach ($_ENV as $key=>$value) $env[$key]=$value;
    $env['STRYKE_EMPLOYEE_TEST_DB']=$db; $env['STRYKE_EMPLOYEE_TEST_ROOT']=$directory;
    $env['STRYKE_EMPLOYEE_TEST_PASSWORD']=$password; $env['EMPLOYEE_ACCOUNT_ACTOR']=(string)$companyAccount;
    foreach (['grant.raceone','grant.racetwo'] as $name) {
        $worker=proc_open([PHP_BINARY,'-d','variables_order=EGPCS',__FILE__,'--worker',(string)$race,$name],[0=>['pipe','r'],1=>['file',$directory.'/'.$name.'.log','w'],2=>['file',$directory.'/'.$name.'.err','w']],$pipes,$root,$env,['bypass_shell'=>true]);
        if (!is_resource($worker)) throw new RuntimeException('Cannot start race worker');
        fclose($pipes[0]); $workers[]=$worker;
    }
    $deadline=microtime(true)+10;
    while (!is_file($directory.'/ready-grant.raceone') || !is_file($directory.'/ready-grant.racetwo')) {
        if (microtime(true)>$deadline) throw new RuntimeException('Race workers not ready'); usleep(10000);
    }
    file_put_contents($directory.'/go','go');
    foreach ($workers as $worker) $check(proc_close($worker)===0,'race worker completed'); $workers=[];
    $results=[trim(file_get_contents($directory.'/grant.raceone.log')),trim(file_get_contents($directory.'/grant.racetwo.log'))]; sort($results);
    $check($results===['created','rejected'],'two concurrent grants create exactly one account');
    $check((int)$pdo->query("SELECT COUNT(*) FROM tai_khoan WHERE ten_dang_nhap IN ('grant.raceone','grant.racetwo')")->fetchColumn()===1,'no orphan account after race');
    // HTTP uses the normal entry point, actual sessions/authentication/CSRF, and this disposable DB only.
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error); if (!$socket) throw new RuntimeException('No test port');
    $address=stream_socket_get_name($socket,false); fclose($socket);
    $server=proc_open([PHP_BINARY,'-d','variables_order=EGPCS','-S',$address,'-t','public','tests/fixtures/employee-scope.php'],
        [0=>['pipe','r'],1=>['file',$directory.'/server.log','w'],2=>['file',$directory.'/server.log','a']],$pipes,$root,$env,['bypass_shell'=>true]);
    if (!is_resource($server)) throw new RuntimeException('Cannot start test HTTP server'); fclose($pipes[0]);
    $base='http://'.$address.'/index.php';
    $cookie=$directory.'/cookies.txt';
    $http=static function(string $route,array $query=[],?array $post=null) use($base,$cookie): array {
        $curl=curl_init($base.'?'.http_build_query(['route'=>$route]+$query));
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_TIMEOUT=>10]);
        if ($post!==null) curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);
        $body=curl_exec($curl); if ($body===false) throw new RuntimeException(curl_error($curl));
        $status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE); curl_close($curl); return [$status,$body];
    };
    for ($i=0;$i<100;$i++) { $socket=@stream_socket_client('tcp://'.$address,$errno,$error,0.05); if ($socket) { fclose($socket); break; } usleep(50000); }
    $token=static function(string $html): string { if (!preg_match('/name="_token" value="([a-f0-9]+)"/',$html,$match)) throw new RuntimeException('Missing CSRF'); return $match[1]; };
    $login=static function(string $username) use($http,$password,$token,$check): void {
        [$status,$html]=$http('/login');
        [$status]=$http('/login',[],['_token'=>$token($html),'username'=>$username,'password'=>$password]);
        $check($status===303,'actual login succeeds: '.$username);
    };
    $login('grant.company');
    [$status,$html]=$http('/company/employees/edit',['id'=>$httpTarget]);
    $check($status===200 && str_contains($html,'provision-username'),'edit exposes first-account form');
    $csrf=$token($html);
    [$status]=$http('/company/employees/account',[],['id'=>$httpTarget,'cap_tai_khoan'=>'1','username'=>'grant.http','password'=>$password,'password_confirmation'=>$password,'role'=>'NHAN_VIEN']);
    $check($status===403,'grant POST requires CSRF');
    [$status,$html]=$http('/company/employees/account',[],['_token'=>$csrf,'id'=>$httpTarget,'cap_tai_khoan'=>'1','username'=>'grant.http','password'=>'bad','password_confirmation'=>'bad','role'=>'NHAN_VIEN']);
    $check($status===422 && !str_contains($html,'value="bad"') && str_contains($html,'password-error'),'invalid grant renders errors without echoing passwords');
    [$status]=$http('/company/employees/account',[],['_token'=>$csrf,'id'=>$httpTarget,'cap_tai_khoan'=>'1','username'=>'grant.http','password'=>$password,'password_confirmation'=>$password,'role'=>'NHAN_VIEN','ma_cong_ty'=>$companyB,'ma_co_so'=>$z,'ho_ten'=>'FORGED NAME']);
    $check($status===303,'HTTP grant success redirects to edit');
    [$status,$html]=$http('/company/employees/edit',['id'=>$httpTarget]);
    $check($status===200 && str_contains($html,'Đã cấp tài khoản') && !str_contains($html,'provision-username'),'edit hides provision form after success');
    $check($pdo->query('SELECT ho_ten FROM nhan_vien WHERE ma_nhan_vien='.$httpTarget)->fetchColumn()==='HTTP TARGET','grant ignores forged personnel changes');
    [$status]=$http('/company/employees/show',['id'=>$outside]); $check($status===404,'HTTP detail outside company denied');
    [$status,$html]=$http('/company/employees/show',['id'=>$httpTarget]);
    $check($status===200 && str_contains($html,'grant.http') && str_contains($html,'Đã cấp'),'detail displays newly linked account');
    [$status,$html]=$http('/company/employees/show',['id'=>$invalid]);
    $check($status===200 && str_contains($html,'Chưa cấp'),'detail displays unprovisioned employee');
    [$status,$html]=$http('/company/employees/edit',['id'=>$retired]);
    $check($status===200 && !str_contains($html,'provision-username'),'retired employee has no grant form');
    [$status]=$http('/company/employees/account',[],['_token'=>$token($html),'id'=>$outside,'cap_tai_khoan'=>'1','username'=>'grant.forbidden','password'=>$password,'password_confirmation'=>$password,'role'=>'NHAN_VIEN']);
    $check($status===404 && $identity->findByUsername('grant.forbidden')===null,'HTTP grant outside scope denied before creating account');
    [$status,$html]=$http('/company/employees');
    $check($status===200 && str_contains($html,'Xem chi tiết'),'list includes detail actions');
    $http('/logout',[],['_token'=>$token($html)]);
    $login('grant.http');
    [$status]=$http('/staff'); $check($status===200,'newly provisioned employee can access staff dashboard');
    if (in_array('--browser',$argv,true)) {
        file_put_contents($directory.'/browser-fixture.json',json_encode(['url'=>'http://'.$address,'target'=>$browserTarget,'existing'=>$httpTarget,'outside'=>$outside]));
        $browser=proc_open(['node','tests/employee-account-browser.cjs',$directory],
            [0=>['pipe','r'],1=>['file',$directory.'/browser.log','w'],2=>['file',$directory.'/browser.log','a']],$pipes,$root,$env,['bypass_shell'=>true]);
        if (!is_resource($browser)) throw new RuntimeException('Cannot start browser'); fclose($pipes[0]);
        $exit=proc_close($browser); echo file_get_contents($directory.'/browser.log');
        $check($exit===0,'Chrome desktop/mobile actual form flow');
    }
    $check(!preg_match('/PHP (Warning|Fatal error|Parse error)/',file_get_contents($directory.'/server.log')),'no PHP runtime errors');
    echo "PASS: $checks MySQL/HTTP/concurrency checks; disposable database only. Artifacts: $directory\n";
} finally {
    foreach ($workers as $worker) if (is_resource($worker)) { proc_terminate($worker); proc_close($worker); }
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack(); $pdo=null;
    if ($owned && $db!==$config['dbname'] && preg_match('/\Astryke_employee_test_[a-f0-9]{10}\z/',$db)) $admin->exec("DROP DATABASE `$db`");
}
