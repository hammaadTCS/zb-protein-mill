<?php

use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Reports\Queries\ExpenseReport;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AcceptanceRunner;

it('R-05 after AT-1', function () {
    $runner = AcceptanceRunner::load('at1')->setUp()->runAll();

    $report = ExpenseReport::for('2026-01-01', '2026-01-31');

    expect($report['totals'])->toBe([
        ['category' => 'Wages', 'amount' => '8000.00'],
        ['category' => 'Food', 'amount' => '4500.00'],
        ['category' => 'Tea', 'amount' => '600.00'],
        ['category' => 'Electricity', 'amount' => '15000.00'],
        ['category' => 'Vehicle Fuel', 'amount' => '5000.00'],
    ])
        ->and($report['total'])->toBe('33100.00')
        ->and(array_column($report['rows'], 'amount'))->toBe(['8000.00', '2500.00', '600.00', '15000.00', '2000.00', '5000.00'])
        ->and(collect($report['rows'])->firstWhere('voucher', 'EXP-000005')['note'])->toBe('Lunch');

    $food = ExpenseCategory::query()->where('name', 'Food')->value('id');

    expect(ExpenseReport::for('2026-01-01', '2026-01-31', $food)['total'])->toBe('4500.00');

    $this->actingAs($runner->accountant)->get(route('reports.expenses', ['from' => '2026-01-01', 'to' => '2026-01-31']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('reports/expenses')->where('report.total', '33100.00'));
});
