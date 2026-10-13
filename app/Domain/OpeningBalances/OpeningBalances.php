<?php

declare(strict_types=1);

namespace App\Domain\OpeningBalances;

use App\Domain\Ledger\LedgerBalance;
use App\Domain\Periods\Models\Period;
use App\Domain\Periods\Month;
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
     * BR-MEC-09: the first closed month, by which openings are locked ("January 2026"),
     * or null while no month is closed.
     */
    public static function lockedBy(): ?string
    {
        $period = Period::query()->where('status', Period::CLOSED)->orderBy('year')->orderBy('month')->first();

        return $period === null ? null : Month::of(sprintf('%04d-%02d', $period->year, $period->month))->name();
    }

    /**
     * BR-OPB-03, BR-MEC-09: openings can change until the Owner marks them complete,
     * and only while no month is closed.
     *
     * @throws ValidationException
     */
    public static function assertEditable(): void
    {
        $month = self::lockedBy();

        if ($month !== null) {
            throw ValidationException::withMessages(['amount' => __('Opening balances are locked because :month is closed.', ['month' => $month])]);
        }

        if (self::completed()) {
            throw ValidationException::withMessages(['amount' => __('Opening balances are marked complete and cannot change.')]);
        }
    }
}
