<?php

// Isolated in-memory unit tests for Admin Booking Management, Manual Confirmation & Invoice Generation (PR4).
// Zero network calls, no real database modified.

require dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

use App\Models\AdminBooking;
use App\Models\HoaDon;
use App\Services\BookingManagementService;
use App\Services\InvoiceService;
use App\Services\ValidationException;
use Core\HttpException;

final class BookingAdminMemoryPDO extends PDO
{
    public array $bookings = [];
    public array $payments = [];
    public array $facilities = [];
    public array $services = [];
    public array $bookingServices = [];
    public array $courts = [];
    public array $invoices = [];
    public int $lastId = 100;
    public bool $transaction = false;
    public bool $simulateBookingUpdateFailure = false;
    public bool $simulateInvoiceCreationFailure = false;
    private array $snapshot = [];

    public function __construct()
    {
        $now = time();

        // Facility 1 (Company 1)
        $this->facilities[1] = [
            'ma_co_so' => 1,
            'ma_cong_ty' => 1,
            'ten_co_so' => 'Cơ sở Quận 7',
            'dia_chi' => '123 Nguyễn Thị Thập, Q.7, TP.HCM',
            'trang_thai' => 'HOAT_DONG',
        ];

        // Facility 2 (Company 2)
        $this->facilities[2] = [
            'ma_co_so' => 2,
            'ma_cong_ty' => 2,
            'ten_co_so' => 'Cơ sở Bình Thạnh',
            'dia_chi' => '456 Điện Biên Phủ, Bình Thạnh, TP.HCM',
            'trang_thai' => 'HOAT_DONG',
        ];

        // Booking 1: CHO_THANH_TOAN còn hạn
        $this->bookings[1] = [
            'ma_dat_san' => 1,
            'ma_co_so' => 1,
            'ma_khach_hang' => 10,
            'ma_nhan_vien' => null,
            'ngay_tao' => date('Y-m-d H:i:s', $now - 60),
            'bat_dau_giu_san_luc' => date('Y-m-d H:i:s', $now - 60),
            'het_han_giu_san_luc' => date('Y-m-d H:i:s', $now + 300), // còn 5 phút
            'tong_tien_san' => '200000.00',
            'tong_tien_dich_vu' => '20000.00',
            'tong_tien_tam_tinh' => '220000.00',
            'trang_thai_dat_san' => 'CHO_THANH_TOAN',
            'ghi_chu' => 'Đơn test 1',
        ];
        $this->payments[1] = [
            'ma_thanh_toan' => 1,
            'ma_dat_san' => 1,
            'ma_tham_chieu' => 'PAY261102B0001TEST',
            'so_tien' => '220000.00',
            'so_tien_thuc_nhan' => null,
            'ma_giao_dich' => null,
            'phuong_thuc' => 'CHUYEN_KHOAN',
            'trang_thai' => 'CHO_THANH_TOAN',
            'ngay_tao' => date('Y-m-d H:i:s', $now - 60),
            'ngay_thanh_toan' => null,
            'ghi_chu' => null,
        ];

        // Booking 2: Đã hết hạn giữ sân
        $this->bookings[2] = [
            'ma_dat_san' => 2,
            'ma_co_so' => 1,
            'ma_khach_hang' => 11,
            'ma_nhan_vien' => null,
            'ngay_tao' => date('Y-m-d H:i:s', $now - 600),
            'bat_dau_giu_san_luc' => date('Y-m-d H:i:s', $now - 600),
            'het_han_giu_san_luc' => date('Y-m-d H:i:s', $now - 60), // đã hết hạn 1 phút trước
            'tong_tien_san' => '150000.00',
            'tong_tien_dich_vu' => '0.00',
            'tong_tien_tam_tinh' => '150000.00',
            'trang_thai_dat_san' => 'HET_HAN',
            'ghi_chu' => 'Đơn hết hạn',
        ];
        $this->payments[2] = [
            'ma_thanh_toan' => 2,
            'ma_dat_san' => 2,
            'ma_tham_chieu' => 'PAY261102B0002EXPD',
            'so_tien' => '150000.00',
            'so_tien_thuc_nhan' => null,
            'ma_giao_dich' => null,
            'phuong_thuc' => 'CHUYEN_KHOAN',
            'trang_thai' => 'HET_HAN',
            'ngay_tao' => date('Y-m-d H:i:s', $now - 600),
            'ngay_thanh_toan' => null,
            'ghi_chu' => null,
        ];

        // Dịch vụ của Booking 1 (Có 2 chai nước khoáng, tồn ban đầu: 10)
        $this->services[101] = [
            'ma_dich_vu' => 101,
            'ma_co_so' => 1,
            'ten_dich_vu' => 'Nước khoáng Lavie',
            'don_vi_tinh' => 'Chai',
            'don_gia' => '10000.00',
            'so_luong_ton' => 10,
        ];
        $this->bookingServices[] = [
            'ma_dat_san' => 1,
            'ma_dich_vu' => 101,
            'so_luong' => 2,
        ];
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return (string) $this->lastId;
    }

    public function beginTransaction(): bool
    {
        if ($this->transaction) {
            throw new RuntimeException('Nested transaction not supported');
        }
        $this->snapshot = [$this->bookings, $this->payments, $this->services, $this->invoices];
        return $this->transaction = true;
    }

    public function commit(): bool
    {
        $this->transaction = false;
        return true;
    }

    public function rollBack(): bool
    {
        [$this->bookings, $this->payments, $this->services, $this->invoices] = $this->snapshot;
        $this->transaction = false;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new BookingAdminMemoryStatement($this, $query);
    }
}

final class BookingAdminMemoryStatement extends PDOStatement
{
    private BookingAdminMemoryPDO $db;
    private string $sql;
    private array $bound = [];
    private $result = false;
    private int $affectedRows = 0;

    public function __construct(BookingAdminMemoryPDO $db, string $sql)
    {
        $this->db = $db;
        $this->sql = $sql;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->bound[(string)$param] = $value;
        return true;
    }

    public function rowCount(): int
    {
        return $this->affectedRows;
    }

