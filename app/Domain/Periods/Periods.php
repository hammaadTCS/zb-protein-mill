<?php

declare(strict_types=1);

namespace App\Domain\Periods;

use App\Domain\Periods\Models\Period;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Support\Dates;
use App\Support\Tenancy\CurrentCompany;
use App\Support\Today;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Date rules for entries and postings (BR-GEN-02, BR-LED-07, BR-MEC-07).
 */
final class Periods
{
    /** The advisory lock of the periods, one per company (any fixed number). */
    private const LOCK_KEY = 460201;

    /** A month is closed only when its periods row says so; months without a row are open. */
    public static function isClosed(string $ymd): bool
    {
        [$year, $month] = self::yearMonth($ymd);

        return Period::query()
            ->withoutGlobalScope('company')
            ->where('company_id', CurrentCompany::id())
            ->where('year', $year)
            ->where('month', $month)
            ->where('status', Period::CLOSED)
            ->exists();
    }

    /**
     * BR-MEC-07, BR-LED-07: nothing may be dated or posted in a closed month.
     *
     * @throws ValidationException on the `date` field
     */
    public static function assertDateOpen(string $ymd): void
    {
        self::lockShared();

        if (self::isClosed($ymd)) {
            throw ValidationException::withMessages([
                'date' => __(':month is closed. Make this change as a correction in an open month.', ['month' => Dates::monthName($ymd)]),
            ]);
        }
    }

    /**
     * BR-GEN-02: an entry date is not in the future, not before the system start
     * date, and in an open month.
     *
     * @throws ValidationException on the `date` field
     */
    public static function assertValidEntryDate(string $ymd): void
    {
        if ($ymd > Today::date()) {
            throw ValidationException::withMessages(['date' => __('Date cannot be in the future.')]);
        }

        $start = Settings::get(SettingKeys::SYSTEM_START_DATE);

        if (is_string($start) && $ymd < $start) {
            throw ValidationException::withMessages([
                'date' => __('Date cannot be before the system start date (:date).', ['date' => Dates::display($start)]),
            ]);
        }

        self::assertDateOpen($ymd);
    }

    /**
     * Every save that checks a date holds this lock until it commits, and closing a
     * month takes it alone (lockExclusive). So a month cannot close while an entry
     * dated in it is half saved, and the entry is refused once the month is closed.
     */
    public static function lockShared(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::select('SELECT pg_advisory_xact_lock_shared(?, ?)', [self::LOCK_KEY, CurrentCompany::id()]);
        }
    }

    /** Month-end close and its cash adjustments: no entry is half saved meanwhile. */
    public static function lockExclusive(): void
    {
        DB::select('SELECT pg_advisory_xact_lock(?, ?)', [self::LOCK_KEY, CurrentCompany::id()]);
    }

    /**
     * @return array{int, int}
     */
    private static function yearMonth(string $ymd): array
    {
        return [(int) substr($ymd, 0, 4), (int) substr($ymd, 5, 2)];
    }
}
