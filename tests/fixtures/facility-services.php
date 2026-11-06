<?php
// Disposable public browsing fixture: SQLite RAM only, never .env or the application database.
$unit = PHP_SAPI === 'cli' && defined('STRYKE_FACILITY_SERVICES_TEST');
if (!$unit && (PHP_SAPI !== 'cli-server' || getenv('STRYKE_FACILITY_SERVICES_TEST') !== '1')) { http_response_code(404); exit; }
if (!$unit && str_starts_with(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/assets/')) return false;
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$_ENV['APP_BASE_PATH']=''; $_SERVER['SCRIPT_NAME']='/index.php'; $_SESSION=[];
date_default_timezone_set('Asia/Ho_Chi_Minh');
$pdo = new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec("CREATE TABLE cong_ty_nhuong_quyen(ma_cong_ty INTEGER PRIMARY KEY,ten_cong_ty TEXT,trang_thai TEXT);
CREATE TABLE co_so(ma_co_so INTEGER PRIMARY KEY,ma_cong_ty INTEGER,ten_co_so TEXT,dia_chi TEXT,gio_mo_cua TEXT,gio_dong_cua TEXT,trang_thai TEXT,
    so_dien_thoai TEXT,email TEXT,mo_ta TEXT,ma_tinh TEXT,ten_tinh TEXT,ma_phuong_xa TEXT,ten_phuong_xa TEXT,anh_dai_dien TEXT,logo_clb TEXT);
CREATE TABLE bo_mon(ma_bo_mon INTEGER PRIMARY KEY,ten_bo_mon TEXT,trang_thai TEXT);
CREATE TABLE loai_san(ma_loai_san INTEGER PRIMARY KEY,ma_bo_mon INTEGER,ten_loai_san TEXT,trang_thai TEXT);
CREATE TABLE san(ma_san INTEGER PRIMARY KEY,ma_co_so INTEGER,ma_loai_san INTEGER,ten_san TEXT,trang_thai TEXT,vi_tri TEXT,mo_ta TEXT);
CREATE TABLE bang_gia(ma_bang_gia INTEGER PRIMARY KEY,ma_co_so INTEGER,ma_loai_san INTEGER,loai_ngay TEXT,gio_bat_dau TEXT,gio_ket_thuc TEXT,don_gia TEXT,ngay_bat_dau TEXT,ngay_ket_thuc TEXT,trang_thai TEXT);
CREATE TABLE dich_vu(ma_dich_vu INTEGER PRIMARY KEY,ma_co_so INTEGER,ten_dich_vu TEXT,loai_dich_vu TEXT,don_gia TEXT,don_vi_tinh TEXT,trang_thai TEXT,so_luong_ton INTEGER);
CREATE TABLE dat_san(ma_dat_san INTEGER PRIMARY KEY,ma_co_so INTEGER,trang_thai_dat_san TEXT,het_han_giu_san_luc TEXT);
CREATE TABLE chi_tiet_dat_san(ma_dat_san INTEGER,ma_san INTEGER,ngay_choi TEXT,gio_bat_dau TEXT,gio_ket_thuc TEXT);
INSERT INTO cong_ty_nhuong_quyen VALUES(1,'Test Company A','HOAT_DONG'),(2,'Test Company B','HOAT_DONG'),(3,'Inactive Company','NGUNG_HOAT_DONG');
INSERT INTO bo_mon VALUES(1,'Badminton','HOAT_DONG'),(2,'Football','HOAT_DONG'),(3,'Inactive sport','NGUNG');
INSERT INTO loai_san VALUES(1,1,'Standard','HOAT_DONG'),(2,1,'VIP','HOAT_DONG'),(3,2,'Five-a-side','HOAT_DONG'),(4,1,'Inactive type','NGUNG');
INSERT INTO san VALUES(1,1,1,'Court 1','HOAT_DONG',NULL,NULL),(2,1,1,'Court 2','HOAT_DONG',NULL,NULL),
    (3,1,2,'VIP Court','HOAT_DONG',NULL,NULL),(4,1,3,'Football Court','HOAT_DONG',NULL,NULL),
    (5,3,1,'Foreign Court','HOAT_DONG',NULL,NULL),(6,1,4,'Inactive type court','HOAT_DONG',NULL,NULL),
    (7,1,1,'Closed court','BAO_TRI',NULL,NULL),(8,6,1,'Single type court','HOAT_DONG',NULL,NULL);
INSERT INTO bang_gia VALUES
    (1,1,1,'NGAY_THUONG','05:00:00','12:00:00','50000.00','2020-01-01',NULL,'DANG_AP_DUNG'),
    (2,1,1,'CUOI_TUAN','05:00:00','12:00:00','50000.00','2020-01-01',NULL,'DANG_AP_DUNG'),
    (3,1,1,'NGAY_THUONG','17:00:00','23:00:00','70000.00','2020-01-01',NULL,'DANG_AP_DUNG'),
    (4,1,1,'CUOI_TUAN','17:00:00','23:00:00','80000.00','2020-01-01',NULL,'DANG_AP_DUNG'),
    (5,1,2,'NGAY_THUONG','05:00:00','12:00:00','110000.00','2020-01-01',NULL,'DANG_AP_DUNG'),
    (6,1,2,'CUOI_TUAN','05:00:00','12:00:00','110000.00','2020-01-01',NULL,'DANG_AP_DUNG'),
    (7,1,3,'NGAY_THUONG','06:00:00','23:00:00','90000.00','2020-01-01',NULL,'DANG_AP_DUNG'),
    (8,3,1,'NGAY_THUONG','06:00:00','23:00:00','999999.00','2020-01-01',NULL,'DANG_AP_DUNG'),
    (9,1,1,'NGAY_THUONG','12:00:00','17:00:00','777777.00','2099-01-01',NULL,'DANG_AP_DUNG'),
    (10,1,1,'NGAY_THUONG','12:00:00','17:00:00','666666.00','2000-01-01','2001-01-01','DANG_AP_DUNG'),
    (11,1,1,'NGAY_THUONG','12:00:00','17:00:00','555555.00','2020-01-01',NULL,'NGUNG'),
    (12,6,1,'NGAY_THUONG','06:00:00','23:00:00','40000.00','2020-01-01',NULL,'DANG_AP_DUNG');
INSERT INTO dich_vu VALUES(1,1,'Water <script>attack()</script>','DO_UONG','10000.00','Bottle','DANG_CUNG_CAP',777),
    (2,1,'Racket','CHO_THUE','20000.00','Hour','DANG_CUNG_CAP',888),
    (3,1,'Coach','HUAN_LUYEN','150000.00','Hour','DANG_CUNG_CAP',NULL),
    (4,1,'Stopped service','KHAC','333333.00','Unit','NGUNG',999),
    (5,3,'Foreign service','DO_UONG','999999.00','Bottle','DANG_CUNG_CAP',123),
    (6,4,'Inactive facility service','DO_UONG','888888.00','Bottle','DANG_CUNG_CAP',456),
    (7,5,'Inactive company service','DO_UONG','777777.00','Bottle','DANG_CUNG_CAP',789);");
$insert=$pdo->prepare('INSERT INTO co_so(ma_co_so,ma_cong_ty,ten_co_so,dia_chi,gio_mo_cua,gio_dong_cua,trang_thai,mo_ta) VALUES(?,?,?,?,?,?,?,?)');
foreach ([[1,1,'Test Facility A','HOAT_DONG'],[2,1,'Empty Facility','HOAT_DONG'],[3,2,'Foreign Facility','HOAT_DONG'],
    [4,1,'Inactive Facility','TAM_NGUNG'],[5,3,'Inactive Company Facility','HOAT_DONG'],[6,1,'Single Type Facility','HOAT_DONG']] as [$id,$company,$name,$status]) {
    $insert->execute([$id,$company,$name,'Test address','05:00:00','23:00:00',$status,'Existing facility information.']);
}
$pdo->exec('PRAGMA query_only=ON');
$app=new Core\Application($pdo);
$controller=new App\Controllers\CourtBrowserController($app);
if (!$unit) {
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
    try {
        if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') throw new Core\HttpException(405,'Read-only fixture');
        match ($_GET['route']??'/courts') {
            '/courts'=>$controller->index(), '/courts/show'=>$controller->show(), '/courts/panel'=>$controller->panel(),
            '/courts/schedule'=>$controller->schedule(), default=>throw new Core\HttpException(404,'Not found'),
        };
    } catch (Core\HttpException $error) { http_response_code($error->status); echo e($error->getMessage()); }
}