    public function execute(?array $params = null): bool
    {
        $params = $params ?? $this->bound;
        $this->affectedRows = 0;

        // 1. SELECT * FROM dat_san
        if (str_contains($this->sql, 'FROM dat_san')) {
            $id = (int) ($params[':id'] ?? $params['id'] ?? $params[0] ?? 0);
            $this->result = isset($this->db->bookings[$id]) ? [$this->db->bookings[$id]] : [];
            return true;
        }

        // 2. SELECT ma_cong_ty FROM co_so
        if (str_contains($this->sql, 'FROM co_so WHERE ma_co_so = :co_so') || str_contains($this->sql, 'FROM co_so WHERE ma_co_so = :fid')) {
            $id = (int) ($params[':co_so'] ?? $params['co_so'] ?? $params[':fid'] ?? $params['fid'] ?? $params[0] ?? 0);
            $fac = $this->db->facilities[$id] ?? null;
            $this->result = $fac ? [['ma_cong_ty' => $fac['ma_cong_ty'], 'ma_co_so' => $id]] : [];
            return true;
        }

        if (str_contains($this->sql, 'FROM co_so cs')) {
            $id = (int) ($params[':fac_id'] ?? $params['fac_id'] ?? $params[0] ?? 1);
            $fac = $this->db->facilities[$id] ?? ['ma_co_so' => $id, 'ten_co_so' => 'Cơ sở Quận 7', 'dia_chi' => '123 Q7', 'so_dien_thoai' => '0901234567'];
            $this->result = [$fac];
            return true;
        }

        // 3. Parent lock statements
        if (str_contains($this->sql, 'FROM cong_ty_nhuong_quyen')) {
            $this->result = [['ma_cong_ty' => 1]];
            return true;
        }
        if (str_contains($this->sql, 'FROM bo_mon')) {
            $this->result = [['ma_bo_mon' => 1]];
            return true;
        }
        if (str_contains($this->sql, 'FROM loai_san')) {
            $this->result = [['ma_loai_san' => 1]];
            return true;
        }
        if (str_contains($this->sql, 'FROM san WHERE ma_san = :id')) {
            $this->result = [['ma_san' => 1]];
            return true;
        }
        if (str_contains($this->sql, 'FROM dich_vu WHERE ma_dich_vu = :id')) {
            $this->result = [['ma_dich_vu' => 1]];
            return true;
        }
        if (str_contains($this->sql, 'FROM chi_tiet_dat_san')) {
            $this->result = [];
            return true;
        }

        // 4. SELECT * FROM thanh_toan
        if (str_contains($this->sql, 'FROM thanh_toan WHERE ma_dat_san = :id') || str_contains($this->sql, 'FROM thanh_toan WHERE ma_dat_san = ?') || str_contains($this->sql, 'FROM thanh_toan WHERE ma_thanh_toan = :pid')) {
            $id = (int) ($params[':id'] ?? $params['id'] ?? $params[':pid'] ?? $params['pid'] ?? $params[0] ?? 0);
            $found = null;
            foreach ($this->db->payments as $p) {
                if ($p['ma_dat_san'] === $id || $p['ma_thanh_toan'] === $id) {
                    $found = $p;
                    break;
                }
            }
            $this->result = $found ? [$found] : [];
            return true;
        }

        // 5. Check ma_giao_dich uniqueness
        if (str_contains($this->sql, 'FROM thanh_toan WHERE ma_giao_dich = :txn_id')) {
            $txn = (string) ($params[':txn_id'] ?? $params['txn_id'] ?? '');
            $paymentId = (int) ($params[':payment_id'] ?? $params['payment_id'] ?? 0);
            $exists = false;
            foreach ($this->db->payments as $p) {
                if ($p['ma_giao_dich'] !== null && $p['ma_giao_dich'] === $txn && $p['ma_thanh_toan'] !== $paymentId) {
                    $exists = true;
                    break;
                }
            }
            $this->result = $exists ? [['ma_thanh_toan' => 999]] : [];
            return true;
        }

        // 6. UPDATE thanh_toan
        if (str_starts_with($this->sql, 'UPDATE thanh_toan')) {
            $id = (int) ($params[':id'] ?? $params['id'] ?? $params[0] ?? 0);
            if (isset($this->db->payments[$id])) {
                $status = $params[':status'] ?? $params['status'] ?? null;
                $actual = $params[':actual_amount'] ?? $params['actual_amount'] ?? $params[':actual'] ?? $params['actual'] ?? null;
                $txn = $params[':bank_txn'] ?? $params['bank_txn'] ?? $params[':txn'] ?? $params['txn'] ?? null;
                $note = $params[':note'] ?? $params['note'] ?? null;
                $paidAt = $params[':paid_at'] ?? $params['paid_at'] ?? null;

                if ($status !== null) $this->db->payments[$id]['trang_thai'] = $status;
                if ($actual !== null) $this->db->payments[$id]['so_tien_thuc_nhan'] = $actual;
                if ($txn !== null) $this->db->payments[$id]['ma_giao_dich'] = $txn;
                if ($note !== null) $this->db->payments[$id]['ghi_chu'] = $note;
                if ($paidAt !== null) $this->db->payments[$id]['ngay_thanh_toan'] = $paidAt;
            }
            $this->affectedRows = 1;
            return true;
        }

        // 7. UPDATE dat_san
        if (str_starts_with($this->sql, 'UPDATE dat_san')) {
            if ($this->db->simulateBookingUpdateFailure) {
                throw new PDOException('Simulated database error updating dat_san');
            }
            $id = (int) ($params[':id'] ?? $params['id'] ?? $params[0] ?? 0);
            if (isset($this->db->bookings[$id])) {
                $status = $params[':status'] ?? $params['status'] ?? null;
                $staffId = $params[':staff_id'] ?? $params['staff_id'] ?? null;

                if ($status !== null) $this->db->bookings[$id]['trang_thai_dat_san'] = $status;
                if ($staffId !== null) $this->db->bookings[$id]['ma_nhan_vien'] = $staffId;
                $note = $params[':note'] ?? $params['note'] ?? null;
                if ($note !== null) $this->db->bookings[$id]['ghi_chu'] = $note;
            }
            $this->affectedRows = 1;
            return true;
        }

        // 8. SELECT chi_tiet_dich_vu_dat
        if (str_contains($this->sql, 'FROM chi_tiet_dich_vu_dat')) {
            $id = (int) ($params[':id'] ?? $params['id'] ?? $params[0] ?? 0);
            $lines = [];
            foreach ($this->db->bookingServices as $bs) {
                if ($bs['ma_dat_san'] === $id) {
                    $svc = $this->db->services[$bs['ma_dich_vu']] ?? [];
                    $lines[] = [
                        'ma_dich_vu' => $bs['ma_dich_vu'],
                        'so_luong' => $bs['so_luong'],
                        'ten_dich_vu' => $svc['ten_dich_vu'] ?? ('Dịch vụ #' . $bs['ma_dich_vu']),
                        'so_luong_ton' => $svc['so_luong_ton'] ?? null,
                    ];
                }
            }
            $this->result = $lines;
            return true;
        }

        // 9. Stock deduction uses distinct quantity/minimum named bindings.
        if (str_starts_with($this->sql, 'UPDATE dich_vu')) {
            $serviceId = (int) ($params[':service_id'] ?? $params['service_id'] ?? 0);
            $qty = (int) ($params[':qty_deduct'] ?? $params['qty_deduct'] ?? 0);
            $minimum = (int) ($params[':qty_min'] ?? $params['qty_min'] ?? 0);
            if ($qty <= 0 || $minimum !== $qty) throw new RuntimeException('Invalid stock quantity/minimum bindings');
            $this->affectedRows = 0;
            if (isset($this->db->services[$serviceId])) {
                $cur = $this->db->services[$serviceId]['so_luong_ton'];
                if ($cur !== null && $cur >= $minimum) {
                    $this->db->services[$serviceId]['so_luong_ton'] -= $qty;
                    $this->affectedRows = 1;
                }
            }
            return true;
        }

        // 10. INSERT INTO hoa_don (PR4)
        if (str_starts_with($this->sql, 'INSERT INTO hoa_don')) {
            if ($this->db->simulateInvoiceCreationFailure) {
                throw new PDOException('Simulated database error creating invoice');
            }
            $invId = ++$this->db->lastId;
            $this->db->invoices[$invId] = [
                'ma_hoa_don' => $invId,
                'so_hoa_don' => (string) ($params[':so_hoa_don'] ?? $params['so_hoa_don'] ?? ''),
                'ma_dat_san' => (int) ($params[':ma_dat_san'] ?? $params['ma_dat_san'] ?? 0),
                'ma_thanh_toan' => (int) ($params[':ma_thanh_toan'] ?? $params['ma_thanh_toan'] ?? 0),
                'ma_nhan_vien' => $params[':ma_nhan_vien'] ?? $params['ma_nhan_vien'] ?? null,
                'ngay_lap' => $params[':ngay_lap'] ?? $params['ngay_lap'] ?? date('Y-m-d H:i:s'),
                'tien_san' => $params[':tien_san'] ?? $params['tien_san'] ?? '0.00',
                'tien_dich_vu' => $params[':tien_dich_vu'] ?? $params['tien_dich_vu'] ?? '0.00',
                'tong_tien' => $params[':tong_tien'] ?? $params['tong_tien'] ?? '0.00',
                'trang_thai' => $params[':trang_thai'] ?? $params['trang_thai'] ?? 'DA_LAP',
                'ghi_chu' => $params[':ghi_chu'] ?? $params['ghi_chu'] ?? null,
            ];
            $this->affectedRows = 1;
            return true;
        }

        // 11. COUNT(*) FROM hoa_don (PR4)
        if (str_contains($this->sql, 'COUNT(*)') && str_contains($this->sql, 'FROM hoa_don')) {
            $cnt = 0;
            if (str_contains($this->sql, 'ma_dat_san = :id')) {
                $bid = (int) ($params[':id'] ?? $params['id'] ?? 0);
                foreach ($this->db->invoices as $inv) {
                    if ($inv['ma_dat_san'] === $bid) $cnt++;
                }
            } elseif (str_contains($this->sql, 'ma_thanh_toan = :id')) {
                $pid = (int) ($params[':id'] ?? $params['id'] ?? 0);
                foreach ($this->db->invoices as $inv) {
                    if ($inv['ma_thanh_toan'] === $pid) $cnt++;
                }
            } elseif (str_contains($this->sql, 'so_hoa_don = :num')) {
                $num = (string) ($params[':num'] ?? $params['num'] ?? '');
                foreach ($this->db->invoices as $inv) {
                    if ($inv['so_hoa_don'] === $num) $cnt++;
                }
            }
            $this->result = [[$cnt]];
            return true;
        }

        // 12. SELECT ... FROM hoa_don (PR4)
        if (str_contains($this->sql, 'FROM hoa_don')) {
            $id = (int) ($params[':id'] ?? $params['id'] ?? $params[':booking_id'] ?? $params['booking_id'] ?? 0);
            $found = null;
            if (isset($this->db->invoices[$id])) {
                $found = $this->db->invoices[$id];
            } else {
                foreach ($this->db->invoices as $inv) {
                    if ($inv['ma_dat_san'] === $id || $inv['ma_hoa_don'] === $id) {
                        $found = $inv;
                        break;
                    }
                }
            }
            if ($found) {
                $b = $this->db->bookings[$found['ma_dat_san']] ?? [];
                $fac = $this->db->facilities[$b['ma_co_so'] ?? 1] ?? [];
                $found = array_merge($found, [
                    'ma_khach_hang' => $b['ma_khach_hang'] ?? 10,
                    'ma_co_so' => $b['ma_co_so'] ?? 1,
                    'ma_cong_ty' => $fac['ma_cong_ty'] ?? 1,
                    'ten_co_so' => $fac['ten_co_so'] ?? 'Cơ sở Quận 7',
                    'ten_khach_hang' => 'Nguyễn Văn Test',
                    'sdt_khach_hang' => '0901234567',
                    'email_khach_hang' => 'test@stryke.vn',
                ]);
            }
            $this->result = $found ? [$found] : [];
            return true;
        }

        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if (is_array($this->result) && !empty($this->result)) {
            return array_shift($this->result);
        }
        return false;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        if (is_array($this->result)) {
            $all = $this->result;
            $this->result = [];
            return $all;
        }
        return [];
    }

