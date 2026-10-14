<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Ledger\Models\JournalLine;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Spec §12.6, oldest first: what increases a party's balance (opening, sales or
 * purchases, payments to them) is matched against what reduces it (receipts or
 * payments, adjustments), oldest first. One way for overdue buyers, average days to
 * pay and advance age.
 *
 * The movements come from the party's posted journal lines on its control account,
 * netted per source record, so a cancelled entry (reversed) drops out and an edited
 * one counts once at its current amount and date. Payments awaiting approval have no
 * journal yet and do not count.
 */
final class Ageing
{
    /**
     * Unmatched part of sales older than the buyer's payment terms (BR-CHK-06 for
     * buyers); "0.00" when the buyer has no terms. `$matched` is match($buyer, $asOf)
     * when the caller already has it.
     *
     * @param  array{open_increases: list<array{source_type: string, date: string, amount: string}>, open_reductions: list<array{source_type: string, date: string, amount: string}>, completed: list<array{source_type: string, date: string, amount: string, completed_on: string}>}|null  $matched
     */
    public static function overdueFor(Party $buyer, string $asOf, ?array $matched = null): string
    {
        $terms = $buyer->payment_terms_days;

        if ($buyer->type !== PartyType::Buyer || $terms === null) {
            return '0.00';
        }

        return self::overdueFromMatch($matched ?? self::match($buyer, $asOf), $terms, $asOf);
    }

    /**
     * overdueFor() for many buyers at once (the dashboard alert, pending balances), in one
     * query. Oldest first always leaves open only the newest increases, worth exactly the
     * balance: an increase that arrives while an advance is open finds no open increase
     * before it, and later ones queue behind it in full. So an increase's open part is
     * what is left of the balance after every newer increase, never below zero and never
     * above its own amount. PostgreSQL sums that per buyer in numeric (exact, no float),
     * over the same movements as match(); tests compare it with overdueFor().
     *
     * @param  iterable<Party>  $buyers
     * @return array<int, string> by party id; "0.00" for a buyer without payment terms
     */
    public static function overdueMany(iterable $buyers, string $asOf): array
    {
        $result = [];
        $withTerms = [];

        foreach ($buyers as $buyer) {
            $result[$buyer->id] = '0.00';

            if ($buyer->type === PartyType::Buyer && $buyer->payment_terms_days !== null) {
                $withTerms[$buyer->id] = (int) $buyer->payment_terms_days;
            }
        }

        if ($withTerms === []) {
            return $result;
        }

        $terms = implode(', ', array_fill(0, count($withTerms), '(?::bigint, ?::int)'));
        $bindings = array_merge(...array_map(fn (int $id, int $days): array => [$id, $days], array_keys($withTerms), $withTerms));
        $movements = self::buyerMovementsSql('terms');

        $rows = DB::select(<<<SQL
            WITH terms (party_id, days) AS (VALUES {$terms}),
            {$movements},
            open AS (
                SELECT party_id, source_type, date, net,
                    SUM(net) OVER (PARTITION BY party_id) AS balance,
                    SUM(GREATEST(net, 0)) OVER (PARTITION BY party_id ORDER BY date DESC, first_id DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) - GREATEST(net, 0) AS newer
                FROM kept
            )
            SELECT open.party_id,
                SUM(LEAST(open.net, GREATEST(open.balance - open.newer, 0)))
                    FILTER (WHERE open.net > 0 AND open.source_type = 'sale' AND ?::date - open.date > terms.days)::numeric(16,2)::text AS overdue
            FROM open
            JOIN terms ON terms.party_id = open.party_id
            GROUP BY open.party_id
            SQL, [...$bindings, PartyType::Buyer->controlAccountCode(), $asOf, $asOf]);

        foreach ($rows as $row) {
            $result[(int) $row->party_id] = $row->overdue ?? '0.00';
        }

        return $result;
    }

