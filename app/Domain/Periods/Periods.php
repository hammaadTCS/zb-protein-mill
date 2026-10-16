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
     *
     * Q-4: MariaDB has no advisory locks, so the shared lock is the company's
     * company_locks row taken LOCK IN SHARE MODE (MariaDB 11.8 has no `FOR SHARE`;
     * it is the older spelling of the same lock). Like pg_advisory_xact_lock_shared
     * it is held until commit or rollback, and only when there is a transaction to
     * hold it.
     */
    public static function lockShared(): void
    {
        if (DB::transactionLevel() > 0) {
            self::lockRow();

            DB::select('SELECT company_id FROM company_locks WHERE company_id = ? LOCK IN SHARE MODE', [CurrentCompany::id()]);
        }
    }

    /** Month-end close and its cash adjustments: no entry is half saved meanwhile. */
    public static function lockExclusive(): void
    {
        self::lockRow();

        DB::select('SELECT company_id FROM company_locks WHERE company_id = ? FOR UPDATE', [CurrentCompany::id()]);
    }

    /**
     * The company's lock row. It is created on first use, so a company that predates
     * this table (or one a test made) still has one; afterwards INSERT IGNORE writes
     * nothing and only reads the primary key.
     */
    private static function lockRow(): void
    {
        DB::table('company_locks')->insertOrIgnore(['company_id' => CurrentCompany::id()]);
    }

    /**
     * @return array{int, int}
     */
    private static function yearMonth(string $ymd): array
    {
        return [(int) substr($ymd, 0, 4), (int) substr($ymd, 5, 2)];
    }
}
