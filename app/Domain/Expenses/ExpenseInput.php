<?php

declare(strict_types=1);

namespace App\Domain\Expenses;

use App\Domain\Expenses\Enums\ExpensePaymentMode;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Sales\Models\Sale;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Support\Decimal;
use App\Support\EntryChecks;
use App\Support\EntryStatus;
use Illuminate\Validation\ValidationException;

/**
 * The checked fields of an expense (BR-EXP-01 to BR-EXP-08), shared by CreateExpense
 * and UpdateExpense.
 */
final readonly class ExpenseInput
{
    public function __construct(
        public string $date,
        public ExpenseCategory $category,
        public string $amount,
        public ExpensePaymentMode $mode,
        public ?MoneyAccount $account,
        public ?Party $party,
        public ?Party $handledBy,
        public ?int $linkedPurchaseId,
        public ?int $linkedSaleId,
        public string $note,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        $categoryId = self::id($data, 'category_id');
        $category = ($categoryId === null ? null : ExpenseCategory::query()->active()->find($categoryId))
            ?? throw ValidationException::withMessages(['category_id' => __('Select an active category.')]); // BR-EXP-01, BR-GEN-08

        $amount = EntryChecks::amount((string) ($data['amount'] ?? '')); // BR-EXP-01
        $note = trim((string) ($data['note'] ?? ''));

        if (mb_strlen($note) < 3) {
            throw ValidationException::withMessages(['note' => __('Write a note of at least 3 characters.')]); // BR-EXP-02
        }

        $mode = ExpensePaymentMode::tryFrom((string) ($data['payment_mode'] ?? ''))
            ?? throw ValidationException::withMessages(['payment_mode' => __('Choose Paid, Unpaid or Paid by employee.')]);

        [$account, $party] = self::modeFields($data, $mode, $amount);
        [$purchaseId, $saleId] = self::link($data);

        return new self(
            date: (string) $data['date'],
            category: $category,
            amount: $amount,
            mode: $mode,
            account: $account,
            party: $party,
            handledBy: EntryChecks::handledBy(self::id($data, 'handled_by_party_id')), // BR-EXP-07
            linkedPurchaseId: $purchaseId,
            linkedSaleId: $saleId,
            note: $note,
        );
    }

    /**
     * BR-EXP-03 to BR-EXP-06: the account and the party the payment mode needs.
     *
     * @param  array<string, mixed>  $data
     * @return array{MoneyAccount|null, Party|null}
     */
    private static function modeFields(array $data, ExpensePaymentMode $mode, string $amount): array
    {
        $partyId = self::id($data, 'party_id');
        $limit = Settings::get(SettingKeys::EXPENSE_PARTY_REQUIRED_LIMIT);
        $partyRequired = $mode !== ExpensePaymentMode::Paid || (is_string($limit) && Decimal::compare($amount, $limit) >= 0);

        if ($partyRequired && $partyId === null) {
            throw ValidationException::withMessages(['party_id' => $mode === ExpensePaymentMode::Paid
                ? __('A party is required for an expense of this amount.') // BR-EXP-03
                : __('Select the party for this payment mode.')]);
        }

        $types = $mode === ExpensePaymentMode::PaidByEmployee ? [PartyType::Employee] : [PartyType::Supplier, PartyType::Other, PartyType::Employee];
        $party = $partyId === null ? null : EntryChecks::party($partyId, $types, 'party_id', $mode === ExpensePaymentMode::PaidByEmployee
            ? __('Select an active employee.') // BR-EXP-06
            : __('Select an active supplier, other party or employee.')); // BR-EXP-05

        if ($mode === ExpensePaymentMode::Paid) {
            return [EntryChecks::moneyAccount(self::id($data, 'money_account_id')), $party]; // BR-EXP-04
        }

        if (self::id($data, 'money_account_id') !== null) {
            throw ValidationException::withMessages(['money_account_id' => __('No account is used for this payment mode.')]);
        }

        return [null, $party];
    }

    /**
     * BR-EXP-08: at most one active purchase or sale (for transport).
     *
     * @param  array<string, mixed>  $data
     * @return array{int|null, int|null}
     */
    private static function link(array $data): array
    {
        $purchaseId = self::id($data, 'linked_purchase_id');
        $saleId = self::id($data, 'linked_sale_id');

        if ($purchaseId !== null && $saleId !== null) {
            throw ValidationException::withMessages(['linked_sale_id' => __('Link the expense to one purchase or one sale, not both.')]);
        }

        if ($purchaseId !== null && ! Purchase::query()->whereKey($purchaseId)->where('status', EntryStatus::Active->value)->exists()) {
            throw ValidationException::withMessages(['linked_purchase_id' => __('Select an active purchase.')]);
        }

        if ($saleId !== null && ! Sale::query()->whereKey($saleId)->where('status', EntryStatus::Active->value)->exists()) {
            throw ValidationException::withMessages(['linked_sale_id' => __('Select an active sale.')]);
        }

        return [$purchaseId, $saleId];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function id(array $data, string $key): ?int
    {
        return isset($data[$key]) && is_numeric($data[$key]) ? (int) $data[$key] : null;
    }
}
