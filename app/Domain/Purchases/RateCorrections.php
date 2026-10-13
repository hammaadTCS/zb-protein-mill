<?php

declare(strict_types=1);

namespace App\Domain\Purchases;

use App\Domain\Purchases\Models\Purchase;
use App\Domain\Purchases\Models\RateCorrection;
use App\Domain\Sales\Models\Sale;
use App\Support\Decimal;

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