    public function fetchColumn(int $column = 0): mixed
    {
        if (is_array($this->result) && !empty($this->result)) {
            $row = reset($this->result);
            if (is_array($row)) {
                $values = array_values($row);
                return $values[$column] ?? false;
            }
        }
        return false;
    }
}

// =========================================================================
// TEST SUITE EXECUTION
// =========================================================================
$checks = 0;
function assertBooking(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException("FAIL: " . $message);
    }
    $checks++;
}

echo "=== STARTING ADMIN BOOKING MANAGEMENT & INVOICE TESTS ===" . PHP_EOL;

// 1. Kiểm tra phân quyền Scope
$admin = ['ten_vai_tro' => 'QUAN_TRI_TAP_DOAN', 'trang_thai' => 'HOAT_DONG', 'ma_nhan_vien' => null, 'ma_cong_ty' => null, 'ma_co_so' => null];
$scopeAdmin = BookingManagementService::resolveScope($admin);
assertBooking($scopeAdmin['company'] === null && $scopeAdmin['facility'] === null, 'Admin can view all facilities and companies');
assertBooking($scopeAdmin['can_confirm'] === true, 'Admin has confirm permission');

$companyMgr = [
    'ten_vai_tro' => 'QUAN_LY_CONG_TY',
    'trang_thai' => 'HOAT_DONG',
    'ma_nhan_vien' => 2,
    'trang_thai_nhan_vien' => 'DANG_LAM',
    'ma_cong_ty' => 1,
    'trang_thai_cong_ty' => 'HOAT_DONG',
    'ma_co_so' => null,
];
$scopeCompany = BookingManagementService::resolveScope($companyMgr);
assertBooking($scopeCompany['company'] === 1 && $scopeCompany['facility'] === null, 'Company manager bounded to their company');

