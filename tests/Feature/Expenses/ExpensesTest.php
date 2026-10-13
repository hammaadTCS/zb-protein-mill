<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Expenses\Actions\CancelExpense;
use App\Domain\Expenses\Actions\CreateExpense;
use App\Domain\Expenses\Actions\DeactivateExpenseCategory;
use App\Domain\Expenses\Actions\UpdateExpense;
use App\Domain\Expenses\Models\Expense;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Items\Models\Item;
use App\Domain\Payments\Actions\CreatePayment;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Sales\Actions\CreateSale;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\Warnings\WarningRequired;
use Database\Seeders\TestExpenseCategoriesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => freezeToday('2026-01-31'));

/**
 * An acceptance world (at1 or at2) with the test expense categories and an employee.
 *
 * @return array{User, array{accounts: array<string, mixed>, parties: array<string, mixed>}}
 */
function expenseWorld(string $file = 'at1'): array
{
    $owner = owner();
    $data = acceptance($file);
    $data['parties'][] = ['key' => 'nadeem', 'type' => 'employee', 'name' => 'Nadeem', 'mobile' => '03000000010'];
    $world = acceptanceWorld($data, $owner);
    test()->seed(TestExpenseCategoriesSeeder::class);

    return [$owner, $world];
}

function expenseEntry(string $file, int|string $no): array
{
    return collect(acceptance($file)['entries'])->firstWhere('no', $no);
}

/**
 * An acceptance `expense` entry as form data.
 *
 * @param  array<string, mixed>  $entry
 * @param  array<string, mixed>  $links  ids of linked records
 * @return array<string, mixed>
 */
function expenseData(array $entry, array $world, array $links = []): array
{
    return [
        'idempotency_key' => (string) Str::uuid(),
        'date' => $entry['date'],
        'category_id' => expenseCategory($entry['category'])->id,
        'amount' => $entry['amount'],
        'payment_mode' => $entry['payment_mode'],
        'money_account_id' => isset($entry['money_account']) ? $world['accounts'][$entry['money_account']]->id : null,
        'party_id' => isset($entry['party']) ? $world['parties'][$entry['party']]->id : null,
        'note' => $entry['note'],
        ...$links,
    ];
}

function expenseCategory(string $name): ExpenseCategory
{
    return ExpenseCategory::query()->where('name', $name)->sole();
}

/**
 * @param  array<string, mixed>  $data
 */
function spend(User $by, array $data): Expense
{
    return app(CreateExpense::class)->handle($by, ['idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-06', ...$data]);
}

/**
 * The edit form of an expense with some fields changed.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function expenseEditForm(Expense $expense, array $changes): array
{
    $expense->refresh();

    return [
        'version' => $expense->updated_at?->toIso8601String(),
        'date' => $expense->entry_date,
        'category_id' => $expense->category_id,
        'amount' => $expense->amount,
        'payment_mode' => $expense->payment_mode->value,
        'money_account_id' => $expense->money_account_id,
        'party_id' => $expense->party_id,
        'handled_by_party_id' => $expense->handled_by_party_id,
        'linked_purchase_id' => $expense->linked_purchase_id,
        'linked_sale_id' => $expense->linked_sale_id,
        'note' => $expense->note,
        ...$changes,
    ];
}

/** The ledger side and amount of an expense category, as in `expected.ledger_accounts`. */
function categoryTotal(string $name): array
{
    $balance = ledgerBalance(expenseCategory($name)->ledgerAccount->code);

    return ['side' => str_starts_with($balance, '-') ? 'credit' : ($balance === '0.00' ? 'zero' : 'debit'), 'amount' => ltrim($balance, '-')];
}

