<?php

declare(strict_types=1);

namespace App\Domain\Partners;

use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;
use App\Domain\Partners\Enums\PartnerTransactionType;
use App\Support\EntryChecks;
use Illuminate\Validation\ValidationException;

/**
 * The checked fields of a partner entry (BR-PTN-01), shared by
 * CreatePartnerTransaction and UpdatePartnerTransaction.
 */
final readonly class PartnerTransactionInput
{
    public function __construct(
        public string $date,
        public Party $partner,
        public PartnerTransactionType $type,
        public string $amount,
        public MoneyAccount $account,
        public ?string $note,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        $partner = EntryChecks::party(self::id($data, 'party_id'), [PartyType::Partner], 'party_id', __('Select an active partner.')); // BR-PTN-01
        $type = PartnerTransactionType::tryFrom((string) ($data['type'] ?? ''))
            ?? throw ValidationException::withMessages(['type' => __('Choose Investment or Withdrawal.')]); // BR-PTN-01
        $note = trim((string) ($data['note'] ?? ''));

        return new self(
            date: (string) $data['date'],
            partner: $partner,
            type: $type,
            amount: EntryChecks::amount((string) ($data['amount'] ?? '')),
            account: EntryChecks::moneyAccount(self::id($data, 'money_account_id')),
            note: $note === '' ? null : $note,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function id(array $data, string $key): ?int
    {
        return isset($data[$key]) && is_numeric($data[$key]) ? (int) $data[$key] : null;
    }
}
