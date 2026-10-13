<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * conventions §4: "today" is today's date in Asia/Karachi, as Y-m-d.
 * Tests freeze it with freezeToday() (Carbon's test clock).
 */
final class Today
{
    public const TIMEZONE = 'Asia/Karachi';

    public static function date(): string
    {
        return CarbonImmutable::now(self::TIMEZONE)->toDateString();
    }
}
