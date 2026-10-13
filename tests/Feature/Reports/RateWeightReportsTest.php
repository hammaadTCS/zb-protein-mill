<?php

use App\Domain\Items\Models\Item;
use App\Domain\Parties\Actions\CreateParty;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Reports\Queries\RateWeightReports;
use App\Domain\Sales\Actions\CreateSale;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AcceptanceRunner;

beforeEach(fn () => freezeToday('2026-01-31'));

/**
 * @param  array<string, mixed>  $extra
 */
function t13Purchase(User $owner, int $partyId, string $kg, string $rate, array $extra = []): void
{
    app(CreatePurchase::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'party_id' => $partyId,
        'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => $kg, 'rate' => $rate, ...$extra,
    ], ['duplicate_entry', 'unusual_rate']);
}

it('T-13 R-10 Aslam waste 800 @ 50 and 1000 @ 52 average 51.11', function () {
    $owner = owner();
    $aslam = app(CreateParty::class)->handle($owner, ['type' => 'supplier', 'name' => 'Aslam', 'mobile' => '03000000001']);
    t13Purchase($owner, $aslam->id, '800', '50');
    t13Purchase($owner, $aslam->id, '1000', '52');

    $groups = RateWeightReports::rates(['type' => 'purchase', 'from' => '2026-01-01', 'to' => '2026-01-31']);

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['average'])->toBe('51.11')
        ->and(array_column($groups[0]['entries'], 'rate'))->toBe(['50.0000', '52.0000'])
        ->and($groups[0]['kg'])->toBe('1800.000');

    $this->actingAs($owner)->get(route('parties.show', $aslam))
        ->assertInertia(fn (Assert $page) => $page->where('rate_history.0.average', '51.11'));
});

it('T-13 R-11 weight differences for purchases and sales', function () {
    $owner = owner();
    $supplier = app(CreateParty::class)->handle($owner, ['type' => 'supplier', 'name' => 'Aslam', 'mobile' => '03000000001']);
    $buyer = app(CreateParty::class)->handle($owner, ['type' => 'buyer', 'name' => 'ABC Feeds', 'mobile' => '03000000004']);
    t13Purchase($owner, $supplier->id, '1000', '50', ['our_weight_kg' => '960']);
    t13Purchase($owner, $supplier->id, '500', '50', ['our_weight_kg' => '500']);
    t13Purchase($owner, $supplier->id, '700', '50'); // no our weight: not counted
    app(CreateSale::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'party_id' => $buyer->id,
        'item_id' => Item::query()->where('code', 'PROTEIN')->value('id'), 'kg' => '295', 'rate' => '210', 'our_weight_kg' => '300',
    ]);

    $purchases = RateWeightReports::weights(['type' => 'purchase', 'from' => '2026-01-01', 'to' => '2026-01-31']);
    $sales = RateWeightReports::weights(['type' => 'sale', 'from' => '2026-01-01', 'to' => '2026-01-31']);

    expect([$purchases[0]['difference'], $purchases[0]['percent']])->toBe(['40.000', '2.74'])
        ->and($purchases[0]['entries'])->toHaveCount(2)
        ->and([$sales[0]['difference'], $sales[0]['percent']])->toBe(['5.000', '1.67']);
});

it('T-13 R-12 AT-2 ABC quality adjustment is 3.17% of 63000.00', function () {
    $runner = AcceptanceRunner::load('at2')->setUp()->runAll();

    $rows = RateWeightReports::adjustments(['type' => 'sale', 'from' => '2026-01-01', 'to' => '2026-01-31']);

    expect($rows)->toBe([[
        'party' => 'ABC Feeds', 'party_id' => $runner->parties['abc']->id, 'reason' => 'quality',
        'total' => '2000.00', 'count' => 1, 'base' => '63000.00', 'percent' => '3.17',
    ]]);

    foreach (['reports.rates', 'reports.weight-difference', 'reports.adjustments'] as $route) {
        $this->actingAs($runner->accountant)->get(route($route, ['type' => 'sale', 'from' => '2026-01-01', 'to' => '2026-01-31']))->assertOk();
    }
});
