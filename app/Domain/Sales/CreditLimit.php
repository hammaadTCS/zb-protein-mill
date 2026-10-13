<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Domain\Ledger\LedgerBalance;
use App\Domain\Parties\Models\Party;
use App\Support\Decimal;
use App\Support\NumberFormat;
use App\Support\Warnings\Warnings;

/**
 * BR-SAL-06: warn when a buyer with a credit limit would owe more than that limit
 * after this save. `$increase` is how much more the buyer owes because of it (the
 * sale amount less money received with it, or the change on an edit).
 */
final class CreditLimit
{
    public static function check(Warnings $warnings, Party $buyer, string $increase): void
    {
        if ($buyer->credit_limit === null || ! Decimal::isPositive($increase)) {
            return;
        }

        $owes = LedgerBalance::byCode('1200', null, $buyer->id); // debit minus credit: what he owes us
        $after = Decimal::add($owes, $increase);

        if (Decimal::compare($after, $buyer->credit_limit) <= 0) {
            return;
        }

        $warnings->raise('credit_limit', __('After this sale :buyer will owe :amount, above the credit limit of :limit.', [
            'buyer' => $buyer->name,
            'amount' => NumberFormat::money($after),
            'limit' => NumberFormat::money($buyer->credit_limit),
        ]), ['owes_after' => $after, 'credit_limit' => $buyer->credit_limit]);
    }
}
