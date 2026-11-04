<?php

// In-memory PDO unit test for Facility Bank Management and VietQR Generation.
// Completely isolated: no database connection, no .env, no MySQL schema writes.

require dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

use App\Models\CoSo;
use App\Models\TaiKhoanNganHang;
use App\Models\ThanhToan;
use App\Services\PaymentService;
use App\Services\ValidationException;
use App\Services\VietQRService;

final class VietQRMemoryPDO extends PDO
{
    public array $bankAccounts = [];
    public array $payments = [];
    public array $bookings = [];
    public array $facilities = [];
    public array $calls = [];
    public ?int $lastInsertedId = null;
    private bool $transaction = false;
    private array $snapshot = [];

    public function __construct()
    {
        $now = time();

        // Facility 1
        $this->facilities[1] = [
            'ma_co_so' => 1,
            'ma_cong_ty' => 1,
            'ten_co_so' => 'Cơ sở Sân Cầu Lông Q7',
            'trang_thai' => 'HOAT_DONG',
        ];

        // Facility 2
        $this->facilities[2] = [
            'ma_co_so' => 2,
            'ma_cong_ty' => 2,
            'ten_co_so' => 'Cơ sở Sân Pickleball Thủ Đức',
            'trang_thai' => 'HOAT_DONG',
        ];

        // Booking 1 thuộc cơ sở 1
        $this->bookings[1] = [
            'ma_dat_san' => 1,
            'ma_co_so' => 1,
            'ma_khach_hang' => 10,
            'ma_nhan_vien' => null,
            'ngay_tao' => date('Y-m-d H:i:s', $now - 60),
            'bat_dau_giu_san_luc' => date('Y-m-d H:i:s', $now - 60),
            'het_han_giu_san_luc' => date('Y-m-d H:i:s', $now + 300),
            'tong_tien_san' => '200000.00',
            'tong_tien_dich_vu' => '0.00',
            'tong_tien_tam_tinh' => '200000.00',
            'trang_thai_dat_san' => 'CHO_THANH_TOAN',
            'ghi_chu' => null,
        ];

        // Booking 2 thuộc cơ sở 2
        $this->bookings[2] = [
            'ma_dat_san' => 2,
            'ma_co_so' => 2,
            'ma_khach_hang' => 11,
            'ma_nhan_vien' => null,
            'ngay_tao' => date('Y-m-d H:i:s', $now - 30),
            'bat_dau_giu_san_luc' => date('Y-m-d H:i:s', $now - 30),
            'het_han_giu_san_luc' => date('Y-m-d H:i:s', $now + 330),
            'tong_tien_san' => '150000.00',
            'tong_tien_dich_vu' => '0.00',
            'tong_tien_tam_tinh' => '150000.00',
            'trang_thai_dat_san' => 'CHO_THANH_TOAN',
            'ghi_chu' => null,
        ];

        // Booking 3 đã hết hạn
        $this->bookings[3] = [
            'ma_dat_san' => 3,
            'ma_co_so' => 1,
            'ma_khach_hang' => 12,
            'ma_nhan_vien' => null,
            'ngay_tao' => date('Y-m-d H:i:s', $now - 600),
            'bat_dau_giu_san_luc' => date('Y-m-d H:i:s', $now - 600),
            'het_han_giu_san_luc' => date('Y-m-d H:i:s', $now - 30),
            'tong_tien_san' => '180000.00',
            'tong_tien_dich_vu' => '0.00',
            'tong_tien_tam_tinh' => '180000.00',
            'trang_thai_dat_san' => 'CHO_THANH_TOAN',
            'ghi_chu' => null,
        ];
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new VietQRMemoryStatement($this, $query);
    }

    public function beginTransaction(): bool
    {
        if ($this->transaction) {
            throw new RuntimeException('Nested transaction');
        }
        $this->snapshot = [$this->bankAccounts, $this->payments];
        return $this->transaction = true;
    }

    public function commit(): bool
    {
        $this->transaction = false;
        return true;
    }

