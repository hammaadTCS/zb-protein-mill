<?php

declare(strict_types=1);

namespace App\Domain\Transfers;

use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Support\EntryChecks;
use Illuminate\Validation\ValidationException;

/** The checked fields of a transfer (BR-TRF-01, BR-TRF-02), shared by create and edit. */
final readonly class TransferInput
{
    public function __construct(
        public string $date,
        public MoneyAccount $from,
        public MoneyAccount $to,
        public string $amount,
        public ?string $referenceNo,
        public ?string $note,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        $from = EntryChecks::moneyAccount(self::id($data, 'from_account_id'), 'from_account_id'); // BR-TRF-01
        $to = EntryChecks::moneyAccount(self::id($data, 'to_account_id'), 'to_account_id');

        if ($from->id === $to->id) {
            throw ValidationException::withMessages(['to_account_id' => __('Choose two different accounts.')]); // BR-TRF-01
        }

        return new self(
            date: (string) $data['date'],
            from: $from,
            to: $to,
            amount: EntryChecks::amount((string) ($data['amount'] ?? '')), // BR-TRF-02
            referenceNo: self::text($data, 'reference_no'),
            note: self::text($data, 'note'),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function id(array $data, string $key): ?int
    {
        return isset($data[$key]) && is_numeric($data[$key]) ? (int) $data[$key] : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function text(array $data, string $key): ?string
    {
        $value = trim((string) ($data[$key] ?? ''));

        return $value === '' ? null : $value;
    }
}
