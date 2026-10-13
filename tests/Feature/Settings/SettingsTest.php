<?php

use App\Domain\Companies\Models\Company;
use App\Domain\Settings\Models\Setting;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Models\User;
use Database\Seeders\SettingsDefinitionSeeder;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Validation\ValidationException;

it('T-10 assertComplete fails while any required setting is empty', function (string $key) {
    Setting::query()->where('key', $key)->update(['value' => null]);

    expect(fn () => Settings::assertComplete())->toThrow(
        ValidationException::class,
        'Setup is not complete. Fill in all required settings before entering transactions.',
    );
    expect(Settings::missingRequired())->toBe([$key]);
})->with(SettingKeys::required());

it('T-10 assertComplete passes with all test values', function () {
    Settings::assertComplete();

    expect(Settings::missingRequired())->toBe([]);
});

it('settings reject a value of the wrong type', function (string $key, mixed $value) {
    expect(fn () => Settings::set($key, $value, User::factory()->create()))
        ->toThrow(ValidationException::class);
})->with([
    'kg_decimals above 3' => ['kg_decimals', 5],
    'rate_decimals above 2' => ['rate_decimals', 3],
    'unknown rounding' => ['amount_rounding', 'abc'],
    'unknown number format' => ['number_format', 'roman'],
    'text for a decimal' => ['payment_approval_limit', 'fifty'],
    'negative decimal' => ['payment_approval_limit', '-1'],
    'three decimals' => ['payment_approval_limit', '1.005'],
    'not a date' => ['system_start_date', '2026-02-30'],
    'text for a boolean' => ['plant_has_scale', 'maybe'],
    'text for an integer' => ['unusual_rate_lookback', 'five'],
    'unknown key' => ['no_such_key', 'x'],
]);

it('settings return values typed by value_type', function () {
    expect(Settings::get('kg_decimals'))->toBe(3)
        ->and(Settings::get('plant_has_scale'))->toBeTrue()
        ->and(Settings::get('slip_no_required'))->toBeFalse()
        ->and(Settings::get('payment_approval_limit'))->toBe('50000.00')
        ->and(Settings::get('system_start_date'))->toBe('2026-01-01')
        ->and(Settings::get('amount_rounding'))->toBe('two_decimals')
        ->and(Settings::get('insights_period_months'))->toBe(3);
});

it('settings store a valid value', function () {
    Settings::set('payment_approval_limit', '75000', User::factory()->create());

    expect(Settings::get('payment_approval_limit'))->toBe('75000.00');
});

it('BR-OPS-11 SettingsDefinitionSeeder never overwrites a saved value', function () {
    Settings::set('payment_approval_limit', '12345.67', User::factory()->create());

    $this->seed(SystemDataSeeder::class);

    expect(Settings::get('payment_approval_limit'))->toBe('12345.67');
});

it('no production default exists for any OPEN value', function () {
    $company = Company::query()->create(['name' => 'Second Mill']);

    $this->seed(SettingsDefinitionSeeder::class);

    $values = Setting::query()->where('company_id', $company->id)->pluck('value', 'key')->all();

    expect(array_keys($values))->toEqualCanonicalizing(SettingKeys::all());
    foreach (SettingKeys::all() as $key) {
        $expected = match ($key) {
            'company_name' => 'Second Mill',
            'insights_period_months' => '3',
            'opening_balances_completed' => '0',
            default => null,
        };
        expect($values[$key])->toBe($expected, "setting {$key}");
    }
});