it('PR-09 AT-1 #7 to #9, #19 and #21 post exact journals and category balances', function () {
    [$owner, $world] = expenseWorld();
    $expenses = [];

    foreach ([7, 8, 9, 19, 21] as $no) {
        $expenses[$no] = app(CreateExpense::class)->handle($owner, expenseData(expenseEntry('at1', $no), $world));
    }

    expect(journalLines($expenses[7]->journal_entry_id))->toBe([
        ['6000', '8000.00', '0.00', null, null],
        ['1010', '0.00', '8000.00', null, null],
    ])
        ->and(journalLines($expenses[8]->journal_entry_id))->toBe([
            ['6001', '2500.00', '0.00', null, null],
            ['1010', '0.00', '2500.00', null, null],
        ])
        ->and(journalLines($expenses[9]->journal_entry_id))->toBe([
            ['6002', '600.00', '0.00', null, null],
            ['1010', '0.00', '600.00', null, null],
        ])
        ->and(journalLines($expenses[19]->journal_entry_id))->toBe([
            ['6003', '15000.00', '0.00', null, null],
            ['1100', '0.00', '15000.00', null, null],
        ])
        ->and(journalLines($expenses[21]->journal_entry_id))->toBe([
            ['6004', '5000.00', '0.00', null, null],
            ['1010', '0.00', '5000.00', null, null],
        ])
        ->and($expenses[7]->voucher_no)->toBe('EXP-000001')
        ->and($expenses[21]->voucher_no)->toBe('EXP-000005')
        ->and($expenses[7]->note)->toBe(expenseEntry('at1', 7)['note']);

    $expected = acceptance('at1')['expected']['ledger_accounts'];

    foreach (['Wages', 'Tea', 'Electricity', 'Vehicle Fuel'] as $name) {
        expect(categoryTotal($name))->toBe($expected["category:{$name}"]);
    }
});

it('BR-GEN-03 AT-1 #20 edit 20000 to 2000 reverses once, reposts and logs old and new', function () {
    [$owner, $world] = expenseWorld();
    app(CreateExpense::class)->handle($owner, expenseData(expenseEntry('at1', 8), $world));
    $expense = app(CreateExpense::class)->handle($owner, expenseData(expenseEntry('at1', 20), $world));
    $original = $expense->journal_entry_id;

    $edited = app(UpdateExpense::class)->handle($owner, $expense, expenseEditForm($expense, expenseEntry('at1', '20b')['changes']));
    $at1 = acceptance('at1')['expected'];
    $reversals = DB::table('journal_entries')->where('reversal_of_id', $original)->get();

    expect($reversals)->toHaveCount($at1['reversal_journals']['e20'])
        ->and(journalLines($reversals->sole()->id))->toBe([
            ['6001', '0.00', '20000.00', null, null],
            ['1010', '20000.00', '0.00', null, null],
        ])
        ->and(journalLines($edited->journal_entry_id))->toBe([
            ['6001', '2000.00', '0.00', null, null],
            ['1010', '0.00', '2000.00', null, null],
        ])
        ->and($edited->voucher_no)->toBe('EXP-000002')
        ->and(categoryTotal('Food'))->toBe($at1['ledger_accounts']['category:Food']);

    $audit = AuditLog::query()->where('action', 'updated')->where('auditable_id', $edited->id)->sole();
    $row = $at1['audit'][0];

    expect($audit->action)->toBe($row['action'])
        ->and($audit->old_values)->toBe($row['old'])
        ->and($audit->new_values)->toBe($row['new']);
});

it('PR-10 AT-2 #8 unpaid transport is owed to the transporter and linked to the sale', function () {
    [$owner, $world] = expenseWorld('at2');
    $s5 = expenseEntry('at2', 5);
    $sale = app(CreateSale::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => $s5['date'], 'party_id' => $world['parties'][$s5['party']]->id,
        'item_id' => Item::query()->where('name', $s5['item'])->value('id'), 'kg' => $s5['kg'], 'rate' => $s5['rate'],
    ]);

    $expense = app(CreateExpense::class)->handle($owner, expenseData(expenseEntry('at2', 8), $world, ['linked_sale_id' => $sale->id]));
    $transporter = $world['parties']['transporter'];

    expect(journalLines($expense->journal_entry_id))->toBe([
        ['6005', '4000.00', '0.00', null, null],
        ['2100', '0.00', '4000.00', $transporter->code, null],
    ])
        ->and($transporter->code)->toBe('O-0001')
        ->and(partyBalance($transporter))->toBe(array_intersect_key(acceptance('at2')['expected']['parties']['transporter'], ['words' => 0, 'amount' => 0]))
        ->and($expense->linked_sale_id)->toBe($sale->id)
        ->and($expense->money_account_id)->toBeNull();
});

