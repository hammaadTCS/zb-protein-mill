<?php

declare(strict_types=1);

namespace App\Domain\Insights;

use App\Domain\Items\Enums\ItemType;
use App\Domain\Items\Models\Item;
use App\Domain\Ledger\LedgerBalance;
use App\Domain\Parties\Models\Party;
use App\Domain\Reports\Ageing;
use App\Domain\Reports\Queries\IncomeVsSpending;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Support\Decimal;
use App\Support\EntryStatus;
use App\Support\Tenancy\CurrentCompany;
use App\Support\Today;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * R-13, spec §12.5 (D-36): rough indicators from what is already recorded. Kg by
 * entry date from purchases and sales (BR-RPT-01, BR-RPT-02: rate-pending kg count);
 * rates as Σ amount ÷ Σ kg of fixed-rate entries at their corrected amount
 * (spec §12.4, BR-LED-09); spending from the Income vs Spending report. Ratios have
 * 4 decimals, rupees 2; a ratio whose divisor is zero is null (shown as "–").
 * The sums run in PostgreSQL, so a period of 100,000 entries stays fast (NFR-02).
 */
final class BusinessInsights
{
    /** Kg of every entry; amount and kg of fixed-rate entries (pending ones have no amount yet). */
    private const SUMS = "COALESCE(SUM(t.kg), 0)::numeric(16, 3)::text AS kg,
        COALESCE(SUM(t.amount) FILTER (WHERE t.rate_status = 'fixed'), 0)::numeric(18, 2)::text AS fixed_amount,
        COALESCE(SUM(t.kg) FILTER (WHERE t.rate_status = 'fixed'), 0)::numeric(16, 3)::text AS fixed_kg";

    /**
     * The default period: `insights_period_months` back from today, to today.
     *
     * @return array{from: string, to: string}
     */
    public static function defaultPeriod(): array
    {
        $months = Settings::get(SettingKeys::INSIGHTS_PERIOD_MONTHS);
        $today = CarbonImmutable::parse(Today::date());

        return ['from' => $today->subMonthsNoOverflow(is_int($months) ? $months : 3)->toDateString(), 'to' => $today->toDateString()];
    }

    /**
     * @return array<string, mixed>
     */
    public static function for(string $from, string $to): array
    {
        $bought = self::totals('purchases', $from, $to, 'type');
        $soldByCode = self::totals('sales', $from, $to, 'code');
        $waste = $bought[ItemType::RawMaterial->value]['kg'] ?? '0.000';
        $wood = $bought[ItemType::Fuel->value]['kg'] ?? '0.000';
        $protein = $soldByCode['PROTEIN']['kg'] ?? '0.000';
        $oilFat = $soldByCode['OILFAT']['kg'] ?? '0.000';
        $products = Decimal::sum(array_column($soldByCode, 'kg'), '0.000');
        $spending = IncomeVsSpending::for($from, $to)['spending'];

        return [
            'from' => $from,
            'to' => $to,
            'kg_ratio_protein' => self::ratio($protein, $waste, 4),
            'kg_ratio_oil_fat' => self::ratio($oilFat, $waste, 4),
            'kg_ratio_combined' => self::ratio($products, $waste, 4),
            'wood_per_kg_waste' => self::ratio($wood, $waste, 4),
            'rough_cost_per_kg_sold' => self::ratio($spending, $products, 2),
            'totals' => ['waste_kg' => $waste, 'wood_kg' => $wood, 'protein_kg' => $protein, 'oil_fat_kg' => $oilFat, 'product_kg' => $products, 'spending' => $spending],
            'months' => self::withBars(self::months($from, $to)),
            'suppliers' => self::suppliers($from, $to),
            'buyers' => self::buyers($from, $to),
        ];
    }

    /**
     * Active purchases or sales dated in the period, with the item and the amount after
     * rate corrections (null while the rate is pending).
     */
    private static function entries(string $table, string $from, string $to): Builder
    {
        $link = $table === 'purchases' ? 'purchase_id' : 'sale_id';
        $corrections = DB::table('rate_corrections')->whereNotNull($link)->groupBy($link)->selectRaw("{$link} AS entry_id, SUM(difference) AS difference");

        return DB::table("{$table} AS e")
            ->join('items', 'items.id', '=', 'e.item_id')
            ->leftJoinSub($corrections, 'rc', 'rc.entry_id', '=', 'e.id')
            ->where('e.company_id', CurrentCompany::id())
            ->where('e.status', EntryStatus::Active->value)
            ->whereBetween('e.entry_date', [$from, $to])
            ->select(['e.id', 'e.party_id', 'e.entry_date', 'e.kg', 'e.our_weight_kg', 'e.rate_status', 'items.type', 'items.code', 'items.name'])
            ->selectRaw('e.amount + COALESCE(rc.difference, 0) AS amount');
    }