    public function rollBack(): bool
    {
        [$this->bankAccounts, $this->payments] = $this->snapshot;
        $this->transaction = false;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return (string) ($this->lastInsertedId ?? 0);
    }
}

final class VietQRMemoryStatement extends PDOStatement
{
    private array $bound = [];
    private array $rows = [];

    public function __construct(private VietQRMemoryPDO $db, private string $sql) {}

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $key = ltrim((string) $param, ':');
        $this->bound[$key] = $value;
        return true;
    }

    public function execute(?array $params = null): bool
    {
        $p = $params ?? $this->bound;
        $sql = preg_replace('/\s+/', ' ', trim($this->sql));
        $this->rows = [];
        $this->db->calls[] = ['sql' => $sql, 'params' => $p];

        // 1. INSERT INTO tai_khoan_ngan_hang_co_so
        if (str_starts_with($sql, 'INSERT INTO tai_khoan_ngan_hang_co_so')) {
            $nextId = $this->db->bankAccounts ? max(array_keys($this->db->bankAccounts)) + 1 : 1;
            $this->db->lastInsertedId = $nextId;
            $this->db->bankAccounts[$nextId] = [
                'ma_tk_ngan_hang' => $nextId,
                'ma_co_so' => (int) $p['ma_co_so'],
                'ten_ngan_hang' => $p['ten_ngan_hang'],
                'ma_bin' => $p['ma_bin'],
                'so_tai_khoan' => $p['so_tai_khoan'],
                'ten_chu_tai_khoan' => $p['ten_chu_tai_khoan'],
                'la_mac_dinh' => (int) $p['la_mac_dinh'],
                'trang_thai' => $p['trang_thai'],
                'ngay_tao' => $p['ngay_tao'],
            ];
            return true;
        }

        // 2. UPDATE tai_khoan_ngan_hang_co_so SET la_mac_dinh = 0
        if (str_contains($sql, 'UPDATE tai_khoan_ngan_hang_co_so SET la_mac_dinh = 0')) {
            $facId = (int) $p['facility_id'];
            $except = isset($p['except_id']) ? (int) $p['except_id'] : null;
            foreach ($this->db->bankAccounts as &$acc) {
                if ($acc['ma_co_so'] === $facId && ($except === null || $acc['ma_tk_ngan_hang'] !== $except)) {
                    $acc['la_mac_dinh'] = 0;
                }
            }
            return true;
        }

        // 3. UPDATE tai_khoan_ngan_hang_co_so SET la_mac_dinh = 1
        if (str_contains($sql, 'SET la_mac_dinh = 1')) {
            $id = (int) $p['id'];
            if (isset($this->db->bankAccounts[$id])) {
                $this->db->bankAccounts[$id]['la_mac_dinh'] = 1;
            }
            return true;
        }

        // 4. UPDATE tai_khoan_ngan_hang_co_so (thông thường)
        if (str_starts_with($sql, 'UPDATE tai_khoan_ngan_hang_co_so')) {
            $id = (int) $p['id'];
            if (isset($this->db->bankAccounts[$id])) {
                if (isset($p['ten_ngan_hang'])) $this->db->bankAccounts[$id]['ten_ngan_hang'] = $p['ten_ngan_hang'];
                if (isset($p['ma_bin'])) $this->db->bankAccounts[$id]['ma_bin'] = $p['ma_bin'];
                if (isset($p['so_tai_khoan'])) $this->db->bankAccounts[$id]['so_tai_khoan'] = $p['so_tai_khoan'];
                if (isset($p['ten_chu_tai_khoan'])) $this->db->bankAccounts[$id]['ten_chu_tai_khoan'] = $p['ten_chu_tai_khoan'];
                if (isset($p['la_mac_dinh'])) $this->db->bankAccounts[$id]['la_mac_dinh'] = (int) $p['la_mac_dinh'];
                if (isset($p['status'])) $this->db->bankAccounts[$id]['trang_thai'] = $p['status'];
                elseif (isset($p['trang_thai'])) $this->db->bankAccounts[$id]['trang_thai'] = $p['trang_thai'];
            }
            return true;
        }

        // 5. SELECT COUNT(*) FROM tai_khoan_ngan_hang_co_so
        if (str_contains($sql, 'COUNT(*)') && str_contains($sql, 'tai_khoan_ngan_hang_co_so')) {
            $facId = (int) $p['facility_id'];
            $cnt = 0;
            foreach ($this->db->bankAccounts as $acc) {
                if ($acc['ma_co_so'] === $facId) $cnt++;
            }
            $this->rows = [[$cnt]];
            return true;
        }

        // 6. SELECT * FROM tai_khoan_ngan_hang_co_so WHERE ma_co_so = :facility_id
        if (str_contains($sql, 'FROM tai_khoan_ngan_hang_co_so WHERE ma_co_so = :facility_id')) {
            $facId = (int) $p['facility_id'];
            $matches = [];
            foreach ($this->db->bankAccounts as $acc) {
                if ($acc['ma_co_so'] === $facId) {
                    if (str_contains($sql, 'la_mac_dinh = 1') && $acc['la_mac_dinh'] != 1) continue;
                    if (str_contains($sql, "trang_thai = 'HOAT_DONG'") && $acc['trang_thai'] !== 'HOAT_DONG') continue;
                    $matches[] = $acc;
                }
            }
            usort($matches, fn($a, $b) => $b['la_mac_dinh'] <=> $a['la_mac_dinh'] ?: $b['ma_tk_ngan_hang'] <=> $a['ma_tk_ngan_hang']);
            $this->rows = $matches;
            return true;
        }

        // 7. SELECT tk.* FROM tai_khoan_ngan_hang_co_so tk JOIN co_so
        if (str_contains($sql, 'FROM tai_khoan_ngan_hang_co_so tk JOIN co_so')) {
            $id = (int) $p['id'];
            if (isset($this->db->bankAccounts[$id])) {
                $acc = $this->db->bankAccounts[$id];
                $f = $this->db->facilities[$acc['ma_co_so']] ?? ['ten_co_so' => '', 'ma_cong_ty' => 0];
                $this->rows = [array_merge($acc, [
                    'ten_co_so' => $f['ten_co_so'],
                    'ma_cong_ty' => $f['ma_cong_ty'],
                ])];
            }
            return true;
        }

        // 8. INSERT INTO thanh_toan
        if (str_starts_with($sql, 'INSERT INTO thanh_toan')) {
            $nextId = $this->db->payments ? max(array_keys($this->db->payments)) + 1 : 1;
            $this->db->lastInsertedId = $nextId;
            $this->db->payments[$nextId] = [
                'ma_thanh_toan' => $nextId,
                'ma_dat_san' => (int) $p['ma_dat_san'],
                'ma_tham_chieu' => (string) $p['ma_tham_chieu'],
                'so_tien' => number_format((float) $p['so_tien'], 2, '.', ''),
                'phuong_thuc' => $p['phuong_thuc'],
                'trang_thai' => $p['trang_thai'],
                'ngay_tao' => $p['ngay_tao'],
                'ghi_chu' => $p['ghi_chu'] ?? null,
            ];
            return true;
        }

        // 9. SELECT FROM dat_san WHERE ma_dat_san = :booking_id
        if (str_contains($sql, 'FROM dat_san') && str_contains($sql, 'ma_dat_san = :booking_id')) {
            $bId = (int) $p['booking_id'];
            if (isset($this->db->bookings[$bId])) {
                $this->rows = [$this->db->bookings[$bId]];
            }
            return true;
        }

        // 10. SELECT FROM thanh_toan t JOIN dat_san d
        if (str_contains($sql, 'FROM thanh_toan t JOIN dat_san d')) {
            foreach ($this->db->payments as $pay) {
                $match = false;
                if (isset($p['id']) && $pay['ma_thanh_toan'] == $p['id']) $match = true;
                if (isset($p['booking_id']) && $pay['ma_dat_san'] == $p['booking_id']) $match = true;
                if (isset($p['reference']) && $pay['ma_tham_chieu'] === $p['reference']) $match = true;

                if ($match && isset($this->db->bookings[$pay['ma_dat_san']])) {
                    $b = $this->db->bookings[$pay['ma_dat_san']];
                    $this->rows[] = array_merge($pay, [
                        'ma_khach_hang' => $b['ma_khach_hang'],
                        'ma_co_so' => $b['ma_co_so'],
                        'bat_dau_giu_san_luc' => $b['bat_dau_giu_san_luc'],
                        'het_han_giu_san_luc' => $b['het_han_giu_san_luc'],
                        'tong_tien_tam_tinh' => $b['tong_tien_tam_tinh'],
                        'trang_thai_dat_san' => $b['trang_thai_dat_san'],
                    ]);
                    break;
                }
            }
            return true;
        }

        // 11. SELECT COUNT(*) FROM thanh_toan WHERE ma_tham_chieu
        if (str_contains($sql, 'COUNT(*)') && str_contains($sql, 'thanh_toan') && str_contains($sql, 'ma_tham_chieu')) {
            $cnt = 0;
            foreach ($this->db->payments as $row) {
                if ($row['ma_tham_chieu'] === $p['reference']) $cnt++;
            }
            $this->rows = [[$cnt]];
            return true;
        }

        // 12. SELECT FROM co_so
        if (str_contains($sql, 'FROM co_so') && isset($p['id'])) {
            $id = (int) $p['id'];
            if (isset($this->db->facilities[$id])) {
                $this->rows = [$this->db->facilities[$id]];
            }
            return true;
        }

        throw new RuntimeException('Unexpected SQL in test: ' . $sql);
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return array_shift($this->rows) ?: false;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $out = $this->rows;
        $this->rows = [];
        return $out;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = array_shift($this->rows);
        return $row ? array_values($row)[$column] : false;
    }
}

