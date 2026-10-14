<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Models;

use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Models\User;
use App\Support\EntryStatus;
use App\Support\HasPublicId;
use App\Support\NeverDeleted;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money moved between two of our own accounts (spec §9.3, D-22): never income or
 * spending (BR-TRF-03).
 *
 * @property int $id
 * @property string $public_id
 * @property int $company_id
 * @property string $voucher_no
 * @property string $entry_date
 * @property int $from_account_id
 * @property int $to_account_id
 * @property string $amount
 * @property string|null $reference_no
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
 * @property-read MoneyAccount $fromAccount
 * @property-read MoneyAccount $toAccount
 */
#[Fillable([
    'company_id', 'voucher_no', 'entry_date', 'from_account_id', 'to_account_id', 'amount', 'reference_no', 'note', 'status',
    'cancel_reason', 'cancelled_by', 'cancelled_at', 'journal_entry_id', 'idempotency_key', 'created_by', 'updated_by',
])]
class Transfer extends Model
{
    use BelongsToCompany;
    use HasPublicId;
    use NeverDeleted;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return BelongsTo<MoneyAccount, $this>
     */
    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class, 'from_account_id');
    }

    /**
     * @return BelongsTo<MoneyAccount, $this>
     */
    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class, 'to_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => EntryStatus::class,
            'cancelled_at' => 'datetime',
        ];
    }
}
