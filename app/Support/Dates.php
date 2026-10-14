<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use Carbon\CarbonImmutable;

/**
 * Dates shown to people, in messages and on PDFs (BR-RPT-05). Stored dates are Y-m-d.
 */
final class Dates
{
    /** The longest range a statement PDF covers, and up to which reports list each entry. */
    public const LONG_RANGE_MONTHS = 3;

    /** A Y-m-d date in the company's `date_format` (for example 01-01-2026). */
    public static function display(string $ymd): string
    {
        $format = Settings::get(SettingKeys::DATE_FORMAT);

        return CarbonImmutable::parse($ymd)->format(is_string($format) ? $format : 'Y-m-d');
    }

    /** The month of a Y-m-d date, for example "January 2026". */
    public static function monthName(string $ymd): string
    {
        return CarbonImmutable::parse($ymd)->format('F Y');
    }

    /**
     * The earliest start of a range of at most 3 months that ends on $to: the day after
     * $to, 3 months back. 31 March gives 1 January and 30 April gives 1 February; when
     * that month is shorter, its last day counts (30 May gives 28 February).
     */
    public static function threeMonthsBefore(string $to): string
    {
        return CarbonImmutable::parse($to)->addDay()->subMonthsNoOverflow(self::LONG_RANGE_MONTHS)->toDateString();
    }

    /** BR-RPT-10, BR-RPT-11: true when from–to is longer than 3 months. */
    public static function longerThanThreeMonths(string $from, string $to): bool
    {
        return CarbonImmutable::parse($from)->lt(CarbonImmutable::parse(self::threeMonthsBefore($to)));
    }
}
