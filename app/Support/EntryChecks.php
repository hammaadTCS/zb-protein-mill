<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Items\Models\Item;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;

/**
 * Field rules shared by the entry Actions (purchases, sales, payments, expenses).
 * Each failure is a ValidationException on the form field (conventions §2).
 */
final class EntryChecks
{
    /**
     * BR-GEN-08, BR-PTY-04: an active party of one of the allowed types.
     *
     * @param  list<PartyType>  $types
     */
    public static function party(?int $partyId, array $types, string $field, string $message): Party
    {
        $party = $partyId === null ? null : Party::query()->active()->find($partyId);

        if ($party === null || ! in_array($party->type, $types, true)) {
            throw ValidationException::withMessages([$field => $message]);
        }

        return $party;
    }

    /** BR-PUR-01 / BR-SAL-01: an active item that is bought, or one that is sold. */
    public static function item(?int $itemId, bool $bought, string $field, string $message): Item
    {
        $item = $itemId === null ? null : Item::query()->active()->find($itemId);

        if ($item === null || $item->type->isBought() !== $bought) {
            throw ValidationException::withMessages([$field => $message]);
        }

        return $item;
    }

    /** BR-PUR-02: kg above 0 with at most `kg_decimals` decimals. Returns kg with 3 decimals. */
    public static function kg(string $kg, string $field = 'kg'): string
    {
        return self::positive($kg, $field, 'Kg', self::setting(SettingKeys::KG_DECIMALS, 3), 3);
    }

    /** BR-PUR-03: rate above 0 with at most `rate_decimals` decimals. Returns rate with 4 decimals. */
    public static function rate(string $rate, string $field = 'rate'): string
    {
        return self::positive($rate, $field, 'Rate', self::setting(SettingKeys::RATE_DECIMALS, 2), 4);
    }

    /** A money amount above 0 (BR-PAY-02, BR-PUR-10). Returns 2 decimals. */
    public static function amount(string $amount, string $field = 'amount'): string
    {
        return self::positive($amount, $field, 'Amount', 2, 2);
    }

    /** BR-PAY-02, BR-GEN-08: an active money account. */
    public static function moneyAccount(?int $accountId, string $field = 'money_account_id'): MoneyAccount
    {
        return ($accountId === null ? null : MoneyAccount::query()->active()->find($accountId))
            ?? throw ValidationException::withMessages([$field => __('Select an active account.')]);
    }

    /** BR-PAY-12, BR-EXP-07: "Handled by" is optional and must be an active employee. */
    public static function handledBy(?int $partyId, string $field = 'handled_by_party_id'): ?Party
    {
        return $partyId === null ? null : self::party($partyId, [PartyType::Employee], $field, __('Handled by must be an active employee.'));
    }

    /**
     * @param  int<0, max>  $decimals
     * @param  int<0, max>  $scale
     */
    private static function positive(string $value, string $field, string $label, int $decimals, int $scale): string
    {
        $number = preg_match('/^\d+(\.\d+)?$/', trim($value)) === 1 ? BigDecimal::of(trim($value)) : null;

        if ($number === null || ! $number->isPositive()) {
            throw ValidationException::withMessages([$field => __(':label must be greater than 0.', ['label' => $label])]);
        }

        if ($number->strippedOfTrailingZeros()->getScale() > $decimals) {
            throw ValidationException::withMessages([$field => __(':label can have at most :count decimals.', ['label' => $label, 'count' => $decimals])]);
        }

        return (string) $number->toScale($scale);
    }

    /**
     * @return int<0, max>
     */
    private static function setting(string $key, int $default): int
    {
        $value = Settings::get($key);

        return max(0, is_int($value) ? $value : $default);
    }
}
