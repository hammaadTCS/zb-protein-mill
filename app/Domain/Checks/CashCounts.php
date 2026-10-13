<?php

declare(strict_types=1);

namespace App\Domain\Checks;

use App\Domain\Checks\Models\CashCount;
use App\Domain\MoneyAccounts\Enums\MoneyAccountType;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use Illuminate\Validation\ValidationException;

/** Shared rules of the daily cash check (BR-CHK-01 to BR-CHK-04). */
final class CashCounts
{
    /** BR-CHK-02: the ledger balance of the cash account with every posting up to that date. */
    public static function systemAmount(MoneyAccount $cash, string $date): string
    {
        return $cash->balance($date);
    }

    /** BR-CHK-01: counts are for cash accounts only. */
    public static function assertCash(MoneyAccount $account): void
    {
        if ($account->type !== MoneyAccountType::Cash) {
            throw ValidationException::withMessages(['money_account_id' => __('Select a cash account.')]);
        }
    }

    /** The latest count or dismissal of an account for a day, if any. */
    public static function latest(MoneyAccount $cash, string $date): ?CashCount
    {
        return CashCount::query()
            ->where('money_account_id', $cash->id)
            ->where('count_date', $date)
            ->latest('id')
            ->first();
    }
}