$facilityMgr = [
    'ten_vai_tro' => 'QUAN_LY_CO_SO',
    'trang_thai' => 'HOAT_DONG',
    'ma_nhan_vien' => 5,
    'trang_thai_nhan_vien' => 'DANG_LAM',
    'ma_cong_ty' => 1,
    'trang_thai_cong_ty' => 'HOAT_DONG',
    'ma_co_so' => 1,
    'trang_thai_co_so' => 'HOAT_DONG',
    'cong_ty_cua_co_so' => 1,
];
$scopeFacility = BookingManagementService::resolveScope($facilityMgr);
assertBooking($scopeFacility['facility'] === 1, 'Facility manager bounded to facility 1');
assertBooking($scopeFacility['staff_id'] === 5, 'Staff ID correctly identified');

$staff = [
    'ten_vai_tro' => 'NHAN_VIEN',
    'trang_thai' => 'HOAT_DONG',
    'ma_nhan_vien' => 8,
    'trang_thai_nhan_vien' => 'DANG_LAM',
    'ma_cong_ty' => 1,
    'trang_thai_cong_ty' => 'HOAT_DONG',
    'ma_co_so' => 1,
    'trang_thai_co_so' => 'HOAT_DONG',
    'cong_ty_cua_co_so' => 1,
];
$scopeStaff = BookingManagementService::resolveScope($staff);
assertBooking($scopeStaff['facility'] === 1, 'Staff bounded to facility 1');
assertBooking($scopeStaff['can_confirm'] === true, 'Staff permitted to confirm bookings');

// Bị từ chối nếu vai trò không hợp lệ (ví dụ Khách hàng)
try {
    BookingManagementService::resolveScope(['ten_vai_tro' => 'KHACH_HANG', 'trang_thai' => 'HOAT_DONG']);
    assertBooking(false, 'Customer must be rejected from admin booking management');
} catch (HttpException $e) {
    assertBooking($e->status === 403, 'Customer gets 403 Forbidden');
}

// Bị từ chối nếu tài khoản bị khóa
try {
    BookingManagementService::resolveScope(['ten_vai_tro' => 'NHAN_VIEN', 'ma_co_so' => 1, 'trang_thai' => 'KHOA']);
    assertBooking(false, 'Locked staff must be rejected');
} catch (HttpException $e) {
    assertBooking($e->status === 403, 'Locked staff gets 403 Forbidden');
}

// 2. Kiểm tra bộ lọc
$service = new BookingManagementService(new BookingAdminMemoryPDO());
$filters = $service->validateFilters([
    'per_page' => '20',
    'date_type' => 'ngay_tao',
    'date_from' => '2026-11-01',
    'date_to' => '2026-11-05',
    'booking_status' => 'CHO_THANH_TOAN',
    'q' => 'Nguyen Van A',
], $scopeAdmin);
assertBooking($filters['per_page'] === 20, 'PerPage normalized correctly');
assertBooking($filters['date_type'] === 'ngay_tao', 'Date type preserved');
assertBooking($filters['date_from'] === '2026-11-01', 'Date from validated');
assertBooking($filters['booking_status'] === 'CHO_THANH_TOAN', 'Booking status validated');

// 3. Kiểm tra Xác nhận thủ công thành công & TỰ ĐỘNG TẠO HÓA ĐƠN TRONG CÙNG TRANSACTION (PR4)
$db = new BookingAdminMemoryPDO();
$bookingService = new BookingManagementService($db);

$res = $bookingService->confirmBookingAndPayment(
    1,
    'FT26110298765432',
    '220000.00',
    '2026-11-02 15:30:00',
    'Khách chuyển khoản VCB đúng nội dung',
    $facilityMgr
);

assertBooking($res['success'] === true, 'Confirmation returns success');
assertBooking($db->bookings[1]['trang_thai_dat_san'] === 'DA_XAC_NHAN', 'Booking state updated to DA_XAC_NHAN');
assertBooking($db->bookings[1]['ma_nhan_vien'] === 5, 'Staff ID recorded on booking');
assertBooking($db->payments[1]['trang_thai'] === 'THANH_CONG', 'Payment state updated to THANH_CONG');
assertBooking($db->payments[1]['ma_giao_dich'] === 'FT26110298765432', 'Bank transaction ID recorded');
assertBooking($db->payments[1]['so_tien_thuc_nhan'] === '220000.00', 'Actual amount recorded');
assertBooking($db->services[101]['so_luong_ton'] === 8, 'Service stock deducted from 10 to 8');

// Kiểm tra hóa đơn được lập tự động trong cùng transaction (PR4)
assertBooking(isset($res['invoice_id']) && $res['invoice_id'] > 0, 'Invoice ID returned in confirmation result');
assertBooking(isset($res['invoice_number']) && str_starts_with($res['invoice_number'], 'INV'), 'Invoice number generated correctly');
assertBooking(count($db->invoices) === 1, 'Exactly one invoice created in database');
$inv = reset($db->invoices);
assertBooking($inv['ma_dat_san'] === 1, 'Invoice linked to booking 1');
assertBooking($inv['ma_thanh_toan'] === 1, 'Invoice linked to payment 1');
assertBooking($inv['tong_tien'] === '220000.00', 'Invoice total amount equals booking total');
assertBooking($inv['tien_san'] === '200000.00', 'Invoice court fee correct');
assertBooking($inv['tien_dich_vu'] === '20000.00', 'Invoice service fee correct');
assertBooking($inv['trang_thai'] === 'DA_LAP', 'Invoice status is DA_LAP');

// 4. Kiểm tra Chống xác nhận trùng lặp
try {
    $bookingService->confirmBookingAndPayment(
        1,
        'FT26110298765432',
        '220000.00',
        null,
        null,
        $facilityMgr
    );
    assertBooking(false, 'Double confirmation must be rejected');
} catch (ValidationException $e) {
    assertBooking(isset($e->errors['booking']), 'Double confirmation triggers validation error');
    assertBooking(count($db->invoices) === 1, 'Double confirmation does NOT create a second invoice');
}

