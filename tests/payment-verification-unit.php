<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Real services/SQL on RAM only. The adapter below is test-only, not a bank simulator in production.
define('STRYKE_PR6_CONTRACT', true);
require __DIR__ . '/fixtures/booking-pr6.php';

use App\Services\{BankTransactionSource, BookingManagementService, PaymentVerificationService, ValidationException};
use Core\HttpException;

final class VerifiedFixtureSource implements BankTransactionSource
{
    public ?array $transaction = null;
    public ?Closure $duringLookup = null;
    public bool $unavailable = false;
    public int $calls = 0;
    public function findVerifiedByReference(string $reference): ?array
    {
        $this->calls++;
        if ($this->unavailable) throw new RuntimeException('Test provider unavailable');
        if ($this->duringLookup) { $callback = $this->duringLookup; $this->duringLookup = null; $callback(); }
        return $this->transaction;
    }
}
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void { if (!$ok) throw new RuntimeException($label); $checks++; };
$tables = ['dat_san', 'thanh_toan', 'dich_vu', 'chi_tiet_dich_vu_dat', 'hoa_don'];
$snapshot = static function () use ($pdo, $tables): array {
    $data = []; foreach ($tables as $table) $data[$table] = $pdo->query("SELECT * FROM $table ORDER BY 1")->fetchAll(); return $data;
};
$seed = $snapshot();
$reset = static function () use ($pdo, $seed): void {
    foreach ($seed as $table => $rows) {
        $pdo->exec('DELETE FROM ' . $table);
        foreach ($rows as $row) $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(',', array_keys($row)) . ') VALUES ('
            . implode(',', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));
    }
    $pdo->exec("UPDATE thanh_toan SET ngay_tao='2000-01-01 00:00:00'");
};
$insert('tai_khoan_ngan_hang_co_so', ['ma_tk_ngan_hang' => 1, 'ma_co_so' => 1, 'ma_bin' => '970422',
    'so_tai_khoan' => 'RAM-ACCOUNT', 'ten_ngan_hang' => 'Ngân hàng fixture', 'trang_thai' => 'HOAT_DONG', 'la_mac_dinh' => 1]);
$pdo->exec('CREATE UNIQUE INDEX fixture_unique_bank_transaction ON thanh_toan(ma_giao_dich)');
$admin = ['ten_vai_tro' => 'QUAN_TRI_TAP_DOAN', 'trang_thai' => 'HOAT_DONG'];
$identity = ['trang_thai' => 'HOAT_DONG', 'ma_nhan_vien' => 1, 'trang_thai_nhan_vien' => 'DANG_LAM',
    'ma_cong_ty' => 1, 'trang_thai_cong_ty' => 'HOAT_DONG', 'ma_co_so' => 1, 'cong_ty_cua_co_so' => 1, 'trang_thai_co_so' => 'HOAT_DONG'];
$source = new VerifiedFixtureSource();
$verification = new PaymentVerificationService($pdo, $source);
$manager = new BookingManagementService($pdo, verification: $verification);
$payment = static function () use ($pdo): array {
    $row = (new App\Models\ThanhToan($pdo))->findByBookingId(1);
    return $row + ['trang_thai_thanh_toan' => $row['trang_thai']];
};
$transfer = static fn(): array => ['transaction_id' => 'RAM-VERIFIED-1', 'reference' => 'PR6-1', 'amount' => '60000.00',
    'occurred_at' => date('Y-m-d H:i:s'), 'currency' => 'VND', 'direction' => 'credit', 'bank_bin' => '970422', 'account_number' => 'RAM-ACCOUNT'];
