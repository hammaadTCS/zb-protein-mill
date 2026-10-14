<?php

declare(strict_types=1);

namespace App\Domain\Expenses\Models;

use App\Domain\Expenses\Enums\ExpensePaymentMode;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\Parties\Models\Party;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Sales\Models\Sale;
use App\Models\User;
use App\Support\EntryStatus;
use App\Support\HasPublicId;
use App\Support\NeverDeleted;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A running cost (spec §9.3, glossary "Expense"), always with a note (BR-EXP-02).
 *
 * @property int $id
 * @property string $public_id
 * @property int $company_id
 * @property string $voucher_no
 * @property string $entry_date
 * @property int $category_id
 * @property string $amount
 * @property ExpensePaymentMode $payment_mode
 * @property int|null $money_account_id
 * @property int|null $party_id
 * @property int|null $handled_by_party_id
 * @property int|null $linked_purchase_id
 * @property int|null $linked_sale_id
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
 * @property-read ExpenseCategory $category
 * @property-read MoneyAccount|null $moneyAccount
 * @property-read Party|null $party
 * @property-read Party|null $handledBy
 * @property-read Purchase|null $linkedPurchase
 * @property-read Sale|null $linkedSale
 * @property-read User|null $creator
 */
#[Fillable([
    'company_id', 'voucher_no', 'entry_date', 'category_id', 'amount', 'payment_mode', 'money_account_id',
    'party_id', 'handled_by_party_id', 'linked_purchase_id', 'linked_sale_id', 'note', 'status',
    'cancel_reason', 'cancelled_by', 'cancelled_at', 'journal_entry_id', 'idempotency_key', 'created_by', 'updated_by',
])]
class Expense extends Model
{
    use BelongsToCompany;
    use HasPublicId;
    use NeverDeleted;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
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
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
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
    public function linkedPurchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'linked_purchase_id');
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function linkedSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'linked_sale_id');
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
            'amount' => 'decimal:2',
            'payment_mode' => ExpensePaymentMode::class,
            'status' => EntryStatus::class,
            'cancelled_at' => 'datetime',
        ];
    }
}
