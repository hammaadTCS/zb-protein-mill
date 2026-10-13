<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Adjustments\Models\Adjustment;
use App\Domain\Purchases\Enums\RateStatus;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Purchases\RateCorrections;
use App\Domain\Sales\Models\Sale;
use App\Support\Decimal;
use App\Support\EntryStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * R-10 rates, R-11 weight differences and R-12 agreed adjustments (spec §12.4). Kg
 * come from purchases and sales only (spec §12.1); cancelled entries never count.
 */
final class RateWeightReports
{
    /**
     * R-10: every fixed rate over time per item and party, and the weighted average
     * Σ amount ÷ Σ kg (2 decimals) before agreed adjustments (BR-RPT-03). A rate
     * correction (BR-LED-09) counts at the corrected rate.
     *
     * @param  array{type: string, from: string, to: string, item_id?: int|null, party_id?: int|null}  $filters
     * @return list<array{item: string, party: string, party_id: int, entries: list<array{date: string, voucher: string, kg: string, rate: string, amount: string}>, kg: string, amount: string, average: string}>
     */
    public static function rates(array $filters): array
    {
        $groups = [];

        $entries = self::entries($filters)->where('rate_status', RateStatus::Fixed->value)->get();
        $corrected = RateCorrections::effectiveMany($entries);

        foreach ($entries as $entry) {
            $effective = $corrected[$entry->id];
            $key = $entry->item_id.'-'.$entry->party_id;
            $groups[$key] ??= ['item' => $entry->item->name, 'party' => $entry->party->name, 'party_id' => $entry->party_id, 'entries' => [], 'kg' => '0.000', 'amount' => '0.00'];
            $groups[$key]['entries'][] = [
                'date' => $entry->entry_date,
                'voucher' => $entry->voucher_no,
                'kg' => $entry->kg,
                'rate' => (string) $effective['rate'],
                'amount' => (string) $effective['amount'],
            ];
            $groups[$key]['kg'] = Decimal::add($groups[$key]['kg'], $entry->kg);
            $groups[$key]['amount'] = Decimal::add($groups[$key]['amount'], (string) $effective['amount']);
        }

        return array_values(array_map(fn (array $g): array => [...$g, 'average' => Decimal::divide($g['amount'], $g['kg'], 2)], $groups));
    }

    /**
     * R-11: per party, only entries with our weight. Purchase difference = supplier kg
     * − our kg; sale difference = our kg − buyer kg (a positive number means kg lost);
     * % = Σ difference ÷ Σ our kg × 100, 2 decimals.
     *
     * @param  array{type: string, from: string, to: string, party_id?: int|null}  $filters
     * @return list<array{party: string, party_id: int, entries: list<array{date: string, voucher: string, their_kg: string, our_kg: string, difference: string}>, their_kg: string, our_kg: string, difference: string, percent: string}>
     */
    public static function weights(array $filters): array
    {
        $groups = [];
        $isSale = $filters['type'] === 'sale';

        foreach (self::entries($filters)->whereNotNull('our_weight_kg')->get() as $entry) {
            $ours = (string) $entry->our_weight_kg;
            $difference = $isSale ? Decimal::sub($ours, $entry->kg) : Decimal::sub($entry->kg, $ours);
            $key = (string) $entry->party_id;
            $groups[$key] ??= ['party' => $entry->party->name, 'party_id' => $entry->party_id, 'entries' => [], 'their_kg' => '0.000', 'our_kg' => '0.000', 'difference' => '0.000'];
            $groups[$key]['entries'][] = ['date' => $entry->entry_date, 'voucher' => $entry->voucher_no, 'their_kg' => $entry->kg, 'our_kg' => Decimal::kg($ours), 'difference' => Decimal::kg($difference)];
            $groups[$key]['their_kg'] = Decimal::kg(Decimal::add($groups[$key]['their_kg'], $entry->kg));
            $groups[$key]['our_kg'] = Decimal::kg(Decimal::add($groups[$key]['our_kg'], $ours));
            $groups[$key]['difference'] = Decimal::kg(Decimal::add($groups[$key]['difference'], $difference));
        }

        return array_values(array_map(fn (array $g): array => [
            ...$g,
            'percent' => Decimal::divide(Decimal::multiply($g['difference'], '100', 6), $g['our_kg'], 2),
        ], $groups));
    }

    /**
     * R-12: per party and reason, the total and count of active agreed adjustments
     * dated in the range, and the % of that party's sales or purchases in the range.
     *
     * @param  array{type: string, from: string, to: string, party_id?: int|null, reason?: string|null}  $filters
     * @return list<array{party: string, party_id: int, reason: string, total: string, count: int, base: string, percent: string|null}>
     */
    public static function adjustments(array $filters): array
    {
        $rows = Adjustment::query()
            ->with('party')
            ->where('target_type', $filters['type'])
            ->where('status', EntryStatus::Active->value)
            ->whereBetween('entry_date', [$filters['from'], $filters['to']])
            ->when($filters['party_id'] ?? null, fn (Builder $q, int $id) => $q->where('party_id', $id))
            ->when($filters['reason'] ?? null, fn (Builder $q, string $reason) => $q->where('reason', $reason))
            ->orderBy('party_id')
            ->get();

        $groups = [];

        foreach ($rows as $adjustment) {
            $key = $adjustment->party_id.'-'.$adjustment->reason->value;
            $groups[$key] ??= ['party' => $adjustment->party->name, 'party_id' => $adjustment->party_id, 'reason' => $adjustment->reason->value, 'total' => '0.00', 'count' => 0];
            $groups[$key]['total'] = Decimal::add($groups[$key]['total'], $adjustment->amount);
            $groups[$key]['count']++;
        }

        $model = $filters['type'] === 'sale' ? Sale::class : Purchase::class;

        return array_values(array_map(function (array $g) use ($model, $filters): array {
            $base = (string) $model::query()
                ->where('party_id', $g['party_id'])
                ->where('status', EntryStatus::Active->value)
                ->where('rate_status', RateStatus::Fixed->value)
                ->whereBetween('entry_date', [$filters['from'], $filters['to']])
                ->toBase()
                ->selectRaw('COALESCE(SUM(amount), 0)::numeric(16,2)::text AS total')
                ->value('total');

            return [...$g, 'base' => $base, 'percent' => Decimal::isZero($base) ? null : Decimal::divide(Decimal::multiply($g['total'], '100', 6), $base, 2)];
        }, $groups));
    }

    /**
     * Active purchases or sales in the range with the filters, oldest first.
     *
     * @param  array{type: string, from: string, to: string, item_id?: int|null, party_id?: int|null}  $filters
     * @return Builder<Purchase>|Builder<Sale>
     */
    private static function entries(array $filters): Builder
    {
        $model = $filters['type'] === 'sale' ? Sale::class : Purchase::class;

        return $model::query()
            ->with(['item', 'party'])
            ->where('status', EntryStatus::Active->value)
            ->whereBetween('entry_date', [$filters['from'], $filters['to']])
            ->when($filters['item_id'] ?? null, fn (Builder $q, int $id) => $q->where('item_id', $id))
            ->when($filters['party_id'] ?? null, fn (Builder $q, int $id) => $q->where('party_id', $id))
            ->orderBy('entry_date')
            ->orderBy('id');
    }
}
