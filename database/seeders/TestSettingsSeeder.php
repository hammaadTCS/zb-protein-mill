<?php

namespace Database\Seeders;

use App\Domain\Settings\Models\Setting;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * TEST values only (docs/conventions.md §11), never the client's. Used by tests and
 * by DatabaseSeeder on local computers. Refuses to run in production.
 */
class TestSettingsSeeder extends Seeder
{
    public const VALUES = [
        SettingKeys::COMPANY_NAME => 'ZB Poultry Protein Mill',
        SettingKeys::SYSTEM_START_DATE => '2026-01-01',
        SettingKeys::PAYMENT_APPROVAL_LIMIT => '50000.00',
        SettingKeys::NEW_PARTY_FIRST_PAYMENT_LIMIT => '100000.00',
        SettingKeys::EXPENSE_PARTY_REQUIRED_LIMIT => '100000.00',
        SettingKeys::UNUSUAL_RATE_PERCENT => '30',
        SettingKeys::UNUSUAL_RATE_LOOKBACK => '5',
        SettingKeys::RATE_PENDING_AGE_DAYS => '7',
        SettingKeys::ADVANCE_AGE_DAYS => '30',
        SettingKeys::KG_DECIMALS => '3',
        SettingKeys::RATE_DECIMALS => '2',
        SettingKeys::AMOUNT_ROUNDING => 'two_decimals',
        SettingKeys::NUMBER_FORMAT => 'lakh',
        SettingKeys::DATE_FORMAT => 'd-m-Y',
        SettingKeys::PLANT_HAS_SCALE => 'true',
        SettingKeys::SLIP_NO_REQUIRED => 'false',
        SettingKeys::SLIP_PHOTO_REQUIRED => 'false',
        SettingKeys::INSIGHTS_PERIOD_MONTHS => '3',
        SettingKeys::OPENING_BALANCES_COMPLETED => 'false',
        SettingKeys::OWNER_TWO_FACTOR_REQUIRED => 'false',
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('TestSettingsSeeder is for local use. Servers use SystemDataSeeder.');
        }

        $companyId = CurrentCompany::id();

        foreach (self::VALUES as $key => $value) {
            Setting::query()->updateOrCreate(
                ['company_id' => $companyId, 'key' => $key],
                ['value' => Settings::normalize($key, $value), 'value_type' => SettingKeys::definition($key)['type']],
            );
        }
    }
}
