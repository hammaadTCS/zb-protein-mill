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

    /**
     * Debit minus credit per party and account code, for many parties in one query
     * (party lists).
     *
     * @param  list<int>  $partyIds
     * @return array<int, array<int|string, string>> party id => [account code => net]
     */
    public static function partyNets(array $partyIds): array
    {
        if ($partyIds === []) {
            return [];
        }

        $rows = JournalLine::query()
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
            ->whereIn('journal_lines.party_id', $partyIds)
            ->groupBy('journal_lines.party_id', 'ledger_accounts.code')
            ->toBase()
            ->selectRaw('journal_lines.party_id, ledger_accounts.code, (SUM(journal_lines.debit) - SUM(journal_lines.credit))::numeric(20, 2)::text AS net')
            ->get();

        $nets = [];

        foreach ($rows as $row) {
            $nets[(int) $row->party_id][(string) $row->code] = (string) $row->net;
        }

        return $nets;
    }

    /** Debit minus credit of one journal's lines on an account code, for one party. */
    public static function journalNet(int $journalEntryId, string $code, int $partyId): string
    {
        $net = JournalLine::query()
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
            ->where('journal_lines.journal_entry_id', $journalEntryId)
            ->where('ledger_accounts.code', $code)
            ->where('journal_lines.party_id', $partyId)
            ->toBase()
            ->selectRaw('COALESCE(SUM(journal_lines.debit) - SUM(journal_lines.credit), 0)::numeric(20, 2)::text AS net')
            ->value('net');

        return (string) $net;
    }

    /** Debit minus credit of one journal's lines on one ledger account. */
    public static function journalAccountNet(int $journalEntryId, int $ledgerAccountId): string
    {
        $net = JournalLine::query()
            ->where('journal_entry_id', $journalEntryId)
            ->where('ledger_account_id', $ledgerAccountId)
            ->toBase()
            ->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0)::numeric(20, 2)::text AS net')
            ->value('net');

        return (string) $net;
    }
}
