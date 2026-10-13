<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Domain\Ledger\Models\LedgerAccount;
use App\Support\Tenancy\CurrentCompany;
use InvalidArgumentException;
use LogicException;

/**
 * Spec §8.1: codes for accounts the system creates automatically
 * (money accounts, items, expense categories).
 */
final class AccountCodeAllocator
{
    /** @var array<string, array{int, int}> */
    private const RANGES = [
        'cash' => [1010, 1099],
        'bank' => [1100, 1199],
        'purchase_item' => [5020, 5899],
        'sales_item' => [4020, 4899],
        'expense_category' => [6000, 6899],
    ];

    /**
     * The lowest code in the range that the company does not use yet.
     *
     * @throws InvalidArgumentException for an unknown range
     * @throws LogicException when every code in the range is used
     */
    public static function next(string $range): string
    {
        [$first, $last] = self::RANGES[$range] ?? throw new InvalidArgumentException("Unknown account code range {$range}.");

        $used = LedgerAccount::query()
            ->withoutGlobalScope('company')
            ->where('company_id', CurrentCompany::id())
            ->pluck('code')
            ->flip();

        for ($code = $first; $code <= $last; $code++) {
            if (! $used->has((string) $code)) {
                return (string) $code;
            }
        }

        throw new LogicException("Every account code from {$first} to {$last} is in use.");
    }
}
