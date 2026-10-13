<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Expenses\Models\Expense;
use App\Domain\Items\Models\Item;
use App\Domain\Payments\Models\Payment;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Sales\Models\Sale;
use App\Support\EntryStatus;

/**
 * Spec §11.3, page 2 "Today": kg bought per item and sold per product (active entries
 * dated today), money received and paid (payments posted today), expenses today.
 */
final class DashboardToday
{
    /**
     * @return array{kg_bought: list<array{item: string, kg: string}>, kg_sold: list<array{item: string, kg: string}>, received: string, paid: string, expenses: string}
     */
    public static function for(string $today): array
    {
        $items = Item::query()->orderBy('id')->get();

        $kg = function (string $model, bool $bought) use ($items, $today): array {
            $totals = $model::query()
                ->where('status', EntryStatus::Active->value)
                ->where('entry_date', $today)
                ->groupBy('item_id')
                ->selectRaw('item_id, SUM(kg)::numeric(14,3)::text AS kg')
                ->pluck('kg', 'item_id');

            return array_values($items
                ->filter(fn (Item $item) => $item->type->isBought() === $bought && ($item->is_active || isset($totals[$item->id])))
                ->map(fn (Item $item): array => ['item' => $item->name, 'kg' => (string) ($totals[$item->id] ?? '0.000')])
                ->all());
        };

        // Posted payments: an approval posts on the approval date (BR-PAY-08), so the
        // journal's posting date decides the day, not the entry date.
        $money = Payment::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'payments.journal_entry_id')
            ->where('payments.status', EntryStatus::Active->value)
            ->where('journal_entries.posting_date', $today)
            ->toBase()
            ->selectRaw("COALESCE(SUM(payments.amount) FILTER (WHERE payments.direction = 'received'), 0)::numeric(16,2)::text AS received")
            ->selectRaw("COALESCE(SUM(payments.amount) FILTER (WHERE payments.direction = 'paid'), 0)::numeric(16,2)::text AS paid")
            ->first();

        $expenses = Expense::query()
            ->where('status', EntryStatus::Active->value)
            ->where('entry_date', $today)
            ->toBase()
            ->selectRaw('COALESCE(SUM(amount), 0)::numeric(16,2)::text AS total')
            ->value('total');

        return [
            'kg_bought' => $kg(Purchase::class, true),
            'kg_sold' => $kg(Sale::class, false),
            'received' => (string) ($money->received ?? '0.00'),
            'paid' => (string) ($money->paid ?? '0.00'),
            'expenses' => (string) ($expenses ?? '0.00'),
        ];
    }
}
