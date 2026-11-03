<?php
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Services\CourtPriceList;
use App\Models\CourtBrowser;
$checks=0;
function check($ok): void { global $checks; if (!$ok) throw new RuntimeException('Failed check '.($checks+1)); $checks++; }
$base=['ma_bo_mon'=>1,'ten_bo_mon'=>'Cầu lông','ma_loai_san'=>1,'ten_loai_san'=>'Sân tiêu chuẩn',
    'ma_bang_gia'=>1,'loai_ngay'=>'NGAY_THUONG','gio_bat_dau'=>'05:00:00','gio_ket_thuc'=>'12:00:00',
    'don_gia'=>'50000.00','ngay_bat_dau'=>'2026-01-01','ngay_ket_thuc'=>null];
$weekend=array_replace($base,['ma_bang_gia'=>2,'loai_ngay'=>'CUOI_TUAN']);
$rates=fn($rows)=>CourtPriceList::group($rows)[0]['types'][1]['rates'];
check(count($rates([$base,$weekend]))===1 && $rates([$base,$weekend])[0]['day_label']==='T2–CN');
check(count($rates([$base,array_replace($weekend,['don_gia'=>'80000.00'])]))===2);
check(count($rates([$base,array_replace($weekend,['ngay_ket_thuc'=>'2026-12-31'])]))===2);
check(count($rates([$base,array_replace($weekend,['gio_ket_thuc'=>'13:00:00'])]))===2);
check($rates([$base])[0]['day_label']==='T2–T6');
$evening=array_replace($base,['ma_bang_gia'=>3,'gio_bat_dau'=>'17:00:00','gio_ket_thuc'=>'22:00:00','don_gia'=>'70000.00']);
$mixed=[$base,$weekend,$evening,array_replace($evening,['ma_bang_gia'=>4,'loai_ngay'=>'CUOI_TUAN','don_gia'=>'80000.00'])];
check(count($rates($mixed))===3);
$weekdayRows=$rates([$evening,$base,array_replace($base,['ma_bang_gia'=>5,'gio_bat_dau'=>'12:00:00','gio_ket_thuc'=>'17:00:00'])]);
check(array_column($weekdayRows,'day_span')===[3,0,0]);
check(array_column($weekdayRows,'gio_bat_dau')===['05:00:00','12:00:00','17:00:00']);
check(array_column($rates($mixed),'day_label')===['T2–CN','T2–T6','T7–CN']);
check(array_column($rates($mixed),'day_span')===[1,1,1]);
$twoTypes=CourtPriceList::group([$base,$evening,array_replace($base,['ma_loai_san'=>2,'ma_bang_gia'=>6,'don_gia'=>'110000.00'])]);
check(count($twoTypes[0]['types'][1]['rates'])===2 && count($twoTypes[0]['types'][2]['rates'])===1);
check($twoTypes[0]['types'][2]['rates'][0]['don_gia']==='110000.00');
check(count(CourtPriceList::group([$base,array_replace($weekend,['ma_loai_san'=>2])])[0]['types'])===2);
check(count(CourtPriceList::group([$base,array_replace($weekend,['ma_bo_mon'=>2])]))===2);
check(CourtPriceList::group([])===[]);
check($rates([array_replace($base,['ma_bang_gia'=>null])])===[]);
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec("CREATE TABLE san(ma_san INT,ma_co_so INT,ma_loai_san INT,trang_thai TEXT);
CREATE TABLE loai_san(ma_loai_san INT,ma_bo_mon INT,ten_loai_san TEXT,trang_thai TEXT);
CREATE TABLE bo_mon(ma_bo_mon INT,ten_bo_mon TEXT,trang_thai TEXT);
CREATE TABLE co_so(ma_co_so INT,ma_cong_ty INT,trang_thai TEXT);
CREATE TABLE cong_ty_nhuong_quyen(ma_cong_ty INT,trang_thai TEXT);
CREATE TABLE bang_gia(ma_bang_gia INT,ma_co_so INT,ma_loai_san INT,loai_ngay TEXT,gio_bat_dau TEXT,gio_ket_thuc TEXT,don_gia TEXT,ngay_bat_dau TEXT,ngay_ket_thuc TEXT,trang_thai TEXT);
INSERT INTO cong_ty_nhuong_quyen VALUES(1,'HOAT_DONG');
INSERT INTO co_so VALUES(1,1,'HOAT_DONG');
INSERT INTO bo_mon VALUES(1,'Cầu lông','HOAT_DONG'),(2,'Pickleball','HOAT_DONG'),(3,'Không có sân','HOAT_DONG');
INSERT INTO loai_san VALUES(1,1,'Tiêu chuẩn','HOAT_DONG'),(2,2,'Đôi','HOAT_DONG');
INSERT INTO san VALUES(1,1,1,'HOAT_DONG'),(2,1,1,'HOAT_DONG'),(3,1,2,'HOAT_DONG');");
$insert=$pdo->prepare('INSERT INTO bang_gia VALUES(?,1,1,?,?,?,?,?,?,?)');
foreach ($mixed as $row) $insert->execute([$row['ma_bang_gia'],$row['loai_ngay'],$row['gio_bat_dau'],$row['gio_ket_thuc'],$row['don_gia'],$row['ngay_bat_dau'],$row['ngay_ket_thuc'],'DANG_AP_DUNG']);
$model=new CourtBrowser($pdo);
check(count($model->priceList(1,'2026-11-03'))===5); // Four prices once, plus unpriced second sport.
check(count($model->priceList(1,'2025-01-01'))===2); // Both types have no effective price.
check($model->priceList(2,'2026-11-03')===[]);
$pdo->exec("UPDATE san SET trang_thai='BAO_TRI' WHERE ma_loai_san=2");
check(count($model->priceList(1,'2026-11-03'))===4);
$pdo->exec("UPDATE cong_ty_nhuong_quyen SET trang_thai='NGUNG'");
check($model->priceList(1,'2026-11-03')===[]);
if (in_array('--render',$argv,true)) {
    $_SERVER['SCRIPT_NAME']='/BaiTapNhomPHP/public/index.php';
    $sports=CourtPriceList::group(array_merge($mixed,[array_replace($base,['ma_bo_mon'=>2,'ten_bo_mon'=>'Pickleball','ma_loai_san'=>2,'ma_bang_gia'=>null])]));
    Core\View::render('courts/prices',['sports'=>$sports,'facility'=>['ten_co_so'=>'Cơ sở kiểm thử'],'date'=>'2026-11-03','priceBackUrl'=>url('/courts/schedule').'&id=1&date=2026-11-03','title'=>'Xem bảng giá sân']);
} else echo "PASS: $checks price-list checks (isolated SQLite).\n";
