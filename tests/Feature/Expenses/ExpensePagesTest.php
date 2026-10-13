<?php

use App\Domain\Expenses\Actions\CreateExpense;
use App\Domain\Expenses\Models\Expense;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Items\Models\Item;
use App\Domain\MoneyAccounts\Actions\CreateMoneyAccount;
use App\Domain\MoneyAccounts\Actions\DeactivateMoneyAccount;
use App\Domain\Sales\Actions\CreateSale;
use App\Models\User;
use Database\Seeders\TestExpenseCategoriesSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => freezeToday('2026-01-31'));

/**
 * @return array{User, array{accounts: array<string, mixed>, parties: array<string, mixed>}}
 */
function expensePagesWorld(): array
{
    $owner = owner();
    $world = acceptanceWorld(acceptance('at1'), $owner);
    test()->seed(TestExpenseCategoriesSeeder::class);

    return [$owner, $world];
}

function pagesExpense(User $by, string $category, string $amount, int $accountId, string $note): Expense
{
    return app(CreateExpense::class)->handle($by, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-06', 'payment_mode' => 'paid',
        'category_id' => ExpenseCategory::query()->where('name', $category)->value('id'),
        'amount' => $amount, 'money_account_id' => $accountId, 'note' => $note,
    ]);
}

it('BR-EXP-09 create page props default to today, Paid and the first cash account', function () {
    $owner = owner();
    $this->seed(TestExpenseCategoriesSeeder::class);
    app(CreateMoneyAccount::class)->handle($owner, ['name' => 'HBL', 'type' => 'bank', 'bank_name' => 'HBL']);
    $first = app(CreateMoneyAccount::class)->handle($owner, ['name' => 'Cash', 'type' => 'cash']);
    $second = app(CreateMoneyAccount::class)->handle($owner, ['name' => 'Office cash', 'type' => 'cash']);

    $this->actingAs($owner)->get(route('expenses.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('expenses/create')
            ->where('defaults', ['date' => '2026-01-31', 'payment_mode' => 'paid', 'money_account_id' => $first->id])
            ->where('categories.0.name', 'Wages')
            ->where('categories.0.is_quick', true)
            ->where('categories.3.is_quick', false)
            ->where('party_required_limit', '100000.00'));

    // The lowest ledger code among active cash accounts, never a bank.
    app(DeactivateMoneyAccount::class)->handle($owner, $first);

    $this->actingAs($owner)->get(route('expenses.create'))
        ->assertInertia(fn (Assert $page) => $page->where('defaults.money_account_id', $second->id));
});

it('expense list filters by category and shows the note in each row', function () {
    [$owner, $world] = expensePagesWorld();
    $cash = $world['accounts']['cash']->id;
    pagesExpense($owner, 'Tea', '600', $cash, 'Tea for the shift');
    pagesExpense($owner, 'Food', '2500', $cash, 'Lunch for staff');
    pagesExpense($owner, 'Tea', '300', $cash, 'Evening tea');

    $this->actingAs($owner)->get(route('expenses.index', ['category_id' => ExpenseCategory::query()->where('name', 'Tea')->value('id')]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('expenses/index')
            ->where('expenses.total', 2)
            ->where('total', '900.00')
            ->where('expenses.data.0.note', 'Evening tea')
            ->where('expenses.data.0.category', 'Tea')
            ->where('expenses.data.1.note', 'Tea for the shift'));

    $this->actingAs($owner)->get(route('expenses.index'))
        ->assertInertia(fn (Assert $page) => $page->where('expenses.total', 3)->where('total', '3400.00'));
});

it('expense detail shows the link, the change history and journal lines for the owner only', function () {
    [$owner, $world] = expensePagesWorld();
    $sale = app(CreateSale::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'party_id' => $world['parties']['abc']->id,
        'item_id' => Item::query()->where('code', 'PROTEIN')->value('id'), 'kg' => '300', 'rate' => '210',
    ]);
    $expense = app(CreateExpense::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-06', 'payment_mode' => 'paid',
        'category_id' => ExpenseCategory::query()->where('name', 'Transport/Freight')->value('id'),
        'amount' => '4000', 'money_account_id' => $world['accounts']['cash']->id, 'linked_sale_id' => $sale->id, 'note' => 'Transport to ABC Feeds',
    ]);

    $this->actingAs($owner)->get(route('expenses.show', $expense))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('expenses/show')
            ->where('expense.voucher_no', 'EXP-000001')
            ->where('expense.payment_mode_label', 'Paid')
            ->where('expense.linked', ['type' => 'sale', 'id' => $sale->id, 'voucher_no' => 'SAL-000001', 'label' => 'SAL-000001 · ABC Feeds'])
            ->where('history.0.action', 'Created')
            ->has('journal', 2));

    $this->actingAs(accountant())->get(route('expenses.show', $expense))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('journal'));
});

it('BR-EXP-08 link lookup finds an active purchase or sale by voucher number', function () {
    [$owner, $world] = expensePagesWorld();
    $sale = app(CreateSale::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'party_id' => $world['parties']['abc']->id,
        'item_id' => Item::query()->where('code', 'PROTEIN')->value('id'), 'kg' => '300', 'rate' => '210',
    ]);

    $this->actingAs($owner)->getJson(route('expenses.link-lookup', ['voucher_no' => 'sal-000001']))
        ->assertOk()
        ->assertExactJson(['type' => 'sale', 'id' => $sale->id, 'voucher_no' => 'SAL-000001', 'label' => 'SAL-000001 · ABC Feeds']);

    expect($this->actingAs($owner)->getJson(route('expenses.link-lookup', ['voucher_no' => 'PUR-000009']))->getContent())->toBe('null');
});

it('saves a quick expense from the form and opens a fresh form on "Save and add another"', function () {
    [$owner, $world] = expensePagesWorld();
    $form = [
        'date' => '2026-01-31', 'category_id' => ExpenseCategory::query()->where('name', 'Tea')->value('id'), 'amount' => '150',
        'payment_mode' => 'paid', 'money_account_id' => $world['accounts']['cash']->id, 'party_id' => null, 'note' => 'Tea',
    ];

    $this->actingAs($owner)->post(route('expenses.store'), [...$form, 'idempotency_key' => (string) Str::uuid()])
        ->assertRedirect(route('expenses.show', Expense::query()->sole()));

    $this->actingAs($owner)->post(route('expenses.store'), [...$form, 'idempotency_key' => (string) Str::uuid(), 'add_another' => true])
        ->assertRedirect(route('expenses.create'));

    expect(Expense::query()->count())->toBe(2);
});

it('expense pages need their permissions', function () {
    [$owner, $world] = expensePagesWorld();
    $expense = pagesExpense($owner, 'Tea', '600', $world['accounts']['cash']->id, 'Tea for the shift');
    $nobody = User::factory()->create();

    $this->actingAs($nobody)->get(route('expenses.index'))->assertForbidden();
    $this->actingAs($nobody)->get(route('expenses.create'))->assertForbidden();
    $this->actingAs($nobody)->get(route('expenses.show', $expense))->assertForbidden();
    $this->actingAs($nobody)->get(route('expenses.edit', $expense))->assertForbidden();
    $this->actingAs($nobody)->getJson(route('expenses.link-lookup', ['voucher_no' => 'SAL-000001']))->assertForbidden();
    $this->actingAs(accountant())->get(route('expenses.create'))->assertOk();
});
