<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Ledger\Models\JournalLine;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;
use App\Support\Decimal;
use Carbon\CarbonImmutable;

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

        $result = $matched ?? self::match($buyer, $asOf);
        $overdue = '0.00';

        foreach ($result['open_increases'] as $open) {
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
        $openIncreases = [];
        $openReductions = [];
        $completed = [];

        foreach (self::movements($party, $asOf) as $movement) {
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
