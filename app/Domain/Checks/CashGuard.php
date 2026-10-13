<?php

declare(strict_types=1);

namespace App\Domain\Checks;

use App\Domain\Ledger\LedgerBalance;
use App\Domain\MoneyAccounts\Enums\MoneyAccountType;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Support\Decimal;
use App\Support\Warnings\Warnings;
use Illuminate\Database\Eloquent\Model;

/**
 * BR-GEN-10: warn when a save would make a cash account's balance negative
 * ("Cash will go below zero. An entry may be missing."). Bank accounts never warn.
 * Called by every save that takes money out of cash.
 */
final class CashGuard
{
    /**
     * @param  string  $outflow  money leaving the account with this save (2 decimals)
     * @param  Model|null  $excluding  the record being edited: its current journal is left out
     */
    public static function check(Warnings $warnings, MoneyAccount $account, string $outflow, ?Model $excluding = null): void
    {
        if ($account->type !== MoneyAccountType::Cash || ! Decimal::isPositive($outflow)) {
            return;
        }

        $balance = $account->balance();
        $journalId = $excluding?->getAttribute('journal_entry_id');

        if (is_int($journalId)) {
            // Take the record's own effect out first, so an edit counts only the difference.
            $balance = Decimal::sub($balance, LedgerBalance::journalAccountNet($journalId, $account->ledger_account_id));
        }

        if (Decimal::isNegative(Decimal::sub($balance, $outflow))) {
            $warnings->raise('negative_cash', __('Cash will go below zero. An entry may be missing.'), [
                'account' => $account->name,
                'balance' => $balance,
                'outflow' => $outflow,
            ]);
        }
    }
}
