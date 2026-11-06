<?php
// Explicit opt-in. Every write stays in an owned local database, never the application DB.
if (PHP_SAPI !== 'cli' || !in_array('--run', $argv, true)) exit("php tests/company-create-integration.php --run [--label=before]\n");
require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
date_default_timezone_set('Asia/Ho_Chi_Minh');
$root = dirname(__DIR__);
$config = require $root . '/config/database.php';
if (!in_array($config['host'], ['localhost','127.0.0.1','::1'], true)) throw new RuntimeException('Local test server required');
$database = 'stryke_company_test_' . bin2hex(random_bytes(5));
if ($database === $config['dbname'] || !preg_match('/\Astryke_company_test_[a-f0-9]{10}\z/', $database)) throw new RuntimeException('Unsafe test database');
$directory = $root . '/storage/temp/company-create-' . bin2hex(random_bytes(5));
mkdir($directory . '/sessions', 0700, true);
$result = ['checks'=>[], 'database'=>$database];
$server = null; $admin = null; $pdo = null; $created = false;
$check = static function(bool $ok, string $label, mixed $evidence = null) use (&$result): void {
    $result['checks'][] = ['ok'=>$ok,'label'=>$label,'evidence'=>$evidence];
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
};
try {
    $admin = new PDO("mysql:host={$config['host']};port={$config['port']};charset=utf8mb4", $config['username'], $config['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $admin->exec('CREATE DATABASE ' . $database . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    $pdo = new PDO("mysql:host={$config['host']};port={$config['port']};dbname=$database;charset=utf8mb4", $config['username'], $config['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) throw new RuntimeException('Wrong test database');
    $schema = preg_replace('/CREATE DATABASE.*?;|USE quanly_chuoi_san_the_thao;/s', '', file_get_contents($root . '/database/quanly_chuoi_san_the_thao.sql'));
    $pdo->exec($schema);
    $insert = static function(string $table, array $data) use ($pdo): int {
        $pdo->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($data)).') VALUES ('.implode(',',array_fill(0,count($data),'?')).')')->execute(array_values($data));
        return (int)$pdo->lastInsertId();
    };
    $company = $insert('cong_ty_nhuong_quyen', ['ten_cong_ty'=>'Test scope company','dia_chi'=>'Local test']);
    $accounts = new App\Services\AccountService($pdo);
    $rootId = $accounts->create('ctroot01', 'Company1!', 'QUAN_TRI_TAP_DOAN');
    $manager = $accounts->create('ctmanager', 'Company1!', 'QUAN_LY_CONG_TY');
    $insert('nhan_vien',['ma_tai_khoan'=>$manager,'ma_cong_ty'=>$company,'ho_ten'=>'Company test manager']);
    $customer = $accounts->create('ctclient', 'Company1!', 'KHACH_HANG');
    $insert('khach_hang',['ma_tai_khoan'=>$customer,'ho_ten'=>'Company test customer','so_dien_thoai'=>'0900000000']);
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if (!$socket) throw new RuntimeException($errstr);
    $address = stream_socket_get_name($socket, false); fclose($socket);
    $base = 'http://' . $address;
    $environment = array_merge(getenv(), ['DB_HOST'=>$config['host'],'DB_PORT'=>(string)$config['port'],'DB_NAME'=>$database,'DB_USER'=>$config['username'],'DB_PASS'=>$config['password'],'APP_BASE_PATH'=>'/']);
    $server = proc_open([PHP_BINARY,'-d','variables_order=EGPCS','-d','session.save_path='.$directory.'/sessions','-S',$address,'-t',$root.'/public'], [0=>['pipe','r'],1=>['file',$directory.'/server.log','a'],2=>['file',$directory.'/server.log','a']], $pipes, $root, $environment, ['bypass_shell'=>true]);
    if (!is_resource($server)) throw new RuntimeException('HTTP test server failed');
    fclose($pipes[0]);
    $ip = '127.0.0.' . random_int(100,240);
    $client = static function() use ($ip): CurlHandle {
        $curl = curl_init();
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>15,CURLOPT_INTERFACE=>$ip]);
        return $curl;
    };
    $request = static function(CurlHandle $curl, string $route, ?array $fields = null, array $query = []) use ($base): array {
        curl_setopt($curl,CURLOPT_URL,$base.'/index.php?'.http_build_query(['route'=>$route]+$query));
        curl_setopt($curl,CURLOPT_POST,$fields!==null);
        if ($fields!==null) curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($fields));
        $raw=curl_exec($curl);
        if ($raw===false) throw new RuntimeException(curl_error($curl));
        $n=curl_getinfo($curl,CURLINFO_HEADER_SIZE);
        return ['status'=>(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE),'headers'=>substr($raw,0,$n),'body'=>substr($raw,$n)];
    };
    $hidden = static function(array $response): array {
        preg_match_all('/<input\b[^>]*type="hidden"[^>]*>/i',$response['body'],$tags);
        $fields=[];
        foreach($tags[0] as $tag) if(preg_match('/name="([^"]+)"/',$tag,$name)&&preg_match('/value="([^"]*)"/',$tag,$value)) $fields[$name[1]]=html_entity_decode($value[1],ENT_QUOTES,'UTF-8');
        return $fields;
    };
    for($i=0;$i<50;$i++){try{$probe=$request($client(),'/login');if($probe['status']===200)break;}catch(Throwable $ignored){}usleep(100000);}
    $login = static function(string $username) use ($client,$request,$hidden): CurlHandle {
        $curl=$client();$fields=$hidden($request($curl,'/login'));
        $r=$request($curl,'/login',$fields+['username'=>$username,'password'=>'Company1!']);
        if(!in_array($r['status'],[302,303],true))throw new RuntimeException('Login prerequisite failed HTTP '.$r['status']);
        return $curl;
    };
    $rootClient=$login('ctroot01');
    $form=$request($rootClient,'/admin/companies/create');
    $check($form['status']===200,'root can open create form');
    $data=['ten_cong_ty'=>'Company create fixture','ma_so_thue'=>'TEST-COMPANY-001','nguoi_dai_dien'=>'Local representative','dia_chi'=>'Local test address','so_dien_thoai'=>'0900000000','email'=>'company@example.test','ngay_tham_gia'=>'2026-11-06','trang_thai'=>'HOAT_DONG'];
    $fields=$hidden($form)+$data;
    $r=$request($rootClient,'/admin/companies',$fields);
    $check($r['status']===303,'valid create redirects HTTP 303',$r['status']);
    $id=(int)$pdo->query("SELECT ma_cong_ty FROM cong_ty_nhuong_quyen WHERE ma_so_thue='TEST-COMPANY-001'")->fetchColumn();
    $row=(new App\Models\CongTyNhuongQuyen($pdo))->find($id);
    $check($row!==null&&$row['ten_cong_ty']===$data['ten_cong_ty']&&$row['ngay_tham_gia']===$data['ngay_tham_gia'],'valid fields persisted correctly');
    $count=static fn():int=>(int)$pdo->query('SELECT COUNT(*) FROM cong_ty_nhuong_quyen')->fetchColumn();
    $before=$count();$replay=$request($rootClient,'/admin/companies',$fields);
    $check(in_array($replay['status'],[303,422],true)&&$count()===$before,'successful form replay cannot create duplicate or return 500',$replay['status']);
    $other=$hidden($request($rootClient,'/admin/companies/create'))+$data;
    $duplicate=$request($rootClient,'/admin/companies',$other);
    $check($duplicate['status']===422&&str_contains($duplicate['body'],'ma_so_thue-error')&&$count()===$before,'duplicate tax ID is field validation HTTP 422',$duplicate['status']);
    $normalized=$data;
    try{(new App\Services\CompanyService($pdo))->create($normalized);$check(false,'service translates duplicate unique violation');}
    catch(App\Services\ValidationException $e){$check(isset($e->errors['ma_so_thue']),'service translates duplicate unique violation');}
    catch(PDOException $e){$result['rootCause']=['sqlstate'=>$e->errorInfo[0]??null,'driver'=>$e->errorInfo[1]??null,'message'=>$e->getMessage(),'trace'=>$e->getTraceAsString()];$check(false,'service translates duplicate unique violation',$e->errorInfo);}
    foreach(['missing-name'=>['ten_cong_ty'=>''],'missing-address'=>['dia_chi'=>''],'bad-email'=>['email'=>'not-email'],'bad-date'=>['ngay_tham_gia'=>'2026-02-30'],'bad-state'=>['trang_thai'=>'TU_CHOI'],'array-input'=>['ten_cong_ty'=>['bad']],'too-long'=>['ten_cong_ty'=>str_repeat('x',201)]] as $label=>$changes){
        $f=$hidden($request($rootClient,'/admin/companies/create'));$r=$request($rootClient,'/admin/companies',$f+array_replace($data,$changes));
        $check($r['status']===422&&$count()===$before,'validation '.$label.' without database write',$r['status']);
    }
    $empty=array_replace($data,['ten_cong_ty'=>'Optional fields fixture','ma_so_thue'=>'','nguoi_dai_dien'=>'','email'=>'','so_dien_thoai'=>'','ngay_tham_gia'=>'']);
    $emptyFields=$hidden($request($rootClient,'/admin/companies/create'))+$empty;
    $r=$request($rootClient,'/admin/companies',$emptyFields);$check($r['status']===303,'optional blank fields can be saved');
    $emptyRow=$pdo->query("SELECT * FROM cong_ty_nhuong_quyen WHERE ten_cong_ty='Optional fields fixture' ORDER BY ma_cong_ty DESC LIMIT 1")->fetch();
    $check($emptyRow['ma_so_thue']===null&&$emptyRow['email']===null&&$emptyRow['ngay_tham_gia']===null,'optional empty values remain SQL NULL');
    $before=$count();$r=$request($rootClient,'/admin/companies',$emptyFields);
    $check(in_array($r['status'],[303,422],true)&&$count()===$before,'replay with NULL tax ID does not create another company',$r['status']);
    $validRetry=$hidden($request($rootClient,'/admin/companies/create'));
    $r=$request($rootClient,'/admin/companies',$validRetry+array_replace($data,['ten_cong_ty'=>'']));
    $retryFields=$hidden($r)+array_replace($data,['ma_so_thue'=>'TEST-COMPANY-002','ten_cong_ty'=>'Validation retry fixture']);
    $r=$request($rootClient,'/admin/companies',$retryFields);$check($r['status']===303,'validation failure retains a usable form for correction');
    $before=$count();$badTokenFields=$hidden($request($rootClient,'/admin/companies/create'))+$empty;
    unset($badTokenFields['company_create_token']);
    $r=$request($rootClient,'/admin/companies',$badTokenFields);
    $check($r['status']===422&&$count()===$before,'missing create token is recoverable validation, not an insert');
    $badTokenFields['company_create_token']=str_repeat('0',48);
    $r=$request($rootClient,'/admin/companies',$badTokenFields);
    $check($r['status']===422&&$count()===$before,'forged create token cannot write');
    $tabs=[$hidden($request($rootClient,'/admin/companies/create')),$hidden($request($rootClient,'/admin/companies/create'))];
    $check($tabs[0]['company_create_token']!==$tabs[1]['company_create_token'],'separate tabs receive independent create tokens');
    foreach($tabs as $n=>$tab){$r=$request($rootClient,'/admin/companies',$tab+array_replace($data,['ma_so_thue'=>'TEST-TAB-'.$n]));$check($r['status']===303,'other open form remains usable tab '.$n);}
    $before=$count();
    foreach($tabs as $n=>$tab)$request($rootClient,'/admin/companies',$tab+array_replace($data,['ma_so_thue'=>'TEST-TAB-'.$n]));
    $check($count()===$before,'replaying either tab cannot create another company');
    $caseFields=$hidden($request($rootClient,'/admin/companies/create'))+array_replace($data,['ma_so_thue'=>' test-company-001 ']);
    $r=$request($rootClient,'/admin/companies',$caseFields);
    $check($r['status']===422&&$count()===$before,'tax normalization and DB collation still reject duplicate');
    $pdo->exec("CREATE TRIGGER company_test_fault BEFORE INSERT ON cong_ty_nhuong_quyen FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Owned test database fault'");
    $propagated=false;
    try{(new App\Services\CompanyService($pdo))->create(array_replace($data,['ma_so_thue'=>'TEST-FAULT']));}catch(PDOException $e){$propagated=($e->errorInfo[0]??'')==='45000';}
    finally{$pdo->exec('DROP TRIGGER company_test_fault');}
    $check($propagated&&$count()===$before,'unrelated database failures are not mislabeled as duplicate tax validation');
    $editFields=$hidden($request($rootClient,'/admin/companies/edit',null,['id'=>$id]));
    $r=$request($rootClient,'/admin/companies/update',$editFields+array_replace($data,['ten_cong_ty'=>'Updated company fixture']),['id'=>$id]);
    $check($r['status']===303&&(new App\Models\CongTyNhuongQuyen($pdo))->find($id)['ten_cong_ty']==='Updated company fixture','edit same tax ID remains valid');
    $r=$request($rootClient,'/admin/companies/update',$editFields+array_replace($data,['ma_so_thue'=>'TEST-COMPANY-002']),['id'=>$id]);
    $check($r['status']===422&&(new App\Models\CongTyNhuongQuyen($pdo))->find($id)['ma_so_thue']==='TEST-COMPANY-001','edit duplicate tax ID is validation and preserves row',$r['status']);
    $check($request($rootClient,'/admin/companies')['status']===200,'company list still renders');
    $check($request($rootClient,'/admin/companies/edit',null,['id'=>$id])['status']===200,'existing company information/edit page still renders');
    $contractData=['ma_cong_ty'=>(string)$id,'so_hop_dong'=>'TEST-CONTRACT-001','ngay_ky'=>'2026-11-06','ngay_bat_dau'=>'2026-11-06','ngay_ket_thuc'=>'2027-11-06','phi_nhuong_quyen'=>'1000000','ty_le_phi'=>'5','noi_dung'=>'Test contract','trang_thai'=>'HIEU_LUC'];
    $contractId=(new App\Services\ContractService($pdo))->create($contractData);
    $facility=$insert('co_so',['ma_cong_ty'=>$id,'ten_co_so'=>'Linked test facility','dia_chi'=>'Local address','gio_mo_cua'=>'05:00','gio_dong_cua'=>'23:00']);
    $linked=$accounts->create('ctlinked','Company1!','QUAN_LY_CONG_TY');
    $insert('nhan_vien',['ma_tai_khoan'=>$linked,'ma_cong_ty'=>$id,'ho_ten'=>'Linked company manager']);
    $check((new App\Services\ContractService($pdo))->byCompany($id)[0]['ma_hop_dong']===$contractId,'contract foreign key and lookup target created company');
    $check($request($rootClient,'/admin/contracts')['status']===200,'contract list still renders');
    $check($request($rootClient,'/admin/contracts/edit',null,['id'=>$contractId])['status']===200,'linked contract information/edit still renders');
    $identity=(new App\Models\TaiKhoan($pdo))->identity($linked);
    $check((int)$identity['ma_cong_ty']===$id,'company manager identity links correct company');
    $linkedClient=$login('ctlinked');
    $profile=$request($linkedClient,'/company/profile');
    $check($profile['status']===200&&str_contains($profile['body'],'Updated company fixture'),'linked manager sees company information and existing relations');
    $r=$request($rootClient,'/admin/companies/status',$editFields+['trang_thai'=>'NGUNG_HOAT_DONG'],['id'=>$id]);
    $check($r['status']===303&&(new App\Models\CongTyNhuongQuyen($pdo))->find($id)['trang_thai']==='NGUNG_HOAT_DONG','company deactivation still works');
    $check(count((new App\Services\ContractService($pdo))->byCompany($id))===1&&(int)$pdo->query('SELECT ma_cong_ty FROM co_so WHERE ma_co_so='.$facility)->fetchColumn()===$id,'deactivation preserves contract and facility relations');
    $before=$count();
    foreach(['ctmanager','ctclient'] as $username){$curl=$login($username);$check($request($curl,'/admin/companies/create')['status']===403,'unauthorized GET denied '.$username);$token=$hidden($request($curl,'/'));$check(isset($token['_token']),'unauthorized role test has valid CSRF '.$username);$r=$request($curl,'/admin/companies',$token+$data);$check($r['status']===403,'unauthorized POST denied '.$username);}
    $check($count()===$before,'unauthorized requests do not write companies');
    $noCsrf=$data;$r=$request($rootClient,'/admin/companies',$noCsrf);$check($r['status']===403&&$count()===$before,'create still enforces CSRF');
    $result['completed']=true;
}catch(Throwable $e){$result['failure']=$e->getMessage();$result['trace']=$e->getTraceAsString();fwrite(STDERR,$e->getMessage().PHP_EOL);}
finally{
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    if($admin&&$created){$pdo=null;$admin->exec('DROP DATABASE '.$database);$result['cleanup']=['removed'=>$database];}
    $result['pass']=count(array_filter($result['checks'],static fn($r)=>$r['ok']));$result['fail']=count($result['checks'])-$result['pass'];
    file_put_contents($directory.'/report.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    echo json_encode(['pass'=>$result['pass'],'fail'=>$result['fail'],'completed'=>$result['completed']??false,'artifact'=>$directory]),PHP_EOL;
}
exit(!empty($result['failure'])||$result['fail']>0?1:0);
