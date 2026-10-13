<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Ledger\Models\JournalLine;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;
use App\Domain\Parties\PartyBalance;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Sales\Models\Sale;
use App\Support\Decimal;
use Illuminate\Database\Query\Builder;

/**
 * R-01 party khata from the journal lines on the party's control account(s), by
 * posting date (BR-RPT-01): opening row, one row per line, running balance in words
 * (BR-RPT-04).
 */
final class PartyKhata
{
    /**
     * @param  string|null  $from  Y-m-d, inclusive; null = from the beginning
     * @param  string|null  $to  Y-m-d, inclusive; null = up to now
     */
    public static function for(Party $party, ?string $from = null, ?string $to = null): KhataResult
    {
        $accountIds = array_values(array_map('intval', LedgerAccount::query()->whereIn('code', self::codes($party))->pluck('id')->all()));

        $opening = '0.00';

        if ($from !== null) {
            $opening = (string) self::lines($party, $accountIds)
                ->where('journal_entries.posting_date', '<', $from)
                ->selectRaw('COALESCE(SUM(journal_lines.debit) - SUM(journal_lines.credit), 0)::numeric(20, 2)::text AS net')
                ->value('net');
        }

        $lines = self::lines($party, $accountIds)
            ->when($from !== null, fn (Builder $q) => $q->where('journal_entries.posting_date', '>=', $from))
            ->when($to !== null, fn (Builder $q) => $q->where('journal_entries.posting_date', '<=', $to))
            ->orderBy('journal_entries.posting_date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_lines.line_no')
            ->get([
                'journal_entries.posting_date', 'journal_entries.source_type', 'journal_entries.source_id',
                'journal_entries.reversal_of_id', 'journal_lines.debit', 'journal_lines.credit',
            ]);

        $sources = EntrySources::describe(array_values($lines->map(fn (object $l): array => [(string) $l->source_type, (int) $l->source_id])->all()));
        $increaseIsDebit = in_array($party->type, [PartyType::Buyer, PartyType::Employee], true);
        $net = $opening;
        $rows = [];

        foreach ($lines as $line) {
            $debit = (string) $line->debit;
            $credit = (string) $line->credit;
            $net = Decimal::add($net, Decimal::sub($debit, $credit));
            $source = $sources["{$line->source_type}:{$line->source_id}"] ?? null;
            $moved = Decimal::isPositive($debit) ? $debit : $credit;
            $isIncrease = Decimal::isPositive($debit) === $increaseIsDebit;

            $rows[] = new KhataRow(
                date: (string) $line->posting_date,
                voucher: $source['voucher'] ?? null,
                description: ($line->reversal_of_id !== null ? 'Reversal – ' : '').($source['description'] ?? ''),
                increase: $isIncrease ? $moved : null,
                decrease: $isIncrease ? null : $moved,
                balance: $party->balanceOfNet($net),
                route: $source['route'] ?? null,
            );
        }

        $rows = self::withPendingRows($party, $rows, $from, $to, $party->balanceOfNet($opening));

        return new KhataResult($from, $to, $party->balanceOfNet($opening), $rows, $party->balanceOfNet($net, $party->pendingKg()));
    }

    /**
     * Rate-pending entries are shown with their kg and no amount, on their entry date,
     * after the money rows of that day; they leave the balance unchanged.
     *
     * @param  list<KhataRow>  $rows
     * @return list<KhataRow>
     */
    private static function withPendingRows(Party $party, array $rows, ?string $from, ?string $to, PartyBalance $opening): array
    {
        $model = $party->type === PartyType::Buyer ? Sale::class : Purchase::class;
        $pending = $model::query()
            ->with('item')
            ->where('party_id', $party->id)
            ->where('status', 'active')
            ->where('rate_status', 'pending')
            ->when($from !== null, fn ($q) => $q->where('entry_date', '>=', $from))
            ->when($to !== null, fn ($q) => $q->where('entry_date', '<=', $to))
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        foreach ($pending as $purchase) {
            $position = count($rows);

            foreach ($rows as $index => $row) {
                if ($row->date > $purchase->entry_date) {
                    $position = $index;

                    break;
                }
            }

            $before = $position > 0 ? $rows[$position - 1]->balance : null;

            array_splice($rows, $position, 0, [new KhataRow(
                date: $purchase->entry_date,
                voucher: $purchase->voucher_no,
                description: EntrySources::entryText($purchase->item->name, $purchase->kg, null),
                increase: null,
                decrease: null,
                balance: $before ?? $opening,
                route: route($model === Sale::class ? 'sales.show' : 'purchases.show', $purchase->id, false),
                pendingKg: $purchase->kg,
            )]);
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private static function codes(Party $party): array
    {
        return $party->type === PartyType::Partner ? ['3000', '3100'] : [(string) $party->type->controlAccountCode()];
    }

    /**
     * @param  list<int>  $accountIds
     */
    private static function lines(Party $party, array $accountIds): Builder
    {
        return JournalLine::query()
            ->toBase()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.party_id', $party->id)
            ->whereIn('journal_lines.ledger_account_id', $accountIds);
    }
}
