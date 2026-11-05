<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('STRYKE_PR6_CONTRACT', true);
define('STRYKE_RECEIPT_NUMERIC', true);
require __DIR__ . '/fixtures/booking-pr6.php';
use App\Services\{BookingManagementService, BookingReceiptService, ReceiptImage, ValidationException};
use Core\HttpException;

$directory = dirname(__DIR__) . '/storage/temp/receipt-confirmation-review/unit';
if (!is_dir($directory . '/receipts')) mkdir($directory . '/receipts', 0700, true);
$imagePath = 'receipts/receipt_1_0123456789abcdef.png';
file_put_contents($directory . '/' . $imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aLX0AAAAASUVORK5CYII='));
$insert('tai_khoan', ['ma_tai_khoan' => 1, 'ten_dang_nhap' => 'Fixture confirmer', 'trang_thai' => 'HOAT_DONG']);
$receipts = new BookingReceiptService($pdo, $directory);
$service = new BookingManagementService($pdo, receipts: $receipts);
$model = new App\Models\ThanhToan($pdo);
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void { if (!$ok) throw new RuntimeException($label); $checks++; };
$snapshot = static function () use ($pdo): array {
    $data=[]; foreach (['dat_san','thanh_toan','dich_vu','chi_tiet_dich_vu_dat','hoa_don'] as $table) $data[$table]=$pdo->query("SELECT * FROM $table ORDER BY 1")->fetchAll(); return $data;
};
$seed = $snapshot();
$reset = static function (bool $withReceipt = true) use ($pdo,$seed,$imagePath): void {
    foreach ($seed as $table=>$rows) {
        $pdo->exec('DELETE FROM ' . $table);
        foreach ($rows as $row) $pdo->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row));
    }
    if ($withReceipt) $pdo->prepare('UPDATE thanh_toan SET anh_minh_chung=?, minh_chung_gui_luc=? WHERE ma_dat_san=1')->execute([$imagePath,date('Y-m-d H:i:s')]);
};
$identity = ['ma_tai_khoan'=>1,'trang_thai'=>'HOAT_DONG','trang_thai_khach'=>'HOAT_DONG','ma_nhan_vien'=>1,'trang_thai_nhan_vien'=>'DANG_LAM',
    'ma_cong_ty'=>1,'ma_co_so'=>1,'cong_ty_cua_co_so'=>1,'trang_thai_cong_ty'=>'HOAT_DONG','trang_thai_co_so'=>'HOAT_DONG'];
