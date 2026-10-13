<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Ledger\Journals;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Sales\Models\Sale;

/**
 * Page 42 (spec §9.6, card T5.1): the settings the Owner fills, by group, each with
 * the OPEN item it answers, and the ones that may no longer change.
 */
final class CompanySettings
{
    /** Group => key => OPEN item id (null when the value is not an OPEN item). */
    public const GROUPS = [
        'Company' => [
            SettingKeys::COMPANY_NAME => null,
            SettingKeys::SYSTEM_START_DATE => 'O-07',
        ],
        'Limits' => [
            SettingKeys::PAYMENT_APPROVAL_LIMIT => 'O-01',
            SettingKeys::NEW_PARTY_FIRST_PAYMENT_LIMIT => 'O-02',
            SettingKeys::EXPENSE_PARTY_REQUIRED_LIMIT => 'O-03',
        ],
        'Formats' => [
            SettingKeys::KG_DECIMALS => 'O-14',
            SettingKeys::RATE_DECIMALS => 'O-15',
            SettingKeys::AMOUNT_ROUNDING => 'O-16',
            SettingKeys::NUMBER_FORMAT => 'O-17',
            SettingKeys::DATE_FORMAT => 'O-18',
        ],
        'Weight' => [
            SettingKeys::PLANT_HAS_SCALE => 'O-25',
            SettingKeys::SLIP_NO_REQUIRED => 'O-26',
            SettingKeys::SLIP_PHOTO_REQUIRED => 'O-26',
        ],
        'Alerts' => [
            SettingKeys::UNUSUAL_RATE_PERCENT => 'O-04',
            SettingKeys::UNUSUAL_RATE_LOOKBACK => 'O-04',
            SettingKeys::RATE_PENDING_AGE_DAYS => 'O-05',
            SettingKeys::ADVANCE_AGE_DAYS => 'O-06',
            SettingKeys::INSIGHTS_PERIOD_MONTHS => null,
        ],
        'Security' => [
            SettingKeys::OWNER_TWO_FACTOR_REQUIRED => 'O-31',
        ],
    ];

    /**
     * Every key on page 42 (all of spec §9.6 except `opening_balances_completed`).
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_merge(...array_map(array_keys(...), array_values(self::GROUPS)));
    }

    /**
     * Keys that may no longer change, with the reason shown under the field.
     *
     * @return array<string, string>
     */
    public static function locked(): array
    {
        $locked = [];

        if (Journals::any()) {
            $locked[SettingKeys::SYSTEM_START_DATE] = __('Locked after the first journal.');
        }

        // Changing these would change how saved figures display and round.
        if (Purchase::query()->exists() || Sale::query()->exists()) {
            foreach ([SettingKeys::KG_DECIMALS, SettingKeys::RATE_DECIMALS, SettingKeys::AMOUNT_ROUNDING] as $key) {
                $locked[$key] = __('Locked after the first purchase or sale.');
            }
        }

        return $locked;
    }
}
