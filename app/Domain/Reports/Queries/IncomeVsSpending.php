<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Ledger\Models\JournalLine;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Sales\Models\Sale;
use App\Support\Decimal;
use App\Support\EntryStatus;
use App\Support\Tenancy\CurrentCompany;

/**
 * R-08 Income vs Spending (spec §12.3, BR-RPT-08) by posting date (BR-RPT-01):
 * income = Σ (credit − debit) over income accounts; spending = Σ (debit − credit)
 * over expense accounts; result = income − spending. Never called profit.
 */
final class IncomeVsSpending
{
    /** Glossary §4, "Income vs Spending banner": always shown with the report. */
    public const BANNER = 'This is not profit. Stock is not recorded, so months with heavy buying look worse and months with heavy selling look better.';

    /**
     * @return array{from: string, to: string, income: string, spending: string, result: string, pending_kg: string, income_lines: list<array{code: string, name: string, amount: string}>, spending_lines: list<array{code: string, name: string, amount: string}>}
     */
    public static function for(string $from, string $to): array
    {
        $lines = JournalLine::query()
            ->withoutGlobalScope('company')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
            ->where('journal_entries.company_id', CurrentCompany::id())
            ->whereBetween('journal_entries.posting_date', [$from, $to])
            ->whereIn('ledger_accounts.type', ['income', 'expense'])
            ->groupBy('ledger_accounts.code', 'ledger_accounts.name', 'ledger_accounts.type')
            ->orderBy('ledger_accounts.code')
            ->toBase()
            ->select('ledger_accounts.code', 'ledger_accounts.name', 'ledger_accounts.type')
            ->selectRaw("CAST(CASE WHEN ledger_accounts.type = 'income' THEN SUM(journal_lines.credit) - SUM(journal_lines.debit) ELSE SUM(journal_lines.debit) - SUM(journal_lines.credit) END AS DECIMAL(16,2)) AS amount")
            ->get();

        $income = [];
        $spending = [];

        foreach ($lines as $line) {
            if (Decimal::isZero((string) $line->amount)) {
                continue;
            }

            $row = ['code' => (string) $line->code, 'name' => (string) $line->name, 'amount' => (string) $line->amount];
            $line->type === 'income' ? $income[] = $row : $spending[] = $row;
        }

        $incomeTotal = Decimal::sum(array_column($income, 'amount'));
        $spendingTotal = Decimal::sum(array_column($spending, 'amount'));

        return [
            'from' => $from,
            'to' => $to,
            'income' => $incomeTotal,
            'spending' => $spendingTotal,
            'result' => Decimal::sub($incomeTotal, $spendingTotal),
            'pending_kg' => self::pendingKg($from, $to),
            'income_lines' => $income,
            'spending_lines' => $spending,
        ];
    }

    /** Kg of active rate-pending purchases and sales dated in the range (not in the money). */
    private static function pendingKg(string $from, string $to): string
    {
        $total = '0';

        foreach ([Purchase::class, Sale::class] as $model) {
            $kg = $model::query()
                ->where('status', EntryStatus::Active->value)
                ->where('rate_status', 'pending')
                ->whereBetween('entry_date', [$from, $to])
                ->toBase()
                ->selectRaw('COALESCE(SUM(kg), 0) AS kg')
                ->value('kg');
            $total = Decimal::add($total, (string) $kg);
        }

        return Decimal::kg($total);
    }
}
