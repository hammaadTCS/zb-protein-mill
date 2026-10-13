<?php

use App\Domain\Expenses\Actions\CreateExpenseCategory;
use App\Domain\Expenses\Actions\DeactivateExpenseCategory;
use App\Domain\Expenses\Actions\UpdateExpenseCategory;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Enums\NormalSide;
use Database\Seeders\TestExpenseCategoriesSeeder;
use Inertia\Testing\AssertableInertia as Assert;

it('a new category gets account 6000, the next 6001', function () {
    $owner = owner();

    $wages = app(CreateExpenseCategory::class)->handle($owner, ['name' => 'Wages', 'is_quick' => true]);
    $food = app(CreateExpenseCategory::class)->handle($owner, ['name' => 'Food']);

    expect($wages->ledgerAccount->code)->toBe('6000')
        ->and($wages->ledgerAccount->name)->toBe('Wages')
        ->and($wages->ledgerAccount->type)->toBe(AccountType::Expense)
        ->and($wages->ledgerAccount->normal_side)->toBe(NormalSide::Debit)
        ->and($wages->is_quick)->toBeTrue()
        ->and($food->ledgerAccount->code)->toBe('6001');
});

it('renaming renames the ledger account', function () {
    $owner = owner();
    $category = app(CreateExpenseCategory::class)->handle($owner, ['name' => 'Tea']);

    app(UpdateExpenseCategory::class)->handle($owner, $category, ['name' => 'Tea and snacks', 'is_quick' => true, 'sort_order' => 5]);

    expect($category->ledgerAccount()->first()?->name)->toBe('Tea and snacks')
        ->and($category->refresh()->is_quick)->toBeTrue()
        ->and($category->sort_order)->toBe(5);
});

it('BR-GEN-08 an inactive category is not active any more', function () {
    $owner = owner();
    $category = app(CreateExpenseCategory::class)->handle($owner, ['name' => 'Rent']);

    app(DeactivateExpenseCategory::class)->handle($owner, $category);

    expect(ExpenseCategory::query()->active()->count())->toBe(0);
});

it('O-11 the test seeder loads the proposed list in order with quick buttons', function () {
    owner();
    $this->seed(TestExpenseCategoriesSeeder::class);
    $this->seed(TestExpenseCategoriesSeeder::class); // runs twice without duplicates

    expect(ExpenseCategory::query()->orderBy('sort_order')->pluck('name')->all())->toBe(array_keys(TestExpenseCategoriesSeeder::CATEGORIES))
        ->and(ExpenseCategory::query()->where('is_quick', true)->orderBy('sort_order')->pluck('name')->all())
        ->toBe(['Wages', 'Food', 'Tea', 'Vehicle Fuel', 'Transport/Freight']);
});

it('page 44 is for settings.manage; names are unique', function () {
    $owner = owner();

    $this->actingAs(accountant())->get(route('expense-categories.index'))->assertForbidden();
    $this->actingAs($owner)->post(route('expense-categories.store'), ['name' => 'Wages', 'is_quick' => true, 'sort_order' => 10])
        ->assertRedirect(route('expense-categories.index'));
    $this->actingAs($owner)->post(route('expense-categories.store'), ['name' => 'Wages'])->assertSessionHasErrors('name');
    $this->actingAs($owner)->get(route('expense-categories.index'))
        ->assertInertia(fn (Assert $page) => $page->component('expense-categories/index')->where('categories.0.code', '6000'));
});
