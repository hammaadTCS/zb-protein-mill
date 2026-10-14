<?php

declare(strict_types=1);

namespace App\Domain\MoneyAccounts\Models;

use App\Domain\Ledger\LedgerBalance;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\MoneyAccounts\Enums\MoneyAccountType;
use App\Support\HasPublicId;
use App\Support\NeverDeleted;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A cash box or bank account (spec §9.2, F-14). Its money lives in its own ledger
 * account (BR-ACC-01).
 *
 * @property int $id
 * @property string $public_id
 * @property int $company_id
 * @property string $name
 * @property MoneyAccountType $type
 * @property string|null $bank_name
 * @property string|null $account_title
 * @property string|null $account_number
 * @property int $ledger_account_id
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LedgerAccount $ledgerAccount
 */
#[Fillable(['company_id', 'name', 'type', 'bank_name', 'account_title', 'account_number', 'ledger_account_id', 'is_active', 'created_by', 'updated_by'])]
class MoneyAccount extends Model
{
    use BelongsToCompany;
    use HasPublicId;
    use NeverDeleted;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return BelongsTo<LedgerAccount, $this>
     */
    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    /**
     * Ledger balance (debit minus credit), optionally up to a posting date.
     *
     * @param  string|null  $asOf  Y-m-d
     */
    public function balance(?string $asOf = null): string
    {
        return LedgerBalance::account($this->ledger_account_id, $asOf);
    }

    /**
     * BR-GEN-08: only active accounts can be picked for new entries.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** "Cash – Main" / "Bank – HBL": the name of its ledger account. */
    public static function ledgerName(MoneyAccountType $type, string $name): string
    {
        return $type->label().' – '.$name;
    }

    protected function casts(): array
    {
        return [
            'type' => MoneyAccountType::class,
            'is_active' => 'boolean',
        ];
    }
}
