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
}
