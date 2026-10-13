<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('reports home lists only permitted reports', function () {
    $titles = fn ($user) => collect($this->actingAs($user)->get(route('reports.index'))->assertOk()->viewData('page')['props']['reports'])->pluck('title')->all();

    $owner = $titles(owner());
    $accountant = $titles(accountant());

    expect($owner)->toContain('Partner statement', 'Trial balance', 'Income vs Spending', 'Pending balances', 'Accounts')
        ->and($accountant)->not->toContain('Partner statement')
        ->and($accountant)->toContain('Trial balance', 'Pending balances', 'Accounts')
        // Owner-only: Partner statement (partner.view) and, from T7.5, Business insights (insights.view).
        ->and(array_values(array_diff($owner, $accountant)))->toBe(['Partner statement', 'Business insights'])
        ->and(array_values(array_diff($accountant, $owner)))->toBe([]);

    $this->actingAs(User::factory()->create())->get(route('reports.index'))->assertForbidden();
});

it('every page shares the company name for printed reports', function () {
    $this->actingAs(owner())->get(route('reports.index'))
        ->assertInertia(fn (Assert $page) => $page->where('company_name', 'ZB Poultry Protein Mill'));
});
