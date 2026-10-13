<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use App\Domain\Adjustments\Models\Adjustment;
use App\Domain\Items\Models\Item;
use App\Domain\Parties\Models\Party;
use App\Domain\Payments\Models\Payment;
use App\Domain\Purchases\Enums\RateStatus;
use App\Models\User;
use App\Support\EntryStatus;
use App\Support\NeverDeleted;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Protein product or oil/fat sold to a buyer (spec §9.3, glossary "Sale").
 * kg is the buyer's weight (BR-SAL-05); amount = kg × rate on the server (BR-GEN-06).
 *
 * @property int $id
 * @property int $company_id
 * @property string $voucher_no
 * @property string $entry_date
 * @property int $party_id
 * @property int $item_id
 * @property string $kg
 * @property string|null $rate
 * @property string|null $amount
 * @property RateStatus $rate_status
 * @property Carbon|null $rate_fixed_at
 * @property string|null $slip_no
 * @property string|null $our_weight_kg
 * @property string|null $note
 * @property EntryStatus $status
 * @property string|null $cancel_reason
 * @property int|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property int|null $journal_entry_id
 * @property string|null $idempotency_key
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Party $party
 * @property-read Item $item
 * @property-read User|null $creator
 */
#[Fillable([
    'company_id', 'voucher_no', 'entry_date', 'party_id', 'item_id', 'kg', 'rate', 'amount', 'rate_status',
    'rate_fixed_at', 'slip_no', 'our_weight_kg', 'note', 'status', 'cancel_reason', 'cancelled_by',
    'cancelled_at', 'journal_entry_id', 'idempotency_key', 'created_by', 'updated_by',
])]
class Sale extends Model
{
    use BelongsToCompany;
    use NeverDeleted;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Money received with this sale ("Received now", BR-SAL-07).
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Agreed adjustments on this entry (BR-ADJ-01).
     *
     * @return HasMany<Adjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(Adjustment::class);
    }

    /**
     * Spec §12.1: reports count active entries only.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', EntryStatus::Active->value);
    }

    protected function casts(): array
    {
        return [
            'kg' => 'decimal:3',
            'rate' => 'decimal:4',
            'amount' => 'decimal:2',
            'our_weight_kg' => 'decimal:3',
            'rate_status' => RateStatus::class,
            'status' => EntryStatus::class,
            'rate_fixed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