// 5. Kiểm tra Chặn xác nhận khi sai số tiền
$db2 = new BookingAdminMemoryPDO();
$bookingService2 = new BookingManagementService($db2);
try {
    // Booking 1 cần 220.000 đ nhưng nhân viên nhập thực nhận 200.000 đ
    $bookingService2->confirmBookingAndPayment(
        1,
        'FT_MISMATCH_AMOUNT',
        '200000.00',
        null,
        null,
        $facilityMgr
    );
    assertBooking(false, 'Mismatched amount must NOT be confirmed as THANH_CONG');
} catch (ValidationException $e) {
    assertBooking(isset($e->errors['so_tien_thuc_nhan']), 'Mismatch amount triggers validation error');
    assertBooking($db2->bookings[1]['trang_thai_dat_san'] === 'CHO_THANH_TOAN', 'Booking stays unconfirmed');
    assertBooking($db2->services[101]['so_luong_ton'] === 10, 'Service stock NOT deducted when amount mismatches');
    assertBooking(count($db2->invoices) === 0, 'No invoice created when amount mismatches');
}

// 6. Kiểm tra Chuyển Cần đối soát (CAN_DOI_SOAT) khi sai tiền
$resReconcile = $bookingService2->markNeedsReconciliation(
    1,
    '200000.00',
    'FT_UNDERPAID',
    'Khách chuyển thiếu 20.000đ',
    $facilityMgr
);
assertBooking($resReconcile['success'] === true, 'Reconciliation marked successfully');
assertBooking($db2->payments[1]['trang_thai'] === 'CAN_DOI_SOAT', 'Payment marked as CAN_DOI_SOAT');
assertBooking($db2->payments[1]['so_tien_thuc_nhan'] === '200000.00', 'Actual underpaid amount recorded');
assertBooking($db2->bookings[1]['trang_thai_dat_san'] === 'CHO_THANH_TOAN', 'Booking is NOT confirmed');
assertBooking($db2->services[101]['so_luong_ton'] === 10, 'Stock is NOT deducted for CAN_DOI_SOAT');
assertBooking(count($db2->invoices) === 0, 'No invoice created for CAN_DOI_SOAT');

// 7. Kiểm tra Chặn xác nhận đơn đã hết hạn giữ chỗ
$db3 = new BookingAdminMemoryPDO();
$bookingService3 = new BookingManagementService($db3);
try {
    $bookingService3->confirmBookingAndPayment(
        2, // Booking 2 đã hết hạn
        'FT_LATE_TRANSFER',
        '150000.00',
        null,
        null,
        $facilityMgr
    );
    assertBooking(false, 'Expired booking must NOT be confirmed');
} catch (ValidationException $e) {
    assertBooking(isset($e->errors['booking']), 'Expired booking rejection verified');
    assertBooking(count($db3->invoices) === 0, 'No invoice created for expired booking');
}

// 8. Chuyển CAN_DOI_SOAT cho đơn hết hạn (Khách chuyển tiền muộn)
$resLate = $bookingService3->markNeedsReconciliation(
    2,
    '150000.00',
    'FT_LATE_TRANSFER',
    'Khách chuyển tiền muộn khi đơn đã hết hạn giữ chỗ',
    $facilityMgr
);
assertBooking($resLate['success'] === true, 'Late transfer recorded to CAN_DOI_SOAT');
assertBooking($db3->payments[2]['trang_thai'] === 'CAN_DOI_SOAT', 'Expired payment updated to CAN_DOI_SOAT for refund');
assertBooking(count($db3->invoices) === 0, 'No invoice created for late reconciled payment');

// 9. Kiểm tra Chống IDOR: Quản lý cơ sở 2 không được xác nhận booking của cơ sở 1
$facility2Mgr = ['ten_vai_tro' => 'QUAN_LY_CO_SO', 'ma_cong_ty' => 2, 'ma_co_so' => 2, 'ma_nhan_vien' => 9, 'trang_thai' => 'HOAT_DONG', 'trang_thai_nhan_vien' => 'DANG_LAM', 'trang_thai_co_so' => 'HOAT_DONG', 'trang_thai_cong_ty' => 'HOAT_DONG', 'cong_ty_cua_co_so' => 2];
$db4 = new BookingAdminMemoryPDO();
$bookingService4 = new BookingManagementService($db4);
try {
    $bookingService4->confirmBookingAndPayment(
        1, // booking 1 thuộc cơ sở 1
        'FT_IDOR_TEST',
        '220000.00',
        null,
        null,
        $facility2Mgr
    );
    assertBooking(false, 'Cross-facility confirmation must be rejected with 403');
} catch (HttpException $e) {
    assertBooking($e->status === 403, 'Cross-facility IDOR prevented with 403');
    assertBooking(count($db4->invoices) === 0, 'No invoice created on cross-facility IDOR attempt');
}

// 10. Test 1: Booking quá hạn nhưng đã tải ảnh biên lai (Ưu tiên 1 - Chống Double Booking)
$dbReceipt = new BookingAdminMemoryPDO();
$dbReceipt->payments[2]['ghi_chu'] = '[BIEN_LAI:/uploads/receipts/test_receipt.jpg] Khách gửi biên lai';
$bookingServiceReceipt = new BookingManagementService($dbReceipt);
try {
    $bookingServiceReceipt->confirmBookingAndPayment(
        2, // Đã hết hạn
        'FT_LATE_WITH_RECEIPT',
        '150000.00',
        null,
        null,
        $facilityMgr
    );
    assertBooking(false, 'Expired booking WITH receipt must STILL be rejected');
} catch (ValidationException $e) {
    assertBooking(isset($e->errors['booking']), 'Expired booking with receipt triggers validation error');
    assertBooking($dbReceipt->bookings[2]['trang_thai_dat_san'] === 'HET_HAN', 'Expired booking remains HET_HAN');
    assertBooking($dbReceipt->payments[2]['trang_thai'] !== 'THANH_CONG', 'Payment is NOT updated to THANH_CONG');
    assertBooking(count($dbReceipt->invoices) === 0, 'No invoice created for expired booking with receipt');
}

