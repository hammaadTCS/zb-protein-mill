<?php

use App\Domain\Reports\Queries\IncomeVsSpending;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AcceptanceRunner;

it('BR-RPT-08 AT-1 and AT-2 income, spending and result', function (string $file) {
    AcceptanceRunner::load($file)->setUp()->runAll();
    $expected = acceptance($file)['expected']['income_vs_spending'];

    $report = IncomeVsSpending::for($expected['from'], $expected['to']);

    expect([$report['income'], $report['spending'], $report['result'], $report['pending_kg']])
        ->toBe([$expected['income'], $expected['spending'], $expected['result'], $expected['pending_kg']]);
})->with(['at1', 'at2']);

it('BR-RPT-08 banner text is present', function () {
    $this->actingAs(owner())->get(route('reports.income-vs-spending'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/income-vs-spending')
            ->where('banner', 'This is not profit. Stock is not recorded, so months with heavy buying look worse and months with heavy selling look better.'));
});

it('BR-RPT-08 the word Profit appears only inside the banner', function () {
    AcceptanceRunner::load('at1')->setUp()->runAll();

    $response = $this->actingAs(owner())->get(route('reports.income-vs-spending', ['from' => '2026-01-01', 'to' => '2026-01-31']));
    $props = $response->viewData('page')['props'];
    $everythingButBanner = json_encode(array_diff_key($props, ['banner' => 0]));
    $page = (string) file_get_contents(resource_path('js/pages/reports/income-vs-spending.tsx'));

    expect(stripos((string) $everythingButBanner, 'profit'))->toBeFalse()
        ->and(stripos($page, 'profit'))->toBeFalse()
        ->and(substr_count(strtolower($props['banner']), 'profit'))->toBe(1);
});
