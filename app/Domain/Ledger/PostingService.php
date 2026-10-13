<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

/**
 * The only writer of journal entries and lines (CLAUDE.md rule 2, conventions §6).
 * The journal models refuse to be created unless this service is posting.
 *
 * T1.5 adds the posting flag only; T1.7 adds post() and reverse().
 */
final class PostingService
{
    /** True only while this service is writing journal rows. Never set from outside. */
    private static bool $posting = false;

    public static function isPosting(): bool
    {
        return self::$posting;
    }
}
