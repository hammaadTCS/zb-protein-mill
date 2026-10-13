<?php

use App\Domain\Items\Models\Item;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Purchases\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => freezeToday('2026-01-31'));

/**
 * @return array{User, array<string, mixed>}
 */
function pagesWorld(): array
{
    $owner = owner();
    $world = acceptanceWorld([
        'money_accounts' => [['key' => 'cash', 'name' => 'Cash', 'type' => 'cash']],
        'parties' => [
            ['key' => 'aslam', 'type' => 'supplier', 'name' => 'Aslam', 'mobile' => '03000000001'],
            ['key' => 'karim', 'type' => 'supplier', 'name' => 'Karim', 'mobile' => '03000000002'],
        ],
    ], $owner);

    return [$owner, $world];
}

function quickPurchase(User $by, int $partyId, string $date, string $kg, string $rate): Purchase
{
    return app(CreatePurchase::class)->handle($by, [
        'idempotency_key' => (string) Str::uuid(), 'date' => $date, 'party_id' => $partyId,
        'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => $kg, 'rate' => $rate,
    ]);
}

it('list shows totals for active entries only', function () {
    [$owner, $world] = pagesWorld();
    quickPurchase($owner, $world['parties']['aslam']->id, '2026-01-05', '800', '50');
    quickPurchase($owner, $world['parties']['karim']->id, '2026-01-06', '600', '48');
    $cancelled = quickPurchase($owner, $world['parties']['karim']->id, '2026-01-07', '100', '40');
    $cancelled->update(['status' => 'cancelled']); // the cancel Action comes with T3.2

    $this->actingAs($owner)->get(route('purchases.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchases/index')
            ->where('filters.from', '2026-01-01')
            ->where('filters.to', '2026-01-31')
            ->where('purchases.total', 3)
            ->where('purchases.data.0.voucher_no', 'PUR-000003') // newest first
            ->where('totals', ['kg' => '1400.000', 'amount' => '68800.00']));
});

it('spec §11.2 the list filters by party, status and search text', function () {
    [$owner, $world] = pagesWorld();
    quickPurchase($owner, $world['parties']['aslam']->id, '2026-01-05', '800', '50');
    quickPurchase($owner, $world['parties']['karim']->id, '2026-01-06', '600', '48');

    $this->actingAs($owner)->get(route('purchases.index', ['party_id' => $world['parties']['karim']->id]))
        ->assertInertia(fn (Assert $page) => $page->where('purchases.total', 1)->where('party.label', 'Karim – 03000000002'));

    $this->actingAs($owner)->get(route('purchases.index', ['q' => 'aslam']))
        ->assertInertia(fn (Assert $page) => $page->where('purchases.total', 1)->where('purchases.data.0.party', 'Aslam'));

    $this->actingAs($owner)->get(route('purchases.index', ['q' => 'PUR-000002']))
        ->assertInertia(fn (Assert $page) => $page->where('purchases.total', 1)->where('purchases.data.0.party', 'Karim'));

    $this->actingAs($owner)->get(route('purchases.index', ['from' => '2026-01-06', 'to' => '2026-01-06', 'status' => 'active']))
        ->assertInertia(fn (Assert $page) => $page->where('purchases.total', 1));
});

it('page 4 opens with today and the bought items; page 5 shows the purchase', function () {
    [$owner, $world] = pagesWorld();

    $this->actingAs($owner)->get(route('purchases.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchases/create')
            ->where('today', '2026-01-31')
            ->where('items.0.name', 'Waste')
            ->where('items.1.name', 'Wood')
            ->has('items', 2)
            ->where('money_accounts.0.name', 'Cash')
            ->where('plant_has_scale', true));

    $purchase = quickPurchase($owner, $world['parties']['aslam']->id, '2026-01-05', '800', '50');

    $this->actingAs(accountant())->get(route('purchases.show', $purchase))
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchases/show')
            ->where('purchase.voucher_no', 'PUR-000001')
            ->where('purchase.amount', '40000.00')
            ->where('purchase.party.name', 'Aslam'));
});

it('"Save and add another" returns to page 4; Save opens the detail page', function () {
    [$owner, $world] = pagesWorld();
    // Different kg each time, so the second save is not a duplicate entry (BR-PUR-08, T3.3).
    $form = fn (string $kg) => [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'party_id' => $world['parties']['aslam']->id,
        'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => $kg, 'rate' => '50',
    ];

    $this->actingAs($owner)->post(route('purchases.store'), [...$form('10'), 'add_another' => true])
        ->assertRedirect(route('purchases.create'));
    $this->actingAs($owner)->post(route('purchases.store'), $form('11'))
        ->assertRedirect(route('purchases.show', Purchase::query()->where('voucher_no', 'PUR-000002')->sole()));
});

it('purchase pages need purchase.view and purchase.create', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('purchases.index'))->assertForbidden();
    $this->actingAs($user)->get(route('purchases.create'))->assertForbidden();
});
