<?php

use App\Domain\Items\Models\Item;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Settings\Settings;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => freezeToday('2026-01-31'));

it('BR-AUD-03 accountant gets 403', function () {
    $this->actingAs(accountant())->get(route('audit-log.index'))->assertForbidden();
});

it('BR-AUD-03 filter by action returns only that action', function () {
    $owner = owner();
    $world = acceptanceWorld(acceptance('at1'), $owner);
    Settings::set('advance_age_days', '45', $owner);
    $purchase = app(CreatePurchase::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'party_id' => $world['parties']['aslam']->id,
        'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => '800', 'rate' => '50',
    ]);

    $this->actingAs($owner)->get(route('audit-log.index', ['action' => 'setting_changed']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audit-log/index')
            ->where('logs.total', 1)
            ->where('logs.data.0.action', 'Setting changed')
            ->where('logs.data.0.changes', [['field' => 'advance age days', 'old' => '30', 'new' => '45']]));

    $this->actingAs($owner)->get(route('audit-log.index', ['action' => 'created', 'type' => 'purchase']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('logs.total', 1)
            ->where('logs.data.0.record', ['type' => 'purchase', 'label' => 'PUR-000001', 'url' => "/purchases/{$purchase->id}"]));
});

it('BR-AUD-03 search finds the rows of a voucher and of a party', function () {
    $owner = owner();
    $world = acceptanceWorld(acceptance('at1'), $owner);
    $purchase = app(CreatePurchase::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'party_id' => $world['parties']['aslam']->id,
        'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => '800', 'rate' => '50',
    ]);

    $this->actingAs($owner)->get(route('audit-log.index', ['q' => $purchase->voucher_no]))
        ->assertInertia(fn (Assert $page) => $page->where('logs.total', 1));

    // Aslam's own "created" row and his purchase's row.
    $this->actingAs($owner)->get(route('audit-log.index', ['q' => 'Aslam']))
        ->assertInertia(fn (Assert $page) => $page->where('logs.total', 2));
});
