<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Items\Models\Item;
use App\Domain\MoneyAccounts\Actions\CreateMoneyAccount;
use App\Domain\OpeningBalances\Actions\SetOpeningBalance;
use App\Domain\Parties\Actions\CreateParty;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Settings\Settings;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => freezeToday('2026-01-31'));

it('page 42 shows every setting by group with its OPEN item and the setup checklist', function () {
    $this->actingAs(owner())->get(route('company-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('company-settings/index')
            ->where('groups.0.title', 'Company')
            ->where('groups.1.fields.0.key', 'payment_approval_limit')
            ->where('groups.1.fields.0.open', 'O-01')
            ->where('groups.1.fields.0.value', '50000.00')
            ->where('groups.5.fields.0.key', 'owner_two_factor_required')
            ->where('checklist.missing', [])
            ->where('checklist.opening_balances_completed', false)
            ->where('checklist.has_cash_account', false)
            ->where('groups', fn ($groups) => ! collect($groups)->flatMap(fn ($g) => $g['fields'])->contains('key', 'opening_balances_completed')));
});

it('BR-GEN-07 accountant gets 403', function () {
    $accountant = accountant();

    $this->actingAs($accountant)->get(route('company-settings.edit'))->assertForbidden();
    $this->actingAs($accountant)->put(route('company-settings.update'), ['values' => ['payment_approval_limit' => '1']])->assertForbidden();

    expect(Settings::get('payment_approval_limit'))->toBe('50000.00');
});

it('system start date is locked after the first journal', function () {
    $owner = owner();

    $this->actingAs($owner)->put(route('company-settings.update'), ['values' => ['system_start_date' => '2026-01-02']])
        ->assertSessionHasNoErrors();
    expect(Settings::get('system_start_date'))->toBe('2026-01-02');

    $cash = app(CreateMoneyAccount::class)->handle($owner, ['name' => 'Cash', 'type' => 'cash']);
    app(SetOpeningBalance::class)->handle($owner, ['money_account_id' => $cash->id, 'amount' => '1000']);

    $this->actingAs($owner)->put(route('company-settings.update'), ['values' => ['system_start_date' => '2026-01-01']])
        ->assertSessionHasErrors(['system_start_date' => 'Locked after the first journal.']);
    expect(Settings::get('system_start_date'))->toBe('2026-01-02');

    // The same value is not a change, so the rest of the form still saves.
    $this->actingAs($owner)->put(route('company-settings.update'), ['values' => ['system_start_date' => '2026-01-02', 'advance_age_days' => '45']])
        ->assertSessionHasNoErrors();
    expect(Settings::get('advance_age_days'))->toBe(45);
});

it('rounding settings are locked after the first purchase', function () {
    $owner = owner();
    $this->actingAs($owner)->put(route('company-settings.update'), ['values' => ['kg_decimals' => '2']])->assertSessionHasNoErrors();

    $aslam = app(CreateParty::class)->handle($owner, ['type' => 'supplier', 'name' => 'Aslam', 'mobile' => '03000000001']);
    app(CreatePurchase::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'party_id' => $aslam->id,
        'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => '800', 'rate' => null,
    ]);

    foreach (['kg_decimals' => '3', 'rate_decimals' => '0', 'amount_rounding' => 'whole_rupee'] as $key => $value) {
        $this->actingAs($owner)->put(route('company-settings.update'), ['values' => [$key => $value, 'company_name' => 'Changed name']])
            ->assertSessionHasErrors([$key => 'Locked after the first purchase or sale.']);
    }

    expect(Settings::get('kg_decimals'))->toBe(2)
        ->and(Settings::get('amount_rounding'))->toBe('two_decimals')
        ->and(Settings::get('company_name'))->toBe('ZB Poultry Protein Mill');

    $this->actingAs($owner)->get(route('company-settings.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('groups.2.fields.0.locked', 'Locked after the first purchase or sale.'));
});

it('every change is audited', function () {
    $owner = owner();

    $this->actingAs($owner)->put(route('company-settings.update'), ['values' => [
        'payment_approval_limit' => '75000', 'rate_pending_age_days' => '10', 'company_name' => 'ZB Poultry Protein Mill',
    ]])->assertRedirect(route('company-settings.edit'));

    $rows = AuditLog::query()->where('action', 'setting_changed')->where('user_id', $owner->id)->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->old_values)->toBe(['key' => 'payment_approval_limit', 'value' => '50000.00'])
        ->and($rows[0]->new_values)->toBe(['key' => 'payment_approval_limit', 'value' => '75000.00'])
        ->and($rows[1]->new_values)->toBe(['key' => 'rate_pending_age_days', 'value' => '10']);
});

it('a wrong value is refused with the setting name', function () {
    $this->actingAs(owner())->put(route('company-settings.update'), ['values' => ['kg_decimals' => '5']])
        ->assertSessionHasErrors('kg_decimals');

    expect(Settings::get('kg_decimals'))->toBe(3);
});
