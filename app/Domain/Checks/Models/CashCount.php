<?php

declare(strict_types=1);

namespace App\Domain\Checks\Models;

use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Models\User;
use App\Support\NeverDeleted;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A daily cash count (spec §9.5, D-25): counted against the ledger, stored, never
 * posted (BR-CHK-03). A row with `business_closed` dismisses a closed day (BR-CHK-04).
 * Several per day are allowed; the latest counts.
 *
 * @property int $id
 * @property int $company_id
 * @property string $count_date
 * @property int $money_account_id
 * @property string|null $counted_amount
 * @property string $system_amount
 * @property string|null $difference counted minus system
 * @property string|null $note
 * @property bool $business_closed
 * @property int $counted_by
 * @property Carbon|null $created_at
 * @property-read MoneyAccount $moneyAccount
 * @property-read User $counter
 */
#[Fillable(['company_id', 'count_date', 'money_account_id', 'counted_amount', 'system_amount', 'difference', 'note', 'business_closed', 'counted_by'])]
class CashCount extends Model
{
    use BelongsToCompany;
    use NeverDeleted;

    public const UPDATED_AT = null;

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
    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    protected function casts(): array
    {
        return [
            'counted_amount' => 'decimal:2',
            'system_amount' => 'decimal:2',
            'difference' => 'decimal:2',
            'business_closed' => 'boolean',
        ];
    }
}
