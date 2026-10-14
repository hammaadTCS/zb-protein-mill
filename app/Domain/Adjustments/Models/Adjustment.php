<?php

declare(strict_types=1);

namespace App\Domain\Adjustments\Models;

use App\Domain\Adjustments\Enums\AdjustmentReason;
use App\Domain\Parties\Models\Party;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Sales\Models\Sale;
use App\Models\User;
use App\Support\EntryStatus;
use App\Support\HasPublicId;
use App\Support\NeverDeleted;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * An agreed adjustment (spec §9.3, glossary "Agreed adjustment"): an amount both sides
 * agreed to deduct after a purchase or sale. The original entry never changes (D-13).
 *
 * @property int $id
 * @property string $public_id
 * @property int $company_id
 * @property string $voucher_no
 * @property string $entry_date
 * @property string $target_type purchase or sale
 * @property int|null $purchase_id
 * @property int|null $sale_id
 * @property int $party_id
 * @property string $amount
 * @property AdjustmentReason $reason
 * @property string $note
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
 * @property-read Purchase|null $purchase
 * @property-read Sale|null $sale
 * @property-read User|null $creator
 */
#[Fillable([
    'company_id', 'voucher_no', 'entry_date', 'target_type', 'purchase_id', 'sale_id', 'party_id', 'amount', 'reason', 'note',
    'status', 'cancel_reason', 'cancelled_by', 'cancelled_at', 'journal_entry_id', 'idempotency_key', 'created_by', 'updated_by',
])]
class Adjustment extends Model
{
    use BelongsToCompany;
    use HasPublicId;
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
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The purchase or sale this adjustment belongs to. */
    public function target(): Purchase|Sale
    {
        return ($this->target_type === 'purchase' ? $this->purchase : $this->sale)
            ?? throw new LogicException("Adjustment {$this->voucher_no} has no linked entry.");
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'reason' => AdjustmentReason::class,
            'status' => EntryStatus::class,
            'cancelled_at' => 'datetime',
        ];
    }
}
