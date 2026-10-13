<?php

declare(strict_types=1);

namespace App\Domain\OpeningBalances;

use App\Domain\Ledger\LedgerBalance;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Support\Decimal;
use Illuminate\Validation\ValidationException;

/** Read helpers for page 47 and the opening-balance Actions (BR-OPB-03, BR-OPB-04). */
final class OpeningBalances
{
    /**
     * BR-OPB-04: what is left in 3900 Opening Balance Equity, as a credit balance
     * (credit minus debit; negative when the debits are larger).
     */
    public static function equity(): string
    {
        return Decimal::negate(LedgerBalance::byCode('3900'));
    }

    public static function completed(): bool
    {
        return Settings::get(SettingKeys::OPENING_BALANCES_COMPLETED) === true;
    }

    /**
     * BR-OPB-03: openings can change until the Owner marks them complete (and, from
     * T7.3, until the first month is closed).
     *
     * @throws ValidationException
     */
    public static function assertEditable(): void
    {
        if (self::completed()) {
            throw ValidationException::withMessages(['amount' => __('Opening balances are marked complete and cannot change.')]);
        }
    }
}
