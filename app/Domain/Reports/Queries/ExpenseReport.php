<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Expenses\Models\Expense;
use App\Domain\Ledger\Models\JournalLine;
use App\Support\Decimal;
use App\Support\EntryStatus;
use App\Support\Tenancy\CurrentCompany;

/**
 * R-05 expense report (page 34): the total per category from the journal lines on
 * the category accounts (spec §12.1, so a cancelled or edited expense counts at its
 * current amount, once), then every active expense with its note.
 */
final class ExpenseReport
{
    /**
     * @return array{totals: list<array{category: string, amount: string}>, total: string, rows: list<array{id: int, date: string, voucher: string, category: string, amount: string, mode: string, party: string|null, note: string}>}
     */
    public static function for(string $from, string $to, ?int $categoryId = null, ?int $partyId = null): array
    {
        $totals = JournalLine::query()
            ->withoutGlobalScope('company')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('expense_categories', 'expense_categories.ledger_account_id', '=', 'journal_lines.ledger_account_id')
            ->join('expenses', function ($join): void {
                $join->on('expenses.id', '=', 'journal_entries.source_id')->where('journal_entries.source_type', '=', 'expense');
            })
            ->where('journal_entries.company_id', CurrentCompany::id())
            ->whereBetween('journal_entries.posting_date', [$from, $to])
            ->when($categoryId, fn ($q, int $id) => $q->where('expense_categories.id', $id))
            ->when($partyId, fn ($q, int $id) => $q->where('expenses.party_id', $id))
            ->groupBy('expense_categories.id', 'expense_categories.name', 'expense_categories.sort_order')
            ->havingRaw('SUM(journal_lines.debit) - SUM(journal_lines.credit) <> 0')
            ->orderBy('expense_categories.sort_order')
            ->orderBy('expense_categories.name')
            ->toBase()
            ->selectRaw('expense_categories.name AS category, (SUM(journal_lines.debit) - SUM(journal_lines.credit))::numeric(16,2)::text AS amount')
            ->get()
            ->map(fn (object $row): array => ['category' => (string) $row->category, 'amount' => (string) $row->amount])
            ->values()
            ->all();

        $rows = Expense::query()
            ->with(['category', 'party'])
            ->where('status', EntryStatus::Active->value)
            ->whereBetween('entry_date', [$from, $to])
            ->when($categoryId, fn ($q, int $id) => $q->where('category_id', $id))
            ->when($partyId, fn ($q, int $id) => $q->where('party_id', $id))
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get()
            ->map(fn (Expense $e): array => [
                'id' => $e->id,
                'date' => $e->entry_date,
                'voucher' => $e->voucher_no,
                'category' => $e->category->name,
                'amount' => $e->amount,
                'mode' => $e->payment_mode->label(),
                'party' => $e->party?->name,
                'note' => $e->note,
            ])
            ->values()
            ->all();

        return [
            'totals' => array_values($totals),
            'total' => Decimal::sum(array_column($totals, 'amount')),
            'rows' => array_values($rows),
        ];
    }
}
