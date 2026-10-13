<?php

declare(strict_types=1);

namespace App\Domain\Parties\Models;

use App\Domain\Ledger\LedgerBalance;
use App\Domain\Parties\Enums\PartyStatus;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\PartyBalance;
use App\Support\Decimal;
use App\Support\NeverDeleted;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Anyone the mill deals with (spec §9.2, D-01 to D-06). The type is fixed after
 * the first entry (BR-PTY-04); a party is never deleted (BR-PTY-05).
 *
 * @property int $id
 * @property int $company_id
 * @property string $code
 * @property PartyType $type
 * @property string $name
 * @property string|null $name_ur
 * @property string|null $business_name
 * @property string|null $contact_person
 * @property string $mobile
 * @property string|null $area
 * @property string|null $city
 * @property string|null $address
 * @property string|null $ntn
 * @property string|null $bank_name
 * @property string|null $bank_account_title
 * @property string|null $bank_account_number
 * @property string|null $credit_limit
 * @property int|null $payment_terms_days
 * @property string|null $profit_share_percent
 * @property string|null $joining_date
 * @property string|null $notes
 * @property PartyStatus $status
 * @property int|null $merged_into_id
 * @property bool $needs_review
 * @property bool $bank_change_needs_review
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company_id', 'code', 'type', 'name', 'name_ur', 'business_name', 'contact_person', 'mobile',
    'area', 'city', 'address', 'ntn', 'bank_name', 'bank_account_title', 'bank_account_number',
    'credit_limit', 'payment_terms_days', 'profit_share_percent', 'joining_date', 'notes', 'status',
    'merged_into_id', 'needs_review', 'bank_change_needs_review', 'reviewed_by', 'reviewed_at',
    'created_by', 'updated_by',
])]
class Party extends Model
{
    use BelongsToCompany;
    use NeverDeleted;

    /** The fields a user may fill on the party form (spec §9.2, §11.10). */
    public const FORM_FIELDS = [
        'type', 'name', 'name_ur', 'business_name', 'contact_person', 'mobile', 'area', 'city', 'address', 'ntn',
        'bank_name', 'bank_account_title', 'bank_account_number', 'credit_limit', 'payment_terms_days',
        'profit_share_percent', 'joining_date', 'notes',
    ];

    /** BR-PTY-07: the bank details an Accountant change must be reviewed. */
    public const BANK_FIELDS = ['bank_name', 'bank_account_title', 'bank_account_number'];

    /** BR-PTY-10: fields that exist only for one type. */
    public const BUYER_FIELDS = ['credit_limit', 'payment_terms_days'];

    public const PARTNER_FIELDS = ['profit_share_percent', 'joining_date'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'active',
        'needs_review' => false,
        'bank_change_needs_review' => false,
    ];

    /**
     * @return BelongsTo<Party, $this>
     */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    /**
     * True once the party has any entry (BR-PTY-04, BR-PTY-05): a journal line, or a
     * purchase or sale (extended in T2.7 and T3.4).
     */
    public function hasEntries(): bool
    {
        return LedgerBalance::partyHasLines($this->id);
    }

    /** The balance in words from the party's control account (spec §8.2). */
    public function balance(): PartyBalance
    {
        if ($this->type === PartyType::Partner) {
            return $this->partnerBalance();
        }

        $code = (string) $this->type->controlAccountCode();
        $net = LedgerBalance::byCode($code, null, $this->id); // debit minus credit

        if (Decimal::isZero($net)) {
            return new PartyBalance('Settled', '0.00', 'zero', $this->pendingKg());
        }

        $side = Decimal::isPositive($net) ? 'debit' : 'credit';

        return new PartyBalance(self::words($this->type, $side), Decimal::abs($net), $side, $this->pendingKg());
    }

    /** Glossary §2: the words for a ledger side, per party type. */
    public static function words(PartyType $type, string $side): string
    {
        return match ($type) {
            PartyType::Supplier, PartyType::Other => $side === 'credit' ? 'We owe' : 'Advance',
            PartyType::Buyer => $side === 'debit' ? 'Owes us' : 'Advance',
            PartyType::Employee => $side === 'debit' ? 'Owes us' : 'We owe',
            PartyType::Partner => $side === 'credit' ? 'Net invested' : 'Withdrawn',
        };
    }

    /** Rate-pending kg of this party's purchases or sales (T3.1). */
    public function pendingKg(): string
    {
        return '0.000';
    }

    /**
     * BR-GEN-08: only active parties can be picked for new entries.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', PartyStatus::Active->value);
    }

    /**
     * R-09: invested (3000, credit), withdrawn (3100, debit) and net.
     */
    private function partnerBalance(): PartyBalance
    {
        $invested = Decimal::negate(LedgerBalance::byCode('3000', null, $this->id));
        $withdrawn = LedgerBalance::byCode('3100', null, $this->id);
        $net = Decimal::sub($invested, $withdrawn);

        if (Decimal::isZero($net)) {
            return new PartyBalance('Settled', '0.00', 'zero', '0.000', $invested, $withdrawn, $net);
        }

        $side = Decimal::isPositive($net) ? 'credit' : 'debit';

        return new PartyBalance(self::words(PartyType::Partner, $side), Decimal::abs($net), $side, '0.000', $invested, $withdrawn, $net);
    }

    protected function casts(): array
    {
        return [
            'type' => PartyType::class,
            'status' => PartyStatus::class,
            'credit_limit' => 'decimal:2',
            'payment_terms_days' => 'integer',
            'profit_share_percent' => 'decimal:2',
            'needs_review' => 'boolean',
            'bank_change_needs_review' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }
}
