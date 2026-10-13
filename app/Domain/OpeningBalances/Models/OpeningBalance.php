<?php

declare(strict_types=1);

namespace App\Domain\OpeningBalances\Models;

use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\OpeningBalances\Enums\OpeningSide;
use App\Domain\Parties\Models\Party;
use App\Support\NeverDeleted;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A balance on the system start date (spec §9.3, BR-OPB-01): for one money account
 * or one party. Amount 0 means the opening was removed.
 *
 * @property int $id
 * @property int $company_id
 * @property string $voucher_no
 * @property string $as_of_date
 * @property int|null $money_account_id
 * @property int|null $party_id
 * @property string $amount
 * @property OpeningSide $side
 * @property int|null $journal_entry_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MoneyAccount|null $moneyAccount
 * @property-read Party|null $party
 */
#[Fillable(['company_id', 'voucher_no', 'as_of_date', 'money_account_id', 'party_id', 'amount', 'side', 'journal_entry_id', 'created_by', 'updated_by'])]
class OpeningBalance extends Model
{
    use BelongsToCompany;
    use NeverDeleted;

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

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'side' => OpeningSide::class,
        ];
    }
}
