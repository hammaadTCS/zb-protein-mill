<?php

declare(strict_types=1);

namespace App\Domain\Periods;

use App\Domain\Periods\Models\Period;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Support\Dates;
use App\Support\Tenancy\CurrentCompany;
use App\Support\Today;
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
     * @return array{int, int}
     */
    private static function yearMonth(string $ymd): array
    {
        return [(int) substr($ymd, 0, 4), (int) substr($ymd, 5, 2)];
    }
}
