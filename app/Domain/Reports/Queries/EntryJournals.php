<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Ledger\Models\JournalLine;
use Illuminate\Database\Eloquent\Model;

/**
 * Spec §11.6: the journal lines of one record, for the Owner (shown only with
 * `audit.view`): every journal of the record, reversals included, in order.
 */
final class EntryJournals
{
    /**
     * @return list<array{entry_no: string, posting_date: string, reversal_of: string|null, code: string, account: string, party: string|null, debit: string, credit: string}>
     */
    public static function for(Model $record): array
    {
        return array_values(JournalLine::query()
            ->toBase()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
            ->leftJoin('parties', 'parties.id', '=', 'journal_lines.party_id')
            ->leftJoin('journal_entries AS reversed', 'reversed.id', '=', 'journal_entries.reversal_of_id')
            ->where('journal_entries.source_type', $record->getMorphClass())
            ->where('journal_entries.source_id', $record->getKey())
            ->orderBy('journal_entries.id')
            ->orderBy('journal_lines.line_no')
            ->get([
                'journal_entries.entry_no', 'journal_entries.posting_date', 'reversed.entry_no AS reversal_of',
                'ledger_accounts.code', 'ledger_accounts.name AS account', 'parties.name AS party',
                'journal_lines.debit', 'journal_lines.credit',
            ])
            ->map(fn (object $row): array => [
                'entry_no' => (string) $row->entry_no,
                'posting_date' => (string) $row->posting_date,
                'reversal_of' => $row->reversal_of === null ? null : (string) $row->reversal_of,
                'code' => (string) $row->code,
                'account' => (string) $row->account,
                'party' => $row->party === null ? null : (string) $row->party,
                'debit' => (string) $row->debit,
                'credit' => (string) $row->credit,
            ])
            ->all());
    }
}