// ==========================================
// TEST SUITE
// ==========================================

$checks = 0;
function check(bool $condition, string $msg): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $msg);
    }
}

// 1. Kiểm tra VietQRService
$vietqr = new VietQRService();
$banks = $vietqr->getBankList();
check(!empty($banks), 'getBankList returns bank catalog');
check(isset($banks[0]['bin'], $banks[0]['name'], $banks[0]['shortName']), 'Bank item has required fields');

// Tìm kiếm ngân hàng theo mã VCB
$vcb = $vietqr->findBank('VCB');
check($vcb !== null && $vcb['bin'] === '970436', 'findBank by code VCB resolves to BIN 970436');

// Tìm kiếm ngân hàng theo BIN 970422 (MB)
$mb = $vietqr->findBank('970422');
check($mb !== null && $mb['code'] === 'MB', 'findBank by BIN 970422 resolves to MB');

// Validate input form: Chủ sân chỉ chọn code VCB, hệ thống tự động map mã BIN 970436
$validInput = $vietqr->validateAccountInput([
    'bank_code' => 'VCB',
    'so_tai_khoan' => '0123456789',
    'ten_chu_tai_khoan' => 'nguyen van a',
]);
check($validInput['ma_bin'] === '970436', 'validateAccountInput automatically binds ma_bin 970436');
check($validInput['ten_chu_tai_khoan'] === 'NGUYEN VAN A', 'Account name auto capitalized');
check($validInput['so_tai_khoan'] === '0123456789', 'Account number preserved');

