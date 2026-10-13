<?php

declare(strict_types=1);

namespace App\Domain\Periods\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Periods\Models\Period;
use App\Domain\Periods\Models\PeriodEvent;
use App\Domain\Periods\Month;
use App\Domain\Periods\Months;
use App\Domain\Periods\Periods;
use App\Models\User;
use App\Support\CancelReason;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * BR-MEC-08: the Owner reopens the latest closed month, with a reason. The event and
 * the audit row keep the history; the month is open again for entries.
 */
final class ReopenMonth
{
    public function handle(User $owner, string $yyyymm, string $reason): Period
    {
        $month = Month::of($yyyymm);
        $reason = CancelReason::check($reason);

        return DB::transaction(function () use ($owner, $month, $reason): Period {
            Periods::lockExclusive();

            $latest = Months::latestClosed();

            if ($latest === null || $latest->key() !== $month->key()) {
                throw ValidationException::withMessages(['period' => $latest === null
                    ? __('No month is closed.')
                    : __('Only the latest closed month (:month) can be reopened.', ['month' => $latest->name()])]);
            }

            $period = $month->period() ?? throw new LogicException('A closed month has a periods row.');
            $old = ['status' => $period->status, 'closed_by' => $period->closed_by, 'closed_at' => $period->closed_at?->toIso8601String()];
            $period->update(['status' => Period::OPEN, 'closed_by' => null, 'closed_at' => null, 'updated_by' => $owner->id]);

            PeriodEvent::query()->create([
                'company_id' => $period->company_id,
                'period_id' => $period->id,
                'event' => PeriodEvent::REOPENED,
                'user_id' => $owner->id,
                'reason' => $reason,
            ]);

            AuditLogger::log(AuditActions::MONTH_REOPENED, $period, $old, ['status' => Period::OPEN], reason: $reason, by: $owner);

            return $period;
        });
    }
}
