<?php

use App\Domain\Items\Models\Item;
use App\Domain\Parties\Actions\CreateParty;
use App\Domain\Payments\Actions\CreatePayment;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Sales\Actions\CreateSale;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AcceptanceRunner;

it('today figures after AT-1 day 1 with today frozen at 2026-01-05', function () {
    $runner = AcceptanceRunner::load('at1')->setUp();
    freezeToday('2026-01-05');

    foreach ($runner->data['entries'] as $entry) {
        if (($entry['date'] ?? null) === '2026-01-05') {
            $runner->run($entry);
        }
    }

    $this->actingAs($runner->owner)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('today', '2026-01-05')
            ->where('figures.kg_bought', [['item' => 'Waste', 'kg' => '1800.000'], ['item' => 'Wood', 'kg' => '0.000']])
            ->where('figures.kg_sold', [['item' => 'Protein product', 'kg' => '500.000'], ['item' => 'Oil/Fat', 'kg' => '0.000']])
            ->where('figures.received', '41000.00')
            ->where('figures.paid', '70000.00')
            ->where('figures.expenses', '11100.00')
            ->where('balances.0.name', 'Cash')
            ->where('balances.1.name', 'HBL'));
});

it('the accountant sees no owner-only alerts', function () {
    freezeToday('2026-01-31');
    $owner = owner();
    $accountant = accountant();
    $world = acceptanceWorld(acceptance('at1'), $owner);
    // A party the accountant adds (needs review) and a payment above the approval limit (waits).
    $party = app(CreateParty::class)->handle($accountant, ['type' => 'supplier', 'name' => 'New Supplier', 'mobile' => '03000000099']);
    app(CreatePayment::class)->handle($accountant, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-06', 'direction' => 'paid', 'party_id' => $world['parties']['aslam']->id,
        'amount' => '60000', 'money_account_id' => $world['accounts']['hbl']->id, 'reference_no' => 'TEST-REF-1',
    ]);

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('alerts', fn ($alerts) => collect($alerts)->pluck('key')->sort()->values()->all() === ['new_parties_to_review', 'payments_awaiting_approval']));

    $this->actingAs($accountant)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('alerts', []));

    expect($party->needs_review)->toBeTrue();
});

it('BR-CHK-05 aged rate-pending entries and buyers over their limit raise alerts for both users', function () {
    freezeToday('2026-01-31');
    $owner = owner();
    $data = acceptance('at1');
    $data['parties'][3]['credit_limit'] = '1000'; // ABC Feeds
    $world = acceptanceWorld($data, $owner);

    app(CreatePurchase::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'party_id' => $world['parties']['karim']->id,
        'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => '400', 'rate' => null,
    ]);
    app(CreateSale::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'party_id' => $world['parties']['abc']->id,
        'item_id' => Item::query()->where('code', 'PROTEIN')->value('id'), 'kg' => '10', 'rate' => '200',
    ], ['credit_limit']);

    foreach ([$owner, accountant()] as $user) {
        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('alerts.0.key', 'rate_pending_aged')
                ->where('alerts.0.count', 1)
                ->where('alerts.0.severity', 'danger')
                ->where('alerts', fn ($alerts) => collect($alerts)->firstWhere('key', 'buyers_over_credit_limit')['count'] === 1));
    }
});