// Sinh QuickLink VietQR
$qrLink = $vietqr->generateQuickLink('970436', '0123456789', 150000, 'PAY261101B0001X', 'NGUYEN VAN A');
check(str_contains($qrLink, 'https://img.vietqr.io/image/970436-0123456789-compact2.png'), 'QuickLink contains base image URL with BIN and Account');
check(str_contains($qrLink, 'amount=150000'), 'QuickLink contains exact amount');
check(str_contains($qrLink, 'addInfo=PAY261101B0001X'), 'QuickLink contains exact payment reference');

// 2. Kiểm tra Model TaiKhoanNganHang (Quản lý tài khoản ngân hàng của cơ sở)
$pdo = new VietQRMemoryPDO();
$bankModel = new TaiKhoanNganHang($pdo);

// Thêm tài khoản 1 cho cơ sở 1 (sẽ tự động thành mặc định)
$id1 = $bankModel->create([
    'ma_co_so' => 1,
    'ten_ngan_hang' => 'Vietcombank',
    'ma_bin' => '970436',
    'so_tai_khoan' => '0123456789',
    'ten_chu_tai_khoan' => 'NGUYEN VAN A',
    'la_mac_dinh' => 0, // Dù truyền 0 nhưng là tài khoản đầu tiên nên tự thành 1
]);
check($id1 === 1, 'First bank account created with ID 1');
$acc1 = $bankModel->find($id1);
check($acc1['la_mac_dinh'] == 1, 'First account automatically set as default');
check($acc1['ten_co_so'] === 'Cơ sở Sân Cầu Lông Q7', 'Account joined facility name correctly');

