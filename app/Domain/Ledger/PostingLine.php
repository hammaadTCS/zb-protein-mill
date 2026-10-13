<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

/**
 * One line of a posting request (conventions §6). The account is given by its code
 * (for example '2000') or, for accounts the system created, by its id.
 */
final readonly class PostingLine
{
    public const DEBIT = 'debit';

    public const CREDIT = 'credit';

    public function __construct(
        public string $side,
        public string $amount,
        public ?string $accountCode = null,
        public ?int $ledgerAccountId = null,
        public ?int $partyId = null,
        public ?int $itemId = null,
    ) {}

    /** A debit line on an account given by code (string) or ledger account id (int). */
    public static function debit(string|int $account, string $amount, ?int $partyId = null, ?int $itemId = null): self
    {
        return self::make(self::DEBIT, $account, $amount, $partyId, $itemId);
    }

    /** A credit line on an account given by code (string) or ledger account id (int). */
    public static function credit(string|int $account, string $amount, ?int $partyId = null, ?int $itemId = null): self
    {
        return self::make(self::CREDIT, $account, $amount, $partyId, $itemId);
    }

    private static function make(string $side, string|int $account, string $amount, ?int $partyId, ?int $itemId): self
    {
        return new self(
            side: $side,
            amount: $amount,
            accountCode: is_string($account) ? $account : null,
            ledgerAccountId: is_int($account) ? $account : null,
            partyId: $partyId,
            itemId: $itemId,
        );
    }
}
