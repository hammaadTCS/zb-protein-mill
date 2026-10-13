<?php

declare(strict_types=1);

namespace App\Domain\Periods\Models;

use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Models\User;
use App\Support\NeverDeleted;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A bank statement closing balance for a month (spec §9.5, BR-MEC-05). The latest row
 * per account and month is used.
 *
 * @property int $id
 * @property int $company_id
 * @property int $period_id
 * @property int $money_account_id
 * @property string $statement_balance
 * @property string $system_balance ledger balance at the month's last day when entered
 * @property string $difference statement minus system
 * @property string|null $note
 * @property int $entered_by
 * @property Carbon|null $entered_at
 * @property-read MoneyAccount $moneyAccount
 * @property-read User $enteredBy
 */
#[Fillable(['company_id', 'period_id', 'money_account_id', 'statement_balance', 'system_balance', 'difference', 'note', 'entered_by', 'entered_at'])]
class BankReconciliation extends Model
{
    use BelongsToCompany;
    use NeverDeleted;

    public $timestamps = false;

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
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    protected function casts(): array
    {
        return [
            'statement_balance' => 'decimal:2',
            'system_balance' => 'decimal:2',
            'difference' => 'decimal:2',
            'entered_at' => 'datetime',
        ];
    }
}
