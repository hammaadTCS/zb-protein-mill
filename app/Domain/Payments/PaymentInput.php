<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use App\Domain\MoneyAccounts\Enums\MoneyAccountType;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;
use App\Domain\Payments\Enums\PaymentDirection;
use App\Support\EntryChecks;
use App\Support\Warnings\Warnings;
use Illuminate\Validation\ValidationException;

/**
 * The checked fields of a payment or receipt (BR-PAY-01 to BR-PAY-04, BR-PAY-12),
 * shared by CreatePayment and UpdatePayment.
 */
final readonly class PaymentInput
{
    public function __construct(
        public string $date,
        public PaymentDirection $direction,
        public Party $party,
        public string $amount,
        public MoneyAccount $account,
        public ?string $referenceNo,
        public ?Party $handledBy,
        public ?string $note,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        $direction = PaymentDirection::tryFrom((string) ($data['direction'] ?? ''))
            ?? throw ValidationException::withMessages(['direction' => __('Choose Paid or Received.')]);

        return new self(
            date: (string) $data['date'],
            direction: $direction,
            // BR-PAY-01: any party except a partner.
            party: EntryChecks::party(self::id($data, 'party_id'), [PartyType::Supplier, PartyType::Buyer, PartyType::Employee, PartyType::Other], 'party_id', __('Select an active party. Partner money is entered on the partner page.')),
            amount: EntryChecks::amount((string) ($data['amount'] ?? '')), // BR-PAY-02
            account: EntryChecks::moneyAccount(self::id($data, 'money_account_id')), // BR-PAY-02
            referenceNo: self::text($data, 'reference_no'),
            handledBy: EntryChecks::handledBy(self::id($data, 'handled_by_party_id')), // BR-PAY-12
            note: self::text($data, 'note'),
        );
    }

    /** BR-PAY-03: the method comes from the account type. */
    public static function method(MoneyAccount $account): string
    {
        return $account->type === MoneyAccountType::Bank ? 'Bank transfer' : 'Cash';
    }

    /** BR-PAY-04: a bank payment or receipt without a transfer reference needs a confirmation. */
    public function checkReference(Warnings $warnings): void
    {
        if ($this->account->type === MoneyAccountType::Bank && $this->referenceNo === null) {
            $warnings->raise('missing_reference', __('No transfer reference entered for a bank payment.'));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function text(array $data, string $key): ?string
    {
        $value = isset($data[$key]) ? trim((string) $data[$key]) : '';

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function id(array $data, string $key): ?int
    {
        return isset($data[$key]) && is_numeric($data[$key]) ? (int) $data[$key] : null;
    }
}
