<?php
namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

/** Read-only verification boundary; VietQR/receipts/manual DB values are not proof. */
final class PaymentVerificationService
{
    public function __construct(private PDO $pdo, private ?BankTransactionSource $source = null) {}

    public function review(array $payment): array
    {
        $result = ['state' => 'waiting', 'can_confirm' => false, 'transaction' => null,
            'bank_name' => null, 'message' => 'Chờ giao dịch ngân hàng đã xác thực.'];
        if (!$this->source) {
            return array_replace($result, ['state' => 'unconfigured',
                'message' => 'Chưa kết nối nguồn giao dịch ngân hàng. Có thể xem đối soát thủ công.']);
        }
        if (empty($payment['ma_tham_chieu'])) return $result;
        try {
            $transaction = $this->source->findVerifiedByReference($payment['ma_tham_chieu']);
        } catch (\Throwable $e) {
            return array_replace($result, ['state' => 'unavailable',
                'message' => 'Chưa thể xác minh nguồn ngân hàng. Vui lòng thử lại hoặc xem đối soát.']);
        }
        if ($transaction === null) return $result;
        $result['transaction'] = $transaction;
        try {
            $result['bank_name'] = $this->requireMatch($transaction, $payment);
            return array_replace($result, ['state' => 'matched', 'can_confirm' => true,
                'message' => 'Giao dịch ngân hàng đã xác thực và khớp đơn. Backend sẽ kiểm tra lại khi xác nhận.']);
        } catch (ValidationException $e) {
            return array_replace($result, ['state' => 'reconciliation', 'message' => reset($e->errors)]);
        }
    }

    /** Re-run with locked booking/payment immediately before finalization. No HTTP calls here. */
    public function requireMatch(array $transaction, array $payment): string
    {
        $reject = static function (string $message): never {
            throw new ValidationException(['bank_transaction' => $message . ' Vui lòng xem đối soát.']);
        };
        foreach (['transaction_id', 'reference', 'amount', 'occurred_at', 'currency', 'direction', 'bank_bin', 'account_number'] as $key) {
            if (!isset($transaction[$key]) || !is_string($transaction[$key]) || trim($transaction[$key]) === '') {
                $reject('Dữ liệu ngân hàng xác thực chưa đầy đủ.');
            }
        }
        if ($transaction['direction'] !== 'credit' || $transaction['currency'] !== 'VND') $reject('Giao dịch không phải tiền VND chuyển vào.');
        if ($transaction['reference'] !== ($payment['ma_tham_chieu'] ?? '')) $reject('Mã tham chiếu không khớp.');
        if (trim($transaction['transaction_id']) !== $transaction['transaction_id']
            || mb_strlen($transaction['transaction_id']) < 3 || mb_strlen($transaction['transaction_id']) > 150) $reject('Mã giao dịch không hợp lệ.');
        $cents = static function ($value): ?int {
            if (!is_string($value) || !preg_match('/\A(\d{1,10})(?:\.(\d{1,2}))?\z/', $value, $parts)) return null;
            return (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
        };
        $expected = $cents((string) ($payment['so_tien'] ?? ''));
        $actual = $cents($transaction['amount']);
        if ($actual === null || $actual <= 0 || $actual !== $expected
            || $expected !== $cents((string) ($payment['tong_tien_tam_tinh'] ?? ''))) $reject('Số tiền ngân hàng không khớp số tiền phải thu.');
        $time = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $transaction['occurred_at'], new DateTimeZone('Asia/Ho_Chi_Minh'));
        if (!$time || $time->format('Y-m-d H:i:s') !== $transaction['occurred_at'] || $time->format('Y') < '1000'
            || $time > BookingManagementService::now()) $reject('Thời gian giao dịch ngân hàng không hợp lệ.');
        $created = $payment['ngay_tao_thanh_toan'] ?? $payment['ngay_tao'] ?? '';
        if ($created === '' || $transaction['occurred_at'] < $created
            || empty($payment['het_han_giu_san_luc']) || $transaction['occurred_at'] >= $payment['het_han_giu_san_luc']
            || !BookingManagementService::actions($payment)['confirm']) $reject('Booking hết hạn, đã xử lý hoặc giao dịch nằm ngoài thời hạn thanh toán.');
        // A current default is not a historical bank snapshot. Match the actual credited
        // account against the facility instead of assuming the current default received it.
        $statement = $this->pdo->prepare("SELECT ten_ngan_hang FROM tai_khoan_ngan_hang_co_so
            WHERE ma_co_so = :facility AND ma_bin = :bin AND so_tai_khoan = :account AND trang_thai = 'HOAT_DONG'");
        $statement->execute(['facility' => (int) $payment['ma_co_so'], 'bin' => $transaction['bank_bin'], 'account' => $transaction['account_number']]);
        $bank = $statement->fetchColumn();
        if ($bank === false) $reject('Tài khoản nhận tiền không khớp tài khoản hoạt động của cơ sở.');
        return (string) $bank;
    }
}
