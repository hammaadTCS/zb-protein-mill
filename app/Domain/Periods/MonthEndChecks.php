<?php

declare(strict_types=1);

namespace App\Domain\Periods;

use App\Domain\Checks\CashCounts;
use App\Domain\MoneyAccounts\Enums\MoneyAccountType;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\Payments\Enums\ApprovalStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Periods\Models\BankReconciliation;
use App\Domain\Periods\Models\Period;
use App\Domain\Purchases\Enums\RateStatus;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Reports\Queries\TrialBalance;
use App\Domain\Sales\Models\Sale;
use App\Support\Decimal;
use App\Support\EntryStatus;
use Illuminate\Support\Collection;

/**
 * The six month-end checks of card T7.1, in order. Balances are always the live ledger
 * balance at the month's last day, so a fix entered after a statement or count counts.
 */
final class MonthEndChecks
{
    /**
     * @return list<MonthEndCheck>
     */
    public static function run(Month $month, bool $acceptPendingRates): array
    {
        return [
            self::previousMonths($month),
            self::pendingRates($month, $acceptPendingRates),
            self::awaitingApproval($month),
            self::trialBalance($month),
            ...self::bankMatches($month),
            ...self::cashMatches($month),
        ];
    }

    /** BR-MEC-01: months close in order, from the month of the system start date. */
    private static function previousMonths(Month $month): MonthEndCheck
    {
        $open = [];
        $first = Month::first();

        for ($m = $first; $m !== null && $m->isBefore($month); $m = $m->next()) {
            if (! $m->isClosed()) {
                $open[] = $m->key();
            }
        }

        return new MonthEndCheck(MonthEndCheck::PREVIOUS_MONTHS, $open === [], ['open_months' => $open]);
    }

    /** BR-MEC-02: rate-pending entries dated in the month pass only when accepted. */
    private static function pendingRates(Month $month, bool $accepted): MonthEndCheck
    {
        $vouchers = [];

        foreach ([Purchase::class, Sale::class] as $model) {
            array_push($vouchers, ...$model::query()
                ->where('status', EntryStatus::Active->value)
                ->where('rate_status', RateStatus::Pending->value)
                ->whereBetween('entry_date', [$month->firstDay(), $month->lastDay()])
                ->orderBy('id')
                ->pluck('voucher_no')
                ->all());
        }

        return new MonthEndCheck(MonthEndCheck::PENDING_RATES, $vouchers === [] || $accepted, [
            'vouchers' => $vouchers,
            'accepted' => $accepted,
        ]);
    }

    /** BR-MEC-03: no payment dated in the month may await approval. */
    private static function awaitingApproval(Month $month): MonthEndCheck
    {
        $vouchers = Payment::query()
            ->where('status', EntryStatus::Active->value)
            ->where('approval_status', ApprovalStatus::Pending->value)
            ->whereBetween('entry_date', [$month->firstDay(), $month->lastDay()])
            ->orderBy('id')
            ->pluck('voucher_no')
            ->all();

        return new MonthEndCheck(MonthEndCheck::AWAITING_APPROVAL, $vouchers === [], ['vouchers' => $vouchers]);
    }

    /** BR-MEC-04: the trial balance as of the last day balances. */
    private static function trialBalance(Month $month): MonthEndCheck
    {
        $trial = TrialBalance::asOf($month->lastDay());

        return new MonthEndCheck(MonthEndCheck::TRIAL_BALANCE, $trial->balanced, [
            'total_debit' => $trial->totalDebit,
            'total_credit' => $trial->totalCredit,
        ]);
    }

    /**
     * BR-MEC-05: each active bank's latest statement for the month equals the ledger.
     *
     * @return list<MonthEndCheck>
     */
    private static function bankMatches(Month $month): array
    {
        $period = $month->period();

        return array_values(self::accounts(MoneyAccountType::Bank)->map(function (MoneyAccount $bank) use ($month, $period): MonthEndCheck {
            $system = $bank->balance($month->lastDay());
            $statement = $period === null ? null : self::latestStatement($period, $bank)?->statement_balance;
            $difference = $statement === null ? null : Decimal::sub($statement, $system);

            return new MonthEndCheck(MonthEndCheck::BANK_MATCH, $difference !== null && Decimal::isZero($difference), [
                'money_account_id' => $bank->id,
                'money_account' => $bank->name,
                'statement_balance' => $statement,
                'system_balance' => $system,
                'difference' => $difference,
            ]);
        })->values()->all());
    }

    /**
     * BR-MEC-06: each active cash account's latest count on the last day equals the
     * ledger. A "business closed" mark is not a count.
     *
     * @return list<MonthEndCheck>
     */
    private static function cashMatches(Month $month): array
    {
        return array_values(self::accounts(MoneyAccountType::Cash)->map(function (MoneyAccount $cash) use ($month): MonthEndCheck {
            $system = $cash->balance($month->lastDay());
            $count = CashCounts::latest($cash, $month->lastDay());
            $counted = $count === null || $count->business_closed ? null : $count->counted_amount;
            $difference = $counted === null ? null : Decimal::sub($counted, $system);

            return new MonthEndCheck(MonthEndCheck::CASH_MATCH, $difference !== null && Decimal::isZero($difference), [
                'money_account_id' => $cash->id,
                'money_account' => $cash->name,
                'counted_amount' => $counted,
                'system_balance' => $system,
                'difference' => $difference,
            ]);
        })->values()->all());
    }

    /** The latest statement of a bank account for a month, if any. */
    public static function latestStatement(Period $period, MoneyAccount $bank): ?BankReconciliation
    {
        return BankReconciliation::query()
            ->where('period_id', $period->id)
            ->where('money_account_id', $bank->id)
            ->latest('id')
            ->first();
    }

    /**
     * @return Collection<int, MoneyAccount>
     */
    private static function accounts(MoneyAccountType $type): Collection
    {
        return MoneyAccount::query()->active()->where('type', $type->value)->orderBy('id')->get();
    }
}
