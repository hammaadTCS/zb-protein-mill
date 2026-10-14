<?php

declare(strict_types=1);

namespace App\Domain\Expenses\Models;

use App\Domain\Ledger\LedgerBalance;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Support\HasPublicId;
use App\Support\NeverDeleted;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A running-cost category (spec §9.2): wages, food, electricity… Its ledger account
 * has the same name (spec §8.1).
 *
 * @property int $id
 * @property string $public_id
 * @property int $company_id
 * @property string $name
 * @property int $ledger_account_id
 * @property bool $is_quick shown as a button on Quick expense
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LedgerAccount $ledgerAccount
 */
#[Fillable(['company_id', 'name', 'ledger_account_id', 'is_quick', 'sort_order', 'is_active', 'created_by', 'updated_by'])]
class ExpenseCategory extends Model
{
    use BelongsToCompany;
    use HasPublicId;
    use NeverDeleted;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_quick' => false,
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * @return BelongsTo<LedgerAccount, $this>
     */
    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    /** Ledger balance (total spent, debit minus credit), optionally up to a date. */
    public function balance(?string $asOf = null): string
    {
        return LedgerBalance::account($this->ledger_account_id, $asOf);
    }

    /**
     * BR-GEN-08: only active categories can be picked for new expenses.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    protected function casts(): array
    {
        return [
            'is_quick' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
