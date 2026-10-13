<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\Parties\Models\Party;
use App\Domain\Payments\Enums\ApprovalStatus;
use App\Domain\Payments\Enums\PaymentDirection;
use App\Domain\Purchases\Models\Purchase;
use App\Models\User;
use App\Support\EntryStatus;
use App\Support\NeverDeleted;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A payment (direction paid) or a receipt (direction received), spec §9.3.
 * The method comes from the money account type (BR-PAY-03).
 *
 * @property int $id
 * @property int $company_id
 * @property string $voucher_no
 * @property string $entry_date
 * @property PaymentDirection $direction
 * @property int $party_id
 * @property string $amount
 * @property int $money_account_id
 * @property string|null $reference_no
 * @property int|null $handled_by_party_id
 * @property int|null $purchase_id
 * @property int|null $sale_id
 * @property ApprovalStatus $approval_status
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $rejection_reason
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
 * @property-read MoneyAccount $moneyAccount
 * @property-read Party|null $handledBy
 * @property-read Purchase|null $purchase
 */
#[Fillable([
    'company_id', 'voucher_no', 'entry_date', 'direction', 'party_id', 'amount', 'money_account_id',
    'reference_no', 'handled_by_party_id', 'purchase_id', 'sale_id', 'approval_status', 'approved_by',
    'approved_at', 'rejection_reason', 'note', 'status', 'cancel_reason', 'cancelled_by', 'cancelled_at',
    'journal_entry_id', 'idempotency_key', 'created_by', 'updated_by',
])]
class Payment extends Model
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
     * @return BelongsTo<MoneyAccount, $this>
     */
    public function moneyAccount(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class);
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'handled_by_party_id');
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', EntryStatus::Active->value);
    }

    protected function casts(): array
    {
        return [
            'direction' => PaymentDirection::class,
            'approval_status' => ApprovalStatus::class,
            'status' => EntryStatus::class,
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
