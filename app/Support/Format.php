<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Card T6.10: the PHP side of `resources/js/lib/format.ts` for PDFs. Grouping (lakh or
 * international), rounding and dates follow the company settings, on strings only
 * (conventions §3); NumberFormat and Dates do the work, so screens, messages and PDFs
 * agree.
 */
final class Format
{
    /** "Rs 1,00,000.00" (or whole rupees, per `amount_rounding`). */
    public static function money(string $amount): string
    {
        return NumberFormat::money($amount);
    }

    /** "800 kg" with `kg_decimals`. */
    public static function kg(string $kg): string
    {
        return NumberFormat::kg($kg);
    }

    /** "Rs 50.00" with `rate_decimals`. */
    public static function rate(string $rate): string
    {
        return NumberFormat::rate($rate);
    }

    /** A Y-m-d date in `date_format`, for example "05-01-2026" or "05 Jan 2026". */
    public static function date(string $ymd): string
    {
        return Dates::display($ymd);
    }

    /** Now in Asia/Karachi, for "Generated on …". */
    public static function now(): string
    {
        return self::date(Today::date()).', '.CarbonImmutable::now(Today::TIMEZONE)->format('H:i');
    }
}
