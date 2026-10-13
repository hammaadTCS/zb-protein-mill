<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Enums\NormalSide;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One account of the chart of accounts (spec §8.1, §9.2).
 *
 * @property int $id
 * @property int $company_id
 * @property string $code
 * @property string $name
 * @property AccountType $type
 * @property NormalSide $normal_side
 * @property string|null $party_type supplier, buyer, partner, employee or other (BR-LED-04)
 * @property bool $is_system
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['company_id', 'code', 'name', 'type', 'normal_side', 'party_type', 'is_system', 'is_active', 'created_by', 'updated_by'])]
class LedgerAccount extends Model
{
    use BelongsToCompany;

    /** BR-LED-01: what a system account may never change. */
    private const LOCKED_ON_SYSTEM = ['code', 'name', 'type', 'normal_side', 'party_type', 'is_active'];

    protected static function booted(): void
    {
        static::updating(function (LedgerAccount $account): void {
            if ($account->getOriginal('is_system') && $account->isDirty(self::LOCKED_ON_SYSTEM)) {
                throw new LogicException("System account {$account->getOriginal('code')} cannot be changed (BR-LED-01).");
            }
        });

        static::deleting(function (LedgerAccount $account): void {
            throw new LogicException('Ledger accounts are never deleted (BR-GEN-03).');
        });
    }

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'normal_side' => NormalSide::class,
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