// 11. Test 2: Booking đã hủy (DA_HUY)
$dbHuy = new BookingAdminMemoryPDO();
$dbHuy->bookings[3] = [
    'ma_dat_san' => 3,
    'ma_co_so' => 1,
    'ma_khach_hang' => 12,
    'ma_nhan_vien' => null,
    'ngay_tao' => date('Y-m-d H:i:s', time() - 300),
    'bat_dau_giu_san_luc' => date('Y-m-d H:i:s', time() - 300),
    'het_han_giu_san_luc' => date('Y-m-d H:i:s', time() + 300),
    'tong_tien_san' => '100000.00',
    'tong_tien_dich_vu' => '0.00',
    'tong_tien_tam_tinh' => '100000.00',
    'trang_thai_dat_san' => 'DA_HUY',
    'ghi_chu' => 'Đơn khách đã hủy',
];
$dbHuy->payments[3] = [
    'ma_thanh_toan' => 3,
    'ma_dat_san' => 3,
    'ma_tham_chieu' => 'PAY261102B0003HUY',
    'so_tien' => '100000.00',
    'so_tien_thuc_nhan' => null,
    'ma_giao_dich' => null,
    'phuong_thuc' => 'CHUYEN_KHOAN',
    'trang_thai' => 'HUY',
    'ngay_tao' => date('Y-m-d H:i:s', time() - 300),
    'ngay_thanh_toan' => null,
    'ghi_chu' => null,
];
$bookingServiceHuy = new BookingManagementService($dbHuy);
try {
    $bookingServiceHuy->confirmBookingAndPayment(
        3,
        'FT_CANCELLED_BOOKING',
        '100000.00',
        null,
        null,
        $facilityMgr
    );
    assertBooking(false, 'Cancelled booking (DA_HUY) must be rejected');
} catch (ValidationException $e) {
    assertBooking(isset($e->errors['booking']), 'Cancelled booking triggers validation error');
    assertBooking($dbHuy->bookings[3]['trang_thai_dat_san'] === 'DA_HUY', 'Booking remains DA_HUY');
    assertBooking($dbHuy->payments[3]['trang_thai'] === 'HUY', 'Payment remains HUY, NOT THANH_CONG');
    assertBooking(count($dbHuy->invoices) === 0, 'No invoice created for cancelled booking');
}

// 12. Test 3: Lỗi cập nhật trạng thái booking (Mô phỏng lỗi DB và kiểm tra Rollback)
$dbErr = new BookingAdminMemoryPDO();
$dbErr->simulateBookingUpdateFailure = true;
$bookingServiceErr = new BookingManagementService($dbErr);
try {
    $bookingServiceErr->confirmBookingAndPayment(
        1,
        'FT_DB_ERROR',
        '220000.00',
        null,
        null,
        $facilityMgr
    );
    assertBooking(false, 'DB error must throw exception');
} catch (PDOException $e) {
    assertBooking($dbErr->inTransaction() === false, 'Transaction was rolled back on DB error');
    assertBooking($dbErr->payments[1]['trang_thai'] === 'CHO_THANH_TOAN', 'Payment was rolled back to CHO_THANH_TOAN');
    assertBooking($dbErr->bookings[1]['trang_thai_dat_san'] === 'CHO_THANH_TOAN', 'Booking was rolled back to CHO_THANH_TOAN');
    assertBooking($dbErr->services[101]['so_luong_ton'] === 10, 'Service inventory untouched on DB rollback');
    assertBooking(count($dbErr->invoices) === 0, 'Zero invoices created when DB error triggers rollback');
}

// 13. Test 4 & 5: Tồn kho không đủ & Rollback khi trừ kho thất bại
$dbStock = new BookingAdminMemoryPDO();
$dbStock->services[101]['so_luong_ton'] = 1; // Chỉ còn 1 nhưng cần 2
$bookingServiceStock = new BookingManagementService($dbStock);
try {
    $bookingServiceStock->confirmBookingAndPayment(
        1,
        'FT_INSUFFICIENT_STOCK',
        '220000.00',
        null,
        null,
        $facilityMgr
    );
    assertBooking(false, 'Insufficient inventory must reject confirmation');
} catch (ValidationException $e) {
    assertBooking(isset($e->errors['inventory']), 'Insufficient inventory triggers validation error');
    assertBooking($dbStock->inTransaction() === false, 'Transaction was rolled back when inventory deduction failed');
    assertBooking($dbStock->payments[1]['trang_thai'] === 'CHO_THANH_TOAN', 'Payment rolled back to CHO_THANH_TOAN');
    assertBooking($dbStock->bookings[1]['trang_thai_dat_san'] === 'CHO_THANH_TOAN', 'Booking rolled back to CHO_THANH_TOAN');
    assertBooking($dbStock->services[101]['so_luong_ton'] === 1, 'Service stock preserved at 1, no partial deduction');
    assertBooking(count($dbStock->invoices) === 0, 'No invoice created when inventory fails');
}

// 14. Test 6: Xác nhận trùng (Double confirmation & Duplicate bank transaction)
$dbDup = new BookingAdminMemoryPDO();
$bookingServiceDup = new BookingManagementService($dbDup);
$dbDup->payments[2]['trang_thai'] = 'CHO_THANH_TOAN';
$dbDup->bookings[2]['trang_thai_dat_san'] = 'CHO_THANH_TOAN';
$dbDup->bookings[2]['het_han_giu_san_luc'] = date('Y-m-d H:i:s', time() + 300);

$bookingServiceDup->confirmBookingAndPayment(1, 'FT_DUP_TXN', '220000.00', null, null, $facilityMgr);
assertBooking(count($dbDup->invoices) === 1, 'First confirmation created invoice');
try {
    $bookingServiceDup->confirmBookingAndPayment(2, 'FT_DUP_TXN', '150000.00', null, null, $facilityMgr);
    assertBooking(false, 'Duplicate bank transaction ID must be rejected');
} catch (ValidationException $e) {
    assertBooking(isset($e->errors['ma_giao_dich']), 'Duplicate transaction ID triggers validation error');
    assertBooking($dbDup->payments[2]['trang_thai'] === 'CHO_THANH_TOAN', 'Payment 2 stays unconfirmed');
    assertBooking(count($dbDup->invoices) === 1, 'No second invoice created on duplicate txn rejection');
}

// 15. Test 7: Hai request xử lý đồng thời (Race condition)
$dbRace = new BookingAdminMemoryPDO();
$bookingServiceRace = new BookingManagementService($dbRace);
$resRaceA = $bookingServiceRace->confirmBookingAndPayment(1, 'FT_RACE_001', '220000.00', null, null, $facilityMgr);
assertBooking($resRaceA['success'] === true, 'Concurrent Request A confirms successfully');
assertBooking($dbRace->services[101]['so_luong_ton'] === 8, 'Stock deducted once to 8');
assertBooking(count($dbRace->invoices) === 1, 'Exactly one invoice created by Request A');