it('PR-11 an employee holding 5000 spends 1200 and owes us 3800', function () {
    [$owner, $world] = expenseWorld();
    $nadeem = $world['parties']['nadeem'];
    app(CreatePayment::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'direction' => 'paid',
        'party_id' => $nadeem->id, 'amount' => '5000', 'money_account_id' => $world['accounts']['cash']->id,
    ]);

    $expense = spend($owner, ['category_id' => expenseCategory('Food')->id, 'amount' => '1200', 'payment_mode' => 'paid_by_employee', 'party_id' => $nadeem->id, 'note' => 'Lunch from cash held']);

    expect(journalLines($expense->journal_entry_id))->toBe([
        ['6001', '1200.00', '0.00', null, null],
        ['1300', '0.00', '1200.00', 'E-0001', null],
    ])
        ->and(partyBalance($nadeem))->toBe(['words' => 'Owes us', 'amount' => '3800.00']);
});

it('BR-EXP-02 empty note is refused', function () {
    [$owner, $world] = expenseWorld();

    expect(fn () => spend($owner, ['category_id' => expenseCategory('Tea')->id, 'amount' => '100', 'payment_mode' => 'paid', 'money_account_id' => $world['accounts']['cash']->id, 'note' => '   ']))
        ->toThrow(ValidationException::class, 'Write a note of at least 3 characters.');
});

it('BR-EXP-02 2-character note is refused', function () {
    [$owner, $world] = expenseWorld();

    expect(fn () => spend($owner, ['category_id' => expenseCategory('Tea')->id, 'amount' => '100', 'payment_mode' => 'paid', 'money_account_id' => $world['accounts']['cash']->id, 'note' => 'ab']))
        ->toThrow(ValidationException::class, 'Write a note of at least 3 characters.')
        ->and(Expense::query()->count())->toBe(0);
});

it('BR-EXP-03 amount at the limit without a party is refused', function () {
    [$owner, $world] = expenseWorld();
    Settings::set('expense_party_required_limit', '10000.00', $owner);
    $data = ['category_id' => expenseCategory('Repair')->id, 'payment_mode' => 'paid', 'money_account_id' => $world['accounts']['hbl']->id, 'note' => 'Boiler repair'];

    expect(fn () => spend($owner, [...$data, 'amount' => '10000']))
        ->toThrow(ValidationException::class, 'A party is required for an expense of this amount.')
        ->and(spend($owner, [...$data, 'amount' => '9999.99'])->party_id)->toBeNull()
        ->and(spend($owner, [...$data, 'amount' => '10000', 'party_id' => $world['parties']['aslam']->id])->party_id)->toBe($world['parties']['aslam']->id);
});

it('BR-EXP-05 unpaid to a buyer is refused', function () {
    [$owner, $world] = expenseWorld();

    expect(fn () => spend($owner, ['category_id' => expenseCategory('Repair')->id, 'amount' => '500', 'payment_mode' => 'unpaid', 'party_id' => $world['parties']['abc']->id, 'note' => 'Repair owed']))
        ->toThrow(ValidationException::class, 'Select an active supplier, other party or employee.');
});

it('BR-EXP-06 paid by employee needs an employee', function () {
    [$owner, $world] = expenseWorld();

    expect(fn () => spend($owner, ['category_id' => expenseCategory('Tea')->id, 'amount' => '100', 'payment_mode' => 'paid_by_employee', 'party_id' => $world['parties']['aslam']->id, 'note' => 'Tea']))
        ->toThrow(ValidationException::class, 'Select an active employee.');
});

it('BR-EXP-04 paid needs an active money account and the other modes take none', function () {
    [$owner, $world] = expenseWorld();
    $base = ['category_id' => expenseCategory('Tea')->id, 'amount' => '100', 'note' => 'Tea'];

    expect(fn () => spend($owner, [...$base, 'payment_mode' => 'paid']))->toThrow(ValidationException::class)
        ->and(fn () => spend($owner, [...$base, 'payment_mode' => 'unpaid', 'party_id' => $world['parties']['aslam']->id, 'money_account_id' => $world['accounts']['cash']->id]))
        ->toThrow(ValidationException::class, 'No account is used for this payment mode.');
});

