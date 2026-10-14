<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Ledger\Models\JournalLine;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;
use App\Domain\Parties\PartyBalance;
use App\Domain\Payments\Models\Payment;
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
                'journal_entries.id AS journal_id', 'journal_entries.posting_date', 'journal_entries.source_type', 'journal_entries.source_id',
                'journal_entries.reversal_of_id', 'journal_lines.debit', 'journal_lines.credit',
            ]);

        $sources = EntrySources::describe(array_values($lines->map(fn (object $l): array => [(string) $l->source_type, (int) $l->source_id])->all()));
        $merges = self::mergeRows($party, array_values($lines->where('source_type', 'party_merge')->map(fn (object $l): int => (int) $l->journal_id)->all()));
        $increaseIsDebit = in_array($party->type, [PartyType::Buyer, PartyType::Employee], true);
        $net = $opening;
        $rows = [];

        foreach ($lines as $line) {
            $debit = (string) $line->debit;
            $credit = (string) $line->credit;
            $net = Decimal::add($net, Decimal::sub($debit, $credit));
            $source = $merges[(int) $line->journal_id] ?? $sources["{$line->source_type}:{$line->source_id}"] ?? null;
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
                journalId: (int) $line->journal_id,
                reversalOfId: $line->reversal_of_id === null ? null : (int) $line->reversal_of_id,
            );
        }

        $rows = self::withPendingRows($party, $rows, $from, $to, $party->balanceOfNet($opening));
        $rows = self::withAwaitingPayments($party, $rows, $from, $to, $party->balanceOfNet($opening));

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
                route: route($model === Sale::class ? 'sales.show' : 'purchases.show', $purchase, false),
                pendingKg: $purchase->kg,
            )]);
        }

        return $rows;
    }

    /**
     * BR-PAY-08: payments waiting for approval, greyed and outside the balance.
     *
     * @param  list<KhataRow>  $rows
     * @return list<KhataRow>
     */
    private static function withAwaitingPayments(Party $party, array $rows, ?string $from, ?string $to, PartyBalance $opening): array
    {
        $increaseIsDebit = in_array($party->type, [PartyType::Buyer, PartyType::Employee], true);

        $payments = Payment::query()
            ->with('moneyAccount')
            ->where('party_id', $party->id)
            ->where('status', 'active')
            ->where('approval_status', 'pending')
            ->when($from !== null, fn ($q) => $q->where('entry_date', '>=', $from))
            ->when($to !== null, fn ($q) => $q->where('entry_date', '<=', $to))
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        foreach ($payments as $payment) {
            $position = count($rows);

            foreach ($rows as $index => $row) {
                if ($row->date > $payment->entry_date) {
                    $position = $index;

                    break;
                }
            }

            // A payment (paid) is a debit on the control account; a receipt a credit.
            $isDebit = $payment->direction->value === 'paid';
            $isIncrease = $isDebit === $increaseIsDebit;
            $source = EntrySources::describe([['payment', $payment->id]])["payment:{$payment->id}"] ?? null;

            array_splice($rows, $position, 0, [new KhataRow(
                date: $payment->entry_date,
                voucher: $payment->voucher_no,
                description: ($source['description'] ?? '').' – Awaiting approval',
                increase: $isIncrease ? $payment->amount : null,
                decrease: $isIncrease ? null : $payment->amount,
                balance: $position > 0 ? $rows[$position - 1]->balance : $opening,
                route: $source['route'] ?? null,
                awaitingApproval: true,
            )]);
        }

        return $rows;
    }

    /**
     * BR-PTY-08: a merge row says where the balance came from or went, and links to
     * the other profile (its history stays there).
     *
     * @param  list<int>  $journalIds
     * @return array<int, array{voucher: null, description: string, route: string}> by journal id
     */
    private static function mergeRows(Party $party, array $journalIds): array
    {
        if ($journalIds === []) {
            return [];
        }

        $others = JournalLine::query()
            ->toBase()
            ->join('parties', 'parties.id', '=', 'journal_lines.party_id')
            ->whereIn('journal_lines.journal_entry_id', $journalIds)
            ->where('journal_lines.party_id', '!=', $party->id)
            ->get(['journal_lines.journal_entry_id', 'parties.id', 'parties.public_id', 'parties.name', 'parties.code']);
        $rows = [];

        foreach ($others as $other) {
            $into = $party->merged_into_id === (int) $other->id;
            $rows[(int) $other->journal_entry_id] = [
                'voucher' => null,
                'description' => ($into ? 'Balance moved to ' : 'Balance moved from ')."{$other->name} ({$other->code})",
                'route' => route('parties.show', (string) $other->public_id, false), // BR-SEC-19
            ];
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