    /**
     * Kg, and amount and kg of fixed-rate entries, grouped by the item's type or code.
     *
     * @param  'type'|'code'  $column
     * @return array<string, array{kg: string, fixed_amount: string, fixed_kg: string}>
     */
    private static function totals(string $table, string $from, string $to, string $column): array
    {
        return DB::query()->fromSub(self::entries($table, $from, $to), 't')
            ->groupBy("t.{$column}")
            ->selectRaw("t.{$column} AS grp")
            ->selectRaw(self::SUMS)
            ->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->grp => self::row($row)])
            ->all();
    }

    /**
     * Wood per kg of waste and buying vs selling rates, per calendar month.
     *
     * @return list<array<string, mixed>>
     */
    private static function months(string $from, string $to): array
    {
        $bought = self::byMonth('purchases', 'type', $from, $to);
        $sold = self::byMonth('sales', 'name', $from, $to);
        $productNames = Item::query()->where('type', ItemType::Product->value)->orderBy('id')->pluck('name')->all();
        $rows = [];

        for ($m = CarbonImmutable::parse($from)->startOfMonth(); $m->toDateString() <= $to; $m = $m->addMonth()) {
            $key = $m->format('Y-m');
            $waste = $bought[$key][ItemType::RawMaterial->value] ?? null;
            $wood = $bought[$key][ItemType::Fuel->value] ?? null;

            $rows[] = [
                'month' => $key,
                'name' => $m->format('M Y'),
                'wood_per_kg_waste' => self::ratio($wood['kg'] ?? '0', $waste['kg'] ?? '0', 4),
                'waste_rate' => self::ratio($waste['fixed_amount'] ?? '0', $waste['fixed_kg'] ?? '0', 2),
                'product_rates' => array_map(fn (string $name): array => [
                    'item' => $name,
                    'rate' => self::ratio($sold[$key][$name]['fixed_amount'] ?? '0', $sold[$key][$name]['fixed_kg'] ?? '0', 2),
                ], $productNames),
            ];
        }

        return $rows;
    }

    /**
     * The sums per month and item type or name.
     *
     * @param  'type'|'name'  $column
     * @return array<string, array<string, array{kg: string, fixed_amount: string, fixed_kg: string}>> month => group => sums
     */
    private static function byMonth(string $table, string $column, string $from, string $to): array
    {
        return DB::query()->fromSub(self::entries($table, $from, $to), 't')
            ->groupByRaw("to_char(t.entry_date, 'YYYY-MM'), t.{$column}")
            ->selectRaw("to_char(t.entry_date, 'YYYY-MM') AS month, t.{$column} AS grp")
            ->selectRaw(self::SUMS)
            ->get()
            ->groupBy('month')
            ->map(fn ($rows) => $rows->mapWithKeys(fn (object $row): array => [(string) $row->grp => self::row($row)])->all())
            ->all();
    }

    /**
     * Per supplier with purchases in the period: kg, average rate, advance outstanding
     * at the period end, weight difference % (spec §12.4).
     *
     * @return list<array<string, mixed>>
     */
    private static function suppliers(string $from, string $to): array
    {
        $rows = DB::query()->fromSub(self::entries('purchases', $from, $to), 't')
            ->groupBy('t.party_id')
            ->selectRaw('t.party_id AS grp')
            ->selectRaw(self::SUMS)
            ->selectRaw('COALESCE(SUM(t.kg - t.our_weight_kg) FILTER (WHERE t.our_weight_kg IS NOT NULL), 0)::numeric(16, 3)::text AS difference')
            ->selectRaw('COALESCE(SUM(t.our_weight_kg), 0)::numeric(16, 3)::text AS our_kg')
            ->get();
        $parties = Party::query()->whereIn('id', $rows->pluck('grp')->all())->get()->keyBy('id');
        $nets = LedgerBalance::partyNets(array_values($parties->modelKeys()), $to); // one query (T9.2)
        $result = [];

        foreach ($rows as $row) {
            $party = $parties->get((int) $row->grp);

            if ($party === null) {
                continue;
            }

            $sums = self::row($row);
            $balance = $party->balanceOfNet($nets[$party->id][(string) $party->type->controlAccountCode()] ?? '0.00');

            $result[] = [
                'party_id' => $party->id,
                'code' => $party->code,
                'name' => $party->name,
                'kg' => $sums['kg'],
                'average_rate' => self::ratio($sums['fixed_amount'], $sums['fixed_kg'], 2),
                'advance' => $balance->words === 'Advance' ? $balance->amount : '0.00',
                'weight_difference_percent' => self::percent((string) $row->difference, (string) $row->our_kg),
            ];
        }

        usort($result, fn (array $a, array $b): int => [$a['name'], $a['party_id']] <=> [$b['name'], $b['party_id']]);

        return $result;
    }

    /**
     * Per buyer with sales in the period: average days to pay and overdue (spec §12.6),
     * agreed adjustments and their % of his sales, his share of all sales.
     *
     * @return list<array<string, mixed>>
     */
    private static function buyers(string $from, string $to): array
    {
        $rows = DB::query()->fromSub(self::entries('sales', $from, $to), 't')
            ->groupBy('t.party_id')
            ->selectRaw('t.party_id AS grp')
            ->selectRaw(self::SUMS)
            ->get();
        $adjustments = DB::table('adjustments')
            ->where('company_id', CurrentCompany::id())
            ->whereNotNull('sale_id')
            ->where('status', EntryStatus::Active->value)
            ->whereBetween('entry_date', [$from, $to])
            ->groupBy('party_id')
            ->selectRaw('party_id, SUM(amount)::numeric(18, 2)::text AS total')
            ->pluck('total', 'party_id')
            ->all();
        $allSales = Decimal::sum($rows->map(fn (object $row): string => self::row($row)['fixed_amount'])->all());
        $parties = Party::query()->whereIn('id', $rows->pluck('grp')->all())->get()->keyBy('id');
        // Spec §12.6 for every buyer in two queries (T9.2: the oldest-first match one buyer
        // at a time took seconds at 100,000 entries). Same figures as Ageing::match().
        $daysToPay = Ageing::averageDaysToPayMany($parties, $from, $to);
        $overdue = Ageing::overdueMany($parties, $to);
        $result = [];

        foreach ($rows as $row) {
            $party = $parties->get((int) $row->grp);

            if ($party === null) {
                continue;
            }

            $his = self::row($row)['fixed_amount'];
            $adjusted = (string) ($adjustments[$party->id] ?? '0.00');

            $result[] = [
                'party_id' => $party->id,
                'code' => $party->code,
                'name' => $party->name,
                'average_days_to_pay' => $daysToPay[$party->id],
                'overdue' => $overdue[$party->id],
                'adjustments' => $adjusted,
                'adjustments_percent' => self::percent($adjusted, $his),
                'share_percent' => self::percent($his, $allSales),
            ];
        }

        usort($result, fn (array $a, array $b): int => [$a['name'], $a['party_id']] <=> [$b['name'], $b['party_id']]);

        return $result;
    }

    /**
     * @return array{kg: string, fixed_amount: string, fixed_kg: string}
     */
    private static function row(object $row): array
    {
        $values = (array) $row;

        return ['kg' => (string) $values['kg'], 'fixed_amount' => (string) $values['fixed_amount'], 'fixed_kg' => (string) $values['fixed_kg']];
    }

    /**
     * Bar lengths for the two charts, as whole percents of the largest value in each
     * chart, so the page draws them without any number maths.
     *
     * @param  list<array<string, mixed>>  $months
     * @return list<array<string, mixed>>
     */
    private static function withBars(array $months): array
    {
        $woodMax = '0';
        $rateMax = '0';

        foreach ($months as $month) {
            $woodMax = self::max($woodMax, $month['wood_per_kg_waste']);
            $rateMax = self::max($rateMax, $month['waste_rate']);

            foreach ($month['product_rates'] as $product) {
                $rateMax = self::max($rateMax, $product['rate']);
            }
        }

        $bar = fn (?string $value, string $max): int => $value === null || Decimal::isZero($max) ? 0 : (int) Decimal::divide(Decimal::multiply($value, '100', 6), $max, 0);

        return array_map(fn (array $month): array => [
            ...$month,
            'wood_bar' => $bar($month['wood_per_kg_waste'], $woodMax),
            'waste_rate_bar' => $bar($month['waste_rate'], $rateMax),
            'product_rates' => array_map(fn (array $p): array => [...$p, 'bar' => $bar($p['rate'], $rateMax)], $month['product_rates']),
        ], $months);
    }

    private static function max(string $current, mixed $value): string
    {
        return is_string($value) && Decimal::compare($value, $current) > 0 ? $value : $current;
    }

    /**
     * @param  int<0, max>  $scale
     */
    private static function ratio(string $part, string $whole, int $scale): ?string
    {
        return Decimal::isZero($whole) ? null : Decimal::divide($part, $whole, $scale);
    }

    private static function percent(string $part, string $whole): ?string
    {
        return Decimal::isZero($whole) ? null : Decimal::divide(Decimal::multiply($part, '100', 6), $whole, 2);
    }
}
