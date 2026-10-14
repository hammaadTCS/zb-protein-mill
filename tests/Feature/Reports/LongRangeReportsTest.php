<?php

use App\Domain\Items\Models\Item;
use App\Domain\Parties\Models\Party;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Purchases\Actions\CreateRateCorrection;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Reports\Queries\RateWeightReports;
use App\Models\User;
use App\Support\Dates;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/** One waste purchase on $date for $party, with our weight when given. */
function longRangePurchase(User $owner, Party $party, string $date, string $kg, string $rate, ?string $ours = null): Purchase
{
    return app(CreatePurchase::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => $date, 'party_id' => $party->id,
        'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => $kg, 'rate' => $rate, 'our_weight_kg' => $ours,
    ], ['duplicate_entry', 'unusual_rate']);
}

/**
 * January to April 2026: Aslam buys in January (rate later corrected from 50 to 55 after
 * January closed), February (two entries) and April; Karim in March.
 *
 * @return array{User, array<string, Party>}
 */
function longRangeWorld(): array
{
    freezeToday('2026-01-31');
    $owner = owner();
    $parties = acceptanceWorld(acceptance('at1'), $owner)['parties'];
    $january = longRangePurchase($owner, $parties['aslam'], '2026-01-10', '1000', '50', '990');

    freezeToday('2026-02-01');
    closeMonthForTest('2026-01');
    freezeToday('2026-02-20');
    app(CreateRateCorrection::class)->handle($owner, $january, '55', '2026-02-20', 'Agreed rate was 55');
    longRangePurchase($owner, $parties['aslam'], '2026-02-10', '800', '52', '790');
    longRangePurchase($owner, $parties['aslam'], '2026-02-20', '200', '54');

    freezeToday('2026-03-15');
    longRangePurchase($owner, $parties['karim'], '2026-03-15', '300', '48', '300');

    freezeToday('2026-04-30');
    longRangePurchase($owner, $parties['aslam'], '2026-04-05', '500', '60', '500');

    return [$owner, $parties];
}

it('BR-RPT-10 BR-RPT-11 a range is longer than 3 months when it starts before the day after its end, 3 months back', function () {
    expect(Dates::threeMonthsBefore('2026-03-31'))->toBe('2026-01-01')
        ->and(Dates::threeMonthsBefore('2026-04-30'))->toBe('2026-02-01')
        ->and(Dates::threeMonthsBefore('2026-05-31'))->toBe('2026-03-01')
        ->and(Dates::longerThanThreeMonths('2026-01-01', '2026-03-31'))->toBeFalse()
        ->and(Dates::longerThanThreeMonths('2026-01-01', '2026-04-01'))->toBeTrue()
        ->and(Dates::longerThanThreeMonths('2026-01-31', '2026-04-29'))->toBeFalse()
        ->and(Dates::longerThanThreeMonths('2026-01-31', '2026-04-30'))->toBeTrue()
        ->and(Dates::longerThanThreeMonths('2026-01-15', '2026-01-15'))->toBeFalse();
});

it('BR-RPT-10 the rate report over 4 months shows one line per month with the corrected rate', function () {
    longRangeWorld();
    $filters = ['type' => 'purchase', 'from' => '2026-01-01', 'to' => '2026-04-30'];

    $groups = RateWeightReports::rateMonths($filters);

    expect(array_map(fn (array $g) => [$g['item'], $g['party']], $groups))->toBe([['Waste', 'Aslam'], ['Waste', 'Karim']])
        ->and($groups[0]['months'])->toBe([
            ['month' => '2026-01', 'label' => 'January 2026', 'kg' => '1000.000', 'amount' => '55000.00', 'average' => '55.00'],
            ['month' => '2026-02', 'label' => 'February 2026', 'kg' => '1000.000', 'amount' => '52400.00', 'average' => '52.40'],
            ['month' => '2026-04', 'label' => 'April 2026', 'kg' => '500.000', 'amount' => '30000.00', 'average' => '60.00'],
        ])
        ->and([$groups[0]['kg'], $groups[0]['amount'], $groups[0]['average']])->toBe(['2500.000', '137400.00', '54.96'])
        ->and($groups[0]['entries'])->toBe([]);

    // The same totals and averages as the entry-by-entry report (spec §12.4).
    $entries = collect(RateWeightReports::rates($filters))->keyBy('party');

    foreach ($groups as $group) {
        expect([$group['kg'], $group['amount'], $group['average']])
            ->toBe([$entries[$group['party']]['kg'], $entries[$group['party']]['amount'], $entries[$group['party']]['average']]);
    }
});