$admin = ['ten_vai_tro'=>'QUAN_TRI_TAP_DOAN']+$identity;
$reject = static function (callable $call,string $label) use ($snapshot,$check,$pdo): void {
    $before=$snapshot();
    try {$call();} catch (ValidationException|HttpException $e) { $check($snapshot()===$before && !$pdo->inTransaction(),$label.': no partial writes');return; }
    throw new RuntimeException('Expected rejection: '.$label);
};
try {
    // Execute the exact production CHECK expression on a separate RAM table.
    $schema=file_get_contents(dirname(__DIR__).'/database/quanly_chuoi_san_the_thao.sql');
    $constraint=trim(explode(') ENGINE',explode('CONSTRAINT chk_thanhtoan_thanhcong',$schema,2)[1],2)[0]);
    $pdo->exec('CREATE TABLE receipt_constraint (trang_thai TEXT NOT NULL, so_tien NUMERIC NOT NULL, so_tien_thuc_nhan NUMERIC,
        ngay_thanh_toan TEXT, ma_giao_dich TEXT, anh_minh_chung TEXT, ma_tai_khoan_xac_nhan INTEGER, xac_nhan_thu_cong_luc TEXT, '.$constraint.')');
    $constraintInsert=$pdo->prepare('INSERT INTO receipt_constraint VALUES (?,?,?,?,?,?,?,?)');
    foreach ([['THANH_CONG',60000,60000,null,null,$imagePath,1,date('Y-m-d H:i:s')],
        ['THANH_CONG',60000,60000,date('Y-m-d H:i:s'),'REAL-MANUAL-BANK-ID',null,null,null]] as $row) {
        $check($constraintInsert->execute($row),'production CHECK accepts complete manual proof or legacy bank facts');
    }
    foreach ([['THANH_CONG',60000,60000,null,null,null,1,date('Y-m-d H:i:s')],
        ['THANH_CONG',60000,60000,null,null,$imagePath,null,date('Y-m-d H:i:s')],
        ['THANH_CONG',60000,55000,null,null,$imagePath,1,date('Y-m-d H:i:s')]] as $row) {
        try {$constraintInsert->execute($row);throw new RuntimeException('Incomplete payment accepted by CHECK');}
        catch(PDOException $e){$check(true,'production CHECK rejects missing evidence/actor or wrong amount');}
    }
    $reset(false); $reject(fn()=>$service->confirmReceiptPayment(1,$admin),'missing receipt');
    $check($model->supportsManualReceipt(),'new schema supports explicit receipt/confirmation metadata');
    $check($receipts->info($model->findByBookingId(1))===null,'orphan disk file is never proof');
    foreach (['QUAN_TRI_TAP_DOAN','QUAN_LY_CONG_TY','QUAN_LY_CO_SO','NHAN_VIEN'] as $role) {
        $reset(); $user=['ten_vai_tro'=>$role]+$identity; $before=$snapshot();
        $check($receipts->authorized(1,$user)['path']===$imagePath,$role.': scoped image access');
        $reserved=(new App\Models\Booking($pdo))->reserved(1,date('Y-m-d H:i:s'));
        $result=$service->confirmReceiptPayment(1,$user);$after=$snapshot();$payment=$model->findByBookingId(1);
        $check($result['success'] && $payment['trang_thai']==='THANH_CONG' && $after['dat_san'][0]['trang_thai_dat_san']==='DA_XAC_NHAN',$role.': atomic booking/payment');
        $check($payment['ma_giao_dich']===null && $payment['ngay_thanh_toan']===null,$role.': no fake bank ID/time');
        $check((int)$payment['ma_tai_khoan_xac_nhan']===1 && !empty($payment['xac_nhan_thu_cong_luc']),$role.': confirmation actor/time persisted separately');
        $check((float)$payment['so_tien_thuc_nhan']===60000.0,$role.': amount recorded as human full-receipt attestation');
        $check((int)$after['dich_vu'][0]['so_luong_ton']===198 && count($after['hoa_don'])===1,$role.': one stock deduction and invoice');
        $check($reserved===2 && (new App\Models\Booking($pdo))->reserved(1,date('Y-m-d H:i:s'))===0,$role.': virtual hold removed by same confirmation commit');
        $check($after['chi_tiet_dich_vu_dat']===$before['chi_tiet_dich_vu_dat'] && $after['dat_san'][0]['het_han_giu_san_luc']===$before['dat_san'][0]['het_han_giu_san_luc'],$role.': snapshots and original hold unchanged');
        $reject(fn()=>$service->confirmReceiptPayment(1,$user),$role.': retry cannot deduct twice');
        $dashboard=(new App\Models\Dashboard($pdo))->revenue(['company'=>null,'facility'=>null],null);
        $check((float)$dashboard[0]['total']===60000.0,$role.': manual confirmation counted once as received revenue');
        $range=['start'=>date('Y-m-d').' 00:00:00','end'=>date('Y-m-d',time()+86400).' 00:00:00'];
        $transactions=(new App\Models\Dashboard($pdo))->transactions(['company'=>null,'facility'=>null],$range);
        $check(count($transactions)===1 && $transactions[0]['ngay_ghi_nhan']===$payment['xac_nhan_thu_cong_luc']
            && $transactions[0]['ngay_thanh_toan']===null,$role.': report distinguishes recognition date from unknown bank time');
    }
    foreach (['DA_HUY','HET_HAN'] as $state) {
        $reset();$pdo->prepare('UPDATE dat_san SET trang_thai_dat_san=? WHERE ma_dat_san=1')->execute([$state]);
        $reject(fn()=>$service->confirmReceiptPayment(1,$admin),'terminal booking '.$state);
    }
    $reset();$pdo->exec("UPDATE dat_san SET het_han_giu_san_luc='2000-01-01 00:00:00' WHERE ma_dat_san=1");
    $reject(fn()=>$service->confirmReceiptPayment(1,$admin),'late proof cannot revive booking');
    foreach (['CAN_DOI_SOAT','THANH_CONG','HUY','HET_HAN'] as $state) {
        $reset();$pdo->prepare('UPDATE thanh_toan SET trang_thai=? WHERE ma_dat_san=1')->execute([$state]);
        $reject(fn()=>$service->confirmReceiptPayment(1,$admin),'payment '.$state);
    }
    $reset();$pdo->exec("UPDATE thanh_toan SET so_tien_thuc_nhan='55000' WHERE ma_dat_san=1");
    $reject(fn()=>$service->confirmReceiptPayment(1,$admin),'known amount mismatch requires reconciliation');
    $reset();$pdo->exec("UPDATE thanh_toan SET anh_minh_chung='receipts/receipt_8_0123456789abcdef.png' WHERE ma_dat_san=1");
    $reject(fn()=>$service->confirmReceiptPayment(1,$admin),'receipt from another booking');
    $reset(); $version=$receipts->info($model->findByBookingId(1))['version'];
    $pdo->exec("UPDATE thanh_toan SET minh_chung_gui_luc='2000-01-01 00:00:00' WHERE ma_dat_san=1");
    $reject(fn()=>$service->confirmReceiptPayment(1,$admin,$version),'changed evidence requires operator review again');
    $reset(); $pdo->exec("UPDATE dat_san SET trang_thai_dat_san='CHO_XAC_NHAN' WHERE ma_dat_san=1");
    $check((new App\Models\Booking($pdo))->reserved(1,date('Y-m-d H:i:s'))===2,'legacy receipt-waiting booking still reserves stock until original deadline');
    $pdo->exec("UPDATE dat_san SET het_han_giu_san_luc='2000-01-01 00:00:00' WHERE ma_dat_san=1");
    $check((new App\Models\Booking($pdo))->reserved(1,date('Y-m-d H:i:s'))===0,'expired receipt-waiting hold releases virtual stock without physical adjustment');
    foreach ([['ten_vai_tro'=>'KHACH_HANG','ma_khach_hang'=>20]+$identity,
        ['ten_vai_tro'=>'QUAN_LY_CONG_TY','ma_cong_ty'=>2]+$identity,
        ['ten_vai_tro'=>'QUAN_LY_CO_SO','ma_co_so'=>2]+$identity,
        ['ten_vai_tro'=>'NHAN_VIEN','ma_co_so'=>3,'ma_cong_ty'=>2,'cong_ty_cua_co_so'=>2]+$identity] as $user) {
        $reset(); $reject(fn()=>$receipts->authorized(1,$user),'image scope'); $reject(fn()=>$service->confirmReceiptPayment(1,$user),'confirmation scope');
    }
    $reset();$check($receipts->authorized(1,['ten_vai_tro'=>'KHACH_HANG','ma_khach_hang'=>10]+$identity)['path']===$imagePath,'owner can read own image');
    foreach (['0','NULL'] as $stock) {
        $reset();$pdo->exec("UPDATE dich_vu SET so_luong_ton=$stock WHERE ma_dich_vu=1");
        if ($stock==='0') $reject(fn()=>$service->confirmReceiptPayment(1,$admin),'zero stock');
        else {$service->confirmReceiptPayment(1,$admin);$check($snapshot()['dich_vu'][0]['so_luong_ton']===null,'untracked stock remains NULL');}
    }
    $reset();$pdo->exec('DELETE FROM chi_tiet_dich_vu_dat WHERE ma_dat_san=1');$service->confirmReceiptPayment(1,$admin);
    $check((int)$snapshot()['dich_vu'][0]['so_luong_ton']===200,'booking without services');
    $reset();$pdo->exec("CREATE TRIGGER receipt_invoice_failure BEFORE INSERT ON hoa_don BEGIN SELECT RAISE(ABORT,'RAM invoice failure'); END");$before=$snapshot();
    try {$service->confirmReceiptPayment(1,$admin);throw new RuntimeException('Missing SQL failure');}
    catch(PDOException $e){$check($snapshot()===$before && !$pdo->inTransaction(),'invoice failure rolls back receipt confirmation/payment/stock/booking');}
    $pdo->exec('DROP TRIGGER receipt_invoice_failure');
    $reset();$service->markNeedsReconciliation(1,'55000','RAM-REVIEW','Amount mismatch',$admin);$before=$snapshot();
    $check($before['dat_san'][0]['trang_thai_dat_san']==='CHO_THANH_TOAN' && (int)$before['dich_vu'][0]['so_luong_ton']===200,'reconciliation never confirms or deducts');
    $reject(fn()=>$service->confirmReceiptPayment(1,$admin),'known reconciliation cannot be bypassed');
    $service->confirmReconciledPayment(1,'RAM-REAL-MANUAL-ENTRY','60000',date('Y-m-d H:i:s'),'Exception reconciled',$admin);
    $check((int)$snapshot()['dich_vu'][0]['so_luong_ton']===198,'existing reconciliation resolution remains available with explicit facts');
    $reset(false);$reject(fn()=>$receipts->upload(1,20,null),'foreign owner cannot upload');
    $reject(fn()=>$receipts->upload(1,10,null),'HTTP upload required');
    $check(!ReceiptImage::validPath('../receipt.png') && !ReceiptImage::validPath('receipts/receipt_1_0123456789abcdef.php'),'path traversal/executable suffix denied');
    echo "PASS: $checks receipt-confirmation SQL/RAM checks; no real database or provider.\n";
} catch(Throwable $e){fwrite(STDERR,(string)$e.PHP_EOL);exit(1);}
