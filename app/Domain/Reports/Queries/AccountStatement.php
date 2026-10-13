<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Ledger\Models\JournalLine;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Support\Decimal;
use Illuminate\Database\Query\Builder;

/**
 * R-06 account statement (page 25): every movement of a cash or bank account for a
 * date range by posting date (BR-RPT-01), with voucher, description, party and a
 * running balance. Opening balances (BR-OPB-01) always count in the opening line.
 */
final class AccountStatement
{
    /**
     * @return array{opening: string, rows: list<array{date: string, voucher: string|null, description: string, party: string|null, in: string|null, out: string|null, balance: string, route: string|null}>, closing: string, total_in: string, total_out: string}
     */
    public static function for(MoneyAccount $account, string $from, string $to): array
    {
        $opening = (string) self::lines($account)
            ->where(fn (Builder $q) => $q->where('journal_entries.posting_date', '<', $from)->orWhere('journal_entries.source_type', 'opening_balance'))
            ->where('journal_entries.posting_date', '<=', $to)
            ->selectRaw('COALESCE(SUM(journal_lines.debit) - SUM(journal_lines.credit), 0)::numeric(20, 2)::text AS net')
            ->value('net');

        $lines = self::lines($account)
            ->whereBetween('journal_entries.posting_date', [$from, $to])
            ->where('journal_entries.source_type', '!=', 'opening_balance')
            ->orderBy('journal_entries.posting_date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_lines.line_no')
            ->select([
                'journal_entries.posting_date', 'journal_entries.source_type', 'journal_entries.source_id',
                'journal_entries.reversal_of_id', 'journal_lines.debit', 'journal_lines.credit',
            ])
            // The party of the movement sits on the other line of the same journal.
            ->selectSub(fn (Builder $q) => $q->from('journal_lines AS other')
                ->join('parties', 'parties.id', '=', 'other.party_id')
                ->whereColumn('other.journal_entry_id', 'journal_lines.journal_entry_id')
                ->orderBy('other.line_no')
                ->limit(1)
                ->select('parties.name'), 'party')
            ->get();

        $sources = EntrySources::describe(array_values($lines->map(fn ($l): array => [(string) $l->source_type, (int) $l->source_id])->all()));
        $balance = $opening;
        $in = '0.00';
        $out = '0.00';
        $rows = [];

        foreach ($lines as $line) {
            $debit = (string) $line->debit;
            $credit = (string) $line->credit;
            $balance = Decimal::add($balance, Decimal::sub($debit, $credit));
            $in = Decimal::add($in, $debit);
            $out = Decimal::add($out, $credit);
            $source = $sources["{$line->source_type}:{$line->source_id}"] ?? null;

            $rows[] = [
                'date' => (string) $line->posting_date,
                'voucher' => $source['voucher'] ?? null,
                'description' => ($line->reversal_of_id !== null ? 'Reversal – ' : '').($source['description'] ?? ''),
                'party' => $line->party !== null ? (string) $line->party : null,
                'in' => Decimal::isPositive($debit) ? $debit : null,
                'out' => Decimal::isPositive($credit) ? $credit : null,
                'balance' => $balance,
                'route' => $source['route'] ?? null,
            ];
        }

        return ['opening' => $opening, 'rows' => $rows, 'closing' => $balance, 'total_in' => $in, 'total_out' => $out];
    }

    private static function lines(MoneyAccount $account): Builder
    {
        return JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.ledger_account_id', $account->ledger_account_id)
            ->toBase();
    }
}