// Request B đến ngay sau đó trên cùng booking 1
try {
    $bookingServiceRace->confirmBookingAndPayment(1, 'FT_RACE_002', '220000.00', null, null, $facilityMgr);
    assertBooking(false, 'Concurrent Request B must be rejected');
} catch (ValidationException $e) {
    assertBooking(isset($e->errors['booking']), 'Concurrent Request B rejected because booking already DA_XAC_NHAN');
    assertBooking($dbRace->services[101]['so_luong_ton'] === 8, 'Stock is NOT deducted a second time');
    assertBooking(count($dbRace->invoices) === 1, 'Request B did NOT create a second invoice');
}

// 16. Test 8: Lỗi khi tạo hóa đơn -> ROLLBACK TOÀN BỘ TRANSACTION (PR4 Core Requirement)
$dbInvFail = new BookingAdminMemoryPDO();
$dbInvFail->simulateInvoiceCreationFailure = true;
$bookingServiceInvFail = new BookingManagementService($dbInvFail);
try {
    $bookingServiceInvFail->confirmBookingAndPayment(
        1,
        'FT_INVOICE_FAIL_TEST',
        '220000.00',
        null,
        null,
        $facilityMgr
    );
    assertBooking(false, 'Failure in invoice creation MUST roll back the entire transaction');
} catch (PDOException $e) {
    assertBooking($dbInvFail->inTransaction() === false, 'Transaction was rolled back when invoice creation failed');
    assertBooking($dbInvFail->payments[1]['trang_thai'] === 'CHO_THANH_TOAN', 'Payment rolled back to CHO_THANH_TOAN (NOT THANH_CONG)');
    assertBooking($dbInvFail->bookings[1]['trang_thai_dat_san'] === 'CHO_THANH_TOAN', 'Booking rolled back to CHO_THANH_TOAN (NOT DA_XAC_NHAN)');
    assertBooking($dbInvFail->services[101]['so_luong_ton'] === 10, 'Service stock preserved at 10, no partial deduction');
    assertBooking(count($dbInvFail->invoices) === 0, 'Zero invoices in database after failure');
}

// 17. Test 9: Anti-IDOR bảo vệ hóa đơn
$invService = new InvoiceService($db, new HoaDon($db));
$invoiceId = (int) $res['invoice_id'];

// Khách hàng sở hữu (ma_khach_hang = 10) xem được
$invOwner = $invService->getInvoice($invoiceId, null, 10);
assertBooking($invOwner['invoice']['ma_hoa_don'] === $invoiceId, 'Invoice owner can access invoice');

// Khách hàng khác (ma_khach_hang = 99) bị từ chối 403
try {
    $invService->getInvoice($invoiceId, null, 99);
    assertBooking(false, 'Other customer must be rejected with 403');
} catch (HttpException $e) {
    assertBooking($e->status === 403, 'Cross-customer invoice IDOR prevented with 403');
}

// Quản lý cơ sở 2 không xem được hóa đơn cơ sở 1
$scopeFacility2 = ['company' => 2, 'facility' => 2];
try {
    $invService->getInvoice($invoiceId, $scopeFacility2, null);
    assertBooking(false, 'Other facility manager must be rejected with 403');
} catch (HttpException $e) {
    assertBooking($e->status === 403, 'Cross-facility invoice IDOR prevented with 403');
}

// 18. Test 10: Quy trình Hủy đặt sân bởi Admin (Giải phóng sân về trạng thái TRỐNG)
$dbCancel = new BookingAdminMemoryPDO();
$mgrCancel = new BookingManagementService($dbCancel);

// Chuyển booking 1 sang CAN_DOI_SOAT trước
$mgrCancel->markNeedsReconciliation(1, '50000', 'TXN_OLD', 'Khách chuyển thiếu tiền', $admin);
assertBooking($dbCancel->payments[1]['trang_thai'] === 'CAN_DOI_SOAT', 'Payment marked as CAN_DOI_SOAT');

// Khách không chuyển thêm tiền -> Admin hủy đặt sân để giải phóng khung giờ
$cancelResult = $mgrCancel->cancelBookingByAdmin(1, 'Khách hàng không chuyển phần tiền còn thiếu sau khi liên hệ Zalo', $admin);
assertBooking($cancelResult['success'] === true, 'Admin cancellation successful');
assertBooking($dbCancel->bookings[1]['trang_thai_dat_san'] === 'DA_HUY', 'Booking status updated to DA_HUY (slot is now free/trống)');
assertBooking($dbCancel->payments[1]['trang_thai'] === 'HUY', 'Payment status updated to HUY');
assertBooking(str_contains($dbCancel->bookings[1]['ghi_chu'], '[HUY_BOI_ADMIN:'), 'Booking note recorded admin cancellation tag');

// Thử hủy lại đơn đã hủy -> Phải ném ValidationException
try {
    $mgrCancel->cancelBookingByAdmin(1, 'Hủy lần 2', $admin);
    assertBooking(false, 'Cannot cancel already cancelled booking');
} catch (ValidationException $e) {
    assertBooking(isset($e->errors['booking']), 'Validation error when cancelling already cancelled booking');
}

// Thử hủy bởi user không có quyền -> 403
$unauthCancelUser = ['ten_vai_tro' => 'KHACH_HANG'];
try {
    $mgrCancel->cancelBookingByAdmin(2, 'Khách hủy', $unauthCancelUser);
    assertBooking(false, 'Unauthorized user cannot cancel booking as admin');
} catch (HttpException $e) {
    assertBooking($e->status === 403, 'Unauthorized cancel rejected with 403');
}

// 19. Test 11: Xác nhận thành công từ trạng thái CẦN ĐỐI SOÁT (CAN_DOI_SOAT)
$dbReconf = new BookingAdminMemoryPDO();
$mgrReconf = new BookingManagementService($dbReconf);

// Đặt booking 1 sang CAN_DOI_SOAT
$mgrReconf->markNeedsReconciliation(1, '100000', 'TXN_PARTIAL', 'Chờ đối soát Zalo', $admin);
assertBooking($dbReconf->payments[1]['trang_thai'] === 'CAN_DOI_SOAT', 'Booking 1 is in CAN_DOI_SOAT');

