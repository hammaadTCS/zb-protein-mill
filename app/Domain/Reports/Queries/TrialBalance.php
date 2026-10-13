<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Ledger\Models\JournalLine;
use App\Support\Decimal;
use App\Support\Tenancy\CurrentCompany;

/**
 * R-07 trial balance, straight from the journal lines (BR-RPT-01: by posting date).
 * The sums are done in SQL on numeric columns and come back as text, never float.
 */
final class TrialBalance
{
    public static function asOf(string $ymd, bool $withParties = false): TrialBalanceResult
    {
        $query = JournalLine::query()
            ->withoutGlobalScope('company')
            ->toBase()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
            ->where('journal_entries.company_id', CurrentCompany::id())
            ->where('journal_entries.posting_date', '<=', $ymd)
            ->groupBy('ledger_accounts.code', 'ledger_accounts.name')
            ->havingRaw('SUM(journal_lines.debit) <> 0 OR SUM(journal_lines.credit) <> 0')
            ->orderBy('ledger_accounts.code')
            ->select('ledger_accounts.code', 'ledger_accounts.name')
            ->selectRaw('SUM(journal_lines.debit)::numeric(16,2)::text AS debit_total')
            ->selectRaw('SUM(journal_lines.credit)::numeric(16,2)::text AS credit_total')
            ->selectRaw('GREATEST(SUM(journal_lines.debit) - SUM(journal_lines.credit), 0)::numeric(16,2)::text AS balance_debit')
            ->selectRaw('GREATEST(SUM(journal_lines.credit) - SUM(journal_lines.debit), 0)::numeric(16,2)::text AS balance_credit');

        if ($withParties) {
            // Control accounts split by party, with the party's code and name.
            $query->leftJoin('parties', 'parties.id', '=', 'journal_lines.party_id')
                ->addSelect('journal_lines.party_id', 'parties.code AS party_code', 'parties.name AS party_name')
                ->groupBy('journal_lines.party_id', 'parties.code', 'parties.name')
                ->orderBy('parties.code');
        }

        $rows = [];

        foreach ($query->get() as $row) {
            $rows[] = new TrialBalanceRow(
                code: (string) $row->code,
                name: (string) $row->name,
                partyId: $withParties && $row->party_id !== null ? (int) $row->party_id : null,
                partyCode: $withParties && $row->party_code !== null ? (string) $row->party_code : null,
                debitTotal: (string) $row->debit_total,
                creditTotal: (string) $row->credit_total,
                balanceDebit: (string) $row->balance_debit,
                balanceCredit: (string) $row->balance_credit,
                partyName: $withParties && $row->party_name !== null ? (string) $row->party_name : null,
            );
        }

        $totalDebit = Decimal::sum(array_map(fn (TrialBalanceRow $r) => $r->balanceDebit, $rows));
        $totalCredit = Decimal::sum(array_map(fn (TrialBalanceRow $r) => $r->balanceCredit, $rows));

        return new TrialBalanceResult(
            asOf: $ymd,
            withParties: $withParties,
            rows: $rows,
            totalDebit: $totalDebit,
            totalCredit: $totalCredit,
            balanced: Decimal::compare($totalDebit, $totalCredit) === 0,
        );
    }
}
