<?php

declare(strict_types=1);

namespace App\Domain\Partners\Models;

use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\Parties\Models\Party;
use App\Domain\Partners\Enums\PartnerTransactionType;
use App\Models\User;
use App\Support\EntryStatus;
use App\Support\NeverDeleted;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A partner's investment or withdrawal (spec §9.3, D-21): never mixed with sales or
 * expenses.
 *
 * @property int $id
 * @property int $company_id
 * @property string $voucher_no
 * @property string $entry_date
 * @property int $party_id
 * @property PartnerTransactionType $type
 * @property string $amount
 * @property int $money_account_id
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
 * @property-read User|null $creator
 */
#[Fillable([
    'company_id', 'voucher_no', 'entry_date', 'party_id', 'type', 'amount', 'money_account_id', 'note', 'status',
    'cancel_reason', 'cancelled_by', 'cancelled_at', 'journal_entry_id', 'idempotency_key', 'created_by', 'updated_by',
])]
class PartnerTransaction extends Model
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
            'type' => PartnerTransactionType::class,
            'status' => EntryStatus::class,
            'cancelled_at' => 'datetime',
        ];
    }
}
