<?php

declare(strict_types=1);

namespace App\Domain\Parties\Models;

use App\Domain\Adjustments\Models\Adjustment;
use App\Domain\Expenses\Models\Expense;
use App\Domain\Ledger\LedgerBalance;
use App\Domain\Parties\Enums\PartyStatus;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\PartyBalance;
use App\Domain\Partners\Models\PartnerTransaction;
use App\Domain\Payments\Models\Payment;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Sales\Models\Sale;
use App\Support\Decimal;
use App\Support\HasPublicId;
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
 * @property string $public_id
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
    use HasPublicId;
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
     * True once the party has any entry (BR-PTY-04, BR-PTY-05): a journal line, a
     * purchase, a sale, a payment (also one awaiting approval) or an expense.
     */
    public function hasEntries(): bool
    {
        return LedgerBalance::partyHasLines($this->id)
            || Purchase::query()->where('party_id', $this->id)->exists()
            || Sale::query()->where('party_id', $this->id)->exists()
            || Expense::query()->where('party_id', $this->id)->exists()
            || Adjustment::query()->where('party_id', $this->id)->exists()
            || PartnerTransaction::query()->where('party_id', $this->id)->exists()
            || Payment::query()->where('party_id', $this->id)->exists();
    }

    /** The balance in words from the party's control account (spec §8.2). */
    public function balance(): PartyBalance
    {
        $codes = $this->type === PartyType::Partner ? ['3000', '3100'] : [(string) $this->type->controlAccountCode()];
        $nets = [];

        foreach ($codes as $code) {
            $nets[$code] = LedgerBalance::byCode($code, null, $this->id);
        }

        return $this->balanceFrom($nets);
    }

    /**
     * The balance in words from already-summed nets (debit minus credit) per
     * account code, so a list can load all its balances in one query.
     *
     * @param  array<int|string, string>  $nets  account code => net (PHP keeps numeric codes as int keys)
     * @param  string|null  $pendingKg  already loaded rate-pending kg (lists), or null to read it
     */
    public function balanceFrom(array $nets, ?string $pendingKg = null): PartyBalance
    {
        if ($this->type === PartyType::Partner) {
            return $this->partnerBalance($nets['3000'] ?? '0.00', $nets['3100'] ?? '0.00');
        }

        return $this->balanceOfNet($nets[(string) $this->type->controlAccountCode()] ?? '0.00', $pendingKg ?? $this->pendingKg());
    }

    /**
     * Words for a net (debit minus credit) on the party's account(s), for example a
     * running balance in the khata (glossary §2).
     */
    public function balanceOfNet(string $net, string $pendingKg = '0.000'): PartyBalance
    {
        if (Decimal::isZero($net)) {
            return new PartyBalance('Settled', '0.00', 'zero', $pendingKg);
        }

        $side = Decimal::isPositive($net) ? 'debit' : 'credit';

        return new PartyBalance(self::words($this->type, $side), Decimal::money(Decimal::abs($net)), $side, $pendingKg);
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

    /** Kg of this party's active rate-pending purchases or sales. */
    public function pendingKg(): string
    {
        return self::pendingKgFor([$this->id])[$this->id] ?? '0.000';
    }

    /**
     * Rate-pending kg for many parties in one query (lists).
     *
     * @param  list<int>  $partyIds
     * @return array<int, string> party id => kg with 3 decimals
     */
    public static function pendingKgFor(array $partyIds): array
    {
        if ($partyIds === []) {
            return [];
        }

        $kg = [];

        // A party is a supplier or a buyer, so only one of the two tables has rows for it.
        foreach ([Purchase::class, Sale::class] as $model) {
            $rows = $model::query()
                ->whereIn('party_id', $partyIds)
                ->where('status', 'active')
                ->where('rate_status', 'pending')
                ->groupBy('party_id')
                ->selectRaw('party_id, CAST(SUM(kg) AS DECIMAL(20,3)) AS kg')
                ->pluck('kg', 'party_id');

            foreach ($rows as $partyId => $sum) {
                $kg[(int) $partyId] = Decimal::kg((string) $sum);
            }
        }

        return $kg;
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
    private function partnerBalance(string $capitalNet, string $withdrawalsNet): PartyBalance
    {
        $invested = Decimal::money(Decimal::negate($capitalNet));
        $withdrawn = Decimal::money($withdrawalsNet);
        $net = Decimal::money(Decimal::sub($invested, $withdrawn));

        if (Decimal::isZero($net)) {
            return new PartyBalance('Settled', '0.00', 'zero', '0.000', $invested, $withdrawn, $net);
        }

        $side = Decimal::isPositive($net) ? 'credit' : 'debit';

        return new PartyBalance(self::words(PartyType::Partner, $side), Decimal::money(Decimal::abs($net)), $side, '0.000', $invested, $withdrawn, $net);
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
