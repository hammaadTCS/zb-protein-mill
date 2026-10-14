<?php

declare(strict_types=1);

namespace App\Domain\Purchases;

use App\Domain\Purchases\Models\Purchase;
use App\Domain\Purchases\Models\RateCorrection;
use App\Domain\Sales\Models\Sale;
use App\Support\Decimal;
use Illuminate\Support\Collection;

/** The rate and amount of a purchase or sale after its rate corrections (BR-LED-09). */
final class RateCorrections
{
    /**
     * The latest corrected rate and the original amount plus every correction, or the
     * entry's own rate and amount when it has none.
     *
     * @return array{rate: string|null, amount: string|null}
     */
    public static function effective(Purchase|Sale $entry): array
    {
        $corrections = RateCorrection::query()
            ->where($entry instanceof Purchase ? 'purchase_id' : 'sale_id', $entry->id)
            ->orderBy('id')
            ->get(['new_rate', 'difference']);

        return self::apply($entry, $corrections);
    }

    /**
     * effective() for many purchases or many sales with one query (reports): rate
     * corrections are few, so all of that kind are read at once.
     *
     * @param  Collection<int, Purchase>|Collection<int, Sale>  $entries  all purchases or all sales
     * @return array<int, array{rate: string|null, amount: string|null}> by entry id
     */
    public static function effectiveMany(Collection $entries): array
    {
        $first = $entries->first();

        if ($first === null) {
            return [];
        }

        $column = $first instanceof Purchase ? 'purchase_id' : 'sale_id';
        $byEntry = RateCorrection::query()->whereNotNull($column)->orderBy('id')->get([$column, 'new_rate', 'difference'])->groupBy($column);
        $result = [];

        foreach ($entries as $entry) {
            $result[$entry->id] = self::apply($entry, $byEntry->get($entry->id) ?? collect());
        }

        return $result;
    }

    /**
     * @param  Collection<int, RateCorrection>  $corrections  oldest first
     * @return array{rate: string|null, amount: string|null}
     */
    private static function apply(Purchase|Sale $entry, Collection $corrections): array
    {
        $latest = $corrections->last();

        if ($latest === null || $entry->amount === null) {
            return ['rate' => $entry->rate, 'amount' => $entry->amount];
        }

        return [
            'rate' => $latest->new_rate,
            'amount' => Decimal::add($entry->amount, Decimal::sum($corrections->map(fn (RateCorrection $c): string => $c->difference)->all())),
        ];
    }
}
