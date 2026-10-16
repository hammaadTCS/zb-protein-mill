<?php

declare(strict_types=1);

namespace App\Domain\Adjustments;

use App\Domain\Adjustments\Models\Adjustment;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Purchases\RateCorrections;
use App\Domain\Sales\Models\Sale;
use App\Support\Decimal;
use App\Support\EntryStatus;

/** Totals of the agreed adjustments on one purchase or sale (BR-ADJ-03, BR-PUR-13). */
final class Adjustments
{
    /** The sum of the entry's active adjustments, "0.00" when none. */
    public static function activeTotal(Purchase|Sale $entry, ?int $exceptId = null): string
    {
        $total = Adjustment::query()
            ->where($entry instanceof Purchase ? 'purchase_id' : 'sale_id', $entry->id)
            ->where('status', EntryStatus::Active->value)
            ->when($exceptId, fn ($q, int $id) => $q->whereKeyNot($id))
            ->toBase()
            ->selectRaw('CAST(COALESCE(SUM(amount), 0) AS DECIMAL(16,2)) AS total')
            ->value('total');

        return (string) ($total ?? '0.00');
    }

    public static function hasActive(Purchase|Sale $entry): bool
    {
        return Adjustment::query()
            ->where($entry instanceof Purchase ? 'purchase_id' : 'sale_id', $entry->id)
            ->where('status', EntryStatus::Active->value)
            ->exists();
    }

    /** The amount adjustments are taken from: the entry's amount after rate corrections. */
    public static function base(Purchase|Sale $entry): string
    {
        return (string) RateCorrections::effective($entry)['amount'];
    }

    /** BR-ADJ-03: the most that may still be adjusted; null when no adjustment is allowed. */
    public static function maximum(Purchase|Sale $entry): ?string
    {
        if ($entry->status !== EntryStatus::Active || $entry->amount === null) {
            return null;
        }

        $remaining = Decimal::sub(self::base($entry), self::activeTotal($entry));

        return Decimal::isPositive($remaining) ? $remaining : null;
    }
}
