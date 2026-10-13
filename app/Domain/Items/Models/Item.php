<?php

declare(strict_types=1);

namespace App\Domain\Items\Models;

use App\Domain\Items\Enums\ItemType;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Purchases\Models\Purchase;
use App\Support\NeverDeleted;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Something the mill buys or sells by the kg (spec §9.2, D-07).
 *
 * @property int $id
 * @property int $company_id
 * @property string $code
 * @property string $name
 * @property ItemType $type
 * @property string $unit
 * @property int|null $purchase_ledger_account_id
 * @property int|null $sales_ledger_account_id
 * @property bool $is_system
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LedgerAccount|null $purchaseAccount
 * @property-read LedgerAccount|null $salesAccount
 */
#[Fillable(['company_id', 'code', 'name', 'type', 'unit', 'purchase_ledger_account_id', 'sales_ledger_account_id', 'is_system', 'is_active', 'created_by', 'updated_by'])]
class Item extends Model
{
    use BelongsToCompany;
    use NeverDeleted;

    /** @var array<string, mixed> */
    protected $attributes = [
        'unit' => 'kg',
        'is_system' => false,
        'is_active' => true,
    ];

    /**
     * @return BelongsTo<LedgerAccount, $this>
     */
    public function purchaseAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'purchase_ledger_account_id');
    }

    /**
     * @return BelongsTo<LedgerAccount, $this>
     */
    public function salesAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'sales_ledger_account_id');
    }

    /** The purchase account of a bought item, or the sales account of a product. */
    public function ledgerAccountId(): int
    {
        return (int) ($this->type->isBought() ? $this->purchase_ledger_account_id : $this->sales_ledger_account_id);
    }

    /**
     * True once any purchase or sale uses this item, cancelled ones included
     * (BR-ITM-02). Sales are added in T3.4.
     */
    public function hasEntries(): bool
    {
        return Purchase::query()->where('item_id', $this->id)->exists();
    }

    /**
     * BR-GEN-08: only active items can be picked for new entries.
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
            'type' => ItemType::class,
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
