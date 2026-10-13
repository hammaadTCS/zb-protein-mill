<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Domain\Ledger\Models\JournalLine;
use App\Domain\Ledger\Models\LedgerAccount;

/**
 * Balances read from journal lines, the only truth for money (spec §6).
 * Always debit minus credit, as a 2-decimal string.
 */
final class LedgerBalance
{
    /**
     * @param  string|null  $asOf  Y-m-d: only journals posted on or before this date
     * @param  int|null  $partyId  only lines of this party (party sub-ledger)
     */
    public static function account(int $ledgerAccountId, ?string $asOf = null, ?int $partyId = null): string
    {
        $balance = JournalLine::query()
            ->where('journal_lines.ledger_account_id', $ledgerAccountId)
            ->when($partyId !== null, fn ($query) => $query->where('journal_lines.party_id', $partyId))
            ->when($asOf !== null, fn ($query) => $query
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->where('journal_entries.posting_date', '<=', $asOf))
            ->toBase()
            ->selectRaw('COALESCE(SUM(journal_lines.debit) - SUM(journal_lines.credit), 0)::numeric(20, 2)::text AS balance')
            ->value('balance');

        return (string) $balance;
    }

    /**
     * The balance of an account given by its code (for example a party's control
     * account), in the current company.
     *
     * @param  string|null  $asOf  Y-m-d
     */
    public static function byCode(string $code, ?string $asOf = null, ?int $partyId = null): string
    {
        $accountId = LedgerAccount::query()->where('code', $code)->value('id');

        return $accountId === null ? '0.00' : self::account((int) $accountId, $asOf, $partyId);
    }

    /** True when any journal line carries this party (BR-PTY-04, BR-PTY-05). */
    public static function partyHasLines(int $partyId): bool
    {
        return JournalLine::query()->where('party_id', $partyId)->exists();
    }
}