it('BR-RPT-10 the weight difference over 4 months shows one line per month with the same totals', function () {
    longRangeWorld();
    $filters = ['type' => 'purchase', 'from' => '2026-01-01', 'to' => '2026-04-30'];

    $groups = RateWeightReports::weightMonths($filters);

    expect($groups[0]['party'])->toBe('Aslam')
        ->and(array_map(fn (array $m) => [$m['month'], $m['their_kg'], $m['our_kg'], $m['difference']], $groups[0]['months']))->toBe([
            ['2026-01', '1000.000', '990.000', '10.000'],
            ['2026-02', '800.000', '790.000', '10.000'],
            ['2026-04', '500.000', '500.000', '0.000'],
        ])
        ->and([$groups[0]['their_kg'], $groups[0]['our_kg'], $groups[0]['difference'], $groups[0]['percent']])->toBe(['2300.000', '2280.000', '20.000', '0.88']);

    $entries = collect(RateWeightReports::weights($filters))->keyBy('party');

    foreach ($groups as $group) {
        expect([$group['their_kg'], $group['our_kg'], $group['difference'], $group['percent']])
            ->toBe([$entries[$group['party']]['their_kg'], $entries[$group['party']]['our_kg'], $entries[$group['party']]['difference'], $entries[$group['party']]['percent']]);
    }
});

it('BR-RPT-10 the report pages switch to monthly lines only for a range longer than 3 months', function () {
    [$owner] = longRangeWorld();

    foreach (['reports.rates', 'reports.weight-difference'] as $route) {
        $this->actingAs($owner)->get(route($route, ['type' => 'purchase', 'from' => '2026-01-01', 'to' => '2026-04-30']))
            ->assertInertia(fn (Assert $page) => $page->where('monthly', true)->has('groups.0.months', 3)->where('groups.0.entries', []));

        $this->actingAs($owner)->get(route($route, ['type' => 'purchase', 'from' => '2026-01-01', 'to' => '2026-03-31']))
            ->assertInertia(fn (Assert $page) => $page->where('monthly', false)->missing('groups.0.months'));
    }
});

it('BR-RPT-11 a statement PDF over more than 3 months is refused with the message', function () {
    [$owner, $parties] = longRangeWorld();
    $url = route('parties.statement', ['party' => $parties['aslam'], 'from' => '2026-01-01', 'to' => '2026-04-01']);

    $this->actingAs($owner)->getJson($url)
        ->assertStatus(422)
        ->assertJsonPath('errors.to.0', 'A statement PDF can cover at most 3 months. Choose a shorter period.');

    $this->actingAs($owner)->get(route('parties.statement', ['party' => $parties['aslam'], 'from' => '2026-01-01', 'to' => '2026-03-31']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('BR-RPT-11 without a period the statement PDF covers the last 3 months, never before the start date', function () {
    [$owner, $parties] = longRangeWorld();

    $this->actingAs($owner)->get(route('parties.statement', $parties['aslam']))
        ->assertOk()
        ->assertDownload("statement-{$parties['aslam']->code}-2026-02-01-2026-04-30.pdf");

    freezeToday('2026-02-20');
    $this->actingAs($owner)->get(route('parties.statement', $parties['aslam']))
        ->assertOk()
        ->assertDownload("statement-{$parties['aslam']->code}-2026-01-01-2026-02-20.pdf");
});
