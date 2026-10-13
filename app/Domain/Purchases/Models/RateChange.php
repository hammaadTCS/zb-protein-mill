<?php

declare(strict_types=1);

namespace App\Domain\Purchases\Models;

use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * BR-PUR-09: one rate added to or changed on a purchase or sale. Written once,
 * never changed (spec §9.7).
 *
 * @property int $id
 * @property int $company_id
 * @property string $target_type purchase or sale
 * @property int $target_id
 * @property string|null $old_rate
 * @property string $new_rate
 * @property string|null $old_amount
 * @property string $new_amount
 * @property int $changed_by
 * @property Carbon $changed_at
 * @property string|null $reason
 */
#[Fillable(['company_id', 'target_type', 'target_id', 'old_rate', 'new_rate', 'old_amount', 'new_amount', 'changed_by', 'changed_at', 'reason', 'created_by'])]
class RateChange extends Model
{
    use BelongsToCompany;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Rate changes are never changed (BR-PUR-09).'));
        static::deleting(fn () => throw new LogicException('Rate changes are never deleted (BR-PUR-09).'));
    }

    /** Writes the row for a rate added or changed on an entry. */
    public static function record(Model $entry, ?string $oldRate, string $newRate, ?string $oldAmount, string $newAmount, User $by, ?string $reason = null): self
    {
        return self::query()->create([
            'company_id' => $by->company_id,
            'target_type' => $entry->getMorphClass(),
            'target_id' => $entry->getKey(),
            'old_rate' => $oldRate,
            'new_rate' => $newRate,
            'old_amount' => $oldAmount,
            'new_amount' => $newAmount,
            'changed_by' => $by->id,
            'changed_at' => now(),
            'reason' => $reason,
            'created_by' => $by->id,
        ]);
    }

    protected function casts(): array
    {
        return [
            'old_rate' => 'decimal:4',
            'new_rate' => 'decimal:4',
            'old_amount' => 'decimal:2',
            'new_amount' => 'decimal:2',
            'changed_at' => 'datetime',
        ];
    }
}
