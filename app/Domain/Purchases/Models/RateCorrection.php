<?php

declare(strict_types=1);

namespace App\Domain\Purchases\Models;

use App\Domain\Sales\Models\Sale;
use App\Models\User;
use App\Support\NeverDeleted;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A rate change for a purchase or sale in a closed month (glossary "Rate correction"):
 * only the difference is posted, in an open month; the original row never changes
 * (BR-LED-09).
 *
 * @property int $id
 * @property int $company_id
 * @property string $voucher_no
 * @property string $correction_date
 * @property string $target_type purchase or sale
 * @property int|null $purchase_id
 * @property int|null $sale_id
 * @property string $old_rate
 * @property string $new_rate
 * @property string $old_amount
 * @property string $new_amount
 * @property string $difference new amount minus old amount
 * @property string $reason
 * @property int|null $journal_entry_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Purchase|null $purchase
 * @property-read Sale|null $sale
 * @property-read User|null $creator
 */
#[Fillable([
    'company_id', 'voucher_no', 'correction_date', 'target_type', 'purchase_id', 'sale_id', 'old_rate', 'new_rate',
    'old_amount', 'new_amount', 'difference', 'reason', 'journal_entry_id', 'created_by', 'updated_by',
])]
class RateCorrection extends Model
{
    use BelongsToCompany;
    use NeverDeleted;

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

    /** The purchase or sale this correction belongs to. */
    public function target(): Purchase|Sale
    {
        return ($this->target_type === 'purchase' ? $this->purchase : $this->sale)
            ?? throw new LogicException("Rate correction {$this->voucher_no} has no linked entry.");
    }

    protected function casts(): array
    {
        return [
            'old_rate' => 'decimal:4',
            'new_rate' => 'decimal:4',
            'old_amount' => 'decimal:2',
            'new_amount' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }
}
