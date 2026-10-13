<?php

declare(strict_types=1);

namespace App\Domain\Periods\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Periods\Models\Period;
use App\Domain\Periods\Models\PeriodEvent;
use App\Domain\Periods\Month;
use App\Domain\Periods\MonthCloseResult;
use App\Domain\Periods\MonthEndChecks;
use App\Domain\Periods\Periods;
use App\Models\User;
use App\Support\Today;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * D-26, BR-MEC-01 to BR-MEC-06: closes a month only when every check passes; then it
 * is locked (BR-MEC-07). Any failing check changes nothing.
 */
final class CloseMonth
{
    public function handle(User $by, string $yyyymm, bool $acceptPendingRates = false): MonthCloseResult
    {
        $month = Month::of($yyyymm);
        $first = Month::first();

        if ($first === null || $month->isBefore($first)) {
            throw ValidationException::withMessages(['period' => __(':month is before the system start date.', ['month' => $month->name()])]);
        }

        // The last day's entries and cash count must be complete before the month closes.
        if ($month->lastDay() >= Today::date()) {
            throw ValidationException::withMessages(['period' => __(':month has not ended yet.', ['month' => $month->name()])]);
        }

        return DB::transaction(function () use ($by, $month, $acceptPendingRates): MonthCloseResult {
            Periods::lockExclusive(); // no entry is half saved while the checks run

            if ($month->isClosed()) {
                throw ValidationException::withMessages(['period' => __(':month is already closed.', ['month' => $month->name()])]);
            }

            $checks = MonthEndChecks::run($month, $acceptPendingRates);
            $result = new MonthCloseResult($month, false, $checks);

            if ($result->failing() !== []) {
                return $result;
            }

            $period = $month->periodOrCreate();
            $period->update(['status' => Period::CLOSED, 'closed_by' => $by->id, 'closed_at' => now(), 'updated_by' => $by->id]);

            PeriodEvent::query()->create([
                'company_id' => $period->company_id,
                'period_id' => $period->id,
                'event' => PeriodEvent::CLOSED,
                'user_id' => $by->id,
            ]);

            AuditLogger::log(AuditActions::MONTH_CLOSED, $period, ['status' => Period::OPEN], [
                'status' => Period::CLOSED,
                'accepted_pending_rates' => $acceptPendingRates,
            ], by: $by);

            return new MonthCloseResult($month, true, $checks);
        });
    }
}
