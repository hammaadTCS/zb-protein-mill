<?php

declare(strict_types=1);

namespace App\Domain\Insights;

use App\Domain\Adjustments\Models\Adjustment;
use App\Domain\Items\Enums\ItemType;
use App\Domain\Items\Models\Item;
use App\Domain\Ledger\LedgerBalance;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Purchases\Enums\RateStatus;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Purchases\RateCorrections;
use App\Domain\Reports\Ageing;
use App\Domain\Reports\Queries\IncomeVsSpending;
use App\Domain\Sales\Models\Sale;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Support\Decimal;
use App\Support\EntryStatus;
use App\Support\Today;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * R-13, spec §12.5 (D-36): rough indicators from what is already recorded. Kg by
 * entry date from purchases and sales (BR-RPT-01, BR-RPT-02: rate-pending kg count);
 * rates as Σ amount ÷ Σ kg of fixed-rate entries (spec §12.4); spending from the
 * Income vs Spending report. Ratios have 4 decimals, rupees 2. A ratio whose divisor
 * is zero is null (shown as "–").
 */
final class BusinessInsights
{
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
        $purchases = self::purchases($from, $to);
        $sales = self::sales($from, $to);

        $waste = self::kg($purchases->filter(fn (Purchase $p): bool => $p->item->type === ItemType::RawMaterial));
        $wood = self::kg($purchases->filter(fn (Purchase $p): bool => $p->item->type === ItemType::Fuel));
        $protein = self::kg($sales->filter(fn (Sale $s): bool => $s->item->code === 'PROTEIN'));
        $oilFat = self::kg($sales->filter(fn (Sale $s): bool => $s->item->code === 'OILFAT'));
        $products = self::kg($sales);
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
            'months' => self::withBars(self::months($purchases, $sales, $from, $to)),
            'suppliers' => self::suppliers($purchases, $to),
            'buyers' => self::buyers($sales, $from, $to),
        ];
    }

    /**
     * Wood per kg of waste and buying vs selling rates, per calendar month.
     *
     * @param  Collection<int, Purchase>  $purchases
     * @param  Collection<int, Sale>  $sales
     * @return list<array<string, mixed>>
     */
    private static function months(Collection $purchases, Collection $sales, string $from, string $to): array
    {
        $productNames = Item::query()->where('type', ItemType::Product->value)->orderBy('id')->pluck('name')->all();
        $rows = [];

        for ($m = CarbonImmutable::parse($from)->startOfMonth(); $m->toDateString() <= $to; $m = $m->addMonth()) {
            $key = $m->format('Y-m');
            $inMonth = fn (Purchase|Sale $e): bool => str_starts_with($e->entry_date, $key);
            $monthPurchases = $purchases->filter($inMonth);
            $monthSales = $sales->filter($inMonth);
            $waste = $monthPurchases->filter(fn (Purchase $p): bool => $p->item->type === ItemType::RawMaterial);

            $rows[] = [
                'month' => $key,
                'name' => $m->format('M Y'),
                'wood_per_kg_waste' => self::ratio(self::kg($monthPurchases->filter(fn (Purchase $p): bool => $p->item->type === ItemType::Fuel)), self::kg($waste), 4),
                'waste_rate' => self::averageRate($waste),
                'product_rates' => array_map(fn (string $name): array => [
                    'item' => $name,
                    'rate' => self::averageRate($monthSales->filter(fn (Sale $s): bool => $s->item->name === $name)),
                ], $productNames),
            ];
        }

        return $rows;
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
     * Per supplier with purchases in the period: kg, average rate, advance outstanding
     * at the period end, weight difference % (spec §12.4).
     *
     * @param  Collection<int, Purchase>  $purchases
     * @return list<array<string, mixed>>
     */
    private static function suppliers(Collection $purchases, string $to): array
    {
        return array_values($purchases->groupBy('party_id')->map(function (Collection $entries) use ($to): array {
            /** @var Purchase $first */
            $first = $entries->first();
            $party = $first->party;
            $balance = $party->balanceOfNet(LedgerBalance::byCode((string) PartyType::Supplier->controlAccountCode(), $to, $party->id));
            $weighed = $entries->filter(fn (Purchase $p): bool => $p->our_weight_kg !== null);
            $ourKg = self::kg($weighed, 'our_weight_kg');
            $difference = Decimal::sub(self::kg($weighed), $ourKg);

            return [
                'party_id' => $party->id,
                'code' => $party->code,
                'name' => $party->name,
                'kg' => self::kg($entries),
                'average_rate' => self::averageRate($entries),
                'advance' => $balance->words === 'Advance' ? $balance->amount : '0.00',
                'weight_difference_percent' => Decimal::isZero($ourKg) ? null : Decimal::divide(Decimal::multiply($difference, '100', 6), $ourKg, 2),
            ];
        })->sortBy('name')->all());
    }

    /**
     * Per buyer with sales in the period: average days to pay and overdue (spec §12.6),
     * agreed adjustments and their % of his sales, his share of all sales.
     *
     * @param  Collection<int, Sale>  $sales
     * @return list<array<string, mixed>>
     */
    private static function buyers(Collection $sales, string $from, string $to): array
    {
        $fixed = $sales->filter(fn (Sale $s): bool => $s->rate_status === RateStatus::Fixed);
        $allSales = self::amount($fixed);

        return array_values($sales->groupBy('party_id')->map(function (Collection $entries) use ($from, $to, $allSales): array {
            /** @var Sale $first */
            $first = $entries->first();
            $party = $first->party;
            $his = self::amount($entries->filter(fn (Sale $s): bool => $s->rate_status === RateStatus::Fixed));
            $adjustments = Decimal::sum(Adjustment::query()
                ->where('party_id', $party->id)
                ->whereNotNull('sale_id')
                ->where('status', EntryStatus::Active->value)
                ->whereBetween('entry_date', [$from, $to])
                ->pluck('amount')
                ->map(fn ($amount): string => (string) $amount)
                ->all());

            return [
                'party_id' => $party->id,
                'code' => $party->code,
                'name' => $party->name,
                'average_days_to_pay' => Ageing::averageDaysToPay($party, $from, $to),
                'overdue' => Ageing::overdueFor($party, $to),
                'adjustments' => $adjustments,
                'adjustments_percent' => self::percent($adjustments, $his),
                'share_percent' => self::percent($his, $allSales),
            ];
        })->sortBy('name')->all());
    }

    /**
     * Active purchases dated in the period.
     *
     * @return Collection<int, Purchase>
     */
    private static function purchases(string $from, string $to): Collection
    {
        return Purchase::query()->with(['item', 'party'])->where('status', EntryStatus::Active->value)
            ->whereBetween('entry_date', [$from, $to])->orderBy('entry_date')->orderBy('id')->get();
    }

    /**
     * Active sales dated in the period.
     *
     * @return Collection<int, Sale>
     */
    private static function sales(string $from, string $to): Collection
    {
        return Sale::query()->with(['item', 'party'])->where('status', EntryStatus::Active->value)
            ->whereBetween('entry_date', [$from, $to])->orderBy('entry_date')->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, Purchase>|Collection<int, Sale>  $entries
     */
    private static function kg(Collection $entries, string $field = 'kg'): string
    {
        return Decimal::sum($entries->map(fn (Purchase|Sale $e): string => (string) $e->getAttribute($field))->all(), '0.000');
    }

    /**
     * Σ amount of fixed-rate entries, at the corrected rate where one was corrected.
     *
     * @param  Collection<int, Purchase>|Collection<int, Sale>  $entries
     */
    private static function amount(Collection $entries): string
    {
        return Decimal::sum($entries->map(fn (Purchase|Sale $e): string => (string) RateCorrections::effective($e)['amount'])->all());
    }

    /**
     * Spec §12.4: Σ amount ÷ Σ kg of fixed-rate entries, 2 decimals; null without any.
     *
     * @param  Collection<int, Purchase>|Collection<int, Sale>  $entries
     */
    private static function averageRate(Collection $entries): ?string
    {
        $fixed = $entries->filter(fn (Purchase|Sale $e): bool => $e->rate_status === RateStatus::Fixed);

        return self::ratio(self::amount($fixed), self::kg($fixed), 2);
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