$reject = static function (callable $action, string $label) use ($check, $snapshot, $pdo): void {
    $before = $snapshot();
    try { $action(); }
    catch (ValidationException|HttpException $e) { $check($snapshot() === $before && !$pdo->inTransaction(), $label . ': no partial writes'); return; }
    throw new RuntimeException('Expected rejection: ' . $label);
};
try {
    $reset();
    $check(!(new PaymentVerificationService($pdo))->review($payment())['can_confirm'], 'unconfigured source cannot verify');
    $reject(fn() => (new BookingManagementService($pdo))->confirmSyncedBookingAndPayment(1, $admin), 'no provider');
    $check(!$verification->review($payment())['can_confirm'], 'no bank transaction');
    $source->unavailable = true;
    $check($verification->review($payment())['state'] === 'unavailable', 'provider errors fail closed'); $source->unavailable = false;
    $source->transaction = $transfer();
    $check($verification->review($payment())['can_confirm'], 'authenticated exact amount/reference/recipient/time matches');
    foreach (['amount' => ['59999', '60001', '0', '-1', '6e4', '60000.001'], 'reference' => ['WRONG'],
        'currency' => ['USD'], 'direction' => ['debit'], 'account_number' => ['FOREIGN'],
        'bank_bin' => ['000000'], 'occurred_at' => ['2000-00-00 00:00:00', '2026-02-30 10:00:00', '2999-01-01 00:00:00'],
        'transaction_id' => ['x', '', ' leading', 'trailing ']] as $field => $values) {
        foreach ($values as $value) {
            $source->transaction = array_replace($transfer(), [$field => $value]);
            $check(!$verification->review($payment())['can_confirm'], "$field=$value requires reconciliation");
            $reject(fn() => $manager->confirmSyncedBookingAndPayment(1, $admin), 'invalid verified ' . $field);
        }
    }
    $source->transaction = $transfer();
    foreach (['QUAN_TRI_TAP_DOAN', 'QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO', 'NHAN_VIEN'] as $role) {
        $reset(); $pdo->exec("UPDATE thanh_toan SET ghi_chu='Existing reconciliation note' WHERE ma_dat_san=1"); $before = $snapshot();
        $manager->confirmSyncedBookingAndPayment(1, ['ten_vai_tro' => $role] + $identity);
        $after = $snapshot();
        $check((int) $after['dich_vu'][0]['so_luong_ton'] === 198 && count($after['hoa_don']) === 1, "$role: inventory hook and invoice once");
        $check($after['dat_san'][0]['trang_thai_dat_san'] === 'DA_XAC_NHAN' && $after['thanh_toan'][0]['trang_thai'] === 'THANH_CONG', "$role: atomic booking/payment");
        $check($after['chi_tiet_dich_vu_dat'] === $before['chi_tiet_dich_vu_dat'], "$role: snapshots unchanged");
        $check($after['thanh_toan'][0]['ngay_thanh_toan'] === $source->transaction['occurred_at'], "$role: actual bank time stored");
        $check($after['thanh_toan'][0]['ghi_chu'] === 'Existing reconciliation note', "$role: bank confirmation preserves existing payment notes");
        $reject(fn() => $manager->confirmSyncedBookingAndPayment(1, ['ten_vai_tro' => $role] + $identity), "$role: duplicate source/request");
    }
    foreach (['DA_HUY', 'HET_HAN'] as $state) {
        $reset(); $pdo->prepare('UPDATE dat_san SET trang_thai_dat_san=? WHERE ma_dat_san=1')->execute([$state]);
        $reject(fn() => $manager->confirmSyncedBookingAndPayment(1, $admin), 'terminal ' . $state);
    }
    $reset(); $pdo->exec("UPDATE dat_san SET het_han_giu_san_luc='2000-01-01 00:00:00' WHERE ma_dat_san=1");
    $reject(fn() => $manager->confirmSyncedBookingAndPayment(1, $admin), 'expired pending');
    $reset(); $pdo->exec("UPDATE thanh_toan SET ma_giao_dich='RAM-VERIFIED-1' WHERE ma_dat_san=4");
    $reject(fn() => $manager->confirmSyncedBookingAndPayment(1, $admin), 'transaction already assigned elsewhere');
    foreach (['0', 'NULL'] as $stock) {
        $reset(); $pdo->exec("UPDATE dich_vu SET so_luong_ton=$stock WHERE ma_dich_vu=1");
        if ($stock === '0') $reject(fn() => $manager->confirmSyncedBookingAndPayment(1, $admin), 'zero stock');
        else { $manager->confirmSyncedBookingAndPayment(1, $admin); $check($snapshot()['dich_vu'][0]['so_luong_ton'] === null, 'untracked NULL stock unchanged'); }
    }
    $reset(); $pdo->exec('DELETE FROM chi_tiet_dich_vu_dat WHERE ma_dat_san=1'); $manager->confirmSyncedBookingAndPayment(1, $admin);
    $check((int) $snapshot()['dich_vu'][0]['so_luong_ton'] === 200, 'booking without services does not deduct');
    $reset(); $pdo->exec("CREATE TRIGGER fail_verified_invoice BEFORE INSERT ON hoa_don BEGIN SELECT RAISE(ABORT, 'RAM invoice failure'); END");
    $before = $snapshot();
    try { $manager->confirmSyncedBookingAndPayment(1, $admin); throw new RuntimeException('Missing invoice failure'); }
    catch (PDOException $e) { $check($snapshot() === $before && !$pdo->inTransaction(), 'invoice failure rolls back stock/payment/booking'); }
    $pdo->exec('DROP TRIGGER fail_verified_invoice');
    // Interleaved stale reads, NOT two MySQL transactions. A wins while B retrieves bank proof.
    $reset(); $source->duringLookup = static fn() => $manager->confirmBookingAndPayment(1, 'RAM-VERIFIED-1', '60000', date('Y-m-d H:i:s'), null, $admin);
    try { $manager->confirmSyncedBookingAndPayment(1, $admin); throw new RuntimeException('Stale second confirmation accepted'); }
    catch (ValidationException $e) { $check((int) $snapshot()['dich_vu'][0]['so_luong_ton'] === 198 && count($snapshot()['hoa_don']) === 1, 'stale second confirmation rejected after first commits'); }
    $reset(); $source->duringLookup = static fn() => $pdo->exec("UPDATE dat_san SET trang_thai_dat_san='DA_HUY' WHERE ma_dat_san=1");
    try { $manager->confirmSyncedBookingAndPayment(1, $admin); throw new RuntimeException('Cancelled while fetching bank proof'); }
    catch (ValidationException $e) { $check($snapshot()['dat_san'][0]['trang_thai_dat_san'] === 'DA_HUY' && (int) $snapshot()['dich_vu'][0]['so_luong_ton'] === 200, 'cancel during provider lookup is rechecked under locks'); }
    $reset(); $pdo->exec("CREATE TRIGGER expire_before_confirmation AFTER UPDATE ON thanh_toan WHEN NEW.trang_thai='THANH_CONG' BEGIN UPDATE dat_san SET het_han_giu_san_luc='2000-01-01 00:00:00' WHERE ma_dat_san=NEW.ma_dat_san; END");
    $reject(fn() => $manager->confirmSyncedBookingAndPayment(1, $admin), 'deadline crossing after stock deduction');
    $pdo->exec('DROP TRIGGER expire_before_confirmation');
    foreach ([['ten_vai_tro' => 'KHACH_HANG', 'trang_thai' => 'HOAT_DONG'],
        ['ten_vai_tro' => 'QUAN_LY_CONG_TY', 'ma_cong_ty' => 2] + $identity,
        ['ten_vai_tro' => 'QUAN_LY_CO_SO', 'ma_co_so' => 2] + $identity,
        ['ten_vai_tro' => 'NHAN_VIEN', 'ma_co_so' => 3, 'ma_cong_ty' => 2, 'cong_ty_cua_co_so' => 2] + $identity] as $user) {
        $reset(); $calls = $source->calls; $reject(fn() => $manager->confirmSyncedBookingAndPayment(1, $user), 'role/company/facility boundary');
        $check($source->calls === $calls, 'out-of-scope identities never query provider');
    }
    echo "PASS: $checks payment verification and real-service RAM integration checks.\n";
} catch (Throwable $e) { fwrite(STDERR, (string) $e . PHP_EOL); exit(1); }
