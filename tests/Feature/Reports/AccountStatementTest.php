<?php

use App\Domain\Reports\Queries\AccountStatement;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AcceptanceRunner;

it('R-06 HBL after AT-1', function () {
    $runner = AcceptanceRunner::load('at1')->setUp()->runAll();
    $hbl = $runner->accounts['hbl'];

    $statement = AccountStatement::for($hbl, '2026-01-01', '2026-01-31');

    expect($statement['opening'])->toBe('300000.00')
        ->and($statement['rows'])->toHaveCount(8)
        ->and(array_values(array_unique(array_column($statement['rows'], 'date'))))->toBe(['2026-01-05', '2026-01-06'])
        ->and($statement['closing'])->toBe('417600.00')
        ->and($statement['closing'])->toBe(acceptance('at1')['expected']['money_accounts']['hbl'])
        ->and($statement['rows'][0])->toMatchArray(['voucher' => 'PAY-000003', 'party' => 'Bashir', 'out' => '20000.00', 'balance' => '280000.00'])
        ->and(collect($statement['rows'])->firstWhere('description', 'Transfer – HBL → Cash – AT1-REF-17')['out'])->toBe('20000.00');

    $this->actingAs($runner->accountant)->get(route('accounts.show', ['moneyAccount' => $hbl->id, 'from' => '2026-01-01', 'to' => '2026-01-31']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('accounts/show')->where('statement.closing', '417600.00'));

    $this->actingAs($runner->accountant)->get(route('accounts.index'))
        ->assertInertia(fn (Assert $page) => $page->where('accounts.1.balance', '417600.00'));
});

it('R-06 a later range starts from the balance before it', function () {
    $runner = AcceptanceRunner::load('at1')->setUp()->runAll();

    $statement = AccountStatement::for($runner->accounts['hbl'], '2026-01-06', '2026-01-31');

    expect($statement['opening'])->toBe('321000.00')
        ->and($statement['rows'])->toHaveCount(6)
        ->and($statement['closing'])->toBe('417600.00');
});
