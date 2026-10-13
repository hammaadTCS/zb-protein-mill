<?php

namespace Database\Seeders;

use App\Domain\Companies\Models\Company;
use App\Domain\Settings\Models\Setting;
use App\Domain\Settings\SettingKeys;
use Illuminate\Database\Seeder;

/**
 * BR-OPS-11, BR-GEN-07: creates every missing settings key of spec §9.6 for every
 * company. OPEN values start as NULL, so no production default exists for them.
 * A key that already exists is never changed.
 */
class SettingsDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Company::query()->orderBy('id')->get() as $company) {
            foreach (SettingKeys::definitions() as $key => $definition) {
                $default = $key === SettingKeys::COMPANY_NAME
                    ? $company->name
                    : (SettingKeys::DEFAULTS[$key] ?? null);

                Setting::query()->firstOrCreate(
                    ['company_id' => $company->id, 'key' => $key],
                    ['value' => $default, 'value_type' => $definition['type']],
                );
            }
        }
    }
}
