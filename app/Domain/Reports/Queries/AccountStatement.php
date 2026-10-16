<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Ledger\Models\JournalLine;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Support\Decimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * R-06 account statement (page 25): every movement of a cash or bank account for a
 * date range by posting date (BR-RPT-01), with voucher, description, party and a
 * running balance. Opening balances (BR-OPB-01) always count in the opening line.
 *
 * BR-RPT-09: the movements are shown 100 per page. Opening, closing and the in/out
 * totals always cover the whole range; a later page starts from the balance brought
 * forward from the rows before it. PostgreSQL sums the running balance (numeric, never
 * float), so a page costs the same whatever the range.
 */
final class AccountStatement
{
    public const PER_PAGE = 100;

    /**
     * @return array{opening: string, brought_forward: string, rows: list<array{date: string, voucher: string|null, description: string, party: string|null, in: string|null, out: string|null, balance: string, route: string|null}>, closing: string, total_in: string, total_out: string, page: array{current_page: int, last_page: int, total: int, per_page: int}}
     */
    public static function for(MoneyAccount $account, string $from, string $to, int $page = 1, int $perPage = self::PER_PAGE): array
    {
        $opening = (string) self::lines($account)
            ->where(fn (Builder $q) => $q->where('journal_entries.posting_date', '<', $from)->orWhere('journal_entries.source_type', 'opening_balance'))
            ->where('journal_entries.posting_date', '<=', $to)
            ->selectRaw('CAST(COALESCE(SUM(journal_lines.debit) - SUM(journal_lines.credit), 0) AS DECIMAL(20,2)) AS net')
            ->value('net');

        $totals = self::movements($account, $from, $to)
            ->selectRaw('COUNT(*) AS count')
            ->selectRaw('CAST(COALESCE(SUM(journal_lines.debit), 0) AS DECIMAL(20,2)) AS total_in')
            ->selectRaw('CAST(COALESCE(SUM(journal_lines.credit), 0) AS DECIMAL(20,2)) AS total_out')
            ->first();

        $count = (int) ($totals->count ?? 0);
        $in = (string) ($totals->total_in ?? '0.00');
        $out = (string) ($totals->total_out ?? '0.00');
        $lastPage = max(1, (int) ceil($count / $perPage));
        $page = min(max(1, $page), $lastPage);

        // The running sum is a window over the whole range; only then is one page cut out,
        // and only that page's rows look up their party and description.
        $numbered = self::movements($account, $from, $to)->select([
            'journal_lines.journal_entry_id', 'journal_lines.debit', 'journal_lines.credit',
            'journal_entries.posting_date', 'journal_entries.source_type', 'journal_entries.source_id',
            'journal_entries.reversal_of_id', 'journal_entries.description AS journal_description',
        ])
            ->selectRaw('ROW_NUMBER() OVER (ORDER BY journal_entries.posting_date, journal_entries.id, journal_lines.line_no) AS row_no')
            ->selectRaw('CAST(SUM(journal_lines.debit - journal_lines.credit) OVER (ORDER BY journal_entries.posting_date, journal_entries.id, journal_lines.line_no ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS DECIMAL(20,2)) AS running');

        $lines = DB::query()->fromSub($numbered, 'm')
            ->select('m.*')
            // The party of the movement sits on the other line of the same journal.
            ->selectSub(fn (Builder $q) => $q->from('journal_lines AS other')
                ->join('parties', 'parties.id', '=', 'other.party_id')
                ->whereColumn('other.journal_entry_id', 'm.journal_entry_id')
                ->orderBy('other.line_no')
                ->limit(1)
                ->select('parties.name'), 'party')
            ->where('m.row_no', '>', ($page - 1) * $perPage)
            ->where('m.row_no', '<=', $page * $perPage)
            ->orderBy('m.row_no')
            ->get();

        $sources = EntrySources::describe(array_values($lines->map(fn ($l): array => [(string) $l->source_type, (int) $l->source_id])->all()));
        $broughtForward = $opening;
        $rows = [];

        foreach ($lines as $i => $line) {
            $debit = (string) $line->debit;
            $credit = (string) $line->credit;
            $balance = Decimal::add($opening, (string) $line->running);

            if ($i === 0) {
                $broughtForward = Decimal::sub($balance, Decimal::sub($debit, $credit));
            }

            $source = $sources["{$line->source_type}:{$line->source_id}"] ?? EntrySources::fromJournal((string) $line->journal_description);

            $rows[] = [
                'date' => (string) $line->posting_date,
                'voucher' => $source['voucher'],
                'description' => ($line->reversal_of_id !== null ? 'Reversal – ' : '').$source['description'],
                'party' => $line->party !== null ? (string) $line->party : null,
                'in' => Decimal::isPositive($debit) ? $debit : null,
                'out' => Decimal::isPositive($credit) ? $credit : null,
                'balance' => $balance,
                'route' => $source['route'],
            ];
        }

        return [
            'opening' => $opening,
            'brought_forward' => $broughtForward,
            'rows' => $rows,
            'closing' => Decimal::add($opening, Decimal::sub($in, $out)),
            'total_in' => $in,
            'total_out' => $out,
            'page' => ['current_page' => $page, 'last_page' => $lastPage, 'total' => $count, 'per_page' => $perPage],
        ];
    }

    /** The account's movements in the range: everything except opening balances. */
    private static function movements(MoneyAccount $account, string $from, string $to): Builder
    {
        return self::lines($account)
            ->whereBetween('journal_entries.posting_date', [$from, $to])
            ->where('journal_entries.source_type', '!=', 'opening_balance');
    }

    private static function lines(MoneyAccount $account): Builder
    {
        return JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.ledger_account_id', $account->ledger_account_id)
            ->toBase();
    }
}
