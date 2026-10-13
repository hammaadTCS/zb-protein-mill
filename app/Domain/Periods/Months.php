<?php

declare(strict_types=1);

namespace App\Domain\Periods;

use App\Domain\Periods\Models\Period;
use App\Support\Today;

/** Months from the start month to the current month (pages 30 and 31). */
final class Months
{
    /**
     * Every month from the month of `system_start_date` to the current month, oldest first.
     *
     * @return list<Month>
     */
    public static function all(): array
    {
        $first = Month::first();
        $current = Month::ofDate(Today::date());
        $months = [];

        for ($m = $first; $m !== null && ! $current->isBefore($m); $m = $m->next()) {
            $months[] = $m;
        }

        return $months;
    }

    /** Page 30: the oldest month that is not closed, if any. */
    public static function oldestOpen(): ?Month
    {
        foreach (self::all() as $month) {
            if (! $month->isClosed()) {
                return $month;
            }
        }

        return null;
    }

    /** BR-MEC-08: only the latest closed month can be reopened. */
    public static function latestClosed(): ?Month
    {
        $period = Period::query()
            ->where('status', Period::CLOSED)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();

        return $period === null ? null : Month::of(sprintf('%04d-%02d', $period->year, $period->month));
    }
}