// Thêm tài khoản 2 cho cơ sở 1 và đặt làm mặc định
$id2 = $bankModel->create([
    'ma_co_so' => 1,
    'ten_ngan_hang' => 'MBBank',
    'ma_bin' => '970422',
    'so_tai_khoan' => '9876543210',
    'ten_chu_tai_khoan' => 'NGUYEN VAN A',
    'la_mac_dinh' => 1,
]);
check($id2 === 2, 'Second bank account created with ID 2');
$acc1After = $bankModel->find($id1);
$acc2After = $bankModel->find($id2);
check($acc1After['la_mac_dinh'] == 0, 'Account 1 is no longer default');
check($acc2After['la_mac_dinh'] == 1, 'Account 2 is now default');

// Lấy tài khoản mặc định của cơ sở 1
$defaultAcc = $bankModel->getDefaultByFacility(1);
check($defaultAcc['ma_tk_ngan_hang'] == 2 && $defaultAcc['ma_bin'] === '970422', 'getDefaultByFacility returns Account 2 (MBBank)');

// Đổi lại tài khoản 1 làm mặc định
$bankModel->setDefault($id1, 1);
$defaultAccAgain = $bankModel->getDefaultByFacility(1);
check($defaultAccAgain['ma_tk_ngan_hang'] == 1 && $defaultAccAgain['ma_bin'] === '970436', 'setDefault successfully made Account 1 default again');

// Ngừng hoạt động tài khoản 2
$bankModel->toggleStatus($id2, 'NGUNG');
$acc2Ngung = $bankModel->find($id2);
check($acc2Ngung['trang_thai'] === 'NGUNG', 'toggleStatus successfully disabled Account 2');

// 3. Thêm tài khoản cho cơ sở 2 (Chủ sân cơ sở 2)
$id3 = $bankModel->create([
    'ma_co_so' => 2,
    'ten_ngan_hang' => 'Techcombank',
    'ma_bin' => '970407',
    'so_tai_khoan' => '190333888999',
    'ten_chu_tai_khoan' => 'TRAN VAN B',
    'la_mac_dinh' => 1,
]);
$defaultAcc2 = $bankModel->getDefaultByFacility(2);
check($defaultAcc2['ma_co_so'] == 2 && $defaultAcc2['ma_bin'] === '970407', 'Facility 2 has its own separate bank account');

// 4. Kiểm tra tích hợp PaymentService: Khách đặt sân cơ sở nào thì sinh mã VietQR đúng ngân hàng của cơ sở đó
$thanhToanModel = new ThanhToan($pdo);
$paymentService = new PaymentService($pdo, $thanhToanModel, $bankModel, $vietqr);

// Đơn đặt sân 1 (Cơ sở 1): Phải sinh VietQR với ngân hàng Vietcombank của cơ sở 1
$pay1 = $paymentService->createPaymentRequest(1, '200000.00');
check($pay1['success'] === true, 'Payment request 1 created successfully');
check($pay1['facility_id'] === 1, 'Payment linked to facility 1');
check($pay1['bank']['bin'] === '970436', 'VietQR generated with Facility 1 BIN (Vietcombank 970436)');
check($pay1['bank']['account_number'] === '0123456789', 'VietQR generated with Facility 1 account number');
check(str_contains($pay1['qr_url'], '970436-0123456789'), 'QR URL targets Facility 1 bank account');
check(str_contains($pay1['qr_url'], 'amount=200000'), 'QR URL targets exact booking amount');
check(str_contains($pay1['qr_url'], $pay1['reference_code']), 'QR URL targets booking reference code');

