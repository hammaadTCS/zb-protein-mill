<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Expenses\Models\Expense;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Sales\Models\Sale;
use App\Support\Decimal;
use App\Support\EntryStatus;

/**
 * R-04 daily summary (page 29): kg bought and sold, money received and paid,
 * expenses by category, each money account's closing balance and the rate-pending
 * entries of the day.
 */
final class DailySummary
{
    /**
     * @return array{date: string, kg_bought: list<array{item: string, kg: string}>, kg_sold: list<array{item: string, kg: string}>, received: string, paid: string, expenses: list<array{category: string, amount: string}>, expenses_total: string, balances: list<array{id: int, name: string, balance: string}>, rate_pending: int}
     */
    public static function for(string $date): array
    {
        $today = DashboardToday::for($date);

        $expenses = Expense::query()
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.category_id')
            ->where('expenses.status', EntryStatus::Active->value)
            ->where('expenses.entry_date', $date)
            ->groupBy('expense_categories.name', 'expense_categories.sort_order')
            ->orderBy('expense_categories.sort_order')
            ->orderBy('expense_categories.name')
            ->toBase()
            ->selectRaw('expense_categories.name AS category, CAST(SUM(expenses.amount) AS DECIMAL(16,2)) AS amount')
            ->get()
            ->map(fn (object $row): array => ['category' => (string) $row->category, 'amount' => (string) $row->amount])
            ->all();

        $pending = 0;

        foreach ([Purchase::class, Sale::class] as $model) {
            $pending += $model::query()->where('status', EntryStatus::Active->value)->where('rate_status', 'pending')->where('entry_date', $date)->count();
        }

        return [
            'date' => $date,
            'kg_bought' => $today['kg_bought'],
            'kg_sold' => $today['kg_sold'],
            'received' => $today['received'],
            'paid' => $today['paid'],
            'expenses' => array_values($expenses),
            'expenses_total' => $today['expenses'],
            'balances' => array_values(MoneyAccount::query()
                ->orderBy(LedgerAccount::query()->select('code')->whereColumn('ledger_accounts.id', 'money_accounts.ledger_account_id'))
                ->get()
                ->filter(fn (MoneyAccount $account) => $account->is_active || ! Decimal::isZero($account->balance($date)))
                ->map(fn (MoneyAccount $account): array => ['id' => $account->id, 'name' => $account->name, 'balance' => $account->balance($date)])
                ->all()),
            'rate_pending' => $pending,
        ];
    }
}