    /**
     * averageDaysToPay() for many buyers at once (business insights), in one query.
     * Oldest first always pays the first min(all increases, all reductions) of the
     * increases, so a sale is complete at the first movement, from its own on, where the
     * reductions so far reach the increases up to and including it: its own date when an
     * advance already covered it, else the date of that receipt. PostgreSQL finds that
     * date with window sums (numeric, exact) and sums amount × days; the division is done
     * here with Decimal, half up, as averageDaysToPay() does.
     *
     * @param  iterable<Party>  $buyers
     * @return array<int, string|null> by party id; null when no sale was completed in the period
     */
    public static function averageDaysToPayMany(iterable $buyers, string $from, string $to): array
    {
        $result = [];

        foreach ($buyers as $buyer) {
            $result[$buyer->id] = null;
        }

        if ($result === []) {
            return $result;
        }

        $ids = implode(', ', array_fill(0, count($result), '(?::bigint)'));
        $movements = self::buyerMovementsSql('buyers');

        $rows = DB::select(<<<SQL
            WITH buyers (party_id) AS (VALUES {$ids}),
            {$movements},
            seq AS (
                SELECT party_id, source_type, date, net,
                    SUM(GREATEST(net, 0)) OVER w AS increased,
                    SUM(GREATEST(-net, 0)) OVER w AS reduced
                FROM kept
                WINDOW w AS (PARTITION BY party_id ORDER BY date, first_id ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW)
            ),
            points AS (
                SELECT party_id, increased AS cumulative, 0 AS kind, source_type, date, net FROM seq WHERE net > 0
                UNION ALL
                SELECT party_id, reduced AS cumulative, 1 AS kind, source_type, date, net FROM seq WHERE net < 0
            ),
            -- Dates never go down along the movements, so the first reduction that reaches a
            -- sale is the one with the earliest date among those that reach it, and the sale
            -- completes on the later of its own date and that one. (Sorted down, so the frame
            -- is a running minimum: a frame up to the end would be recounted for every row.)
            reached AS (
                SELECT party_id, kind, source_type, date, net,
                    MIN(CASE WHEN kind = 1 THEN date END) OVER (PARTITION BY party_id ORDER BY cumulative DESC, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS paid_on
                FROM points
            ),
            completed AS (
                SELECT party_id, date AS sale_date, net AS amount, GREATEST(date, paid_on) AS completed_on
                FROM reached
                WHERE kind = 0 AND source_type = 'sale' AND paid_on IS NOT NULL
            )
            SELECT party_id,
                SUM(amount * (completed_on - sale_date))::text AS weighted,
                SUM(amount)::text AS total
            FROM completed
            WHERE completed_on BETWEEN ?::date AND ?::date
            GROUP BY party_id
            SQL, [...array_keys($result), PartyType::Buyer->controlAccountCode(), $to, $from, $to]);

        foreach ($rows as $row) {
            $total = (string) $row->total;
            $result[(int) $row->party_id] = Decimal::isZero($total) ? null : Decimal::divide((string) $row->weighted, $total, 2);
        }

        return $result;
    }

    /**
     * The `movements` and `kept` parts of a query: the buyers' movements on their control
     * account up to a date, one per source record, as movements() reads them. Bindings:
     * the account code, then the as-of date.
     */
    private static function buyerMovementsSql(string $partiesCte): string
    {
        return <<<SQL
            movements AS (
                SELECT journal_lines.party_id,
                    journal_entries.source_type,
                    SUM(journal_lines.debit - journal_lines.credit) AS net,
                    (ARRAY_AGG(journal_entries.posting_date ORDER BY journal_entries.id DESC) FILTER (WHERE journal_entries.reversal_of_id IS NULL))[1] AS date,
                    MIN(journal_entries.id) AS first_id
                FROM journal_lines
                JOIN journal_entries ON journal_entries.id = journal_lines.journal_entry_id
                JOIN ledger_accounts ON ledger_accounts.id = journal_lines.ledger_account_id
                WHERE journal_lines.party_id IN (SELECT party_id FROM {$partiesCte})
                    AND ledger_accounts.code = ?
                    AND journal_entries.posting_date <= ?::date
                GROUP BY journal_lines.party_id, journal_entries.source_type, journal_entries.source_id
            ),
            kept AS (
                SELECT * FROM movements WHERE net <> 0 AND date IS NOT NULL
            )
            SQL;
    }

    /**
     * The unmatched part of sales older than the terms, from a match() result.
     *
     * @param  array{open_increases: list<array{source_type: string, date: string, amount: string}>, open_reductions: list<array{source_type: string, date: string, amount: string}>, completed: list<array{source_type: string, date: string, amount: string, completed_on: string}>}  $matched
     */
    public static function overdueFromMatch(array $matched, int $terms, string $asOf): string
    {
        $overdue = '0.00';

        foreach ($matched['open_increases'] as $open) {
            if ($open['source_type'] === 'sale' && self::days($open['date'], $asOf) > $terms) {
                $overdue = Decimal::add($overdue, $open['amount']);
            }
        }

        return $overdue;
    }

    /**
     * Days from each sale to the receipt that completed it, weighted by sale amount,
     * over sales completed in the period; null when none was completed. `$matched` is
     * match($buyer, $to) when the caller already has it.
     *
     * @param  array{open_increases: list<array{source_type: string, date: string, amount: string}>, open_reductions: list<array{source_type: string, date: string, amount: string}>, completed: list<array{source_type: string, date: string, amount: string, completed_on: string}>}|null  $matched
     */
    public static function averageDaysToPay(Party $buyer, string $from, string $to, ?array $matched = null): ?string
    {
        $weighted = '0';
        $total = '0';

        foreach (($matched ?? self::match($buyer, $to))['completed'] as $done) {
            if ($done['source_type'] !== 'sale' || $done['completed_on'] < $from || $done['completed_on'] > $to) {
                continue;
            }

            $weighted = Decimal::add($weighted, Decimal::multiply($done['amount'], (string) self::days($done['date'], $done['completed_on']), 2));
            $total = Decimal::add($total, $done['amount']);
        }

        return Decimal::isZero($total) ? null : Decimal::divide($weighted, $total, 2);
    }

    /**
     * When the balance is an advance: the age in days of the oldest unmatched payment
     * to a supplier (or receipt from a buyer); null otherwise.
     */
    public static function advanceAgeDays(Party $party, string $asOf): ?int
    {
        $open = self::match($party, $asOf)['open_reductions'];

        return $open === [] ? null : self::days($open[0]['date'], $asOf);
    }

    /**
     * Matches reductions to increases, oldest first, in date order.
     *
     * @return array{
     *     open_increases: list<array{source_type: string, date: string, amount: string}>,
     *     open_reductions: list<array{source_type: string, date: string, amount: string}>,
     *     completed: list<array{source_type: string, date: string, amount: string, completed_on: string}>
     * }
     */
    public static function match(Party $party, string $asOf): array
    {
        return self::matchMovements(self::movements($party, $asOf));
    }

    /**
     * match() on movements already read, in date order.
     *
     * @param  list<array{source_type: string, date: string, net: string}>  $movements
     * @return array{
     *     open_increases: list<array{source_type: string, date: string, amount: string}>,
     *     open_reductions: list<array{source_type: string, date: string, amount: string}>,
     *     completed: list<array{source_type: string, date: string, amount: string, completed_on: string}>
     * }
     */
    public static function matchMovements(array $movements): array
    {
        $openIncreases = [];
        $openReductions = [];
        $completed = [];

        foreach ($movements as $movement) {
            $amount = Decimal::abs($movement['net']);

            if (Decimal::isPositive($movement['net'])) {
                // An increase first uses up an advance, oldest first.
                while (Decimal::isPositive($amount) && $openReductions !== []) {
                    $take = Decimal::compare($openReductions[0]['amount'], $amount) <= 0 ? $openReductions[0]['amount'] : $amount;
                    $amount = Decimal::sub($amount, $take);
                    $openReductions[0]['amount'] = Decimal::sub($openReductions[0]['amount'], $take);

                    if (Decimal::isZero($openReductions[0]['amount'])) {
                        array_shift($openReductions);
                    }
                }

                if (Decimal::isZero($amount)) {
                    $completed[] = ['source_type' => $movement['source_type'], 'date' => $movement['date'], 'amount' => Decimal::abs($movement['net']), 'completed_on' => $movement['date']];
                } else {
                    $openIncreases[] = ['source_type' => $movement['source_type'], 'date' => $movement['date'], 'amount' => $amount, 'full' => Decimal::abs($movement['net'])];
                }

                continue;
            }

            // A reduction pays the oldest open increases first.
            while (Decimal::isPositive($amount) && $openIncreases !== []) {
                $take = Decimal::compare($openIncreases[0]['amount'], $amount) <= 0 ? $openIncreases[0]['amount'] : $amount;
                $amount = Decimal::sub($amount, $take);
                $openIncreases[0]['amount'] = Decimal::sub($openIncreases[0]['amount'], $take);

                if (Decimal::isZero($openIncreases[0]['amount'])) {
                    $done = array_shift($openIncreases);
                    $completed[] = ['source_type' => $done['source_type'], 'date' => $done['date'], 'amount' => $done['full'], 'completed_on' => $movement['date']];
                }
            }

            if (Decimal::isPositive($amount)) {
                $openReductions[] = ['source_type' => $movement['source_type'], 'date' => $movement['date'], 'amount' => $amount];
            }
        }

        return [
            'open_increases' => array_map(fn (array $open): array => ['source_type' => $open['source_type'], 'date' => $open['date'], 'amount' => $open['amount']], $openIncreases),
            'open_reductions' => $openReductions,
            'completed' => $completed,
        ];
    }

    /**
     * The party's movements on its control account up to a date, one per source
     * record, positive when it increases the balance, in date order.
     *
     * @return list<array{source_type: string, date: string, net: string}>
     */
    private static function movements(Party $party, string $asOf): array
    {
        $code = $party->type->controlAccountCode();

        if ($code === null) {
            return [];
        }

        // Buyers and employees owe us (debit side); suppliers and others are owed (credit side).
        $sign = in_array($party->type, [PartyType::Buyer, PartyType::Employee], true)
            ? 'journal_lines.debit - journal_lines.credit'
            : 'journal_lines.credit - journal_lines.debit';

        $rows = JournalLine::query()
            ->withoutGlobalScope('company')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
            ->where('journal_lines.party_id', $party->id)
            ->where('ledger_accounts.code', $code)
            ->where('journal_entries.posting_date', '<=', $asOf)
            ->groupBy('journal_entries.source_type', 'journal_entries.source_id')
            ->toBase()
            ->select('journal_entries.source_type')
            ->selectRaw("SUM({$sign})::numeric(16,2)::text AS net")
            // The date of the source's newest journal that is not a reversal.
            ->selectRaw('(ARRAY_AGG(journal_entries.posting_date ORDER BY journal_entries.id DESC) FILTER (WHERE journal_entries.reversal_of_id IS NULL))[1]::text AS date')
            ->selectRaw('MIN(journal_entries.id) AS first_id')
            ->get();

        $movements = [];

        foreach ($rows as $row) {
            if (Decimal::isZero((string) $row->net) || $row->date === null) {
                continue;
            }

            $movements[] = ['source_type' => (string) $row->source_type, 'date' => (string) $row->date, 'net' => (string) $row->net, 'first_id' => (int) $row->first_id];
        }

        usort($movements, fn (array $a, array $b): int => [$a['date'], $a['first_id']] <=> [$b['date'], $b['first_id']]);

        return array_map(fn (array $m): array => ['source_type' => $m['source_type'], 'date' => $m['date'], 'net' => $m['net']], $movements);
    }

    private static function days(string $from, string $to): int
    {
        return (int) CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to));
    }
}
