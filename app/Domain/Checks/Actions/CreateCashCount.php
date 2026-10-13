<?php

declare(strict_types=1);

namespace App\Domain\Checks\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Checks\CashCounts;
use App\Domain\Checks\Models\CashCount;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Models\User;
use App\Support\Decimal;
use App\Support\Today;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Page 28: counted cash against the system amount (BR-CHK-02). The difference is
 * stored and shown, never posted (BR-CHK-03).
 */
final class CreateCashCount
{
    public function handle(User $by, MoneyAccount $cash, string $date, ?string $counted, ?string $note = null): CashCount
    {
        return DB::transaction(function () use ($by, $cash, $date, $counted, $note): CashCount {
            CashCounts::assertCash($cash); // BR-CHK-01

            if ($date > Today::date()) {
                throw ValidationException::withMessages(['date' => __('Date cannot be in the future.')]);
            }

            $text = trim((string) $counted);

            if (preg_match('/^\d+(\.\d{1,2})?$/', $text) !== 1) {
                throw ValidationException::withMessages(['counted_amount' => __('Enter the counted amount: 0 or more, at most 2 decimals.')]);
            }

            $counted = Decimal::money($text);
            $system = CashCounts::systemAmount($cash, $date);
            $note = $note === null || trim($note) === '' ? null : trim($note);

            $count = CashCount::query()->create([
                'company_id' => $by->company_id,
                'count_date' => $date,
                'money_account_id' => $cash->id,
                'counted_amount' => $counted,
                'system_amount' => $system,
                'difference' => Decimal::sub($counted, $system), // BR-CHK-03
                'note' => $note,
                'business_closed' => false,
                'counted_by' => $by->id,
            ]);

            AuditLogger::log(AuditActions::CASH_COUNT_ENTERED, $count, [], [
                'count_date' => $count->count_date,
                'money_account_id' => $count->money_account_id,
                'counted_amount' => $count->counted_amount,
                'system_amount' => $count->system_amount,
                'difference' => $count->difference,
            ], by: $by);

            return $count;
        });
    }
}
