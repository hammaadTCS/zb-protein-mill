<?php

declare(strict_types=1);

namespace App\Domain\Checks\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Checks\CashCounts;
use App\Domain\Checks\Models\CashCount;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Models\User;
use App\Support\Today;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BR-CHK-04: the Owner marks a day the business was closed, so no cash count is
 * expected; stored as a cash_counts row with business_closed and no counted amount.
 */
final class DismissCashCount
{
    public function handle(User $owner, MoneyAccount $cash, string $date): CashCount
    {
        return DB::transaction(function () use ($owner, $cash, $date): CashCount {
            CashCounts::assertCash($cash);

            if ($date > Today::date()) {
                throw ValidationException::withMessages(['date' => __('Date cannot be in the future.')]);
            }

            $count = CashCount::query()->create([
                'company_id' => $owner->company_id,
                'count_date' => $date,
                'money_account_id' => $cash->id,
                'counted_amount' => null,
                'system_amount' => CashCounts::systemAmount($cash, $date),
                'difference' => null,
                'business_closed' => true,
                'counted_by' => $owner->id,
            ]);

            AuditLogger::log(AuditActions::CASH_COUNT_ENTERED, $count, [], [
                'count_date' => $count->count_date,
                'money_account_id' => $count->money_account_id,
                'business_closed' => true,
            ], by: $owner);

            return $count;
        });
    }
}
