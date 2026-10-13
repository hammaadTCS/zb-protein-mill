<?php

use App\Domain\Parties\Enums\PartyType;
use App\Domain\Reports\Queries\PendingBalances;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AcceptanceRunner;

it('R-02 after AT-1', function () {
    $runner = AcceptanceRunner::load('at1')->setUp()->runAll();
    $sections = PendingBalances::asOf('2026-01-31');
    $pick = fn (string $section) => array_map(fn (array $row) => [$row['name'], $row['amount']], $sections[$section]['rows']);

    expect($pick('We owe'))->toBe([['Aslam', '52000.00'], ['Bashir', '7000.00']])
        ->and($sections['We owe']['total'])->toBe('59000.00')
        ->and($pick('Owes us'))->toBe([['ABC Feeds', '23000.00']])
        ->and($pick('Advance'))->toBe([['Rehman Feeds', '30000.00']])
        ->and(collect($sections)->flatMap(fn ($s) => $s['rows'])->pluck('name')->all())->not->toContain('Karim')
        ->and($sections['Advance']['rows'][0]['advance_age_days'])->toBe(25);

    $this->actingAs($runner->accountant)->get(route('reports.pending-balances'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/pending-balances')
            ->where('sections.We owe.total', '59000.00'));
});

it('R-02 as of an earlier date and by party type', function () {
    AcceptanceRunner::load('at1')->setUp()->runAll();

    // End of day 1, before Karim was paid (#18): #2 owed 18800.00, and #4's rate added on
    // day 2 posts on its own entry date (BR-LED-08), so it counts on day 1 too.
    $day1 = PendingBalances::asOf('2026-01-05');
    $karim = collect($day1['We owe']['rows'])->firstWhere('name', 'Karim');

    expect($karim['amount'])->toBe('38400.00')
        ->and($karim['pending_kg'])->toBe('0.000')
        ->and(PendingBalances::asOf('2026-01-31', PartyType::Buyer)['We owe']['rows'])->toBe([]);
});
