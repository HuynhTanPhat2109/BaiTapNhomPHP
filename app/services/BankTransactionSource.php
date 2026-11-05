<?php
namespace App\Services;

/** Server-side adapter contract. No provider is enabled by default. */
interface BankTransactionSource
{
    /**
     * Return only an authenticated, settled bank credit, or null when absent.
     * The adapter must verify provider credentials/signatures, replay protection and
     * recipient ownership before returning data. Never pass browser/webhook JSON through.
     * Multiple/ambiguous transfers must not be selected arbitrarily.
     *
     * @return array{transaction_id:string, reference:string, amount:string,
     *   occurred_at:string, currency:string, direction:string,
     *   bank_bin:string, account_number:string}|null
     * occurred_at is the actual bank time in Asia/Ho_Chi_Minh (Y-m-d H:i:s).
     */
    public function findVerifiedByReference(string $reference): ?array;
}
