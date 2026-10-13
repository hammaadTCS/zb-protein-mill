<?php

use App\Domain\Reports\Queries\DailySummary;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AcceptanceRunner;

it('R-04 AT-1 day 1', function () {
    $runner = AcceptanceRunner::load('at1')->setUp();
    freezeToday('2026-01-05');

    foreach ($runner->data['entries'] as $entry) {
        if (($entry['date'] ?? null) === '2026-01-05') {
            $runner->run($entry);
        }
    }

    $summary = DailySummary::for('2026-01-05');

    expect($summary['kg_bought'][0])->toBe(['item' => 'Waste', 'kg' => '1800.000'])
        ->and($summary['kg_sold'][0])->toBe(['item' => 'Protein product', 'kg' => '500.000'])
        ->and($summary['received'])->toBe('41000.00')
        ->and($summary['paid'])->toBe('70000.00')
        ->and($summary['expenses'])->toBe([
            ['category' => 'Wages', 'amount' => '8000.00'],
            ['category' => 'Food', 'amount' => '2500.00'],
            ['category' => 'Tea', 'amount' => '600.00'],
        ])
        ->and($summary['balances'])->toBe([
            ['id' => $runner->accounts['cash']->id, 'name' => 'Cash', 'balance' => '18900.00'],
            ['id' => $runner->accounts['hbl']->id, 'name' => 'HBL', 'balance' => '321000.00'],
        ])
        ->and($summary['rate_pending'])->toBe(1);

    $this->actingAs($runner->accountant)->get(route('reports.daily-summary', ['date' => '2026-01-05']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('reports/daily-summary')->where('summary.received', '41000.00'));
});