// Sau khi đối soát Zalo, khách gửi đủ 220.000đ -> Admin bấm Xác nhận thành công
$confirmReconcileRes = $mgrReconf->confirmBookingAndPayment(1, 'FT_FINAL_CONFIRM_123', '220000', date('Y-m-d H:i:s'), 'Đối soát Zalo thành công', $admin);
assertBooking($confirmReconcileRes['success'] === true, 'Confirmation from CAN_DOI_SOAT succeeded');
assertBooking($dbReconf->bookings[1]['trang_thai_dat_san'] === 'DA_XAC_NHAN', 'Booking updated to DA_XAC_NHAN');
assertBooking($dbReconf->payments[1]['trang_thai'] === 'THANH_CONG', 'Payment updated to THANH_CONG');
assertBooking(!empty($confirmReconcileRes['invoice_id']), 'Invoice generated upon confirmation from CAN_DOI_SOAT');

// 20. Test 12: Thao tác duyệt, đối soát, hủy đơn và bảo vệ Anti-IDOR cho Quản lý cơ sở và Nhân viên cơ sở
$dbStaff = new BookingAdminMemoryPDO();
$mgrStaff = new BookingManagementService($dbStaff);

$staffF1 = [
    'ten_vai_tro' => 'NHAN_VIEN',
    'trang_thai' => 'HOAT_DONG',
    'ma_nhan_vien' => 22,
    'trang_thai_nhan_vien' => 'DANG_LAM',
    'ma_cong_ty' => 1,
    'trang_thai_cong_ty' => 'HOAT_DONG',
    'ma_co_so' => 1,
    'trang_thai_co_so' => 'HOAT_DONG',
    'cong_ty_cua_co_so' => 1,
];

$mgrF1 = [
    'ten_vai_tro' => 'QUAN_LY_CO_SO',
    'trang_thai' => 'HOAT_DONG',
    'ma_nhan_vien' => 20,
    'trang_thai_nhan_vien' => 'DANG_LAM',
    'ma_cong_ty' => 1,
    'trang_thai_cong_ty' => 'HOAT_DONG',
    'ma_co_so' => 1,
    'trang_thai_co_so' => 'HOAT_DONG',
    'cong_ty_cua_co_so' => 1,
];

$staffF2 = [
    'ten_vai_tro' => 'NHAN_VIEN',
    'trang_thai' => 'HOAT_DONG',
    'ma_nhan_vien' => 25,
    'trang_thai_nhan_vien' => 'DANG_LAM',
    'ma_cong_ty' => 2,
    'trang_thai_cong_ty' => 'HOAT_DONG',
    'ma_co_so' => 2,
    'trang_thai_co_so' => 'HOAT_DONG',
    'cong_ty_cua_co_so' => 2,
];

// A. Nhân viên F1 chuyển trạng thái sang CAN_DOI_SOAT cho đơn F1
$recResStaff = $mgrStaff->markNeedsReconciliation(1, '150000', 'STAFF_REC_TXN', 'Nhân viên yêu cầu đối soát số tiền thiếu', $staffF1);
assertBooking($recResStaff['success'] === true, 'Staff at F1 successfully requested reconciliation for booking at F1');
assertBooking($dbStaff->payments[1]['trang_thai'] === 'CAN_DOI_SOAT', 'Payment updated to CAN_DOI_SOAT by staff');

// B. Nhân viên F1 xác nhận thành công đơn F1 và tự động lập hóa đơn
$confResStaff = $mgrStaff->confirmBookingAndPayment(1, 'STAFF_MATCHED_123', '220000', date('Y-m-d H:i:s'), 'Nhân viên xác nhận đủ tiền', $staffF1);
assertBooking($confResStaff['success'] === true, 'Staff at F1 successfully confirmed booking at F1');
assertBooking($dbStaff->bookings[1]['trang_thai_dat_san'] === 'DA_XAC_NHAN', 'Booking updated to DA_XAC_NHAN by staff');
assertBooking((int) $dbStaff->bookings[1]['ma_nhan_vien'] === 22, 'Staff ID 22 recorded on booking');
assertBooking(!empty($confResStaff['invoice_id']), 'Invoice successfully generated with staff confirmation');

// Expired bookings cannot be cancelled again; a live pending booking can be cancelled.
try {
    $mgrStaff->cancelBookingByAdmin(2, 'Không được hủy lại đơn quá hạn', $mgrF1);
    assertBooking(false, 'Expired cancellation must be rejected');
} catch (ValidationException $e) {
    assertBooking(isset($e->errors['booking']), 'Expired cancellation rejected by backend');
}
$dbStaff->bookings[2]['trang_thai_dat_san'] = 'CHO_THANH_TOAN';
$dbStaff->bookings[2]['het_han_giu_san_luc'] = date('Y-m-d H:i:s', time() + 360);
$dbStaff->payments[2]['trang_thai'] = 'CHO_THANH_TOAN';
$cancelResMgr = $mgrStaff->cancelBookingByAdmin(2, 'Quản lý cơ sở hủy đơn khách không đến', $mgrF1);
assertBooking($cancelResMgr['success'] === true, 'Facility Manager at F1 successfully cancelled booking at F1');
assertBooking($dbStaff->bookings[2]['trang_thai_dat_san'] === 'DA_HUY', 'Booking 2 cancelled by Facility Manager');

// D. Anti-IDOR: Nhân viên F2 (cơ sở 2) cố tình thao tác trên đơn của cơ sở 1 -> Phải bị chặn 403
$dbIdor = new BookingAdminMemoryPDO();
$mgrIdor = new BookingManagementService($dbIdor);

try {
    $mgrIdor->confirmBookingAndPayment(1, 'HACK_TXN', '220000', date('Y-m-d H:i:s'), 'Staff F2 trying to confirm F1', $staffF2);
    assertBooking(false, 'Cross-facility confirm by staff must be rejected');
} catch (HttpException $e) {
    assertBooking($e->status === 403, 'Cross-facility confirm rejected with 403');
}

try {
    $mgrIdor->markNeedsReconciliation(1, '50000', 'HACK_REC', 'Staff F2 trying to reconcile F1', $staffF2);
    assertBooking(false, 'Cross-facility reconcile by staff must be rejected');
} catch (HttpException $e) {
    assertBooking($e->status === 403, 'Cross-facility reconcile rejected with 403');
}

try {
    $mgrIdor->cancelBookingByAdmin(1, 'Staff F2 trying to cancel F1', $staffF2);
    assertBooking(false, 'Cross-facility cancel by staff must be rejected');
} catch (HttpException $e) {
    assertBooking($e->status === 403, 'Cross-facility cancel rejected with 403');
}

echo "PASS: Tất cả {$checks} kiểm thử nghiệp vụ Quản lý Booking, Xác nhận & Lập Hóa đơn (PR4) đều đạt 100%!" . PHP_EOL;
