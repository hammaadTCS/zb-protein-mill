<?php

use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AcceptanceRunner;

it('BR-DSH-01 the accountant dashboard has today, their alerts and the quick buttons, but no balances', function () {
    $runner = AcceptanceRunner::load('at1')->setUp();
    freezeToday('2026-01-05');

    foreach ($runner->data['entries'] as $entry) {
        if (($entry['date'] ?? null) === '2026-01-05') {
            $runner->run($entry);
        }
    }

    $this->actingAs($runner->accountant)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('today', '2026-01-05')
            ->where('figures.kg_bought', [['item' => 'Waste', 'kg' => '1800.000'], ['item' => 'Wood', 'kg' => '0.000']])
            ->where('figures.received', '41000.00')
            ->where('figures.paid', '70000.00')
            ->where('figures.expenses', '11100.00')
            ->where('balances', [])
            ->has('alerts')
            // The quick buttons are drawn from these permissions (lib/navigation.ts bottomBar).
            ->where('auth.user.permissions', fn ($permissions) => collect(['purchase.create', 'sale.create', 'payment.create', 'expense.create'])
                ->diff($permissions)->isEmpty())
            ->where('auth.user.permissions', fn ($permissions) => ! collect($permissions)->contains('dashboard.balances')));
});

it('BR-DSH-01 the owner dashboard still shows every active cash and bank balance', function () {
    $runner = AcceptanceRunner::load('at1')->setUp()->runAll();
    $expected = acceptance('at1')['expected']['money_accounts'];

    $this->actingAs($runner->owner)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('balances.0.name', 'Cash')
            ->where('balances.0.balance', $expected['cash'])
            ->where('balances.1.name', 'HBL')
            ->where('balances.1.balance', $expected['hbl']));
});