it('BR-EXP-08 linking both a purchase and a sale is refused', function () {
    [$owner, $world] = expenseWorld();
    $item = fn (string $name) => Item::query()->where('name', $name)->value('id');
    $purchase = app(CreatePurchase::class)->handle($owner, ['idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'party_id' => $world['parties']['aslam']->id, 'item_id' => $item('Waste'), 'kg' => '800', 'rate' => '50']);
    $sale = app(CreateSale::class)->handle($owner, ['idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'party_id' => $world['parties']['abc']->id, 'item_id' => $item('Protein product'), 'kg' => '300', 'rate' => '210']);

    expect(fn () => spend($owner, ['category_id' => expenseCategory('Transport/Freight')->id, 'amount' => '4000', 'payment_mode' => 'paid', 'money_account_id' => $world['accounts']['cash']->id, 'linked_purchase_id' => $purchase->id, 'linked_sale_id' => $sale->id, 'note' => 'Transport']))
        ->toThrow(ValidationException::class, 'Link the expense to one purchase or one sale, not both.')
        ->and(spend($owner, ['category_id' => expenseCategory('Transport/Freight')->id, 'amount' => '4000', 'payment_mode' => 'paid', 'money_account_id' => $world['accounts']['cash']->id, 'linked_purchase_id' => $purchase->id, 'note' => 'Transport'])->linked_purchase_id)
        ->toBe($purchase->id);
});

it('BR-EXP-01 an inactive category cannot be used', function () {
    [$owner, $world] = expenseWorld();
    app(DeactivateExpenseCategory::class)->handle($owner, expenseCategory('Tea'));

    expect(fn () => spend($owner, ['category_id' => expenseCategory('Tea')->id, 'amount' => '100', 'payment_mode' => 'paid', 'money_account_id' => $world['accounts']['cash']->id, 'note' => 'Tea']))
        ->toThrow(ValidationException::class, 'Select an active category.');
});

it('T-18 BR-GEN-11 the same idempotency key twice creates one expense and one voucher', function () {
    [$owner, $world] = expenseWorld();
    $data = expenseData(expenseEntry('at1', 9), $world);

    $first = app(CreateExpense::class)->handle($owner, $data);
    $second = app(CreateExpense::class)->handle($owner, $data);

    expect($second->id)->toBe($first->id)
        ->and(Expense::query()->count())->toBe(1)
        ->and(DB::table('journal_entries')->where('source_type', 'expense')->count())->toBe(1)
        ->and(DB::table('voucher_sequences')->where('doc_type', 'expense')->value('next_number'))->toBe(2);
});

it('BR-GEN-10 an expense paid from cash beyond the balance warns', function () {
    [$owner, $world] = expenseWorld();

    expect(fn () => spend($owner, ['category_id' => expenseCategory('Repair')->id, 'amount' => '90000', 'payment_mode' => 'paid', 'money_account_id' => $world['accounts']['cash']->id, 'party_id' => $world['parties']['aslam']->id, 'note' => 'Big repair']))
        ->toThrow(WarningRequired::class);
});

it('BR-GEN-04 cancelling reverses the journal and keeps the row', function () {
    [$owner, $world] = expenseWorld();
    $expense = app(CreateExpense::class)->handle($owner, expenseData(expenseEntry('at1', 7), $world));

    $cancelled = app(CancelExpense::class)->handle($owner, $expense, 'Entered twice by mistake');

    expect($cancelled->status->value)->toBe('cancelled')
        ->and(Expense::query()->count())->toBe(1)
        ->and(DB::table('journal_entries')->where('reversal_of_id', $expense->journal_entry_id)->exists())->toBeTrue()
        ->and(categoryTotal('Wages'))->toBe(['side' => 'zero', 'amount' => '0.00'])
        ->and(fn () => DB::table('expenses')->where('id', $expense->id)->delete())->toThrow(QueryException::class);
});
