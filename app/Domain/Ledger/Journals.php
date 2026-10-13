<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Domain\Ledger\Models\JournalEntry;

/** Read-only facts about a journal, for business Actions that keep only its id. */
final class Journals
{
    /** Y-m-d posting date of a journal. */
    public static function postingDate(int $journalEntryId): string
    {
        return (string) JournalEntry::query()->whereKey($journalEntryId)->value('posting_date');
    }
}
