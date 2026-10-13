<?php

use App\Domain\Insights\BusinessInsights;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AcceptanceRunner;

it('R-13 AT-1 insights from at1.json', function () {
    AcceptanceRunner::load('at1')->setUp()->runAll();
    $expected = acceptance('at1')['expected']['insights'];
    $period = BusinessInsights::defaultPeriod();

    expect($period)->toBe(['from' => '2025-10-31', 'to' => '2026-01-31'])
        ->and(array_intersect_key(BusinessInsights::for($period['from'], $period['to']), $expected))->toBe($expected);
});

it('R-13 months, suppliers and buyers after AT-1', function () {
    AcceptanceRunner::load('at1')->setUp()->runAll();
    $insights = BusinessInsights::for('2026-01-01', '2026-01-31');
    $january = $insights['months'][0];
    $aslam = collect($insights['suppliers'])->firstWhere('name', 'Aslam');
    $buyers = collect($insights['buyers']);

    expect(count($insights['months']))->toBe(1)
        ->and($january['wood_per_kg_waste'])->toBe($insights['wood_per_kg_waste'])
        ->and($january['wood_bar'])->toBe(100)
        ->and($aslam['kg'])->toBe('1800.000')
        ->and($aslam['average_rate'])->toBe('51.11')
        ->and($aslam['advance'])->toBe('0.00')
        ->and(collect($insights['suppliers'])->pluck('weight_difference_percent')->unique()->all())->toBe([null])
        ->and($buyers->sum(fn (array $b): int => $b['share_percent'] === null ? 0 : 1))->toBe($buyers->count());
});

it('R-13 a ratio with nothing to divide by is empty, not zero', function () {
    $insights = BusinessInsights::for('2026-01-01', '2026-01-31');

    expect($insights['kg_ratio_protein'])->toBeNull()
        ->and($insights['rough_cost_per_kg_sold'])->toBeNull()
        ->and($insights['suppliers'])->toBe([])
        ->and($insights['months'][0]['wood_bar'])->toBe(0);
});

it('page 40 needs insights.view and shows the default period', function () {
    freezeToday('2026-01-31');

    $this->actingAs(accountant())->get(route('insights.show'))->assertForbidden();
    $this->actingAs(owner())->get(route('insights.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/insights')
            ->where('insights.from', '2025-10-31')
            ->where('insights.to', '2026-01-31'));

    $this->actingAs(owner())->get(route('reports.index'))
        ->assertInertia(fn (Assert $page) => $page->where('reports', fn ($reports) => collect($reports)->contains('title', 'Business insights')));
    $this->actingAs(accountant())->get(route('reports.index'))
        ->assertInertia(fn (Assert $page) => $page->where('reports', fn ($reports) => ! collect($reports)->contains('title', 'Business insights')));
});

it('R-13 BR-LED-09 a corrected rate counts at the corrected amount (AT-5)', function () {
    AcceptanceRunner::load('controls', 'AT-5')->setUp()->runAll();
    $insights = BusinessInsights::for('2026-01-01', '2026-02-05');

    expect($insights['suppliers'][0]['average_rate'])->toBe('48.00')
        ->and($insights['months'][0]['waste_rate'])->toBe('48.00');
});
