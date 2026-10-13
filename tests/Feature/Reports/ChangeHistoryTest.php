<?php

use App\Domain\Items\Models\Item;
use App\Domain\Parties\Actions\UpdateParty;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Purchases\Actions\UpdatePurchase;
use App\Domain\Purchases\Models\Purchase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => freezeToday('2026-01-31'));

function historyPurchase(): array
{
    $owner = owner();
    $world = acceptanceWorld(acceptance('at1'), $owner);
    $purchase = app(CreatePurchase::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'party_id' => $world['parties']['aslam']->id,
        'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => '800', 'rate' => '50',
    ]);

    return [$owner, $world, $purchase];
}

it('accountant does not receive journal lines in the page props', function () {
    [$owner, , $purchase] = historyPurchase();

    $this->actingAs(accountant())->get(route('purchases.show', $purchase))
        ->assertInertia(fn (Assert $page) => $page->component('purchases/show')->missing('journal')->has('history'));

    $this->actingAs($owner)->get(route('purchases.show', $purchase))
        ->assertInertia(fn (Assert $page) => $page
            ->has('journal', 2)
            ->where('journal.0.entry_no', 'JV-000005') // the four AT-1 opening journals come first
            ->where('journal.0.code', '5000')
            ->where('journal.0.debit', '40000.00')
            ->where('journal.1.party', 'Aslam'));
});

it('history shows old and new values after an edit', function () {
    [$owner, , $purchase] = historyPurchase();
    $this->travel(5)->minutes();

    app(UpdatePurchase::class)->handle($owner, $purchase, [
        'version' => Purchase::query()->find($purchase->id)?->updated_at?->toIso8601String(),
        'date' => '2026-01-05', 'party_id' => $purchase->party_id, 'item_id' => $purchase->item_id, 'kg' => '820', 'rate' => '52',
    ]);

    $this->actingAs(accountant())->get(route('purchases.show', $purchase))
        ->assertInertia(fn (Assert $page) => $page
            ->where('history.0.action', 'Edited')
            ->where('history.0.changes', [
                ['field' => 'kg', 'old' => '800.000', 'new' => '820.000'],
                ['field' => 'rate', 'old' => '50.0000', 'new' => '52.0000'],
                ['field' => 'amount', 'old' => '40000.00', 'new' => '42640.00'],
            ])
            ->where('history.1.action', 'Rate changed')
            ->where('history.1.changes.0', ['field' => 'rate', 'old' => '50.0000', 'new' => '52.0000'])
            ->where('history.2.action', 'Created'));
});

it('party change history shows bank detail changes with old and new values', function () {
    $owner = owner();
    $world = acceptanceWorld(acceptance('at1'), $owner);
    $aslam = $world['parties']['aslam'];
    $this->travel(5)->minutes();
    app(UpdateParty::class)->handle(accountant(), $aslam, ['type' => 'supplier', 'name' => 'Aslam', 'mobile' => $aslam->mobile, 'area' => 'Sadar', 'bank_account_number' => '123']);

    $this->actingAs($owner)->get(route('parties.show', $aslam))
        ->assertInertia(fn (Assert $page) => $page
            ->where('history.0.action', 'Bank details changed')
            ->where('history.0.changes', [['field' => 'bank account number', 'old' => null, 'new' => '123']]));
});