// Đơn đặt sân 2 (Cơ sở 2): Phải sinh VietQR với ngân hàng Techcombank của cơ sở 2
$pay2 = $paymentService->createPaymentRequest(2, '150000.00');
check($pay2['success'] === true, 'Payment request 2 created successfully');
check($pay2['facility_id'] === 2, 'Payment linked to facility 2');
check($pay2['bank']['bin'] === '970407', 'VietQR generated with Facility 2 BIN (Techcombank 970407)');
check($pay2['bank']['account_number'] === '190333888999', 'VietQR generated with Facility 2 account number');
check(str_contains($pay2['qr_url'], '970407-190333888999'), 'QR URL targets Facility 2 bank account');

// Idempotency: Gọi lại đơn 1 trả về cùng yêu cầu và QR
$pay1Repeat = $paymentService->createPaymentRequest(1, 200000);
check($pay1Repeat['payment_id'] === $pay1['payment_id'], 'Repeat request returns existing payment ID');
check($pay1Repeat['reference_code'] === $pay1['reference_code'], 'Repeat request returns existing reference code');

// Kiểm tra chặn khi đơn đặt sân hết hạn giữ sân (Booking 3)
try {
    $paymentService->createPaymentRequest(3, 180000);
    check(false, 'Should throw ValidationException for expired booking');
} catch (ValidationException $e) {
    check(isset($e->errors['booking']), 'ValidationException thrown when booking hold time expired');
}

// Kiểm tra chặn khi số tiền không khớp
try {
    $paymentService->createPaymentRequest(2, 999999);
    check(false, 'Should throw ValidationException for amount mismatch');
} catch (ValidationException $e) {
    check(isset($e->errors['amount']), 'ValidationException thrown when amount mismatches');
}

// 5. Kiểm tra phòng chống IDOR và kiểm soát quyền truy cập
// Booking 1 thuộc khách hàng 10, Booking 2 thuộc khách hàng 11
$payment1 = $paymentService->getPaymentByBooking(1);
check($payment1 !== null, 'Payment 1 exists');
check((int) $payment1['ma_khach_hang'] === 10, 'Payment 1 is owned by customer 10');

$payment2 = $paymentService->getPaymentByBooking(2);
check($payment2 !== null, 'Payment 2 exists');
check((int) $payment2['ma_khach_hang'] === 11, 'Payment 2 is owned by customer 11');

// Kịch bản A: Khách hàng 11 truy cập đơn của Khách hàng 10 -> IDOR check must fail
$customer11Id = 11;
$isAllowed11on10 = ((int) $payment1['ma_khach_hang'] === $customer11Id);
check($isAllowed11on10 === false, 'IDOR protection: Customer 11 is forbidden from viewing Customer 10 payment');

// Kịch bản B: Khách hàng 10 truy cập đơn của chính mình -> OK
$customer10Id = 10;
$isAllowed10on10 = ((int) $payment1['ma_khach_hang'] === $customer10Id);
check($isAllowed10on10 === true, 'Access control: Customer 10 is permitted to view their own payment');

// Kịch bản C: Mã tham chiếu của đơn 1 không cấp quyền truy cập cho khách hàng 11
$paymentByRef = $paymentService->getPaymentByReference($pay1['reference_code']);
check($paymentByRef !== null, 'Payment found by reference code');
$isRefAllowed = ((int) $paymentByRef['ma_khach_hang'] === $customer11Id);
check($isRefAllowed === false, 'IDOR protection: Reference code lookup validates customer ownership');

echo "PASS: $checks VietQR, Facility Bank, and Anti-IDOR checks passed cleanly in memory!\n";
